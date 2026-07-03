<?php

namespace App\Http\Controllers;

use App\Exceptions\ZenVpnApiException;
use App\Models\VpnDevice;
use App\Services\ZenVpnApiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class VpnDeviceController extends Controller
{
    public function __construct(private readonly ZenVpnApiService $api)
    {
    }

    /**
     * Store a newly created VPN device for the authenticated user.
     * Provisions the user on the FastAPI backend first, then saves to DB.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Enforce device limit
        if (! $user->canAddDevice()) {
            return back()->withErrors([
                'device_name' => "You have reached your device limit of {$user->device_limit}.",
            ])->withInput();
        }

        $validated = $request->validate([
            'device_name' => ['required', 'string', 'max:64'],
            'sni'         => ['required', 'string', Rule::in(array_keys(VpnDevice::SNI_OPTIONS))],
        ]);

        try {
            if (empty($user->fastapi_username)) {
                // Generate a unique fastapi_username: laravel-{user_id}
                $fastapiUsername = 'laravel-' . $user->id;

                // Determine appropriate FastAPI plan based on device limit
                $plan = 'basic';
                if ($user->device_limit > 5 || $user->device_limit === 0) {
                    $plan = 'premium';
                } elseif ($user->device_limit > 2) {
                    $plan = 'pro';
                }

                // Provision on FastAPI backend — returns real UUIDs and the device identifier
                $uuids = $this->api->createUser($fastapiUsername, $plan);
                
                // Save fastapi_username on the user record
                $user->update(['fastapi_username' => $fastapiUsername]);
                
                $deviceIdentifier = $uuids['device'] ?? 'device-1';
            } else {
                // Call addDevice instead of creating a new user
                $uuids = $this->api->addDevice($user->fastapi_username, $validated['device_name'], $validated['sni']);
                
                $deviceIdentifier = $validated['device_name'];
            }
        } catch (ZenVpnApiException $e) {
            Log::error('[VpnDeviceController] provisioning failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
            return back()
                ->withInput()
                ->withErrors(['device_name' => 'VPN provisioning temporarily unavailable, please try again.']);
        }

        $user->vpnDevices()->create([
            'device_name'       => $validated['device_name'],
            'sni'               => $validated['sni'],
            'vpn_username'      => null, // Clear this to avoid unique constraint issues
            'device_identifier' => $deviceIdentifier,
            'vless_uuid'        => $uuids['vless_uuid'],
            'trojan_uuid'       => $uuids['trojan_uuid'],
            'vmess_uuid'        => $uuids['vmess_uuid'],
            'status'            => 'active',
        ]);

        return redirect()->route('dashboard')
            ->with('success', "Device \"{$validated['device_name']}\" added and provisioned successfully.");
    }

    /**
     * Update a device's name and/or SNI (no backend API call needed for these fields).
     */
    public function update(Request $request, VpnDevice $device): RedirectResponse
    {
        if ($device->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'device_name' => ['required', 'string', 'max:64'],
            'sni'         => ['required', 'string', Rule::in(array_keys(VpnDevice::SNI_OPTIONS))],
        ]);

        $sniChanged = $device->sni !== $validated['sni'];

        $device->update([
            'device_name' => $validated['device_name'],
            'sni'         => $validated['sni'],
        ]);

        $message = "Device \"{$validated['device_name']}\" updated.";
        if ($sniChanged) {
            $message .= ' Reconnect your VPN app to apply the new SNI config.';
        }

        return redirect()->route('dashboard')->with('success', $message);
    }

    /**
     * Remove a VPN device — deletes from the backend first, then from the DB.
     */
    public function destroy(Request $request, VpnDevice $device): RedirectResponse
    {
        $user = $request->user();
        if ($device->user_id !== $user->id) {
            abort(403);
        }

        $name = $device->device_name;

        // Call backend to remove the device
        if ($user->fastapi_username && $device->device_identifier) {
            try {
                $this->api->removeDevice($user->fastapi_username, $device->device_identifier);
            } catch (ZenVpnApiException $e) {
                // Log is already recorded in the service; carry on to delete from DB
            }
        }

        // Only if it's their very last device, clear fastapi_username
        if ($user->vpnDevices()->count() === 1) {
            $user->update(['fastapi_username' => null]);
        }

        $device->delete();

        return redirect()->route('dashboard')->with('success', "Device \"{$name}\" removed.");
    }
}
