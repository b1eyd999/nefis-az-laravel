<?php

namespace App\Filament\Resources\CorporateRequestResource\Pages;

use App\Filament\Resources\CorporateRequestResource;
use Filament\Resources\Pages\ListRecords;

/** Companies waiting for an answer, newest first. */
class ListCorporateRequests extends ListRecords
{
    protected static string $resource = CorporateRequestResource::class;

    /** Nothing is created here: a request comes from the company's own page. */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
