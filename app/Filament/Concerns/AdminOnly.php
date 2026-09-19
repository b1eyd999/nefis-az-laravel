<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * A panel section only admins may open; managers get into the panel for the
 * orders and never see these.
 */
trait AdminOnly
{
    public static function can(string $action, ?Model $record = null): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }
}
