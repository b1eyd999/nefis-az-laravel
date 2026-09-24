<?php

namespace App\Support;

use App\Models\Chocolate;
use App\Models\Market;
use Illuminate\Support\Facades\Http;

/**
 * Reads chocolate bars and their prices off Bravo Hypermarket, as Wolt
 * lists them.
 *
 * Bravo has no site of its own to read, but it sells through Wolt, whose
 * assortment API answers plain JSON: one call per category page, 50 items at
 * a time, carrying the name, the picture, the price in qəpiks and — while a
 * promotion runs — the price before it. Bravo spreads its chocolate over
 * several shelves (Kinder sits with the children's sweets), so all of them
 * are read and the weight decides what goes in a box.
 */
class Bravo
{
    public const VENUE = 'bravo-hypermarket-koroglu';

    /**
     * Wolt's slugs for Bravo's chocolate shelves. The bars are spread over
     * several: Kinder sits on the children's shelf, Ritter Sport on the
     * plain one, so all of them are read and the weight decides what fits.
     */
    public const CATEGORIES = [
        'plitka-sokoladlar-80',
        'sokoladlar-78',
        'usaqlar-ucun-sokoladlar-79',
        'dubai-sokolad-85',
    ];

    public const API = 'https://consumer-api.wolt.com/consumer-api/consumer-assortment/v1/venues/slug/';

    public const PAGE_URL = 'https://wolt.com/en/aze/baku/venue/';

    /** Everything the chocolate shelves list, keyed by Wolt's item id. */
    public static function products(): array
    {
        $all = [];

        foreach (self::CATEGORIES as $category) {
            $token = null;
            $page = 0;

            do {
                $data = Http::timeout(30)->retry(2, 500)->acceptJson()
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; NefisShokoladEvi/1.0)'])
                    ->get(self::API . self::VENUE . '/assortment/categories/slug/' . $category,
                        array_filter(['language' => 'az', 'page_token' => $token]))
                    ->throw()
                    ->json();

                foreach ($data['items'] ?? [] as $item) {
                    $all[$item['id']] = $item + ['_shelf' => $category];
                }

                $token = $data['metadata']['next_page_token'] ?? null;
            } while ($token && ++$page < 20);
        }

        return $all;
    }

    /** Whether a listed product is a bar the boxes take. */
    public static function isBoxBar(array $item): bool
    {
        if (($item['disabled_info'] ?? null) !== null) {
            return false;                                   // out of stock at Bravo
        }

        return ChocolateImport::fits(self::grams($item));
    }

    /**
     * The weight: from the title when it says one ("Milka … 90qr"), else from
     * Wolt's own unit ("85 g"), which it fills in for most of the shelf.
     */
    public static function grams(array $item): ?float
    {
        return ChocolateImport::grams((string) ($item['name'] ?? ''))
            ?? ChocolateImport::grams((string) ($item['unit_info'] ?? ''));
    }

    /**
     * Brings Bravo's bars and their prices up to date.
     *
     * @return array{found: int, created: int, updated: int, missing: int}
     */
    public static function sync(?array $products = null): array
    {
        @set_time_limit(300);
        $rows = [];

        foreach (array_filter($products ?? self::products(), [self::class, 'isBoxBar']) as $item) {
            $now = (float) ($item['price'] ?? 0) / 100;          // Wolt counts in qəpiks
            $before = (float) ($item['original_price'] ?? 0) / 100;
            if ($now <= 0) {
                continue;
            }

            $onSale = $before > $now;
            $rows[] = [
                'source_id' => (string) $item['id'],
                'name' => trim((string) $item['name']),
                'grams' => self::grams($item),
                'base' => $onSale ? $before : $now,
                'sale' => $onSale ? $now : null,
                'sale_percent' => $onSale ? (int) round(($before - $now) / $before * 100) : null,
                'url' => self::PAGE_URL . self::VENUE . '/items/' . ($item['_shelf'] ?? self::CATEGORIES[0]),
                'barcode' => $item['barcode_gtin'] ?? null,
                'seller' => 'Bravo',
                'image' => $item['images'][0]['url'] ?? null,
            ];
        }

        $market = Market::forImporter(Chocolate::SOURCE_BRAVO, 'Bravo', 'https://wolt.com/en/aze/baku/venue/' . self::VENUE);

        return ChocolateImport::upsert(Chocolate::SOURCE_BRAVO, $market, $rows);
    }
}
