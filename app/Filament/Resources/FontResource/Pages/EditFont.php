<?php

namespace App\Filament\Resources\FontResource\Pages;

use App\Filament\Resources\FontResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFont extends EditRecord
{
    protected static string $resource = FontResource::class;

    /** Renaming an uploaded face renames its family too; a Google one keeps its own. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->record->file || ($data['file'] ?? null)) {
            $data['family'] = FontResource::familyFor($data['name']);
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
