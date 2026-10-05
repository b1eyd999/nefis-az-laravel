<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Pages\Page;

/**
 * Where the couriers are.
 *
 * A map and a list beside it, both redrawn by the page itself every fifteen
 * seconds — not by Livewire, because a re-render would throw the map away
 * under the owner's finger. Everything it draws comes from one address,
 * `couriers.live`, which is the only thing staff may ask of the courier
 * controller.
 *
 * A courier appears here while he is sharing, and while he still holds an
 * order that has not been delivered. He switches the sharing on himself and it
 * lapses by itself: a man who has gone home shows as his last known place and
 * the hour, never as a dot that follows him.
 */
class Couriers extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Mağaza';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Kuryerlər';

    protected static ?string $navigationLabel = 'Kuryerlər';

    protected static string $view = 'filament.pages.couriers';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isStaff();
    }

    /** The badge is the number of couriers whose phones are reporting now. */
    public static function getNavigationBadge(): ?string
    {
        $out = User::query()->where('role', User::COURIER)
            ->whereNotNull('sharing_until')->where('sharing_until', '>', now())->count();

        return $out > 0 ? (string) $out : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }
}
