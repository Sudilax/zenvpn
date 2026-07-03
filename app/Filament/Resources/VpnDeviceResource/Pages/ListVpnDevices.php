<?php

namespace App\Filament\Resources\VpnDeviceResource\Pages;

use App\Filament\Resources\VpnDeviceResource;
use Filament\Resources\Pages\ListRecords;

class ListVpnDevices extends ListRecords
{
    protected static string $resource = VpnDeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
