<?php

namespace App\Filament\Resources\GiftPageResource\Pages;

use App\Filament\Resources\GiftPageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGiftPage extends EditRecord
{
    protected static string $resource = GiftPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('view')->label('Saytda bax')->icon('heroicon-o-eye')->color('gray')
                ->url(fn () => $this->record->url())->openUrlInNewTab(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
