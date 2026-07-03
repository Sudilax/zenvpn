<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentUsersWidget extends BaseWidget
{
    protected static ?int $sort = 3;
    protected int|string|array $columnSpan = 'full';
    protected static ?string $heading = 'Recently Registered Users (Last 7 Days)';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                User::query()
                    ->where('created_at', '>=', now()->subDays(7))
                    ->orderByDesc('created_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable(),

                Tables\Columns\TextColumn::make('vpnDevices_count')
                    ->label('Devices')
                    ->counts('vpnDevices'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active'    => 'success',
                        'suspended' => 'danger',
                        default     => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registered')
                    ->since(),
            ])
            ->paginated(false);
    }
}
