<?php

use App\Http\Controllers\OneTimeSetupController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/one-time-setup/{secret}', [OneTimeSetupController::class, 'run']);
