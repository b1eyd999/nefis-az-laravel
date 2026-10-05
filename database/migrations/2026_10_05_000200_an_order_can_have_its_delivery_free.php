<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The owner waives the delivery on an order.
 *
 * It is his decision after the order exists, never something the customer is
 * offered at the checkout — so nothing about it appears there.
 *
 * A flag rather than zeroing `delivery_price`: the method and its price stay
 * on the order the way every other figure stays, so the waiving can be undone
 * and the shop can still see what the courier run was worth.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('free_delivery')->default(false)->after('delivery_price');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('free_delivery');
        });
    }
};
