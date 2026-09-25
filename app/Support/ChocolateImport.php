<?php

namespace App\Support;

use App\Models\Chocolate;
use App\Models\Market;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Writes the bars a shop's website lists into the catalogue — the part every
 * shop importer (Araz Market, Birmarket, …) shares.
 *
 * New bars are added and shown; known ones get fresh prices but keep the
 * owner's name, picture, markup and on/off switch. A bar the owner deleted
 * stays out. A bar the shop no longer lists is flagged, not hidden, so a
 * hiccup on their side cannot empty the choice.
 */
class ChocolateImport
{
    /** Bars that fit the boxes, unless the owner set his own range. */
    public const MIN_GRAMS = 90;

    public const MAX_GRAMS = 105;

    public static function minGrams(): float
    {
        return (float) (Setting::get(Setting::CHOCOLATE_MIN_G) ?: self::MIN_GRAMS);
    }

    public static function maxGrams(): float
    {
        return (float) (Setting::get(Setting::CHOCOLATE_MAX_G) ?: self::MAX_GRAMS);
    }

    /** A weight in grams, read from a product title ("Milka … 90 qr", "Yummy, 100 q"). */
    public static function grams(string $title): ?float
    {
        return preg_match('/(\d+(?:[.,]\d+)?)\s*(?:qr|q|gr|g|qram|gram)\b/iu', $title, $m)
            ? (float) str_replace(',', '.', $m[1])
            : null;
    }

    public static function fits(?float $grams): bool
    {
        return $grams !== null && $grams >= self::minGrams() && $grams <= self::maxGrams();
    }

    /**
     * @param  array<int, array{source_id: string, name: string, grams: ?float, base: float, sale: ?float,
     *                    sale_percent: ?int, url: ?string, barcode: ?string, seller: ?string, image: ?string}>  $rows
     * @return array{found: int, created: int, updated: int, missing: int}
     */
    public static function upsert(string $source, Market $market, array $rows): array
    {
        @set_time_limit(300);
        if (! $rows) {
            throw new RuntimeException($market->name . ' saytında ' . (int) self::minGrams() . '–' . (int) self::maxGrams()
                . ' q plitka şokolad tapılmadı, heç nə dəyişdirilmədi.');
        }

        $created = $updated = 0;
        $seen = [];
        foreach ($rows as $row) {
            $chocolate = Chocolate::withTrashed()->firstOrNew(['source' => $source, 'source_id' => (string) $row['source_id']]);
            if ($chocolate->trashed()) {
                $seen[] = $chocolate->id;   // the owner deleted it: leave it out
                continue;
            }
            $isNew = ! $chocolate->exists;
            if ($isNew) {
                $chocolate->fill(['name' => trim($row['name']), 'is_active' => true]);
            }
            $chocolate->market_id ??= $market->id;
            $chocolate->fill([
                'weight_g' => $row['grams'],
                'base_price' => $row['base'],
                'sale_price' => $row['sale'],
                'sale_percent' => $row['sale'] !== null ? $row['sale_percent'] : null,
                'source_url' => $row['url'],
                'barcode' => $row['barcode'],
                'seller' => $row['seller'],
                'in_source' => true,
                'synced_at' => now(),
            ]);
            if (! $chocolate->image && $row['image']) {
                $chocolate->image = self::storeImage($row['image'], $source . '-' . $row['source_id']);
            }
            $chocolate->save();
            $seen[] = $chocolate->id;
            $isNew ? $created++ : $updated++;
        }

        $missing = Chocolate::where('source', $source)->whereNotIn('id', $seen)->update(['in_source' => false]);

        return ['found' => count($rows), 'created' => $created, 'updated' => $updated, 'missing' => $missing];
    }

    /** The bar's picture, shrunk and kept as WebP on this site. */
    public static function storeImage(string $url, string $name): ?string
    {
        try {
            $bytes = Http::timeout(20)->get($url)->throw()->body();
            $im = @imagecreatefromstring($bytes);
            if (! $im) {
                return null;
            }
            imagepalettetotruecolor($im);
            $w = imagesx($im);
            $h = imagesy($im);
            $k = min(1, 600 / max($w, $h));
            if ($k < 1) {
                $small = imagecreatetruecolor((int) round($w * $k), (int) round($h * $k));
                imagealphablending($small, false);
                imagesavealpha($small, true);
                imagecopyresampled($small, $im, 0, 0, 0, 0, imagesx($small), imagesy($small), $w, $h);
                imagedestroy($im);
                $im = $small;
            }
            imagealphablending($im, false);
            imagesavealpha($im, true);
            ob_start();
            imagewebp($im, null, 85);
            $path = 'chocolates/' . $name . '.webp';
            Storage::disk('public')->put($path, ob_get_clean());
            imagedestroy($im);

            return $path;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
