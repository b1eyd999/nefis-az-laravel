<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The owner's maintenance switch; staff and the admin panel stay open.
        $middleware->web(append: [\App\Http\Middleware\MaintenanceMode::class]);
        // Which language a page is written in comes from its address.
        // Who may open which part of the phone admin: the shop's people see
        // the orders, the owner alone sees the books, the stock and the videos.
        $middleware->alias([
            'locale' => \App\Http\Middleware\SetLocale::class,
            'staff' => \App\Http\Middleware\StaffOnly::class,
            'owner' => \App\Http\Middleware\OwnerOnly::class,
            // Addresses a page fetches from, so `back()` never lands on them.
            'data' => \App\Http\Middleware\AnswerIsNotAPage::class,
        ]);
        // Telegram and the ePoint callback carry no session and no form token;
        // their own secret and signature guard them.
        $middleware->validateCsrfTokens(except: ['telegram/kuryer/*', 'telegram/sohbet/*', 'epoint/result']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // More than the hosting takes in one sending (a long video): a plain
        // page that says so, rather than an error — no session exists yet here.
        $exceptions->render(function (\Illuminate\Http\Exceptions\PostTooLargeException $e) {
            return response()->view('errors.too-large', [], 413);
        });
    })->create();
