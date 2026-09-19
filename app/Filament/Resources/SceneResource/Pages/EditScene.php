<?php

namespace App\Filament\Resources\SceneResource\Pages;

use App\Filament\Resources\SceneResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditScene extends EditRecord
{
    protected static string $resource = SceneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('editor')
                ->label('Səhnə redaktoru')
                ->icon('heroicon-o-paint-brush')
                ->url(fn () => route('scene.edit', $this->record)),
            Actions\DeleteAction::make(),
        ];
    }
}
