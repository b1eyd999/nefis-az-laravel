<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The point a door delivery goes to, picked on the checkout map, so the
 * courier can open it in a maps app.
 *
 * Rerun-safe: production is MySQL, which keeps DDL that ran before a failure.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['delivery_lat', 'delivery_lng'] as $column) {
            if (! Schema::hasColumn('orders', $column)) {
                Schema::table('orders', fn (Blueprint $t) => $t->decimal($column, 10, 7)->nullable());
            }
        }
    }

    public function down(): void
    {
        foreach (['delivery_lat', 'delivery_lng'] as $column) {
            if (Schema::hasColumn('orders', $column)) {
                Schema::table('orders', fn (Blueprint $t) => $t->dropColumn($column));
            }
        }
    }
};
