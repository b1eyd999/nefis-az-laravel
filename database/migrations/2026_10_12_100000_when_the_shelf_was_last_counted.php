<?php

use App\Models\StockMovement;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When the owner last said how much of a thing is on the shelf.
 *
 * The warehouse needed to tell «we have none» from «nobody has ever said»,
 * because six rolls of ribbon appeared on the page with a price and no count,
 * and calling them «bitib» would have been six alarms he could not clear.
 *
 * The first attempt read that off `stock_movements` — no movement, never
 * counted. Two things were wrong with it. A «Sayım» that matches the books
 * writes no movement on purpose, so counting an empty shelf and typing 0 —
 * exactly the gesture that should end it — left the row «sayılmayıb» for ever.
 * And it made every screen carry a `withCount` to avoid a query per row.
 *
 * One column says it plainly instead. `purchase()` and `adjust()` both stamp
 * it, the second even when the count matched and nothing was written: he did
 * look at the shelf, and that is the whole question.
 *
 * Backfilled from what the books already know, so nothing the shop has been
 * buying and counting for weeks suddenly reads as untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('materials', 'counted_at')) {
            Schema::table('materials', function (Blueprint $table) {
                $table->timestamp('counted_at')->nullable()->after('low_stock');
            });
        }

        $last = DB::table('stock_movements')
            ->whereIn('type', [StockMovement::PURCHASE, StockMovement::ADJUST])
            ->groupBy('material_id')
            ->pluck(DB::raw('MAX(created_at)'), 'material_id');

        foreach ($last as $material => $when) {
            DB::table('materials')->where('id', $material)->whereNull('counted_at')
                ->update(['counted_at' => $when]);
        }

        /* A shelf with something on it was counted by somebody, even if the
           movement behind it is older than the rows this table keeps. */
        DB::table('materials')->whereNull('counted_at')->where('stock', '>', 0)
            ->update(['counted_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('materials', 'counted_at')) {
            Schema::table('materials', fn (Blueprint $t) => $t->dropColumn('counted_at'));
        }
    }
};
