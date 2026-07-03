<?php

namespace App\Http\Controllers;

use App\Models\VpnDevice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VpnDeviceController extends Controller
{
    /**
     * Store a newly created VPN device for the authenticated user.
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
        ]);

        $user->vpnDevices()->create([
            'device_name' => $validated['device_name'],
            'vless_uuid'  => (string) Str::uuid(),
            'trojan_uuid' => (string) Str::uuid(),
            'vmess_uuid'  => (string) Str::uuid(),
            'status'      => 'active',
        ]);

        return redirect()->route('dashboard')->with('success', "Device \"{$validated['device_name']}\" added successfully.");
    }

    /**
     * Remove the specified VPN device.
     */
    public function destroy(Request $request, VpnDevice $device): RedirectResponse
    {
        // Ensure the device belongs to the authenticated user
        if ($device->user_id !== $request->user()->id) {
            abort(403);
        }

        $name = $device->device_name;
        $device->delete();

        return redirect()->route('dashboard')->with('success', "Device \"{$name}\" removed.");
    }
}
