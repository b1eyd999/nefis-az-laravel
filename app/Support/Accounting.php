<?php

namespace App\Support;

use App\Models\Expense;
use App\Models\Material;
use App\Models\Order;
use App\Models\Setting;
use App\Models\StockMovement;
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
        $orders = Order::with('items')
            ->where('status', '!=', 'cancelled')
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->latest()
            ->get();

        $rows = $orders->map(function (Order $o) {
            $goods = $o->itemsTotal();
            $delivery = (float) ($o->delivery_price ?? 0);
            $chocolate = (float) $o->items->sum(fn ($i) => (float) ($i->chocolate_cost ?? 0) * $i->quantity);
            $materials = (float) ($o->materials_cost ?? 0);
            $revenue = $goods + $delivery;

            return [
                'order' => $o, 'boxes' => (int) $o->items->whereNotNull('product_id')->sum('quantity'),
                'revenue' => $revenue, 'goods' => $goods, 'delivery' => $delivery,
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

        $revenue = $rows->sum('revenue');
        $chocolate = $rows->sum('chocolate');
        $materials = $rows->sum('materials');
        $gross = $revenue - $chocolate - $materials;
        $net = $gross - $expenses;

        // Each staff member's share, as the admin gave it with the role. Until
        // anyone has one, the older list of names from the settings stands.
        $holders = User::shareholders();
        $shares = ($holders->isNotEmpty()
            ? $holders->map(fn (User $u) => ['user_id' => $u->id, 'name' => $u->name, 'percent' => $u->profit_percent])
            : collect(Setting::profitShares())->map(fn ($s) => ['user_id' => null, 'name' => $s['name'], 'percent' => (float) $s['percent']])
        )->map(fn ($s) => $s + ['amount' => round($net * $s['percent'] / 100, 2)])->values()->all();

        return [
            'orders' => $rows->count(),
            'boxes' => $rows->sum('boxes'),
            'revenue' => round($revenue, 2),
            'delivery' => round($rows->sum('delivery'), 2),
            'chocolate' => round($chocolate, 2),
            'materials' => round($materials, 2),
            'gross' => round($gross, 2),
            'expenses' => round($expenses, 2),
            'net' => round($net, 2),
            'purchases' => round($purchases, 2),
            // Money in hand: what came in, less everything paid out (chocolate
            // is bought for each order; stock in packs, ahead of time).
            'cash' => round($revenue - $chocolate - $purchases - $expenses, 2),
            'shares' => $shares,
            'rows' => $rows->take(100)->all(),
        ];
    }
}
