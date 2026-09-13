<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Artisan;

// Müvəqqəti: shell girişi olmayan hostingdə migrasiya üçün.
// İstifadədən sonra bu fayl və ona aid route silinməlidir.
class RunMigrationsController extends Controller
{
    public function run(string $secret)
    {
        if (! hash_equals(env('RUN_MIGRATIONS_SECRET', ''), $secret)) {
            abort(404);
        }

        Artisan::call('migrate', ['--force' => true]);

        return response('<pre>'.e(Artisan::output()).'</pre>');
    }
}
