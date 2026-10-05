<?php

use App\Http\Middleware\AnswerIsNotAPage;
use App\Http\Middleware\CourierOnly;
use App\Http\Middleware\MaintenanceMode;
use App\Http\Middleware\OwnerOnly;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\StaffOnly;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The owner's maintenance switch; staff and the admin panel stay open.
        $middleware->web(append: [MaintenanceMode::class]);
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
    })->create();
