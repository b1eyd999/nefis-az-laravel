<?php

namespace App\Filament\Resources\ContactMessageResource\Pages;

use App\Filament\Resources\ContactMessageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

/** One message: what was asked, and whether anybody has answered yet. */
class EditContactMessage extends EditRecord
{
    protected static string $resource = ContactMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    /**
     * The switch is a switch, not a column: it is the moment and the person
     * that are written down, so an answer can be seen to have been given by
     * somebody at some time.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $answered = (bool) ($this->data['answered'] ?? false);

        if ($answered && ! $this->record->answered_at) {
            $data['answered_at'] = now();
            $data['answered_by'] = auth()->id();
        } elseif (! $answered && $this->record->answered_at) {
            $data['answered_at'] = null;
            $data['answered_by'] = null;
        }

        return $data;
    }
}
