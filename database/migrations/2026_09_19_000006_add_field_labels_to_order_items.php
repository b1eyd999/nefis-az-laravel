<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An order line keeps the names of the fields the customer filled in
 * ("1. Şəkil", "Mətn 1", "Vaxt"…) beside the values, so the admin reads the
 * order the way the customer saw the form — even after the design changes
 * or is deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['photo_labels', 'text_labels'] as $column) {
            if (! Schema::hasColumn('order_items', $column)) {
                Schema::table('order_items', fn (Blueprint $t) => $t->json($column)->nullable());
            }
        }
    }

    public function down(): void
    {
        foreach (['photo_labels', 'text_labels'] as $column) {
            if (Schema::hasColumn('order_items', $column)) {
                Schema::table('order_items', fn (Blueprint $t) => $t->dropColumn($column));
            }
        }
    }
};
