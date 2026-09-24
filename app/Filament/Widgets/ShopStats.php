<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Support\Accounting;
use App\Support\Price;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/**
 * The month at a glance: how many orders came, what they were worth, what is
 * left to do. The money is the owner's business, so managers see the counts
 * and not the sums.
 */
class ShopStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    // Drawn with the page: these are three small queries, and a lazy
    // widget can sit empty if its own request never fires.
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isStaff();
    }

    protected function getStats(): array
    {
        $month = Carbon::now()->startOfMonth();
        $before = (clone $month)->subMonth();

        $orders = fn (Carbon $from, ?Carbon $to = null) => Order::where('status', '!=', 'cancelled')
            ->where('created_at', '>=', $from)
            ->when($to, fn ($q) => $q->where('created_at', '<', $to))
            ->count();

        $now = $orders($month);
        $then = $orders($before, $month);
        $waiting = Order::whereIn('status', ['awaiting_payment', 'payment_check', 'pending'])->count();

        $stats = [
            Stat::make('Bu ay sifariş', $now)
                ->description($this->change($now, $then) . ' — keçən ay ' . $then)
                ->descriptionIcon($now >= $then ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($now >= $then ? 'success' : 'danger'),
            Stat::make('Gözləyən sifariş', $waiting)
                ->description($waiting ? 'Ödəniş və hazırlanma gözləyir' : 'Hamısı bağlanıb')
                ->color($waiting ? 'warning' : 'success'),
        ];

        if (! auth()->user()?->isAdmin()) {
            return $stats;
        }

        $report = Accounting::report($month);
        $revenue = (float) ($report['revenue'] ?? 0);

        return array_merge($stats, [
            Stat::make('Bu ayın gəliri', Price::format($revenue))
                ->description('Xalis mənfəət: ' . Price::format((float) ($report['net'] ?? 0)))
                ->color('success'),
            Stat::make('Orta sifariş', Price::format($now > 0 ? $revenue / $now : 0))
                ->description('Bir sifarişin ortalaması')
                ->color('gray'),
        ]);
    }

    private function change(int $now, int $then): string
    {
        if ($then === 0) {
            return $now > 0 ? 'ilk ay' : 'hələ yoxdur';
        }

        $percent = (int) round(($now - $then) / $then * 100);

        return ($percent >= 0 ? '+' : '') . $percent . '%';
    }
}
