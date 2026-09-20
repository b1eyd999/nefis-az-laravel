<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paying by transfer: the owner keeps his own cards, M10 wallets and bank
 * accounts here, the customer is shown one of them, pays and sends the
 * receipt, and the owner confirms the order.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_accounts')) {
            Schema::create('payment_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('type', 20)->default('card');   // card | m10 | iban
                $table->string('label');                       // bank and the name on it
                $table->string('number');                      // card number, wallet number or IBAN
                $table->string('note')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'payment_account_id')) {
                $table->foreignId('payment_account_id')->nullable()->after('status')->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('orders', 'payment_receipt')) {
                $table->string('payment_receipt')->nullable()->after('payment_account_id');
                $table->timestamp('receipt_at')->nullable()->after('payment_receipt');
                $table->timestamp('payment_confirmed_at')->nullable()->after('receipt_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_account_id');
            $table->dropColumn(['payment_receipt', 'receipt_at', 'payment_confirmed_at']);
        });
        Schema::dropIfExists('payment_accounts');
    }
};
