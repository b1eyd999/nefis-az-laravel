<?php

use App\Models\GiftPage;
use Illuminate\Database\Migrations\Migration;

/**
 * The eight commercial gift pages, written out to full length.
 *
 * They stood at 54–154 words. For "подарок девушке" or "ad günü hədiyyəsi"
 * that is not an answer, it is a label, and pages that say more were winning
 * the searches these exist for. The new bodies are in
 * database/data/gift-bodies-2026-10.php.
 *
 * The owner edits these pages in the admin, so this must never flatten his
 * work: a page is only written if its body is still the short original. The
 * measure is the body's own length — anything past 1,500 characters has been
 * worked on since and is left exactly as it is.
 *
 * Rerun-safe by the same test: once a page carries the long text it is far
 * over the line, so a second run passes over it.
 */
return new class extends Migration
{
    /** Below this many characters a body is still the one that shipped. */
    private const STILL_THE_STUB = 1500;

    public function up(): void
    {
        $bodies = require database_path('data/gift-bodies-2026-10.php');

        foreach ($bodies as $key => $body) {
            [$locale, $slug] = explode(':', $key, 2);

            $page = GiftPage::where('locale', $locale)->where('slug', $slug)->first();
            if (! $page) {
                continue;               // renamed or removed since this was written
            }

            if (mb_strlen((string) $page->body) >= self::STILL_THE_STUB) {
                continue;               // somebody has written here; leave it alone
            }

            $page->forceFill(['body' => $body])->save();
        }
    }

    public function down(): void
    {
        // The old bodies were a sentence or two and are not worth keeping;
        // the owner's own edits are what this migration protects, and it
        // never touched them.
    }
};
