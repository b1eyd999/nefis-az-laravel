<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Three English gift pages linked to /en/for-companies, which has never
 * existed — the corporate page is /en/sirketler-ucun, the same slug in all
 * three languages.
 *
 * It was the only broken link on the whole site, and it sat on three pages
 * that are themselves in the sitemap, so Google met it on every crawl. The
 * wording lives in the database because the owner edits these pages himself,
 * which is why this is a migration and not a change to the seed file.
 */
return new class extends Migration
{
    private const WRONG = '/en/for-companies';

    private const RIGHT = '/en/sirketler-ucun';

    public function up(): void
    {
        $this->swap(self::WRONG, self::RIGHT);
    }

    public function down(): void
    {
        $this->swap(self::RIGHT, self::WRONG);
    }

    private function swap(string $from, string $to): void
    {
        foreach (DB::table('gift_pages')->where('body', 'like', '%' . $from . '%')->get() as $page) {
            DB::table('gift_pages')->where('id', $page->id)->update([
                'body' => str_replace($from, $to, (string) $page->body),
            ]);
        }
    }
};
