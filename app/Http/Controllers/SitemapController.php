<?php

namespace App\Http\Controllers;

use App\Models\GiftPage;
use App\Models\Product;
use App\Models\Wrapping;
use App\Support\Letter;
use App\Support\LiveMaterials;
use App\Support\Media;
use App\Support\Seo;
use Illuminate\Http\Response;

/**
 * /sitemap.xml: every page a search engine should know about, with the
 * designs' pictures so they can turn up in image search too.
 */
class SitemapController extends Controller
{
    public function index(): Response
    {
        $products = Product::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get()
            ->filter->isCustomizable();
        $gifts = GiftPage::shown()->get();
        $newest = $products->max('updated_at');

        $urls = collect([
            ['loc' => route('home'), 'lastmod' => $newest],
            ['loc' => route('designs.index'), 'lastmod' => $newest],
            ['loc' => route('gifts.index'), 'lastmod' => $gifts->max('updated_at')],
        ]);

        foreach ($gifts as $page) {
            $urls->push(['loc' => $page->url(), 'lastmod' => $page->updated_at]);
        }
        if (Wrapping::where('is_active', true)->exists()) {
            $urls->push(['loc' => route('wrappings.index')]);
        }
        if (Letter::enabled()) {
            $urls->push(['loc' => route('letters.create')]);
        }
        if (LiveMaterials::enabled()) {
            $urls->push(['loc' => route('live.create')]);
        }
        foreach ($products as $product) {
            $urls->push([
                'loc' => route('products.customize', $product->slug),
                'lastmod' => $product->updated_at,
                'image' => Media::url($product->catalogImage()),
            ]);
        }

        return response()
            ->view('sitemap', ['urls' => $urls->map(fn (array $u) => array_map(
                fn ($v) => is_string($v) ? Seo::canonical($v) : $v,
                $u,
            ))])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
