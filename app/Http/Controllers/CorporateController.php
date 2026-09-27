<?php

namespace App\Http\Controllers;

use App\Models\CorporateRequest;
use App\Support\CorporatePage;
use App\Support\Telegram;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The page that offers companies the small chocolate with their own logo on
 * it, and takes their first message about it.
 *
 * Nothing is priced here: what it costs turns on the number and on what the
 * logo needs, so the page ends in a request rather than a basket.
 */
class CorporateController extends Controller
{
    public function index(): View
    {
        return view('corporate.index', ['page' => CorporatePage::all()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $minimum = CorporatePage::minimum();

        $data = $request->validate([
            'company' => ['required', 'string', 'max:150'],
            'person' => ['nullable', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:150'],
            'quantity' => ['required', 'integer', 'min:' . $minimum, 'max:1000000'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:4096'],
            'box_color' => ['nullable', 'string', 'max:30'],
            'slogan' => ['nullable', 'string', 'max:120'],
            'qr_target' => ['nullable', 'string', 'max:300'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'company.required' => __('Şirkətin adını yazın.'),
            'phone.required' => __('Əlaqə nömrəsini yazın.'),
            'quantity.required' => __('Neçə ədəd lazım olduğunu yazın.'),
            'quantity.min' => __('Ən azı :min ədəddən sifariş qəbul edirik.', ['min' => $minimum]),
            'logo.image' => __('Loqo şəkil olmalıdır (PNG, JPG, SVG).'),
            'logo.max' => __('Loqo 4 MB-dan böyük ola bilməz.'),
        ]);

        // Kept beside the cart's own uploads, in a folder of its own so a
        // clean-up of customer photos never touches a company's logo.
        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('corporate-logos', 'public');
        }

        $made = CorporateRequest::create($data);

        // The shop hears about it at once; a failure here must not lose the
        // request the company has already sent.
        try {
            Telegram::corporate($made);
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('corporate.sent', CorporatePage::text('form_thanks'))->withFragment('muraciet');
    }
}
