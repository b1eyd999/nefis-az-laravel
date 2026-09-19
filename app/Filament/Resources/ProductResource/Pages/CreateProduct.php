<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Support\YandexDisk;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Http\UploadedFile;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    /** The poster, fetched before creating so a bad link stops the save. */
    private ?UploadedFile $poster = null;

    protected function beforeCreate(): void
    {
        $link = trim((string) ($this->data['poster_url'] ?? ''));
        if ($link === '') {
            return;
        }

        try {
            $this->poster = YandexDisk::downloadImage($link);
        } catch (\RuntimeException $e) {
            Notification::make()->danger()->title('Poster yüklənmədi')->body($e->getMessage())->persistent()->send();
            $this->halt();
        }
    }

    protected function afterCreate(): void
    {
        if ($this->poster) {
            $this->record->storePoster($this->poster, $this->record->poster_url);
        }
    }

    /** A new box is empty until it is built, so go straight to the editor. */
    protected function getRedirectUrl(): string
    {
        return route('box.edit', $this->record->slug);
    }
}
