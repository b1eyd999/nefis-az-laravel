<?php

namespace App\Filament\Pages;

use App\Support\Accounting;
use Carbon\Carbon;
use Filament\Pages\Page;

/**
 * The books at a glance: what came in, what it cost, the real profit and
 * everyone's share of it, for this month, last month, this year or all time.
 */
class Balance extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationGroup = 'Mühasibatlıq';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.balance';

    public const PERIODS = [
        'month' => 'Bu ay',
        'last_month' => 'Keçən ay',
        'year' => 'Bu il',
        'all' => 'Hamısı',
    ];

    public string $period = 'month';

    /** The admin sees the books; a manager sees their own share of them. */
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isStaff();
    }

    public static function getNavigationLabel(): string
    {
        return auth()->user()?->isAdmin() ? 'Balans' : 'Balansım';
    }

    public function getTitle(): string
    {
        return static::getNavigationLabel();
    }

    /** The signed-in manager's line of the profit split, if they have one. */
    public function mine(): ?array
    {
        return collect($this->report['shares'])->firstWhere('user_id', auth()->id());
    }

    /** @return array{0: ?Carbon, 1: ?Carbon} */
    public function range(): array
    {
        return match ($this->period) {
            'last_month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            'year' => [now()->startOfYear(), now()->endOfYear()],
            'all' => [null, null],
            default => [now()->startOfMonth(), now()->endOfMonth()],
        };
    }

    public function getReportProperty(): array
    {
        [$from, $to] = $this->range();

        return Accounting::report($from, $to);
    }
}
