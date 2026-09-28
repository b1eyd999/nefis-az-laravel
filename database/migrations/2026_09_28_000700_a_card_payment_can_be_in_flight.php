<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A payment that has left for the bank but has not been answered for yet.
 *
 * The bank sends the customer back to us before it tells our server what
 * happened, so for a few seconds — sometimes a minute — he is looking at a
 * shop that still believes nothing was paid. Without these two columns the
 * shop offers him the pay button again, and the second tap is a second
 * charge on his card.
 *
 * `payment_asked_for` is also what the gateway's answer is checked against,
 * instead of a total recomputed later: an order edited while the customer was
 * at the bank must not turn a good payment into a mismatch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('payment_started_at')->nullable()->after('epoint_ref');
            $table->decimal('payment_asked_for', 8, 2)->nullable()->after('payment_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['payment_started_at', 'payment_asked_for']);
        });
    }
};
