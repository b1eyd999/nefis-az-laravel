<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A company is not an ordinary customer.
 *
 * It writes from the corporate page with its logo and its numbers, the shop
 * answers, and only then is there an account. What is ordered into it is
 * agreed beforehand — a hundred small chocolates beside the coffee, or a
 * tray of them — and the designs drawn for that work have no business on
 * the public shelf: they carry somebody else's logo.
 *
 * So a design can be marked as the companies' own. It then leaves the
 * catalogue, the sitemap and the feed, and nobody but a business account —
 * or the shop itself — can put it in a basket.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('business_only')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('business_only');
        });
    }
};
