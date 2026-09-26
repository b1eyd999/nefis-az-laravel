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
        $products = Product::where('is_active', true)
            ->withCount('layers')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $designCount = Product::where('is_active', true)->count();

        // The opening banner is the owner's own: without a slide the page
        // starts with the designs themselves.
        $slides = HeroSlide::shown()->get();
        $autoplay = Setting::get(Setting::HERO_AUTOPLAY) === '1';
        $interval = max(2, (int) Setting::get(Setting::HERO_INTERVAL));

        $gifts = GiftPage::shown()->inLocale('az')->get();

        return view('welcome', compact('products', 'designCount', 'slides', 'autoplay', 'interval', 'gifts'));
    }
}
