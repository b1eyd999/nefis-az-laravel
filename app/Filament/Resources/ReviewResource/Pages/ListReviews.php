<?php

namespace App\Filament\Resources\ReviewResource\Pages;

use App\Filament\Resources\ReviewResource;
use Filament\Resources\Pages\ListRecords;

/** The queue: what customers wrote, and what is already on the site. */
class ListReviews extends ListRecords
{
    protected static string $resource = ReviewResource::class;

    /** Nothing is written here: a review comes from a customer's order. */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
