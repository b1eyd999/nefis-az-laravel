<?php

use App\Models\Material;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * What a roll of ribbon costs, in the owner's own words (2026-10-12).
 *
 * «цена ленты 1,20 манат рулон, хватает на 30 коробок» — so a roll is 1.20 ₼
 * and does thirty wrapped boxes, which is 0.04 ₼ a box: two and a half times
 * what the wrapping sheet costs, and worth seeing in the figures.
 *
 * The ribbons went onto the page yesterday with no price at all, because there
 * was none to write. Only those are filled in: a roll he has already priced
 * himself is his own figure and is left alone. The unit changes with the price
 * — a roll is counted in wraps, not in boxes, and «30 bükməlik» is what the
 * «Alış» form should say when he buys the next one.
 *
 * Nothing is bought here and no money moves: this is the recipe, not a
 * purchase. What is actually on his shelf he writes himself with «Sayım».
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('materials')
            ->where('spent_on', Material::RIBBON)
            ->where('pack_price', '<=', 0)
            ->update([
                'pack_price' => 1.20,
                'pack_size' => 30,
                'per_box' => 1,
                'unit' => 'bükməlik',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Back to a roll nobody has priced. Only the ones this migration could
        // have touched: a ribbon he has bought since has a price of his own.
        DB::table('materials')
            ->where('spent_on', Material::RIBBON)
            ->where('pack_price', 1.20)
            ->where('pack_size', 30)
            ->update(['pack_price' => 0, 'pack_size' => 100, 'unit' => 'qutuluq']);
    }
};
