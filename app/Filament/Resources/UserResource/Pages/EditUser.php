<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /** Refuses a role change that would lock the admins out. */
    protected function beforeSave(): void
    {
        UserResource::guardRoleChange($this->record, $this->data['role'] ?? $this->record->role, fn () => $this->halt());
    }

    /** The share is never mass-assigned: it is saved with the role, here. */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $percent = $data['profit_percent'] ?? $record->profit_percent;
        unset($data['profit_percent']);
        $record->update($data);
        UserResource::applyRole($record, $record->role, $percent);

        return $record;
    }
}
