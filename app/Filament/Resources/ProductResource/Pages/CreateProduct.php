<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    /** A new box is empty until it is built, so go straight to the editor. */
    protected function getRedirectUrl(): string
    {
        return route('box.edit', $this->record->slug);
    }
}
