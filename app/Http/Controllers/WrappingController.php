<?php

namespace App\Http\Controllers;

use App\Models\Wrapping;
use Illuminate\View\View;

/**
 * The gift wraps on a page of their own, each on a box, grouped by price.
 * The customer picks one on a design's page, after the chocolate.
 */
class WrappingController extends Controller
{
    public function index(): View
    {
        $groups = Wrapping::shown()->get()->map->toCustomer()
            ->groupBy(fn (array $w) => number_format($w['price'], 2, '.', ''));

        return view('wrappings.index', compact('groups'));
    }
}
