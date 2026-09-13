<?php

namespace App\Http\Controllers;

use App\Models\Product;

class ProductController extends Controller
{
    public function customize(Product $product)
    {
        abort_unless($product->is_active, 404);

        $product->load('angles');

        return view('products.customize', compact('product'));
    }
}
