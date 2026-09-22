<?php

namespace App\Http\Controllers;

use App\Models\GiftPage;
use App\Models\Product;
use App\Models\Wrapping;
use App\Support\Letter;
use App\Support\LiveMaterials;
use App\Support\Media;
use App\Support\Seo;
use Carbon\CarbonInterface;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

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
        $newest = $this->newest($products->concat($gifts));

        $urls = collect([
            ['loc' => route('home'), 'lastmod' => $newest],
            ['loc' => route('designs.index'), 'lastmod' => $newest],
            ['loc' => route('gifts.index'), 'lastmod' => $this->newest($gifts)],
        ]);

        foreach ($gifts as $page) {
            $urls->push(['loc' => $page->url(), 'lastmod' => $this->changed($page)]);
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
                'lastmod' => $this->changed($product),
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

    /**
     * When a row was last touched, read past the model's date casting: a row
     * whose timestamp was never set (or holds MySQL's zero date) must not
     * bring the whole sitemap down.
     */
    private function changed(object $record): ?CarbonInterface
    {
        try {
            $raw = $record->getRawOriginal('updated_at') ?: $record->getRawOriginal('created_at');
            $date = filled($raw) ? Carbon::parse((string) $raw) : null;
        } catch (Throwable) {
            return null;
        }

        return $date && $date->year > 1971 ? $date : null;
    }

    /** @param Collection<int, object> $records */
    private function newest(Collection $records): ?CarbonInterface
    {
        return $records->map(fn (object $r) => $this->changed($r))->filter()->max();
    }
}
