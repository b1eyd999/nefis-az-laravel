<?php

namespace App\Filament\Resources\WrappingResource\Pages;

use App\Filament\Resources\WrappingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWrapping extends CreateRecord
{
    protected static string $resource = WrappingResource::class;

    /** Back to the wrap, so its preview shows the paper just uploaded. */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
