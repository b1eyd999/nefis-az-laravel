<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    /** Set after a save that changed what the catalogue cover shows. */
    private bool $coverOutdated = false;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('editor')
                ->label('Qutu redaktoru')
                ->icon('heroicon-o-paint-brush')
                ->url(fn () => route('box.edit', $this->record->slug)),
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $product = $this->record;

        if (! $product->cover_scene_id) {
            if ($product->cover_image) {
                Storage::disk('public')->delete($product->cover_image);
                $product->forceFill(['cover_image' => null])->saveQuietly();
            }

            return;
        }

        // Nothing to draw until the box editor has a visual to put in the scene.
        $this->coverOutdated = $product->preview_image
            && ($product->wasChanged(['cover_scene_id', 'box_color']) || ! $product->cover_image);
    }

    /** A cover is drawn in the browser, on a page that then comes back here. */
    protected function getRedirectUrl(): ?string
    {
        if (! $this->coverOutdated) {
            return null;
        }

        return route('cover.page', [
            'ids' => $this->record->id,
            'back' => ProductResource::getUrl('edit', ['record' => $this->record], isAbsolute: false),
        ]);
    }
}
