<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Support\YandexDisk;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    /** Set after a save that changed what the catalogue cover shows. */
    private bool $coverOutdated = false;

    /** A new poster, fetched before saving so a bad link stops the save. */
    private ?UploadedFile $poster = null;

    protected function beforeSave(): void
    {
        $link = trim((string) ($this->data['poster_url'] ?? ''));
        if ($link === '' || ($link === $this->record->poster_url && $this->record->poster_image)) {
            return;
        }

        try {
            $this->poster = YandexDisk::downloadImage($link);
        } catch (\RuntimeException $e) {
            Notification::make()->danger()->title('Poster yüklənmədi')->body($e->getMessage())->persistent()->send();
            $this->halt();
        }
    }

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

        if ($this->poster) {
            $product->storePoster($this->poster, $product->poster_url);
        } elseif (! $product->poster_url && $product->poster_image) {
            $product->removePoster();
        }

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
