<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orders stopped going through when photos moved to `customer_photos`: the
 * old single-photo column stayed NOT NULL, checkout no longer fills it, and
 * every order failed on the insert. A text-only design has no photo at all,
 * so the column has to allow empty either way.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('customer_photo')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('customer_photo')->nullable(false)->change();
        });
    }
};
