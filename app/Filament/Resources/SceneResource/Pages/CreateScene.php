<?php

namespace App\Filament\Resources\SceneResource\Pages;

use App\Filament\Resources\SceneResource;
use Filament\Resources\Pages\CreateRecord;

class CreateScene extends CreateRecord
{
    protected static string $resource = SceneResource::class;

    /** A new scene is empty until it is built, so go straight to the editor. */
    protected function getRedirectUrl(): string
    {
        return route('scene.edit', $this->record);
    }
}
