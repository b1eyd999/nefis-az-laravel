<?php

namespace App\Http\Controllers;

use App\Models\GiftPage;
use App\Models\Wrapping;
use App\Support\Letter;
use App\Support\LiveMaterials;
use App\Support\Locale;
use Illuminate\View\View;

/**
 * Gift ideas: the list of occasions, and one page per occasion with the
 * designs that suit it. Written for the searches people make — /hediyye in
 * Azerbaijani, /ru/podarki in Russian, /en/gifts in English — and each page
 * belongs to one language only.
 */
class GiftPageController extends Controller
{
    public function index(): View
    {
        $locale = Locale::current();
        $pages = GiftPage::shown()->inLocale($locale)->get();

        // The same ideas in whichever other language the owner has written them.
        $otherHub = null;
        foreach (array_diff(Locale::all(), [$locale]) as $other) {
            if (GiftPage::shown()->inLocale($other)->exists()) {
                $otherHub = GiftPage::hubUrl($other);
                break;
            }
        }

        return view('gifts.index', compact('pages', 'locale', 'otherHub'));
    }

    public function show(GiftPage $giftPage): View
    {
        $locale = Locale::current();
        abort_unless($giftPage->is_active && $giftPage->locale === $locale, 404);

        $products = $giftPage->shownProducts();
        $from = $products->pluck('price')->filter(fn ($p) => $p > 0)->min();
        $others = GiftPage::shown()->inLocale($locale)->whereKeyNot($giftPage->id)->get();

        // The same page in the other language, for readers and for search engines.
        $alternate = $giftPage->alt ?? $giftPage->alternates()->where('is_active', true)->first();
        if ($alternate && ! $alternate->is_active) {
            $alternate = null;
        }

        // What else can go with the box, only what is on sale now.
        $extras = array_values(array_filter([
            Wrapping::where('is_active', true)->exists()
                ? ['🎁', ...$this->extra($locale, 'wrap'), lroute('wrappings.index')] : null,
            Letter::enabled()
                ? ['💌', ...$this->extra($locale, 'letter'), lroute('letters.create')] : null,
            LiveMaterials::enabled()
                ? ['🎬', ...$this->extra($locale, 'live'), lroute('live.create')] : null,
        ]));

        return view('gifts.show', ['page' => $giftPage] + compact('products', 'from', 'others', 'extras', 'alternate'));
    }

    /** @return array{0: string, 1: string} the name and the line under it */
    private function extra(string $locale, string $key): array
    {
        $words = [
            'az' => [
                'wrap' => ['Hədiyyə qablaşdırması', 'Qutunu naxışlı kağıza büküb lentlə bağlayırıq.'],
                'letter' => [Letter::text('menu'), 'Qutunun içinə şəkil və sözlərinizlə polaroid məktub.'],
                'live' => ['Canlı şəkil', 'Telefonu qutudakı şəklə tutanda sizin videonuz oynayır.'],
            ],
            'ru' => [
                'wrap' => ['Подарочная упаковка', 'Заворачиваем коробку в узорную бумагу и перевязываем лентой.'],
                'letter' => ['Полароид-письмо', 'Письмо с вашим фото и словами — внутрь коробки.'],
                'live' => ['Живое фото', 'Наводите телефон на фото — и поверх играет ваше видео.'],
            ],
        ];

        return $words[$locale][$key];
    }
}
