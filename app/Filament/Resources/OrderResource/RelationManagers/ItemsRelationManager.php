<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

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
                Tables\Columns\ImageColumn::make('customer_photo')
                    ->label('Müştərinin şəkli')
                    ->disk('public')
                    ->square()
                    ->size(80),
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Məhsul'),
                Tables\Columns\ImageColumn::make('product.template_image')
                    ->label('Qutu dizaynı')
                    ->disk('public')
                    ->square()
                    ->size(80),
                Tables\Columns\TextColumn::make('custom_text')
                    ->label('Mətn')
                    ->placeholder('—'),
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
