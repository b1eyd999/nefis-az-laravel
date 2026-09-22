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
        $products = Product::where('is_active', true)
            ->withCount('layers')
            ->orderBy('sort_order')
            ->take(8)
            ->get();

        $designCount = Product::where('is_active', true)->count();

        // The opening banner: the owner's slides, or the original wording if none is on.
        $slides = HeroSlide::shown()->get();
        if ($slides->isEmpty()) {
            $slides = collect([HeroSlide::fallback()]);
        }
        $autoplay = Setting::get(Setting::HERO_AUTOPLAY) === '1';
        $interval = max(2, (int) Setting::get(Setting::HERO_INTERVAL));

        $gifts = GiftPage::shown()->inLocale('az')->get();

        return view('welcome', compact('products', 'designCount', 'slides', 'autoplay', 'interval', 'gifts'));
    }
}
