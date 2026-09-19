<?php

namespace App\Filament\Resources\SceneResource\Pages;

use App\Filament\Resources\SceneResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListScenes extends ListRecords
{
    protected static string $resource = SceneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
