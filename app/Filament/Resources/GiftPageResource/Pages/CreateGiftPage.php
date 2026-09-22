<?php

namespace App\Filament\Resources\GiftPageResource\Pages;

use App\Filament\Resources\GiftPageResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGiftPage extends CreateRecord
{
    protected static string $resource = GiftPageResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
