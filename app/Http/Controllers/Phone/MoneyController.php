<?php

namespace App\Http\Controllers\Phone;

use App\Filament\Pages\Balance;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Material;
use App\Models\Order;
use App\Models\Setting;
use App\Support\Accounting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The till, on a phone.
 *
 * Six figures the owner actually asks himself during the day, in the order he
 * asks them, and one honest line under the first: some of that money has not
 * arrived yet. The per-order table stays on the computer — eight numeric
 * columns do not survive a phone, and he reconciles sitting down anyway.
 */
class MoneyController extends Controller
{
    public function index(Request $request): View
    {
        $period = (string) $request->query('period', 'month');
        if (! array_key_exists($period, Balance::PERIODS)) {
            $period = 'month';
        }

        [$from, $to] = $this->range($period);
        $report = Accounting::report($from, $to);

        // The books count only money that came. This is what is still out
        // there waiting to be paid, shown beside the till so the owner knows
        // what may yet arrive — and what may not.
        $unpaid = Order::query()
            ->with('items:id,order_id,quantity,price,chocolate_price,wrapping_price,letter_price,ar_price')
            ->whereIn('status', ['awaiting_payment', 'payment_check'])
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->get()
            ->sum(fn (Order $o) => $o->itemsTotal() + (float) ($o->delivery_price ?? 0) + (float) ($o->rush_fee ?? 0));

        return view('phone.money', [
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'r' => $report,
            'unpaid' => (float) $unpaid,
            'waiting' => Order::whereIn('status', ['awaiting_payment', 'payment_check', 'pending'])->count(),
            'low' => Material::where('is_active', true)->orderBy('stock')->get()->filter->isLow(),
            'categories' => collect(Expense::CATEGORIES)
                ->merge(Expense::query()->distinct()->pluck('category'))
                ->filter()->unique()->sort()->values(),
            'shopClosed' => Setting::get(Setting::MAINTENANCE) === '1',
        ]);
    }

    public function storeExpense(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'spent_on' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'category' => ['required', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'amount.required' => 'Məbləği yazın.',
            'amount.min' => 'Məbləğ sıfırdan böyük olmalıdır.',
            'category.required' => 'Xərcin növünü yazın.',
        ]);

        Expense::create($data + ['user_id' => $request->user()->id]);

        return back()->with('phone.flash', [
            'title' => 'Xərc yazıldı',
            'body' => $data['category'] . ' — ' . \App\Support\Price::format((float) $data['amount']),
        ]);
    }

    /** The shop's own door. Never a bare switch — the page asks first. */
    public function toggleShop(Request $request): RedirectResponse
    {
        $close = $request->boolean('close');
        Setting::put(Setting::MAINTENANCE, $close ? '1' : '0');

        return back()->with('phone.flash', [
            'title' => $close ? 'Mağaza bağlandı' : 'Mağaza açıldı',
            'body' => $close
                ? 'Müştərilər sifariş verə bilməyəcək. Admin panel işləyir.'
                : 'Sayt yenidən sifariş qəbul edir.',
        ]);
    }

    /** @return array{0: ?\Carbon\CarbonInterface, 1: ?\Carbon\CarbonInterface} */
    private function range(string $period): array
    {
        return match ($period) {
            'last_month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            'year' => [now()->startOfYear(), now()->endOfYear()],
            'all' => [null, null],
            default => [now()->startOfMonth(), now()->endOfMonth()],
        };
    }
}
