<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Exceptions\ZenVpnApiException;
use App\Models\VpnDevice;
use App\Services\ZenVpnApiService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;

class VpnDevicesRelationManager extends RelationManager
{
    protected static string $relationship = 'vpnDevices';

    protected static ?string $title = 'VPN Devices';

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                TextInput::make('device_name')
                    ->label('Device Name')
                    ->required()
                    ->maxLength(64),
                Select::make('sni')
                    ->label('SNI (Server Name)')
                    ->options(VpnDevice::SNI_OPTIONS)
                    ->required()
                    ->default('m.zoom.us'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('device_name')
            ->columns([
                Tables\Columns\TextColumn::make('device_name')
                    ->label('Device Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sni')
                    ->label('SNI')
                    ->badge()
                    ->color('indigo'),

                Tables\Columns\TextColumn::make('last_ip')
                    ->label('Last IP')
                    ->placeholder('N/A'),

                Tables\Columns\IconColumn::make('online')
                    ->label('Status')
                    ->getStateUsing(fn (VpnDevice $record): bool =>
                        $record->last_seen !== null &&
                        $record->last_seen->greaterThan(now()->subMinutes(5))
                    )
                    ->boolean()
                    ->trueIcon('heroicon-o-signal')
                    ->falseIcon('heroicon-o-signal-slash')
                    ->trueColor('success')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('last_seen')
                    ->label('Last Seen')
                    ->since()
                    ->placeholder('Never'),

                Tables\Columns\TextColumn::make('data_used_mb')
                    ->label('Usage')
                    ->getStateUsing(fn (VpnDevice $record): string =>
                        $record->formattedUsage()
                    )
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Device')
                    ->before(function (CreateAction $action) {
                        $user = $this->getOwnerRecord();

                        // Check limit
                        if (!$user->canAddDevice()) {
                            Notification::make()
                                ->title('Device Limit Reached')
                                ->body("This user has already reached their device limit of {$user->device_limit}.")
                                ->danger()
                                ->send();

                            $action->halt();
                        }
                    })
                    ->using(function (array $data, string $modelClass): VpnDevice {
                        $user = $this->getOwnerRecord();
                        $api = app(ZenVpnApiService::class);

                        try {
                            if (empty($user->fastapi_username)) {
                                $fastapiUsername = 'laravel-' . $user->id;

                                $plan = 'basic';
                                if ($user->device_limit > 5 || $user->device_limit === 0) {
                                    $plan = 'premium';
                                } elseif ($user->device_limit > 2) {
                                    $plan = 'pro';
                                }

                                $uuids = $api->createUser($fastapiUsername, $plan);
                                $user->update(['fastapi_username' => $fastapiUsername]);
                                $deviceIdentifier = $uuids['device'] ?? 'device-1';
                            } else {
                                $uuids = $api->addDevice($user->fastapi_username, $data['device_name'], $data['sni']);
                                $deviceIdentifier = $data['device_name'];
                            }
                        } catch (ZenVpnApiException $e) {
                            Notification::make()
                                ->title('VPN Provisioning Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            throw $e;
                        }

                        return $user->vpnDevices()->create([
                            'device_name'       => $data['device_name'],
                            'sni'               => $data['sni'],
                            'vpn_username'      => null,
                            'device_identifier' => $deviceIdentifier,
                            'vless_uuid'        => $uuids['vless_uuid'],
                            'trojan_uuid'       => $uuids['trojan_uuid'],
                            'vmess_uuid'        => $uuids['vmess_uuid'],
                            'status'            => 'active',
                        ]);
                    }),
            ])
            ->actions([
                Action::make('show_uris')
                    ->label('Connection URIs')
                    ->icon('heroicon-o-key')
                    ->color('info')
                    ->modalHeading('Connection Config URIs')
                    ->modalSubmitAction(false) // View only
                    ->form([
                        Section::make('VPN Configuration URIs')
                            ->description('Copy these URIs into your VPN client.')
                            ->collapsible()
                            ->schema([
                                TextInput::make('vless_uri')
                                    ->label('VLESS Config')
                                    ->default(fn (VpnDevice $record) => $record->getVlessUri())
                                    ->readOnly()
                                    ->copyable(),
                                TextInput::make('trojan_uri')
                                    ->label('Trojan Config')
                                    ->default(fn (VpnDevice $record) => $record->getTrojanUri())
                                    ->readOnly()
                                    ->copyable(),
                                TextInput::make('vmess_uri')
                                    ->label('VMess Config')
                                    ->default(fn (VpnDevice $record) => $record->getVmessUri())
                                    ->readOnly()
                                    ->copyable(),
                                TextInput::make('hysteria2_uri')
                                    ->label('Hysteria2 Config')
                                    ->default(fn (VpnDevice $record) => $record->getHysteria2Uri())
                                    ->readOnly()
                                    ->copyable(),
                            ]),
                    ]),
                DeleteAction::make()
                    ->label('Remove')
                    ->before(function (VpnDevice $record) {
                        $user = $record->user;
                        if ($user && $user->fastapi_username && $record->device_identifier) {
                            try {
                                app(ZenVpnApiService::class)->removeDevice($user->fastapi_username, $record->device_identifier);
                            } catch (ZenVpnApiException $e) {
                                Log::warning('[Admin RelationManager] Backend delete failed on revoke', [
                                    'username'          => $user->fastapi_username,
                                    'device_identifier' => $record->device_identifier,
                                    'error'             => $e->getMessage(),
                                ]);
                            }
                        }

                        // Only if it's their very last device, clear fastapi_username
                        if ($user && $user->vpnDevices()->count() === 1) {
                            $user->update(['fastapi_username' => null]);
                        }
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Remove selected')
                        ->before(function ($records) {
                            $api = app(ZenVpnApiService::class);
                            foreach ($records as $device) {
                                $user = $device->user;
                                if ($user && $user->fastapi_username && $device->device_identifier) {
                                    try {
                                        $api->removeDevice($user->fastapi_username, $device->device_identifier);
                                    } catch (ZenVpnApiException $e) {
                                        Log::warning('[Admin RelationManager] Bulk backend delete failed', [
                                            'username'          => $user->fastapi_username,
                                            'device_identifier' => $device->device_identifier,
                                            'error'             => $e->getMessage(),
                                        ]);
                                    }
                                }
                                // Only if it's their very last device, clear fastapi_username
                                if ($user && $user->vpnDevices()->count() === 1) {
                                    $user->update(['fastapi_username' => null]);
                                }
                            }
                        }),
                ]),
            ]);
    }
}
