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
 *
 * The file is built entry by entry, each one guarded: one product with
 * something odd in the database is reported and left out instead of taking
 * the whole sitemap down.
 */
class SitemapController extends Controller
{
    /** @var array<int, string> */
    private array $xml = [];

    public function index(): Response
    {
        $products = $this->guard(fn () => Product::where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->get()->filter->isCustomizable()) ?? collect();
        $gifts = $this->guard(fn () => GiftPage::shown()->get()) ?? collect();
        $ruGifts = $gifts->where('locale', 'ru');
        $gifts = $gifts->where('locale', 'az');
        $newest = $this->newest($products->concat($gifts));

        $this->add(fn () => [route('home'), $newest]);
        $this->add(fn () => [route('designs.index'), $newest]);
        $this->add(fn () => [route('gifts.index'), $this->newest($gifts)]);

        foreach ($gifts as $page) {
            $this->add(fn () => [$page->url(), $this->changed($page)]);
        }
        if ($ruGifts->isNotEmpty()) {
            $this->add(fn () => [GiftPage::hubUrl('ru'), $this->newest($ruGifts)]);
            foreach ($ruGifts as $page) {
                $this->add(fn () => [$page->url(), $this->changed($page)]);
            }
        }
        if ($this->guard(fn () => Wrapping::where('is_active', true)->exists())) {
            $this->add(fn () => [route('wrappings.index')]);
        }
        if ($this->guard(fn () => Letter::enabled())) {
            $this->add(fn () => [route('letters.create')]);
        }
        if ($this->guard(fn () => LiveMaterials::enabled())) {
            $this->add(fn () => [route('live.create')]);
        }
        foreach ($products as $product) {
            $this->add(fn () => [
                route('products.customize', $product->slug),
                $this->changed($product),
                Media::url($product->catalogImage()),
            ]);
        }

        $body = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n"
            . implode('', $this->xml)
            . '</urlset>' . "\n";

        return response($body)->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /**
     * One <url>, from a closure giving [address, when it changed, picture].
     * Anything that throws is reported and skipped.
     */
    private function add(callable $entry): void
    {
        $this->guard(function () use ($entry) {
            [$loc, $lastmod, $image] = array_pad((array) $entry(), 3, null);

            $url = '  <url>' . "\n" . '    <loc>' . $this->text(Seo::canonical($loc)) . '</loc>' . "\n";
            if ($lastmod instanceof CarbonInterface) {
                $url .= '    <lastmod>' . $lastmod->toAtomString() . '</lastmod>' . "\n";
            }
            if (filled($image)) {
                $url .= '    <image:image>' . "\n"
                    . '      <image:loc>' . $this->text(Seo::canonical($image)) . '</image:loc>' . "\n"
                    . '    </image:image>' . "\n";
            }
            $this->xml[] = $url . '  </url>' . "\n";
        });
    }

    private function text(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /** Runs $fn, and on failure reports it and answers null rather than breaking the file. */
    private function guard(callable $fn): mixed
    {
        try {
            return $fn();
        } catch (Throwable $e) {
            report($e);

            return null;
        }
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
