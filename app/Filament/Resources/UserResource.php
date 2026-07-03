<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;
    protected static ?string $navigationLabel = 'Users';
    protected static ?int $navigationSort = 1;

    // ─── Form (Edit only — no Create via admin) ───────────────────────────────

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Account')->components([
                TextInput::make('name')
                    ->disabled(),

                TextInput::make('email')
                    ->disabled(),

                Select::make('status')
                    ->options([
                        'active'    => 'Active',
                        'suspended' => 'Suspended',
                    ])
                    ->required(),

                Toggle::make('is_admin')
                    ->label('Admin access')
                    ->helperText('Grants access to this admin panel.'),
            ])->columns(2),

            Section::make('VPN Limits')->components([
                TextInput::make('device_limit')
                    ->label('Device limit')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->required(),

                TextInput::make('data_cap_mb')
                    ->label('Data Cap (GB)')
                    ->numeric()
                    ->minValue(0)
                    ->formatStateUsing(fn ($state) => $state !== null ? $state / 1024 : null)
                    ->dehydrateStateUsing(fn ($state) => $state !== null ? $state * 1024 : null)
                    ->helperText('Enter 0 for unlimited. Example: 50 = 50 GB.')
                    ->required(),
            ])->columns(2),
        ]);
    }

    // ─── Table ────────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('vpn_devices_count')
                    ->label('Devices')
                    ->counts('vpnDevices')
                    ->formatStateUsing(fn ($state, User $record): string =>
                        ($state ?? 0) . ' / ' . ($record->device_limit === 0 ? 'Unlimited' : $record->device_limit)
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('data_usage')
                    ->label('Data used')
                    ->getStateUsing(fn (User $record): string =>
                        $record->data_cap_mb === 0
                            ? round($record->data_used_mb / 1024, 1) . ' / Unlimited'
                            : round($record->data_used_mb / 1024, 1) . ' / ' . round($record->data_cap_mb / 1024, 1) . ' GB'
                    ),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active'    => 'success',
                        'suspended' => 'danger',
                        default     => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_admin')
                    ->label('Admin')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registered')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active'    => 'Active',
                        'suspended' => 'Suspended',
                    ]),

                Tables\Filters\TernaryFilter::make('is_admin')
                    ->label('Admin accounts'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('activate')
                        ->label('Activate selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['status' => 'active']))
                        ->deselectRecordsAfterCompletion()
                        ->requiresConfirmation(),

                    BulkAction::make('suspend')
                        ->label('Suspend selected')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->action(fn ($records) => $records->each->update(['status' => 'suspended']))
                        ->deselectRecordsAfterCompletion()
                        ->requiresConfirmation(),

                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'edit'  => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
