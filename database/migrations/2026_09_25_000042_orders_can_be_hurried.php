<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A customer in a hurry pays a little more and his box is made before the
 * others, in hours instead of days. What he paid is kept on the order, so a
 * later change to the fee never rewrites an old one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('rush_fee', 8, 2)->nullable()->after('delivery_price');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('rush_fee');
        });
    }
};
