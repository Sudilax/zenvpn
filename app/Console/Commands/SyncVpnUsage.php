<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\VpnDevice;
use App\Services\ZenVpnApiService;
use App\Exceptions\ZenVpnApiException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncVpnUsage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vpn:sync-usage';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize VPN data usage statistics from the FastAPI backend';

    /**
     * Execute the console command.
     */
    public function handle(ZenVpnApiService $api): int
    {
        $this->info('Starting VPN data usage sync...');
        
        $users = User::whereNotNull('fastapi_username')->get();
        
        $this->info("Found {$users->count()} user(s) to sync.");
        
        foreach ($users as $user) {
            $this->info("Syncing usage for user: {$user->email} ({$user->fastapi_username})");
            
            try {
                $usage = $api->getUserUsage($user->fastapi_username);
                
                // Update total used data
                $totalUsedMb = isset($usage['total_used_mb']) ? (int) $usage['total_used_mb'] : 0;
                $user->update(['data_used_mb' => $totalUsedMb]);
                
                $this->info("  Total used: {$totalUsedMb} MB");
                
                // Update per-device usage
                if (isset($usage['devices']) && is_array($usage['devices'])) {
                    foreach ($usage['devices'] as $deviceData) {
                        if (!is_array($deviceData)) {
                            continue;
                        }

                        $deviceIdentifier = $deviceData['device'] ?? null;
                        $deviceUsedMb = isset($deviceData['used_mb']) ? (int) $deviceData['used_mb'] : 0;

                        if (empty($deviceIdentifier)) {
                            continue;
                        }
                        
                        // Find the device by identifier, or fallback by name if needed
                        $device = $user->vpnDevices()
                            ->where('device_identifier', $deviceIdentifier)
                            ->first();
                            
                        if (!$device) {
                            $device = $user->vpnDevices()
                                ->where('device_name', $deviceIdentifier)
                                ->first();
                        }
                        
                        if ($device) {
                            $device->update(['data_used_mb' => $deviceUsedMb]);
                            $this->info("    Device '{$device->device_name}' (ID: {$deviceIdentifier}) usage updated to {$deviceUsedMb} MB");
                        } else {
                            $this->warn("    No matching device found for identifier '{$deviceIdentifier}'");
                        }
                    }
                }
            } catch (ZenVpnApiException $e) {
                $this->error("  Error syncing usage for user {$user->email}: " . $e->getMessage());
                Log::error('[SyncVpnUsage] failed to sync usage', [
                    'email' => $user->email,
                    'username' => $user->fastapi_username,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        $this->info('VPN data usage sync completed.');
        return Command::SUCCESS;
    }
}
