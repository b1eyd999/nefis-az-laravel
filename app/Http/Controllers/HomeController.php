<?php

namespace App\Http\Controllers;

use App\Models\GiftPage;
use App\Models\HeroSlide;
use App\Models\Product;
use App\Models\Setting;

class HomeController extends Controller
{
    public function index()
    {
        // The whole catalogue opens right under the banner: the first thing a
        // visitor sees is the boxes themselves, not a description of them.
        $products = Product::onTheShelf()
            ->withCount('layers')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        /* Then the owner's own front row, set in the admin under «Ana səhifə».
           Whatever he put there leads, in his order; everything else keeps the
           order it already had, which is what a stable sort gives us. A design
           he picked and has since hidden simply is not in this list to begin
           with, so it cannot pull an empty space to the front. */
        $front = array_flip(Setting::homeFirst());

        if ($front !== []) {
            $products = $products->sortBy(fn (Product $design) => $front[$design->id] ?? PHP_INT_MAX)->values();
        }

        $designCount = Product::onTheShelf()->count();

        // The opening banner is the owner's own: without a slide the page
        // starts with the designs themselves.
        $slides = HeroSlide::shown()->get();
        $autoplay = Setting::get(Setting::HERO_AUTOPLAY) === '1';
        $interval = max(2, (int) Setting::get(Setting::HERO_INTERVAL));

        $gifts = GiftPage::shown()->inLocale(\App\Support\Locale::current())->get();

        return view('welcome', compact('products', 'designCount', 'slides', 'autoplay', 'interval', 'gifts'));
    }
}
