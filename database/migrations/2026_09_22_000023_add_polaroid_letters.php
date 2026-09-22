<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Polaroid letters: a photo and a few words, printed like a Polaroid shot.
 * One can go inside a box, or be ordered on its own — then its order line
 * has no box at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->change();

            if (! Schema::hasColumn('order_items', 'letter_text')) {
                $table->text('letter_text')->nullable()->after('wrapping_price');
                $table->string('letter_photo')->nullable()->after('letter_text');
                $table->decimal('letter_price', 8, 2)->nullable()->after('letter_photo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['letter_text', 'letter_photo', 'letter_price']);
        });
    }
};
