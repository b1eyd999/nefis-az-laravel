<?php

namespace App\Filament\Resources\FontResource\Pages;

use App\Filament\Resources\FontResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFont extends CreateRecord
{
    protected static string $resource = FontResource::class;

    /** The family is the site's own name for the face, never the file's. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['family'] = FontResource::familyFor($data['name']);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
