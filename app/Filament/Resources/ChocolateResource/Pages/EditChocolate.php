<?php

namespace App\Filament\Resources\ChocolateResource\Pages;

use App\Filament\Resources\ChocolateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditChocolate extends EditRecord
{
    protected static string $resource = ChocolateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return ChocolateResource::getUrl('index');
    }
}
