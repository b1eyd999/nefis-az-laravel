<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use App\Support\Media;
use App\Support\Price;
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
                // On a phone only "what the customer sent" stays, with the rest summed up at its
                // top (see filament.order-item-fields): side by side it all ran off the screen.
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Məhsul')
                    ->visibleFrom('md')
                    // The name was kept on the order line for when the design is gone.
                    ->getStateUsing(fn ($record) => $record->product?->name ?? $record->product_name ?? 'Silinmiş məhsul'),
                Tables\Columns\ImageColumn::make('product.template_image')
                    ->label('Qutu dizaynı')
                    // A letter bought on its own has no box: its photo stands in.
                    ->getStateUsing(fn ($record) => $record->product ? Media::url($record->product->catalogImage()) : $record->letterPhotoUrl())
                    ->square()
                    ->size(80)
                    ->visibleFrom('md'),
                // The photos and captions under the names the customer filled
                // them in, as on the design's page.
                Tables\Columns\ViewColumn::make('fields')
                    ->label('Müştərinin göndərdiyi')
                    ->view('filament.order-item-fields'),
                Tables\Columns\TextColumn::make('chocolate_name')
                    ->label('Şokolad')
                    ->description(fn ($record) => $record->chocolate_price ? Price::format($record->chocolate_price) : null)
                    ->placeholder('—')
                    ->wrap()
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('wrapping_name')
                    ->label('Qablaşdırma')
                    ->description(fn ($record) => $record->wrapping_price ? Price::format($record->wrapping_price) : null)
                    ->placeholder('—')
                    ->wrap()
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Say')
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('price')
                    ->label('Qiymət')
                    ->visibleFrom('md')
                    // The box and the bar each, then the line's total.
                    ->getStateUsing(fn ($record) => $record->unitPrice() > 0 ? Price::format($record->unitPrice() * $record->quantity) : '—')
                    ->description(fn ($record) => ($record->chocolate_price || $record->wrapping_price)
                        ? 'qutu ' . ($record->price ? Price::format($record->price) : '—')
                            . ($record->chocolate_price ? ' + şokolad ' . Price::format($record->chocolate_price) : '')
                            . ($record->wrapping_price ? ' + qablaşdırma ' . Price::format($record->wrapping_price) : '')
                            . ($record->quantity > 1 ? ' × ' . $record->quantity : '')
                        : null),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
