<?php

namespace App\Filament\Resources\MaterialResource\RelationManagers;

use App\Models\StockMovement;
use App\Support\Price;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/** Every change to this material's stock: purchases, orders, returns, counts. */
class MovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'movements';

    protected static ?string $title = 'Anbar hərəkətləri';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        $unit = fn () => $this->getOwnerRecord()->unit;

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Tarix')->dateTime('d.m.Y H:i'),
                Tables\Columns\TextColumn::make('type')
                    ->label('Növ')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => StockMovement::TYPES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        StockMovement::PURCHASE => 'success', StockMovement::USAGE => 'warning', StockMovement::RETURN => 'info', default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Miqdar')
                    ->formatStateUsing(fn ($state) => ($state > 0 ? '+' : '') . rtrim(rtrim(number_format($state, 3, '.', ' '), '0'), '.') . ' ' . $unit()),
                Tables\Columns\TextColumn::make('amount')->label('Məbləğ')->formatStateUsing(fn ($state) => Price::format(abs($state))),
                Tables\Columns\TextColumn::make('note')->label('Qeyd')->placeholder('—'),
            ])
            ->defaultSort('id', 'desc');
    }
}
