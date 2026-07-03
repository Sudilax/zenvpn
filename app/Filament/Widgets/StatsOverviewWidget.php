<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Models\VpnDevice;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalUsers        = User::count();
        $totalDevices      = VpnDevice::count();
        $nearCapCount      = User::whereRaw('data_cap_mb > 0 AND data_used_mb / data_cap_mb > 0.8')->count();
        $recentUsersCount  = User::where('created_at', '>=', now()->subDays(7))->count();

        return [
            Stat::make('Total Users', $totalUsers)
                ->description('Registered accounts')
                ->icon('heroicon-o-users')
                ->color('indigo'),

            Stat::make('Total Devices', $totalDevices)
                ->description('Provisioned VPN devices')
                ->icon('heroicon-o-device-tablet')
                ->color('violet'),

            Stat::make('Near Data Cap', $nearCapCount)
                ->description('Users over 80% of their data cap')
                ->icon('heroicon-o-exclamation-triangle')
                ->color($nearCapCount > 0 ? 'warning' : 'success'),

            Stat::make('New This Week', $recentUsersCount)
                ->description('Users registered in the last 7 days')
                ->icon('heroicon-o-user-plus')
                ->color('emerald'),
        ];
    }
}
