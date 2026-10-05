<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promo codes: the owner makes one, says what it takes off, and a customer
 * types it in at the checkout.
 *
 * What the code was worth is frozen on the order, the way every other price
 * on an order is frozen. A code edited or deleted afterwards must not change
 * what somebody already paid — and the books are read back from the orders,
 * so a figure that moved underneath them would quietly rewrite a closed month.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->decimal('percent', 5, 2);
            $table->boolean('is_active')->default(true);

            /** When it may be used. Both ends are optional. */
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            /** How many orders may use it in all, and how many already have. */
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('used_count')->default(0);

            /** Not worth taking off a small basket. */
            $table->decimal('min_total', 10, 2)->nullable();

            /** The owner's own note — who it was made for, where it went. */
            $table->string('note')->nullable();

            $table->timestamps();
            $table->index(['is_active', 'ends_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            // The code as it was used, its percent and the manats it took off,
            // all three frozen: the order must still add up in a year.
            $table->string('promo_code', 32)->nullable()->after('rush_fee');
            $table->decimal('promo_percent', 5, 2)->nullable()->after('promo_code');
            $table->decimal('discount', 10, 2)->default(0)->after('promo_percent');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['promo_code', 'promo_percent', 'discount']);
        });
        Schema::dropIfExists('promo_codes');
    }
};
