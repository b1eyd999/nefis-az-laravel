<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which customer a prepared basket was put into.
 *
 * Until now a basket waited behind an address and the owner had to get that
 * address to the right person himself. When the customer already has an
 * account, there is a shorter way: the basket goes straight into his, and it
 * is there the next time he opens the shop — on whichever telephone he opens
 * it. This column records who it was given to, so the list says so and the
 * same basket is not handed over twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cart_handoffs') || Schema::hasColumn('cart_handoffs', 'user_id')) {
            return;
        }

        Schema::table('cart_handoffs', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('note')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('given_at')->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cart_handoffs') || ! Schema::hasColumn('cart_handoffs', 'user_id')) {
            return;
        }

        Schema::table('cart_handoffs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('given_at');
        });
    }
};
