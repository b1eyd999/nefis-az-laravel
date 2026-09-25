<?php

namespace App\Support;

use App\Models\Chocolate;
use App\Models\Market;
use Illuminate\Support\Facades\Http;
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
            throw new RuntimeException('Araz Market səhifəsində məhsul siyahısı tapılmadı, saytın quruluşu dəyişib.');
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
        return ChocolateImport::grams($title);
    }

    /** Whether a listed product is a bar the boxes take. */
    public static function isBoxBar(array $product): bool
    {
        return (int) ($product['category_id'] ?? 0) === self::CATEGORY_ID
            && ChocolateImport::fits(self::grams((string) ($product['title'] ?? '')));
    }

    /**
     * Brings Araz Market's bars and their prices up to date.
     *
     * @return array{found: int, created: int, updated: int, missing: int}
     */
    public static function sync(?array $products = null): array
    {
        @set_time_limit(300);   // ~70 catalogue pages and new pictures
        $rows = [];
        foreach (array_filter($products ?? self::products(), [self::class, 'isBoxBar']) as $p) {
            $onSale = ! empty($p['is_discount']) && (float) $p['discount_price'] < (float) $p['sales_price'];
            $rows[] = [
                'source_id' => (string) $p['id'],
                'name' => (string) $p['title'],
                'grams' => self::grams((string) $p['title']),
                'base' => (float) $p['sales_price'],
                'sale' => $onSale ? (float) $p['discount_price'] : null,
                'sale_percent' => $onSale ? ($p['discount_percent'] ?? null) : null,
                'url' => 'https://www.arazmarket.az/az/products/' . ($p['slug'] ?? ''),
                'barcode' => $p['barcode'] ?? null,
                'seller' => null,
                'image' => $p['images'][0] ?? null,
            ];
        }

        $market = Market::forImporter(Chocolate::SOURCE_ARAZ, 'Araz Market', 'https://www.arazmarket.az');

        return ChocolateImport::upsert(Chocolate::SOURCE_ARAZ, $market, $rows);
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
