<?php

namespace App\Support;

use App\Models\Chocolate;
use App\Models\Market;
use Illuminate\Support\Facades\Http;

/**
 * Reads chocolate bars and their prices off Birmarket (the Umico
 * marketplace).
 *
 * Birmarket's pages call Umico's catalogue API, which answers plain JSON:
 * the "plitka şokolad" tag (search_tag_id 1640229, category 2600) lists
 * ~800 sweets, 24 per page. Each has a name, a default offer with the
 * current price (retail_price), the price before a promotion (old_price,
 * 0 when there is none) and its seller.
 *
 * The tag holds candies, wafers, cookies and marmalade too, so only
 * chocolate of 90-105 g that is not one of those — nor a multipack, a
 * Toblerone prism, Kinder sticks or a figure — is taken as a bar.
 */
class Birmarket
{
    public const API = 'https://mp-catalog.umico.az/api/v1/products';

    public const TAG_ID = 1640229;

    public const CATEGORY_ID = 2600;

    public const PER_PAGE = 24;   // the API refuses more

    private const CHOCOLATE = '/şokolad|chocolate|шоколад|plitka/iu';

    private const NOT_A_BAR = '/konfet|marmelad|peçenye|biskvit|vafli|keks|trüfel|truffle|şirni|çubuq|baton|sendviç|qutuda|praline?s\b|dates|xurma|'
        . 'şaxta baba|toblerone|kinder|kit-?kat|\d+\s*əd\b|\d+\s*x\s*\d+|\bx\s*\d+/iu';

    /**
     * The API's pages overlap under any one sort, so one pass misses some of
     * the tag's products (the default order reached 644 of 823). Newest-first
     * reaches all but one; together with the default order, everything.
     */
    public const SORTS = ['activated_at_desc', 'default_reranker'];

    /** Every product the tag lists, keyed by Birmarket's id. */
    public static function products(): array
    {
        $all = [];
        foreach (self::SORTS as $sort) {
            $page = 1;
            do {
                $data = Http::timeout(30)->retry(2, 500)->acceptJson()
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; NefisShokoladEvi/1.0)', 'Accept-Language' => 'az'])
                    ->get(self::API, [
                        'page' => $page, 'per_page' => self::PER_PAGE, 'sort' => $sort,
                        'search_tag_id' => self::TAG_ID, 'category_id' => self::CATEGORY_ID,
                    ])
                    ->throw()
                    ->json();
                foreach ($data['products'] ?? [] as $item) {
                    $all[$item['id']] = $item;
                }
                $pages = (int) ceil(((int) ($data['meta']['total'] ?? 0)) / self::PER_PAGE);
                $page++;
            } while ($page <= min($pages, 60));
        }

        return $all;
    }

    /** Whether a listed product is a bar the boxes take. */
    public static function isBoxBar(array $product): bool
    {
        $name = (string) ($product['name'] ?? '');

        return ChocolateImport::fits(ChocolateImport::grams($name))
            && preg_match(self::CHOCOLATE, $name) === 1
            && preg_match(self::NOT_A_BAR, $name) === 0
            && ($product['status'] ?? 'active') === 'active';
    }

    /**
     * Brings Birmarket's bars and their prices up to date.
     *
     * @return array{found: int, created: int, updated: int, missing: int}
     */
    public static function sync(?array $products = null): array
    {
        @set_time_limit(300);   // ~70 catalogue pages and new pictures
        $rows = [];
        foreach (array_filter($products ?? self::products(), [self::class, 'isBoxBar']) as $p) {
            $offer = $p['default_offer'] ?? [];
            $now = (float) ($offer['retail_price'] ?? 0);
            $before = (float) ($offer['old_price'] ?? 0);
            if ($now <= 0) {
                continue;   // not on sale anywhere right now
            }
            $onSale = $before > $now;
            $rows[] = [
                'source_id' => (string) $p['id'],
                'name' => (string) $p['name'],
                'grams' => ChocolateImport::grams((string) $p['name']),
                'base' => $onSale ? $before : $now,
                'sale' => $onSale ? $now : null,
                'sale_percent' => $onSale ? (int) round(($before - $now) / $before * 100) : null,
                'url' => 'https://birmarket.az/product/' . $p['id'] . '-' . ($p['slugged_name'] ?? ''),
                'barcode' => null,
                'seller' => $offer['seller']['marketing_name']['name'] ?? null,
                'image' => $p['main_img']['medium'] ?? $p['main_img']['big'] ?? $p['main_img']['small'] ?? null,
            ];
        }

        $market = Market::forImporter(Chocolate::SOURCE_BIRMARKET, 'Birmarket', 'https://birmarket.az');

        return ChocolateImport::upsert(Chocolate::SOURCE_BIRMARKET, $market, $rows);
    }
}
