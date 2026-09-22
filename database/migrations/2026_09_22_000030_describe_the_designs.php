<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Every design had the same two sentences under it, because none of them
 * carried a description of its own: the catalogue cards repeated themselves
 * and search engines saw 26 nearly identical pages. Each design now says who
 * it suits and what photo works on it.
 *
 * Only empty descriptions are filled — whatever the owner wrote stays. Names
 * that were typed in capitals or with stray spaces are tidied the same way,
 * because they are what a search result shows.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (require database_path('data/design-descriptions.php') as $slug => $text) {
            DB::table('products')->where('slug', $slug)
                ->where(fn ($q) => $q->whereNull('description')->orWhere('description', ''))
                ->update(['description' => $text]);
        }

        foreach (DB::table('products')->get(['id', 'name']) as $product) {
            $name = $this->tidy((string) $product->name);
            if ($name !== $product->name && $name !== '') {
                DB::table('products')->where('id', $product->id)->update(['name' => $name]);
            }
        }
    }

    /** "LOVE STORY VOL 1" → "Love Story Vol 1", "Milka " → "Milka". */
    private function tidy(string $name): string
    {
        $name = preg_replace('/\s+/u', ' ', trim($name));

        return implode(' ', array_map(function (string $word) {
            if (mb_strtoupper($word, 'UTF-8') !== $word || mb_strlen($word) < 2) {
                return $word;                                  // already mixed case, or a number
            }
            if (preg_match('/^\W+$/u', $word)) {
                return $word;
            }

            // Azerbaijani's dotted İ lowercases to "i̇" (i plus a dot) unless it is mapped first.
            $rest = str_replace(['İ', 'I'], ['i', 'i'], mb_substr($word, 1, null, 'UTF-8'));

            return mb_strtoupper(mb_substr($word, 0, 1, 'UTF-8'), 'UTF-8') . mb_strtolower($rest, 'UTF-8');
        }, explode(' ', $name)));
    }

    public function down(): void
    {
        // The descriptions are the owner's to keep or rewrite.
    }
};
