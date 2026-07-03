<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class UsersNearCapWidget extends BaseWidget
{
    protected static ?int $sort = 2;
    protected int|string|array $columnSpan = 'full';
    protected static ?string $heading = 'Users Near Data Cap (>80%)';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                User::query()
                    ->whereRaw('data_cap_mb > 0 AND data_used_mb / data_cap_mb > 0.8')
                    ->orderByRaw('data_used_mb / data_cap_mb DESC')
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable(),

                Tables\Columns\TextColumn::make('usage')
                    ->label('Usage')
                    ->getStateUsing(fn (User $record): string =>
                        round($record->data_used_mb / 1024, 1) . ' GB / ' .
                        round($record->data_cap_mb / 1024, 1) . ' GB'
                    ),

                Tables\Columns\TextColumn::make('percent')
                    ->label('% Used')
                    ->getStateUsing(fn (User $record): string =>
                        $record->dataUsagePercent() . '%'
                    )
                    ->badge()
                    ->color(fn (User $record): string =>
                        $record->dataUsagePercent() >= 95 ? 'danger' : 'warning'
                    ),
            ])
            ->paginated(false);
    }
}
