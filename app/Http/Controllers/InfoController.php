<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Support\Contact;
use App\Support\Info;
use App\Support\Telegram;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The three pages that answer a visitor before he buys anything.
 *
 * All three were sections of the front page, reachable only by «#how» and
 * «#faq»: nothing to send anybody, nothing a search engine could land on,
 * and from any other page the footer's own links went nowhere.
 */
class InfoController extends Controller
{
    public function how(): View
    {
        return view('info.how', ['page' => Info::all()]);
    }

    public function faq(): View
    {
        return view('info.faq', ['page' => Info::all(), 'faq' => Info::faq()]);
    }

    public function contact(): View
    {
        return view('info.contact', ['page' => Info::all()]);
    }

    /**
     * A message from the contact page.
     *
     * Written down before it is sent anywhere: a message that exists only in
     * a Telegram chat is lost the first time the bot's token changes.
     */
    public function write(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:150'],
            'about' => ['nullable', 'string', 'max:150'],
            'message' => ['required', 'string', 'min:5', 'max:2000'],
        ], [
            'name.required' => __('Adınızı yazın.'),
            'phone.required' => __('Əlaqə nömrənizi yazın.'),
            'message.required' => __('Nə yazmaq istədiyinizi yazın.'),
            'message.min' => __('Bir az ətraflı yazın.'),
        ]);

        $message = ContactMessage::create($data + ['locale' => \App\Support\Locale::current()]);

        // Telegram being off, or the token being wrong, must not cost the
        // message: it is already written down by the time we get here.
        defer(fn () => Telegram::contact($message));

        return back()->with('status', Info::text('contact_form_thanks'));
    }

    /** The shop's own particulars, as every one of these pages shows them. */
    public static function details(): array
    {
        return [
            'phone' => Contact::has() ? Contact::display() : null,
            'dial' => Contact::has() ? Contact::dial() : null,
            'whatsapp' => Contact::has() ? Contact::whatsapp() : null,
            'hours' => Contact::hours() ? __(Contact::hours()) : null,
            'email' => \App\Support\CustomerNotice::FROM,
            'legal' => array_filter([
                \App\Models\Setting::get(\App\Models\Setting::LEGAL_NAME),
                \App\Models\Setting::get(\App\Models\Setting::LEGAL_VOEN),
                \App\Models\Setting::get(\App\Models\Setting::LEGAL_ADDRESS),
            ]),
        ];
    }
}
