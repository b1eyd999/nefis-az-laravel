<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Three things for the chocolate shops.
 *
 * - A bar the owner deletes is only marked deleted, so the next import from
 *   its shop knows to leave it out; before, the import brought it straight
 *   back. That already happened once (Babaevskiy Lyuks and three Merci bars,
 *   deleted and then re-imported by a deploy): the copies the import made
 *   well after the first import are marked deleted again here.
 * - A marketplace bar records who sells it.
 * - Birmarket joins Araz Market as a shop whose prices refresh by themselves.
 *
 * Rerun-safe: production is MySQL, which keeps DDL that ran before a failure.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('chocolates', 'deleted_at')) {
            Schema::table('chocolates', fn (Blueprint $t) => $t->softDeletes());
        }
        if (! Schema::hasColumn('chocolates', 'seller')) {
            Schema::table('chocolates', fn (Blueprint $t) => $t->string('seller')->nullable()->after('barcode'));
        }

        $first = DB::table('chocolates')->where('source', 'arazmarket')->min('created_at');
        if ($first) {
            DB::table('chocolates')
                ->where('source', 'arazmarket')
                ->whereNull('deleted_at')
                ->where('created_at', '>', \Illuminate\Support\Carbon::parse($first)->addMinutes(10))
                ->update(['deleted_at' => now()]);
        }

        if (! DB::table('markets')->where('importer', 'birmarket')->exists()) {
            DB::table('markets')->insert([
                'name' => 'Birmarket', 'slug' => 'birmarket', 'website' => 'https://birmarket.az',
                'importer' => 'birmarket', 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('markets')->where('importer', 'birmarket')->delete();
        foreach (['deleted_at', 'seller'] as $column) {
            if (Schema::hasColumn('chocolates', $column)) {
                Schema::table('chocolates', fn (Blueprint $t) => $t->dropColumn($column));
            }
        }
    }
};
