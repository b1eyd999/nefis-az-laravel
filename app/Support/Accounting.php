<?php

namespace App\Support;

use App\Models\Expense;
use App\Models\Material;
use App\Models\Order;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Withdrawal;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * The shop's books.
 *
 * Every order takes its materials out of stock as it is placed (what each
 * box needs × how many boxes), at what they cost then; a cancelled order
 * puts them back, and one brought back from cancelled takes them again.
 * Profit is what orders bring in, less what their boxes and chocolate cost
 * and the other expenses — shared out between the owner and the managers.
 * Buying stock is cash going out, not a loss: it only turns into cost as
 * boxes are made from it.
 */
class Accounting
{
    /** The line the profit nobody has been given is shown under. */
    public const BUSINESS = 'Biznesin inkişafı';

    /**
     * The day the books start counting from, if the owner has drawn a line.
     *
     * Nothing is deleted by it: the orders, the expenses and the withdrawals
     * all stay where they are, and the page simply begins after that moment
     * — the way a new ledger begins on a new page.
     */
    public static function booksFrom(): ?\Carbon\CarbonInterface
    {
        $when = Setting::get(Setting::BOOKS_FROM);

        return filled($when) ? \Illuminate\Support\Carbon::parse($when) : null;
    }

    /** Takes an order's materials out of stock and records what they cost. */
    public static function consume(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $boxes = (int) $order->items()->whereNotNull('product_id')->sum('quantity');
            $total = 0.0;
            foreach (Material::used()->lockForUpdate()->get() as $m) {
                $qty = round($m->per_box * $boxes, 3);
                if ($qty <= 0) {
                    continue;
                }
                $unit = $m->unitCost();
                StockMovement::create([
                    'material_id' => $m->id, 'order_id' => $order->id, 'type' => StockMovement::USAGE,
                    'quantity' => -$qty, 'unit_cost' => round($unit, 4), 'amount' => round($qty * $unit, 2),
                    'note' => "Sifariş #{$order->id}: {$boxes} qutu",
                ]);
                $m->decrement('stock', $qty);
                $total += $qty * $unit;
            }
            $order->forceFill(['materials_cost' => round($total, 2)])->saveQuietly();
        });
    }

    /**
     * Brings an order's materials in line with what it now holds.
     *
     * `consume()` is for a brand new order: it counts every box and writes a
     * fresh set of movements. Call it a second time and it takes the same
     * boxes out of stock again. This one compares what the order should have
     * taken with what it has already taken and moves only the difference —
     * out when a box is added, back when one is removed — so it may be called
     * as often as the order is edited, and after an edit that changed nothing
     * it does nothing at all.
     *
     * An order that is off the books has already given everything back, and
     * is left alone: bringing it back from cancelled is `consume()`'s job.
     */
    public static function resync(Order $order): void
    {
        if (in_array($order->status, Order::OFF_THE_BOOKS, true)) {
            return;
        }

        DB::transaction(function () use ($order) {
            $boxes = (int) $order->items()->whereNotNull('product_id')->sum('quantity');
            $moves = StockMovement::where('order_id', $order->id)
                ->whereIn('type', [StockMovement::USAGE, StockMovement::RETURN])
                ->get()
                ->groupBy('material_id');

            foreach (Material::used()->lockForUpdate()->get() as $m) {
                $mine = $moves->get($m->id) ?? collect();
                $out = round(-(float) $mine->sum('quantity'), 3);     // what this order holds now
                $want = round($m->per_box * $boxes, 3);
                $delta = round($want - $out, 3);
                if (abs($delta) < 0.0005) {
                    continue;
                }

                // Taking more is valued at today's cost; giving back is valued
                // at the price it went out at, so the two cancel exactly.
                $unit = $delta > 0
                    ? $m->unitCost()
                    : ($mine->firstWhere('type', StockMovement::USAGE)?->unit_cost ?? $m->unitCost());

                // Usage is written negative and a return positive, as the rest
                // of the ledger does — and -$delta is already both.
                StockMovement::create([
                    'material_id' => $m->id,
                    'order_id' => $order->id,
                    'type' => $delta > 0 ? StockMovement::USAGE : StockMovement::RETURN,
                    'quantity' => -$delta,
                    'unit_cost' => round($unit, 4),
                    'amount' => round(abs($delta) * $unit, 2),
                    'note' => "Sifariş #{$order->id} dəyişdi: {$boxes} qutu",
                ]);
                $m->decrement('stock', $delta);
            }

            // What the order cost in materials, read back from its own
            // movements: for each material, what is still out, at the price it
            // went out at. Rounded once at the end, as `consume()` rounds, so
            // an order edited and edited back comes to the same figure it had
            // when it was placed rather than drifting by a kopek a time.
            $total = 0.0;
            foreach (StockMovement::where('order_id', $order->id)
                ->whereIn('type', [StockMovement::USAGE, StockMovement::RETURN])
                ->get()->groupBy('material_id') as $mine) {
                $out = -(float) $mine->sum('quantity');
                $total += $out * (float) ($mine->firstWhere('type', StockMovement::USAGE)?->unit_cost ?? 0);
            }

            $order->forceFill(['materials_cost' => round(max(0, $total), 2)])->saveQuietly();
        });
    }

    /** Puts a cancelled order's materials back into stock. */
    public static function restore(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $taken = StockMovement::where('order_id', $order->id)
                ->whereIn('type', [StockMovement::USAGE, StockMovement::RETURN])
                ->get()
                ->groupBy('material_id');
            foreach ($taken as $materialId => $moves) {
                $out = -$moves->sum('quantity');   // what is still out for this order
                if ($out <= 0) {
                    continue;
                }
                $usage = $moves->firstWhere('type', StockMovement::USAGE);
                StockMovement::create([
                    'material_id' => $materialId, 'order_id' => $order->id, 'type' => StockMovement::RETURN,
                    'quantity' => $out, 'unit_cost' => $usage?->unit_cost ?? 0, 'amount' => round($out * ($usage?->unit_cost ?? 0), 2),
                    'note' => "Sifariş #{$order->id} ləğv edildi",
                ]);
                Material::whereKey($materialId)->increment('stock', $out);
            }
        });
    }

    /** Buys stock: packs × pack size go in, the pack price is remembered. */
    public static function purchase(Material $m, float $packs, float $packPrice, ?string $note = null): StockMovement
    {
        return DB::transaction(function () use ($m, $packs, $packPrice, $note) {
            $qty = round($packs * $m->pack_size, 3);
            $m->forceFill(['pack_price' => $packPrice])->save();
            $m->increment('stock', $qty);

            return StockMovement::create([
                'material_id' => $m->id, 'type' => StockMovement::PURCHASE, 'quantity' => $qty,
                'unit_cost' => round($m->unitCost(), 4), 'amount' => round($packs * $packPrice, 2),
                'note' => $note ?: rtrim(rtrim(number_format($packs, 2, '.', ''), '0'), '.') . ' paçka',
                'user_id' => auth()->id(),
            ]);
        });
    }

    /** Sets the stock to what was actually counted. */
    public static function adjust(Material $m, float $counted, ?string $note = null): ?StockMovement
    {
        $diff = round($counted - $m->stock, 3);
        if ($diff == 0.0) {
            return null;
        }

        return DB::transaction(function () use ($m, $counted, $diff, $note) {
            $m->forceFill(['stock' => $counted])->save();

            return StockMovement::create([
                'material_id' => $m->id, 'type' => StockMovement::ADJUST, 'quantity' => $diff,
                'unit_cost' => round($m->unitCost(), 4), 'amount' => round($diff * $m->unitCost(), 2),
                'note' => $note ?: 'Sayım', 'user_id' => auth()->id(),
            ]);
        });
    }

    /**
     * The books for a period (null = all time): what came in, what it cost,
     * the net profit and everyone's share of it.
     */
    public static function report(?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        // Paid, or at least taken on by hand. An order still waiting for its
        // money is a promise, not income — and with the card the only way to
        // pay, a payment page closed half-way is an everyday thing. Counting
        // those inflated the takings, the profit and every share of it.
        // Everything before the line the owner drew is another chapter.
        $start = self::booksFrom();
        if ($start && (! $from || $start->greaterThan($from))) {
            $from = $start;
        }

        $orders = Order::with('items', 'adjustments')
            ->whereNotIn('status', array_merge(Order::OFF_THE_BOOKS, ['awaiting_payment', 'payment_check']))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->latest()
            ->get();

        $rows = $orders->map(function (Order $o) {
            // What was actually charged for the goods. A promo code came off
            // them before the customer paid, so it never was income; and money
            // still owed either way over a later change has not moved yet — a
            // promise is no more income here than it is anywhere else.
            $goods = $o->itemsTotal() - (float) ($o->discount ?? 0) - $o->outstanding() + $o->owedBack();
            $delivery = (float) ($o->delivery_price ?? 0);
            $chocolate = (float) $o->items->sum(fn ($i) => (float) ($i->chocolate_cost ?? 0) * $i->quantity);
            $materials = (float) ($o->materials_cost ?? 0);
            // The rush fee is charged and settled by the bank like everything
            // else; the books used to leave it out, three manat at a time.
            $rush = (float) ($o->rush_fee ?? 0);
            $revenue = $goods + $delivery + $rush;

            return [
                'order' => $o, 'boxes' => (int) $o->items->whereNotNull('product_id')->sum('quantity'),
                'revenue' => $revenue, 'goods' => $goods, 'delivery' => $delivery, 'rush' => $rush,
                'chocolate' => $chocolate, 'materials' => $materials,
                'profit' => $revenue - $chocolate - $materials,
            ];
        });

        $expenses = (float) Expense::query()
            ->when($from, fn ($q) => $q->whereDate('spent_on', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('spent_on', '<=', $to))
            ->sum('amount');
        $purchases = (float) StockMovement::where('type', StockMovement::PURCHASE)
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->sum('amount');
        // Money carried out of the till. It is not a cost of making the boxes,
        // so the profit stands; only what is left in hand goes down.
        $taken = Withdrawal::query()
            ->when($from, fn ($q) => $q->whereDate('taken_on', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('taken_on', '<=', $to))
            ->orderByDesc('taken_on')->orderByDesc('id')
            ->get();
        $withdrawn = (float) $taken->sum('amount');

        $revenue = $rows->sum('revenue');
        $chocolate = $rows->sum('chocolate');
        $materials = $rows->sum('materials');
        $gross = $revenue - $chocolate - $materials;
        $net = $gross - $expenses;

        // Each staff member's share, as the admin gave it with the role. Until
        // anyone has one, the older list of names from the settings stands.
        $holders = User::shareholders();
        $shares = $holders->isNotEmpty()
            ? $holders->map(fn (User $u) => ['user_id' => $u->id, 'name' => $u->name, 'percent' => $u->profit_percent])
            : collect(Setting::profitShares())->map(fn ($s) => ['user_id' => null, 'name' => $s['name'], 'percent' => (float) $s['percent']]);

        // What nobody takes home stays in the shop. It is a real part of the
        // profit and belongs on the page: the owner wants to see how much is
        // left for the business itself, not to work it out in his head.
        $rest = round(100 - $shares->sum('percent'), 2);
        if ($holders->isNotEmpty() && $rest > 0.005) {
            $shares->push(['user_id' => null, 'name' => self::BUSINESS, 'percent' => $rest, 'rest' => true]);
        }

        // What each person has already drawn against their share. Money taken
        // out with nobody's name on it — tax, cash set aside — is the shop's
        // own and is not charged to anybody.
        $drawn = $taken->whereNotNull('user_id')->groupBy('user_id')->map(fn ($rows) => (float) $rows->sum('amount'));

        $shares = $shares->map(function (array $s) use ($net, $drawn) {
            $amount = round($net * $s['percent'] / 100, 2);
            $tookOut = round((float) ($s['user_id'] ? ($drawn[$s['user_id']] ?? 0) : 0), 2);

            return $s + [
                'amount' => $amount,
                'taken' => $tookOut,
                // What is still his to take. It may go negative: a man who
                // drew more than he had earned is owed nothing and owes the
                // till the difference, and the page should say so plainly.
                'left' => round($amount - $tookOut, 2),
            ];
        })->values()->all();

        return [
            'orders' => $rows->count(),
            'boxes' => $rows->sum('boxes'),
            'revenue' => round($revenue, 2),
            'delivery' => round($rows->sum('delivery'), 2),
            'rush' => round($rows->sum('rush'), 2),
            'chocolate' => round($chocolate, 2),
            'materials' => round($materials, 2),
            'gross' => round($gross, 2),
            'expenses' => round($expenses, 2),
            'net' => round($net, 2),
            'purchases' => round($purchases, 2),
            // Money in hand: what came in, less everything paid out (chocolate
            // is bought for each order; stock in packs, ahead of time) and less
            // whatever has been taken out of the till.
            'cash' => round($revenue - $chocolate - $purchases - $expenses - $withdrawn, 2),
            'withdrawn' => round($withdrawn, 2),
            'withdrawals' => $taken->all(),
            'shares' => $shares,
            'rows' => $rows->take(100)->all(),
        ];
    }
}
