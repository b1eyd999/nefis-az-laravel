<?php

namespace App\Filament\Resources\MaterialResource\Pages;

use App\Filament\Resources\MaterialResource;
use App\Models\Material;
use App\Support\Price;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMaterials extends ListRecords
{
    protected static string $resource = MaterialResource::class;

    public function getSubheading(): ?string
    {
        return 'Bir hazır qutunun materialı: ' . Price::format(round(Material::costOfOneBox(), 3))
            . ' (şokoladsız). Hər sifarişdə material anbardan özü çıxılır.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Material əlavə et'),
        ];
    }
}
