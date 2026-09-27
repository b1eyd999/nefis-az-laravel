<?php

namespace App\Filament\Resources\CorporateRequestResource\Pages;

use App\Filament\Resources\CorporateRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

/** One company's request: what they want, and where the talk got to. */
class EditCorporateRequest extends EditRecord
{
    protected static string $resource = CorporateRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
