<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /** Refuses a role change that would lock the admins out. */
    protected function beforeSave(): void
    {
        UserResource::guardRoleChange($this->record, $this->data['role'] ?? $this->record->role, fn () => $this->halt());
    }
}
