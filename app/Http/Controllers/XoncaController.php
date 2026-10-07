<?php

namespace App\Http\Controllers;

use App\Support\Xonca;
use Illuminate\View\View;

/**
 * The small chocolates for a xonça: nişan, hinayaxdı, toy.
 *
 * The page exists even before a design is filed under it, because the menu
 * can carry a "Tezliklə" on the line while the owner draws them; what it
 * shows then is his own "coming soon" sentence rather than an empty shelf.
 */
class XoncaController extends Controller
{
    public function index(): View
    {
        return view('xonca.index', [
            'page' => Xonca::page(),
            'designs' => Xonca::designs(),
        ]);
    }
}
