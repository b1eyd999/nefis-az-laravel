<?php

namespace App\Http\Controllers;

use App\Support\Cart;
use App\Support\Letter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * A Polaroid letter bought on its own: a photo and a few words, printed like
 * a Polaroid shot, to slip into a box or hand over as it is.
 */
class LetterController extends Controller
{
    public function create(): View
    {
        abort_unless(Letter::enabled(), 404);

        return view('letters.create', ['price' => Letter::price(), 'max' => Letter::maxLength()]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(Letter::enabled(), 404);

        $request->validate(Letter::rules() + ['quantity' => ['nullable', 'integer', 'min:1', 'max:20']], Letter::messages() + [
            'quantity.min' => 'Say 1 ilə 20 arasında olmalıdır.',
            'quantity.max' => 'Say 1 ilə 20 arasında olmalıdır.',
        ]);
        $letter = Letter::fromRequest($request);
        if (! $letter) {
            throw ValidationException::withMessages(['letter_text' => 'Şəkil və ya mətn əlavə edin.']);
        }

        Cart::addLetter($letter, (int) $request->input('quantity', 1));

        return redirect()->route('cart.index')->with('status', 'Polaroid məktub səbətə əlavə olundu.');
    }
}
