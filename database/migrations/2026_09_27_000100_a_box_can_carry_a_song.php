<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Designs that carry a Spotify code: the customer pastes the link to a song
 * and the scannable bar goes on the box. Only the designs the owner marks ask
 * for it — the rest of the catalogue must not grow a field nobody wants.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('spotify_code')->default(false)->after('category');
        });

        Schema::table('order_items', function (Blueprint $table) {
            // Kept as spotify:track:… — the canonical form, whatever the
            // customer pasted, so the picture can be redrawn years later.
            $table->string('spotify_uri')->nullable()->after('text_labels');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('spotify_code');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('spotify_uri');
        });
    }
};
