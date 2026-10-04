<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Mətn 1" is what the editor calls a caption nobody has named yet.
 *
 * It was left standing on love-is-blue2, so that design asked the customer to
 * fill in "Text 1" while the three boxes added beside it the same day —
 * love-is-mix, love-is-orange2, love-is-red3 — asked for the name of the
 * person the gift is for. Seven designs in the shop use that same wording;
 * this is the eighth.
 *
 * The slot is renamed only while it still carries the editor's default, so
 * that a word the owner types himself later is never overwritten.
 */
return new class extends Migration
{
    private const SLUG = 'love-is-blue2';

    private const DEFAULT_LABEL = 'Mətn 1';

    /** What its own family calls the field (cici-bebe-boz, love-is-mix, love-is-orange2, love-is-red3, …). */
    private const FAMILY_LABEL = 'Hədiyyə verəcəyiniz şəxsin adı';

    public function up(): void
    {
        if (DB::table('products')->count() === 0) {
            return;                         // a fresh install, and every test database
        }

        $box = Product::where('slug', self::SLUG)->first();
        if (! $box) {
            echo '  label: ' . self::SLUG . " is not here\n";

            return;
        }

        $slots = $box->textSlots()->where('label', self::DEFAULT_LABEL)->get();

        if ($slots->isEmpty()) {
            echo '  label: ' . self::SLUG . " no longer says \"" . self::DEFAULT_LABEL . "\", left alone\n";

            return;
        }

        // The query above already keeps this to the first caption: a second
        // one would be "Mətn 2" and is not this migration's business.
        foreach ($slots as $slot) {
            $slot->update(['label' => self::FAMILY_LABEL]);
            echo '  label: ' . self::SLUG . ' #' . $slot->id . ' → "' . self::FAMILY_LABEL . "\"\n";
        }
    }

    public function down(): void
    {
        $box = Product::where('slug', self::SLUG)->first();
        if (! $box) {
            return;
        }

        $box->textSlots()->where('label', self::FAMILY_LABEL)->update(['label' => self::DEFAULT_LABEL]);
    }
};
