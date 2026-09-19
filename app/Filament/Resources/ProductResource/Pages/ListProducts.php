<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Covers are pictures drawn once; after a change to how scenes
            // draw (a new colour strength, say) this draws them all again.
            Actions\Action::make('covers')
                ->label('Qapaqları yenilə')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn () => Product::whereNotNull('cover_scene_id')->exists())
                ->url(fn () => route('cover.page', [
                    'ids' => Product::whereNotNull('cover_scene_id')->pluck('id')->implode(','),
                    'back' => ProductResource::getUrl('index', isAbsolute: false),
                ])),
            Actions\CreateAction::make(),
        ];
    }
}
