<?php

namespace App\Filament\Resources\CartHandoffResource\Pages;

use App\Filament\Resources\CartHandoffResource;
use Filament\Resources\Pages\ListRecords;

class ListCartHandoffs extends ListRecords
{
    protected static string $resource = CartHandoffResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
