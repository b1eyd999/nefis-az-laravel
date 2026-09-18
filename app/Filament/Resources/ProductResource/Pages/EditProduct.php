<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('editor')
                ->label('Qutu redaktoru')
                ->icon('heroicon-o-paint-brush')
                ->url(fn () => route('box.edit', $this->record->slug)),
            Actions\DeleteAction::make(),
        ];
    }
}
