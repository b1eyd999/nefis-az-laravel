<?php

namespace App\Filament\Resources\ContactMessageResource\Pages;

use App\Filament\Resources\ContactMessageResource;
use Filament\Resources\Pages\ListRecords;

/** The owner's queue: who wrote, and whether anybody has answered. */
class ListContactMessages extends ListRecords
{
    protected static string $resource = ContactMessageResource::class;

    /** Nothing is created here: a message comes from the contact page. */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
