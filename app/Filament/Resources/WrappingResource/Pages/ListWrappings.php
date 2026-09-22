<?php

namespace App\Filament\Resources\WrappingResource\Pages;

use App\Filament\Resources\WrappingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWrappings extends ListRecords
{
    protected static string $resource = WrappingResource::class;

    public function getSubheading(): ?string
    {
        return 'Müştəri şokoladdan sonra qablaşdırma seçir və qutunu bu kağıza bükülmüş görür. Eyni qiymətlilər bir qrupdadır.';
    }

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Qablaşdırma əlavə et')];
    }
}
