<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\Media;
use App\Support\Seo;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * /feed.xml — the designs as a product feed. Google Merchant Center reads it
 * and can show them in Shopping's free listings; the owner only has to point
 * his Merchant account at this address.
 */
class FeedController extends Controller
{
    public const CATEGORY = 'Food, Beverages & Tobacco > Food Items > Candy & Chocolate';

    public function index(): Response
    {
        $items = '';

        foreach (Product::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get() as $product) {
            if (! $product->isCustomizable() || ! $product->price) {
                continue;                                   // nothing to sell, or no price to show
            }

            $image = Media::url($product->catalogImage());
            $description = $product->description
                ?: $product->name . ' dizaynında fərdi şokolad qutusu: öz şəklinizi və sözlərinizi əlavə edin.';

            $items .= '  <item>' . "\n"
                . $this->tag('g:id', 'design-' . $product->id)
                . $this->tag('title', Str::limit($product->name . ', şəkilli şokolad qutusu', 145))
                . $this->tag('description', Str::limit(strip_tags($description), 4900))
                . $this->tag('link', Seo::canonical(route('products.customize', $product->slug)))
                . ($image ? $this->tag('g:image_link', Seo::canonical($image)) : '')
                . $this->tag('g:condition', 'new')
                . $this->tag('g:availability', 'in_stock')
                . $this->tag('g:price', number_format((float) $product->price, 2, '.', '') . ' AZN')
                . $this->tag('g:brand', 'Nefis')
                . $this->tag('g:identifier_exists', 'no')
                . $this->tag('g:google_product_category', self::CATEGORY)
                . '  </item>' . "\n";
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . "\n"
            . '<channel>' . "\n"
            . $this->tag('title', 'Nefis Şokolad Evi, fərdi şokolad qutuları')
            . $this->tag('link', Seo::canonical(route('home')))
            . $this->tag('description', 'Öz şəkliniz və sözlərinizlə fərdi şokolad qutuları.')
            . $items
            . '</channel>' . "\n</rss>\n";

        return response($xml)->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    private function tag(string $name, string $value): string
    {
        return '    <' . $name . '>' . htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</' . $name . '>' . "\n";
    }
}
