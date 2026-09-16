<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;

/**
 * Temporary: the hosting account has no SSH, so migrations and seeders are run
 * through this route once after a deploy. Remove it (and DEPLOY_TOKEN) as soon
 * as the deploy is confirmed.
 */
class DeployController extends Controller
{
    public function run(string $token): Response
    {
        $expected = config('app.deploy_token');

        abort_if(blank($expected) || ! hash_equals($expected, $token), 404);

        $output = [];

        foreach ([
            ['migrate', ['--force' => true]],
            ['db:seed', ['--class' => 'Database\\Seeders\\DesignSeeder', '--force' => true]],
            ['db:seed', ['--class' => 'Database\\Seeders\\DesignTemplateSeeder', '--force' => true]],
        ] as [$command, $args]) {
            Artisan::call($command, $args);
            $output[] = "\$ php artisan {$command}\n" . Artisan::output();
        }

        return response(implode("\n", $output), 200)
            ->header('Content-Type', 'text/plain; charset=utf-8');
    }
}
