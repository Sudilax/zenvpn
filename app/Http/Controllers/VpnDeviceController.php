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

        // Generate a unique backend username: u{user_id}-{6 random hex chars}
        $vpnUsername = 'u' . $user->id . '-' . Str::lower(Str::random(6));

        try {
            // Provision on FastAPI backend — returns real UUIDs
            $uuids = $this->api->createUser($vpnUsername);
        } catch (ZenVpnApiException $e) {
            Log::error('[VpnDeviceController] createUser failed', [
                'vpn_username' => $vpnUsername,
                'user_id'      => $user->id,
                'error'        => $e->getMessage(),
            ]);
            return back()
                ->withInput()
                ->withErrors(['device_name' => 'VPN provisioning temporarily unavailable, please try again.']);
        }

        $user->vpnDevices()->create([
            'device_name'  => $validated['device_name'],
            'sni'          => $validated['sni'],
            'vpn_username' => $vpnUsername,
            'vless_uuid'   => $uuids['vless_uuid'],
            'trojan_uuid'  => $uuids['trojan_uuid'],
            'vmess_uuid'   => $uuids['vmess_uuid'],
            'status'       => 'active',
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
        if ($device->user_id !== $request->user()->id) {
            abort(403);
        }

        $name = $device->device_name;

        // Call backend to remove the user (silently ignore failures — DB record is always deleted)
        if ($device->vpn_username) {
            try {
                $this->api->deleteUser($device->vpn_username);
            } catch (ZenVpnApiException $e) {
                // Log is already recorded in the service; carry on to delete from DB
            }
        }

        $device->delete();

        return redirect()->route('dashboard')->with('success', "Device \"{$name}\" removed.");
    }
}
