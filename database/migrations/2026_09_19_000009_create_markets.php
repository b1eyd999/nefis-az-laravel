<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The shops the chocolate bars are bought from, each its own category in the
 * admin. A shop whose site can be read has an `importer` (its bars and prices
 * refresh by themselves); any other shop's bars are added by hand.
 *
 * Araz Market is the first; the bars already imported from it join it.
 * Rerun-safe: production is MySQL, which keeps DDL that ran before a failure.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('markets')) {
            Schema::create('markets', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('website')->nullable();
                $table->string('importer', 30)->nullable()->unique();
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('chocolates', 'market_id')) {
            Schema::table('chocolates', fn (Blueprint $t) => $t->unsignedBigInteger('market_id')->nullable()->index()->after('id'));
        }

        if (! DB::table('markets')->where('importer', 'arazmarket')->exists()) {
            DB::table('markets')->insert([
                'name' => 'Araz Market', 'slug' => 'araz-market', 'website' => 'https://www.arazmarket.az',
                'importer' => 'arazmarket', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $araz = DB::table('markets')->where('importer', 'arazmarket')->value('id');
        DB::table('chocolates')->where('source', 'arazmarket')->whereNull('market_id')->update(['market_id' => $araz]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('chocolates', 'market_id')) {
            Schema::table('chocolates', function (Blueprint $t) {
                $t->dropIndex(['market_id']);
                $t->dropColumn('market_id');
            });
        }
        Schema::dropIfExists('markets');
    }
};
