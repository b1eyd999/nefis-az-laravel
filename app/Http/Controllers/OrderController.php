<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = $request->user()
            ->orders()
            // The changes come along so the page can say what is still owed
            // either way without asking the database once per order.
            ->with(['items.product', 'items.livePhotos', 'adjustments'])
            ->latest()
            ->get();

        return view('orders.index', compact('orders'));
    }
}
