<?php

namespace App\Filament\Resources;

use App\Exceptions\ZenVpnApiException;
use App\Filament\Resources\VpnDeviceResource\Pages;
use App\Models\VpnDevice;
use App\Services\ZenVpnApiService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;

class VpnDeviceResource extends Resource
{
    protected static ?string $model = VpnDevice::class;
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDeviceTablet;
    protected static ?string $navigationLabel = 'VPN Devices';
    protected static ?string $modelLabel = 'VPN Device';
    protected static ?int $navigationSort = 2;

    // ─── Form (view-only; devices are not created via admin) ─────────────────

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('device_name')->disabled(),
            TextInput::make('vpn_username')->disabled(),
            TextInput::make('sni')->disabled(),
            TextInput::make('status')->disabled(),
        ]);
    }

    // ─── Table ────────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('device_name')
                    ->label('Device')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.email')
                    ->label('Owner')
                    ->description(fn (VpnDevice $record): ?string => $record->user?->name)
                    ->searchable(query: function ($query, string $search) {
                        return $query->whereHas('user', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%")
                              ->orWhere('email', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('sni')
                    ->label('SNI')
                    ->badge()
                    ->color('indigo'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active'   => 'success',
                        'inactive' => 'gray',
                        default    => 'gray',
                    }),

                // Green dot if last_seen within the last 5 minutes
                Tables\Columns\IconColumn::make('online')
                    ->label('Online')
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
                    ->label('Last seen')
                    ->since()
                    ->placeholder('Never'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Added')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active'   => 'Active',
                        'inactive' => 'Inactive',
                    ]),

                Tables\Filters\SelectFilter::make('sni')
                    ->options(VpnDevice::SNI_OPTIONS),
            ])
            ->recordActions([
                Action::make('revoke')
                    ->label('Revoke')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Revoke Device')
                    ->modalDescription(fn (VpnDevice $record): string =>
                        "Remove \"{$record->device_name}\" from {$record->user->email}? " .
                        "This will also delete the user from the VPN backend."
                    )
                    ->action(function (VpnDevice $record): void {
                        // Call FastAPI backend to remove user
                        if ($record->vpn_username) {
                            try {
                                app(ZenVpnApiService::class)->deleteUser($record->vpn_username);
                            } catch (ZenVpnApiException $e) {
                                Log::warning('[Admin] Backend delete failed on revoke', [
                                    'vpn_username' => $record->vpn_username,
                                    'error'        => $e->getMessage(),
                                ]);
                            }
                        }

                        $record->delete();

                        Notification::make()
                            ->title('Device revoked')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Revoke selected')
                        ->before(function ($records): void {
                            $api = app(ZenVpnApiService::class);
                            foreach ($records as $device) {
                                if ($device->vpn_username) {
                                    try {
                                        $api->deleteUser($device->vpn_username);
                                    } catch (ZenVpnApiException $e) {
                                        Log::warning('[Admin] Bulk backend delete failed', [
                                            'vpn_username' => $device->vpn_username,
                                        ]);
                                    }
                                }
                            }
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVpnDevices::route('/'),
        ];
    }
}
