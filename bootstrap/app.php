<?php

use App\Http\Middleware\AnswerIsNotAPage;
use App\Http\Middleware\CourierOnly;
use App\Http\Middleware\MaintenanceMode;
use App\Http\Middleware\OwnerOnly;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SignedInPagesAreNotKept;
use App\Http\Middleware\StaffOnly;
use App\Support\Locale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The owner's maintenance switch; staff and the admin panel stay open.
        $middleware->web(append: [MaintenanceMode::class, SignedInPagesAreNotKept::class]);
        /* The shop is Azerbaijani unless the address says otherwise. Said here
           rather than left to the server's own APP_LOCALE: a page outside the
           /ru and /en groups — the sign-out, a wrong address, a live photo —
           used to come back in whatever language the hosting was configured
           with, which is how signing out turned the shop English. */
        $middleware->prependToGroup('web', SetLocale::class);
        // Which language a page is written in comes from its address.
        // Who may open which part of the phone admin: the shop's people see
        // the orders, the owner alone sees the books, the stock and the videos.
        $middleware->alias([
            'locale' => SetLocale::class,
            'staff' => StaffOnly::class,
            'owner' => OwnerOnly::class,
            // And the courier's own screen, which is neither of those.
            'courier' => CourierOnly::class,
            // Addresses a page fetches from, so `back()` never lands on them.
            'data' => AnswerIsNotAPage::class,
        ]);
        // Telegram and the ePoint callback carry no session and no form token;
        // their own secret and signature guard them.
        $middleware->validateCsrfTokens(except: ['telegram/kuryer/*', 'telegram/sohbet/*', 'epoint/result']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // More than the hosting takes in one sending (a long video): a plain
        // page that says so, rather than an error — no session exists yet here.
        $exceptions->render(function (PostTooLargeException $e) {
            return response()->view('errors.too-large', [], 413);
        });

        /*
         * A form sent from a page that sat open until the session ran out.
         *
         * The one form on this shop that people leave open longest is the
         * sign-out in the menu — a shop is opened in the morning and closed at
         * night — and after a couple of hours its token is no longer the
         * session's. Laravel's answer to that is a bare English "Page Expired",
         * which is what the owner was shown instead of being signed out.
         *
         * Signing out with no session left is not an error at all: there is
         * nothing left to sign out of, and nothing to protect, so he is simply
         * taken home. A bad token on a session that IS alive is exactly what
         * the check exists for, so that still stops — at `errors/419`, which
         * Laravel picks up by itself, in his language and with a button whose
         * token is drawn fresh.
         *
         * Hinted on HttpException rather than on TokenMismatchException: the
         * framework turns one into the other (a 419) in `prepareException()`,
         * before any of these callbacks is asked, so a callback hinted on the
         * token exception is never called at all.
         */
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419) {
                return null;    // every other HTTP error is answered as before
            }

            $path = trim($request->path(), '/');
            $signingOut = $path === 'logout' || str_ends_with($path, '/logout');

            if (! $signingOut || auth()->check()) {
                return null;    // on to the 419 page
            }

            if (str_starts_with($path, 'admin')) {
                return redirect()->route('filament.admin.auth.login');
            }

            /* Home in the language he was reading. Taken from the address
               rather than from the app's locale: the token check belongs to
               the web group and throws before the route's own `locale:ru`
               has had a chance to run, so a Russian customer was being sent
               to the Azerbaijani shop. */
            $lang = explode('/', $path)[0];

            return redirect(route(
                in_array($lang, Locale::published(), true) && $lang !== Locale::DEFAULT
                    ? $lang.'.home'
                    : 'home'
            ));
        });
    })->create();
