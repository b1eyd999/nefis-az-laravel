<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A slide now knows which page it stands on.
 *
 * The banner was the home page's alone. The xonça page wants one of its own,
 * and the next page after that will too, so a slide carries the name of the
 * page it belongs to and each page asks for its own.
 *
 * Everything written so far belongs to the home page, which is where it has
 * been showing — hence the default and the backfill.
 *
 * Rerun-safe: production is MySQL, which keeps DDL that ran before a failure.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('hero_slides', 'place')) {
            Schema::table('hero_slides', function (Blueprint $t) {
                $t->string('place', 20)->default('home')->index();
            });
        }

        DB::table('hero_slides')->whereNull('place')->orWhere('place', '')->update(['place' => 'home']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('hero_slides', 'place')) {
            Schema::table('hero_slides', function (Blueprint $t) {
                $t->dropColumn('place');
            });
        }
    }
};
