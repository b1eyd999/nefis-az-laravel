<?php

namespace App\Filament\Resources\WrappingResource\Pages;

use App\Filament\Resources\WrappingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWrapping extends EditRecord
{
    protected static string $resource = WrappingResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
