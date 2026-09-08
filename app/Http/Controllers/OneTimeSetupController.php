<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Artisan;

// Müvəqqəti: shell girişi olmayan hostingdə migrasiya/admin qurulumu üçün.
// İstifadədən sonra bu fayl və ona aid route silinməlidir.
class OneTimeSetupController extends Controller
{
    public function run(string $secret)
    {
        if (!hash_equals(env('ONE_TIME_SETUP_SECRET', ''), $secret)) {
            abort(404);
        }

        Artisan::call('migrate', ['--force' => true]);
        $migrateOutput = Artisan::output();

        Artisan::call('make:filament-user', [
            '--name' => env('ONE_TIME_ADMIN_NAME', 'Admin'),
            '--email' => env('ONE_TIME_ADMIN_EMAIL'),
            '--password' => env('ONE_TIME_ADMIN_PASSWORD'),
            '--no-interaction' => true,
        ]);
        $userOutput = Artisan::output();

        return response(
            '<pre>' . e($migrateOutput) . "\n" . e($userOutput) . '</pre>'
        );
    }
}
