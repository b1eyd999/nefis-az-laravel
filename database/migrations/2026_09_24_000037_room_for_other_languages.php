<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Room beside every text the owner writes for the Russian and English words
 * that mean the same. One JSON column each, so nothing already written moves.
 */
return new class extends Migration
{
    private const TABLES = ['products', 'delivery_methods', 'wrappings', 'hero_slides'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->json('i18n')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('i18n'));
        }
    }
};
