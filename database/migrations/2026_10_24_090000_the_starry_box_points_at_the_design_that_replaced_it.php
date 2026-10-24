<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;

/**
 * «Ulduzlu box» is «Burc astronimiya stars» now.
 *
 * Search Console still lists /products/ulduzlu-box/customize and its English
 * twin under «Not found (404)». The design was renamed before the shop began
 * writing old addresses down, so nothing recorded where it went, and the
 * only person who knows is the owner. He said so in as many words —
 * «ulduzlu-box вместо него burc-astronimiya-stars» — which is why this is
 * written in by hand rather than guessed from a name that looks similar.
 *
 * The other three dead addresses in that report stay 404 on the same
 * instruction: lokasiya-home (the house-shaped map), alyonka-azerbaijan-style
 * and klassik-qutu were withdrawn rather than renamed. 404 is the truthful
 * answer for a design that is simply gone — and a redirect to something
 * vaguely similar is both the soft-404 Google penalises and a way to sell
 * somebody the wrong box.
 *
 * A migration and not the admin field that does the same job, because the
 * hosting has no shell: the deploy is the only thing that runs there.
 */
return new class extends Migration
{
    private const WAS = 'ulduzlu-box';

    private const NOW = 'burc-astronimiya-stars';

    public function up(): void
    {
        /* Nothing to forward to on a database that has no catalogue yet — a
           test run, or the shop before its designs are loaded. Running it
           twice is harmless as well: rememberOldSlugs writes the one row
           either way, and refuses outright any address a live design still
           answers to. */
        Product::where('slug', self::NOW)->first()?->rememberOldSlugs([self::WAS]);
    }

    public function down(): void
    {
        Product::where('slug', self::NOW)->first()?->forgetOldSlugs([self::WAS]);
    }
};
