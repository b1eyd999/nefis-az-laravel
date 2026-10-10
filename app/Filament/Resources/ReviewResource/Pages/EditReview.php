<?php

namespace App\Filament\Resources\ReviewResource\Pages;

use App\Filament\Resources\ReviewResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

/** One review: what was said, what we answer, and whether it is up. */
class EditReview extends EditRecord
{
    protected static string $resource = ReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    /**
     * The switch is a switch, not a column: what is written down is the
     * moment and the person, so a review can be seen to have been let
     * through by somebody at some time.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $shown = (bool) ($this->data['shown'] ?? false);

        if ($shown && ! $this->record->approved_at) {
            $data['approved_at'] = now();
            $data['approved_by'] = auth()->id();
        } elseif (! $shown && $this->record->approved_at) {
            $data['approved_at'] = null;
            $data['approved_by'] = null;
        }

        return $data;
    }
}
