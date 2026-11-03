<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * «Zəncirli Box» was still «Звездная коробка» and «Starry Box».
 *
 * The design at `zencirli-box` is a hand-drawn barbed-wire frame — its own
 * Russian and English descriptions say exactly that — but its translated
 * *name* was never changed when the design was: both languages still carried
 * the name of the starry box, a different design that has since been
 * withdrawn (see `2026_10_24_090000_…`, which points its address at
 * `burc-astronimiya-stars`).
 *
 * Two things came of it. A Russian or English customer was shown a name that
 * contradicted the picture and the text beside it. And Search Console's
 * «Страница является копией. Канонические версии… не совпадают» was this one
 * page: Google still remembers a «Звездная коробка» at another address and
 * reads the two as the same page, so it keeps the old one as canonical and
 * drops this one from the index.
 *
 * The stale names are removed rather than replaced. `tr()` then falls back to
 * the owner's own «Zəncirli Box» in all three languages, which is what most
 * of the catalogue already does — and inventing a Russian name here would
 * only put a third word in play next to «zəncir» and «tikanlı məftil». The
 * owner writes the real one in the admin whenever he likes.
 *
 * Guarded: a name he has written himself in the meantime is left alone.
 */
return new class extends Migration
{
    /** What was there, and must go. */
    private const STALE = [
        'ru' => 'Звездная коробка',
        'en' => 'Starry Box',
    ];

    public function up(): void
    {
        $row = DB::table('products')->where('slug', 'zencirli-box')->first(['id', 'i18n']);

        if (! $row) {
            return;
        }

        $i18n = json_decode((string) $row->i18n, true);

        if (! is_array($i18n)) {
            return;
        }

        $changed = false;

        foreach (self::STALE as $locale => $stale) {
            if (($i18n[$locale]['name'] ?? null) !== $stale) {
                continue;
            }

            unset($i18n[$locale]['name']);
            $changed = true;

            // A language left with nothing in it is no language at all.
            if ($i18n[$locale] === []) {
                unset($i18n[$locale]);
            }
        }

        if (! $changed) {
            return;
        }

        DB::table('products')->where('id', $row->id)->update([
            'i18n' => $i18n === [] ? null : json_encode($i18n, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Putting a wrong name back is not an undo worth having.
    }
};
