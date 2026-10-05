<?php

namespace App\Filament\Resources\PromoCodeResource\Pages;

use App\Filament\Resources\PromoCodeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPromoCodes extends ListRecords
{
    protected static string $resource = PromoCodeResource::class;

    public function getSubheading(): ?string
    {
        return 'Kodu müştəri sifarişi tamamlayarkən yazır. Endirim yalnız məhsullardan tutulur — '
            . 'çatdırılma və təcili haqqı toxunulmur. Bir kodun faizini sonra dəyişsəniz, '
            . 'artıq verilmiş sifarişlərə heç nə olmur.';
    }

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Promokod yarat')];
    }
}
