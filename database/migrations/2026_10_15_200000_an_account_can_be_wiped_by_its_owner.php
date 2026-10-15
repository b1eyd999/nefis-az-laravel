<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When an account was wiped at its owner's request.
 *
 * Not SoftDeletes. `orders.user_id` is NOT NULL and cascades on delete, so
 * removing the row would take the orders with it — and with them the money,
 * the stock movements and the e-qaimə numbers that have to keep adding up.
 * The row therefore stays and its personal fields are scrubbed; this column
 * is what says so, so the admin list and the «someone just ordered» ticker
 * can leave those accounts alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('scrubbed_at')->nullable()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('scrubbed_at');
        });
    }
};
