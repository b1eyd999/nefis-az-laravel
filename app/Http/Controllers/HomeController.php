<?php

namespace App\Http\Controllers;

use App\Models\Product;

class HomeController extends Controller
{
    public function index()
    {
        $products = Product::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return view('welcome', compact('products'));
    }
}
