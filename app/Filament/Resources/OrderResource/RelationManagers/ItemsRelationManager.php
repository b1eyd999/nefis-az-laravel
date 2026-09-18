<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use App\Support\Media;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Sifariş məhsulları';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\ImageColumn::make('customer_photos')
                    ->label('Müştərinin şəkilləri')
                    ->disk('public')
                    ->square()
                    ->size(80)
                    ->stacked()
                    ->limit(4),
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Məhsul')
                    // The name was kept on the order line for when the design is gone.
                    ->getStateUsing(fn ($record) => $record->product?->name ?? $record->product_name ?? 'Silinmiş məhsul'),
                Tables\Columns\ImageColumn::make('product.template_image')
                    ->label('Qutu dizaynı')
                    ->getStateUsing(fn ($record) => Media::url($record->product?->catalogImage()))
                    ->square()
                    ->size(80),
                Tables\Columns\TextColumn::make('custom_texts')
                    ->label('Mətn')
                    ->formatStateUsing(fn ($state) => implode(' · ', array_filter((array) $state)))
                    ->placeholder('—')
                    ->wrap(),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Say'),
                Tables\Columns\TextColumn::make('price')
                    ->label('Qiymət')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state) . ' ₼' : '—'),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
