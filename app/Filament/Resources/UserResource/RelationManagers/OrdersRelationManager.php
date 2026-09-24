<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Support\Price;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/** The user's orders, newest first, each opening the order itself. */
class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $title = 'Sifarişləri';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(fn ($query) => $query->with('items')->withCount('items'))
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('№'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => OrderResource::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'pending' => 'warning', 'confirmed' => 'info', 'ready' => 'primary', 'completed' => 'success', 'cancelled' => 'danger', default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('items_count')->label('Məhsul'),
                Tables\Columns\TextColumn::make('total')
                    ->label('Məbləğ')
                    ->getStateUsing(function (Order $o) {
                        $sum = $o->items->sum(fn ($i) => $i->unitPrice() * $i->quantity);

                        return $sum > 0 ? Price::format($sum) : '—';
                    }),
                Tables\Columns\TextColumn::make('created_at')->label('Tarix')->dateTime('d.m.Y H:i'),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label('Aç')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Order $o) => OrderResource::getUrl('edit', ['record' => $o])),
            ]);
    }
}
