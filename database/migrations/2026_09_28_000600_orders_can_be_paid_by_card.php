<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What an order remembers about a card payment.
 *
 * payment_method  transfer (the receipt the owner checks) or card (ePoint)
 * epoint_ref      the reference the gateway was given for the last attempt
 * epoint_transaction  the gateway's own id, for looking a payment up later
 *
 * Rerun-safe: production is MySQL, which keeps DDL that ran before a failure.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'payment_method')) {
                $table->string('payment_method', 20)->default('transfer');
            }
            if (! Schema::hasColumn('orders', 'epoint_ref')) {
                $table->string('epoint_ref', 40)->nullable()->index();
            }
            if (! Schema::hasColumn('orders', 'epoint_transaction')) {
                $table->string('epoint_transaction', 64)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            foreach (['payment_method', 'epoint_ref', 'epoint_transaction'] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
