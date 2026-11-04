<?php

namespace App\Filament\Resources\BoxSizeResource\Pages;

use App\Filament\Resources\BoxSizeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBoxSize extends EditRecord
{
    protected static string $resource = BoxSizeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('view')->label('Saytda bax')->icon('heroicon-o-eye')->color('gray')
                ->url(fn () => route('corporate.index'))->openUrlInNewTab(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
