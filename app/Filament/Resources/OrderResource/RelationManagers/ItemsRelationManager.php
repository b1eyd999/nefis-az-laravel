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
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Məhsul')
                    // The name was kept on the order line for when the design is gone.
                    ->getStateUsing(fn ($record) => $record->product?->name ?? $record->product_name ?? 'Silinmiş məhsul'),
                Tables\Columns\ImageColumn::make('product.template_image')
                    ->label('Qutu dizaynı')
                    ->getStateUsing(fn ($record) => Media::url($record->product?->catalogImage()))
                    ->square()
                    ->size(80),
                // The photos and captions under the names the customer filled
                // them in, as on the design's page.
                Tables\Columns\ViewColumn::make('fields')
                    ->label('Müştərinin göndərdiyi')
                    ->view('filament.order-item-fields'),
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
