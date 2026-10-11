<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * What a box really costs in materials, as the owner buys them (2026-10-11).
 *
 * The accounting started with two guessed rows — paper at 9.80 ₼ for fifty
 * sheets and glue — and nothing else, so «Balans» has been reporting 0.212 ₼
 * a box while the real figure is twice that. Every profit figure on that page
 * was flattering by the difference.
 *
 * What he actually pays:
 *   box paper   50 sheets for 14 ₼, one sheet to a box   → 0.280 ₼
 *   print ink   six cartridges at 35 ₼ = 210 ₼ a set,
 *               and a set prints about 2 000 boxes       → 0.105 ₼
 *   wrap paper  500 sheets for 8 ₼                       → 0.016 ₼
 *   glue        unchanged, 0.80 ₼ to fifty boxes         → 0.016 ₼
 *                                                   total 0.417 ₼
 *
 * Ink is counted in boxes' worth rather than in cartridges, the way the glue
 * already is: `per_box` holds three decimals, so a set written as one unit
 * would need per_box = 0.0005 and round itself to nothing.
 *
 * Only the price is corrected on the paper — the owner's own stock figure and
 * his low-stock line are his and are left alone. Past orders keep the cost
 * they were made at: `materials_cost` is written once per order and every
 * stock movement froze the price of the day, which is what makes last month's
 * profit last month's profit.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // The sheet got dearer: 9.80 → 14.00 for fifty.
        DB::table('materials')->where('name', 'Qutu kağızı')
            ->update(['pack_price' => 14.00, 'updated_at' => $now]);

        $rows = [
            [
                'name' => 'Çap boyası',
                'unit' => 'qutuluq',
                'pack_price' => 210.00,   // six cartridges at 35 ₼
                'pack_size' => 2000,      // and a set prints about this many
                'per_box' => 1,
                'low_stock' => 200,
                'sort_order' => 3,
            ],
            [
                'name' => 'Bükmə kağızı',
                'unit' => 'ədəd',
                'pack_price' => 8.00,
                'pack_size' => 500,
                'per_box' => 1,
                'low_stock' => 50,
                'sort_order' => 4,
            ],
        ];

        foreach ($rows as $row) {
            // Named rather than numbered, and only added when it is missing:
            // the owner may have put one in himself by the time this runs.
            if (DB::table('materials')->where('name', $row['name'])->exists()) {
                continue;
            }

            DB::table('materials')->insert($row + [
                // Nothing is claimed to be on the shelf: he counts it himself
                // with «Alış» in Anbar, and a wrong number there would be a
                // wrong number in the books.
                'stock' => 0,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('materials')->whereIn('name', ['Çap boyası', 'Bükmə kağızı'])->delete();
        DB::table('materials')->where('name', 'Qutu kağızı')->update(['pack_price' => 9.80]);
    }
};
