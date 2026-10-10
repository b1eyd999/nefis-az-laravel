<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two letters the shop sends without anybody pressing anything: a reminder
 * to somebody who left the payment page, and a thank-you once the box is in
 * his hands.
 *
 * Each one is written down on the order, because each must go exactly once.
 * A reminder sent every hour is a reason to block the sender.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'payment_reminded_at')) {
                $table->timestamp('payment_reminded_at')->nullable()->after('payment_confirmed_at');
            }
            if (! Schema::hasColumn('orders', 'thanked_at')) {
                $table->timestamp('thanked_at')->nullable()->after('delivered_at');
            }
            // The code that went out with the thank-you, so the owner can see
            // which letter a returning customer came back on.
            if (! Schema::hasColumn('orders', 'thanks_promo')) {
                $table->string('thanks_promo')->nullable()->after('thanked_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            foreach (['payment_reminded_at', 'thanked_at', 'thanks_promo'] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
