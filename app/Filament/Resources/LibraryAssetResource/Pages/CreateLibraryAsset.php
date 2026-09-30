<?php

namespace App\Filament\Resources\LibraryAssetResource\Pages;

use App\Filament\Resources\LibraryAssetResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLibraryAsset extends CreateRecord
{
    protected static string $resource = LibraryAssetResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
