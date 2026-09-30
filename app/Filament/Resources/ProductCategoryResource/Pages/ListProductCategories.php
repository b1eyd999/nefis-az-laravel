<?php

namespace App\Filament\Resources\ProductCategoryResource\Pages;

use App\Filament\Resources\ProductCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProductCategories extends ListRecords
{
    protected static string $resource = ProductCategoryResource::class;

    public function getSubheading(): ?string
    {
        return 'Dizaynlar səhifəsindəki rəflər. Sıranı sürüşdürməklə dəyişin; bir dizaynın hansı rəfdə olduğu «Məhsullar» səhifəsində seçilir.';
    }

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Kateqoriya əlavə et')];
    }
}
