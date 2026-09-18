<?php

namespace App\Http\Controllers;

use App\Models\Product;

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

        return view('welcome', compact('products', 'designCount'));
    }
}
