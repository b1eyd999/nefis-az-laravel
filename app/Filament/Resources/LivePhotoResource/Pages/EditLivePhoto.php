<?php

namespace App\Filament\Resources\LivePhotoResource\Pages;

use App\Filament\Resources\LivePhotoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLivePhoto extends EditRecord
{
    use ChecksVideoLink;

    protected static string $resource = LivePhotoResource::class;

    protected function beforeSave(): void
    {
        $link = trim((string) ($this->data['video_url'] ?? ''));
        if ($link !== '' && $link !== $this->record->video_url) {
            $this->checkVideoLink();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('push')->label('Videonu Yandex-ə köçür')->icon('heroicon-o-cloud-arrow-up')->color('warning')
                ->visible(fn () => $this->record->videoPlace() === 'hosting')
                ->action(function () {
                    LivePhotoResource::pushNow($this->record);
                    $this->refreshFormData(['video_url']);
                }),
            Actions\Action::make('open')->label('Səhifəni aç')->icon('heroicon-o-arrow-top-right-on-square')->color('gray')
                ->url(fn () => $this->record->url())->openUrlInNewTab(),
            Actions\DeleteAction::make(),
        ];
    }
}
