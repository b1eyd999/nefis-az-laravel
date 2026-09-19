<?php

namespace App\Support;

use App\Models\Chocolate;
use App\Models\Market;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Reads chocolate bars and their prices off Araz Market's website.
 *
 * The shop has no public API; its category pages are rendered by Next.js,
 * which ships the product list inside the page as a series of
 * `self.__next_f.push([1,"…"])` string chunks. Joined and unescaped, they
 * hold `"products":[{…}]` with each product's regular price (sales_price),
 * current price (discount_price) and promotion flag.
 *
 * Only bars of 90-105 g from the "Plitka şokolad" category are taken: the
 * size that fits the boxes. The weight is read from the product's title.
 */
class ArazMarket
{
    public const CATEGORY_URL = 'https://www.arazmarket.az/az/categories/plitka-sokolad-635';

    public const CATEGORY_ID = 635;

    public const MIN_GRAMS = 90;

    public const MAX_GRAMS = 105;

    /** Every product the category's pages list, keyed by the shop's id. */
    public static function products(string $url = self::CATEGORY_URL): array
    {
        $all = [];
        $page = 1;
        do {
            $html = Http::timeout(30)->retry(2, 500)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; NefisShokoladEvi/1.0)', 'Accept-Language' => 'az'])
                ->get($url, $page > 1 ? ['page' => $page] : [])
                ->throw()
                ->body();
            [$items, $lastPage] = self::parse($html);
            foreach ($items as $item) {
                $all[$item['id']] = $item;
            }
            $page++;
        } while ($page <= min($lastPage, 10));

        return $all;
    }

    /**
     * The products on one category page, and how many pages there are.
     *
     * @return array{0: array, 1: int}
     */
    public static function parse(string $html): array
    {
        $payload = '';
        $marker = 'self.__next_f.push([1,"';
        $offset = 0;
        while (($start = strpos($html, $marker, $offset)) !== false) {
            $start += strlen($marker);
            $end = self::closingQuote($html, $start);
            $payload .= json_decode('"' . substr($html, $start, $end - $start) . '"') ?? '';
            $offset = $end;
        }

        $page = strpos($payload, '"page_type":"categories"');
        $list = strpos($payload, '"products":[', $page === false ? 0 : $page);
        if ($list === false) {
            throw new RuntimeException('Araz Market səhifəsində məhsul siyahısı tapılmadı — saytın quruluşu dəyişib.');
        }
        $json = self::balanced($payload, $list + strlen('"products":'));
        $items = json_decode($json, true);
        if (! is_array($items)) {
            throw new RuntimeException('Araz Market məhsulları oxunmadı.');
        }

        preg_match('/"last_page":(\d+)/', substr($payload, $list + strlen($json), 200000), $m);

        return [$items, max(1, (int) ($m[1] ?? 1))];
    }

    /** A bar's weight in grams, read from its title ("Milka … 90 qr"). */
    public static function grams(string $title): ?float
    {
        return preg_match('/(\d+(?:[.,]\d+)?)\s*(?:qr|q|gr|g|qram|gram)\b/iu', $title, $m)
            ? (float) str_replace(',', '.', $m[1])
            : null;
    }

    /** Whether a listed product is a bar the boxes take. */
    public static function isBoxBar(array $product): bool
    {
        $grams = self::grams((string) ($product['title'] ?? ''));

        return (int) ($product['category_id'] ?? 0) === self::CATEGORY_ID
            && $grams !== null && $grams >= self::MIN_GRAMS && $grams <= self::MAX_GRAMS;
    }

    /**
     * Brings the bars and their prices up to date. New bars are added (and
     * shown); known ones get fresh prices but keep the owner's name, picture,
     * markup and on/off switch. A bar the shop no longer lists is flagged,
     * not hidden, so a hiccup on their side cannot empty the choice.
     *
     * @return array{found: int, created: int, updated: int, missing: int}
     */
    public static function sync(?array $products = null): array
    {
        @set_time_limit(180);
        $bars = array_filter($products ?? self::products(), [self::class, 'isBoxBar']);
        if (! $bars) {
            throw new RuntimeException('Araz Market-də 90–105 q plitka şokolad tapılmadı — heç nə dəyişdirilmədi.');
        }

        $market = Market::forImporter(Chocolate::SOURCE_ARAZ, 'Araz Market', 'https://www.arazmarket.az');
        $created = $updated = 0;
        $seen = [];
        foreach ($bars as $p) {
            $chocolate = Chocolate::firstOrNew(['source' => Chocolate::SOURCE_ARAZ, 'source_id' => (string) $p['id']]);
            $isNew = ! $chocolate->exists;
            $onSale = ! empty($p['is_discount']) && (float) $p['discount_price'] < (float) $p['sales_price'];

            if ($isNew) {
                $chocolate->fill(['name' => trim($p['title']), 'is_active' => true]);
            }
            $chocolate->market_id ??= $market->id;
            $chocolate->fill([
                'weight_g' => self::grams($p['title']),
                'base_price' => (float) $p['sales_price'],
                'sale_price' => $onSale ? (float) $p['discount_price'] : null,
                'sale_percent' => $onSale ? ($p['discount_percent'] ?? null) : null,
                'source_url' => 'https://www.arazmarket.az/az/products/' . ($p['slug'] ?? ''),
                'barcode' => $p['barcode'] ?? null,
                'in_source' => true,
                'synced_at' => now(),
            ]);
            if (! $chocolate->image && ($image = $p['images'][0] ?? null)) {
                $chocolate->image = self::storeImage($image, 'araz-' . $p['id']);
            }
            $chocolate->save();
            $seen[] = $chocolate->id;
            $isNew ? $created++ : $updated++;
        }

        $missing = Chocolate::where('source', Chocolate::SOURCE_ARAZ)->whereNotIn('id', $seen)->update(['in_source' => false]);

        return ['found' => count($bars), 'created' => $created, 'updated' => $updated, 'missing' => $missing];
    }

    /** The bar's picture, shrunk and kept as WebP on this site. */
    private static function storeImage(string $url, string $name): ?string
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

    /** Index of the quote that ends a JS string starting at $start. */
    private static function closingQuote(string $s, int $start): int
    {
        $len = strlen($s);
        for ($i = $start; $i < $len; $i++) {
            if ($s[$i] === '\\') {
                $i++;
            } elseif ($s[$i] === '"') {
                return $i;
            }
        }

        return $len;
    }

    /** The JSON array or object that opens at $start, brackets balanced. */
    private static function balanced(string $s, int $start): string
    {
        $depth = 0;
        $inString = false;
        $len = strlen($s);
        for ($i = $start; $i < $len; $i++) {
            $c = $s[$i];
            if ($inString) {
                if ($c === '\\') {
                    $i++;
                } elseif ($c === '"') {
                    $inString = false;
                }
            } elseif ($c === '"') {
                $inString = true;
            } elseif ($c === '[' || $c === '{') {
                $depth++;
            } elseif ($c === ']' || $c === '}') {
                if (--$depth === 0) {
                    return substr($s, $start, $i - $start + 1);
                }
            }
        }
        throw new RuntimeException('Araz Market məhsul siyahısı natamamdır.');
    }
}
