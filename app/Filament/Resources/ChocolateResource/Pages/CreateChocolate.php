<?php

namespace App\Filament\Resources\ChocolateResource\Pages;

use App\Filament\Resources\ChocolateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateChocolate extends CreateRecord
{
    protected static string $resource = ChocolateResource::class;

    protected function getRedirectUrl(): string
    {
        return ChocolateResource::getUrl('index');
    }
}
