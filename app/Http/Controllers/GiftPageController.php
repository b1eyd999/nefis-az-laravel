<?php

namespace App\Http\Controllers;

use App\Models\GiftPage;
use App\Models\Wrapping;
use App\Support\Letter;
use App\Support\LiveMaterials;
use Illuminate\View\View;

/**
 * Gift ideas: the list of occasions, and one page per occasion with the
 * designs that suit it. Written for the searches people make.
 */
class GiftPageController extends Controller
{
    public function index(): View
    {
        $pages = GiftPage::shown()->get();

        return view('gifts.index', compact('pages'));
    }

    public function show(GiftPage $giftPage): View
    {
        abort_unless($giftPage->is_active, 404);

        $products = $giftPage->shownProducts();
        $from = $products->pluck('price')->filter(fn ($p) => $p > 0)->min();
        $others = GiftPage::shown()->whereKeyNot($giftPage->id)->get();

        // What else can go with the box, only what is on sale now.
        $extras = array_values(array_filter([
            Wrapping::where('is_active', true)->exists()
                ? ['🎁', 'Hədiyyə qablaşdırması', 'Qutunu naxışlı kağıza büküb lentlə bağlayırıq.', route('wrappings.index')] : null,
            Letter::enabled()
                ? ['💌', Letter::text('menu'), 'Qutunun içinə şəkil və sözlərinizlə polaroid məktub.', route('letters.create')] : null,
            LiveMaterials::enabled()
                ? ['🎬', 'Canlı şəkil', 'Telefonu qutudakı şəklə tutanda sizin videonuz oynayır.', route('live.create')] : null,
        ]));

        return view('gifts.show', ['page' => $giftPage] + compact('products', 'from', 'others', 'extras'));
    }
}
