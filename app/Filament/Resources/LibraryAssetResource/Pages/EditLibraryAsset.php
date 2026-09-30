<?php

namespace App\Filament\Resources\LibraryAssetResource\Pages;

use App\Filament\Resources\LibraryAssetResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLibraryAsset extends EditRecord
{
    protected static string $resource = LibraryAssetResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
