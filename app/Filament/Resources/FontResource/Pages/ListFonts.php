<?php

namespace App\Filament\Resources\FontResource\Pages;

use App\Filament\Resources\FontResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFonts extends ListRecords
{
    protected static string $resource = FontResource::class;

    public function getSubheading(): ?string
    {
        return 'Qutu redaktorunda mətn seçiləndə bu şriftlər təklif olunur. Yeni şrift buradan və ya redaktorun özündən yüklənir.';
    }

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Şrift yüklə')];
    }
}
