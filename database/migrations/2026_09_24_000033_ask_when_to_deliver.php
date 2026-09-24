<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Checkout asks when the box should arrive. Every box is made by hand, so
 * nothing can be handed over the same day; the customer sees the earliest
 * date and picks a day and a part of the day from there.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('orders', 'delivery_date')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->date('delivery_date')->nullable()->after('delivery_price');
            $table->string('delivery_slot', 40)->nullable()->after('delivery_date');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['delivery_date', 'delivery_slot']);
        });
    }
};
