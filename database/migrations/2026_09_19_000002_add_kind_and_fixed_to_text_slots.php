<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Not every caption on a box is the customer's to write. Some are part of
 * the design and must stay as the owner set them (`fixed`), and some take a
 * particular shape — a song's elapsed and total time on the Spotify box is
 * always four digits as mm:ss (`kind` = time).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('text_slots', function (Blueprint $table) {
            $table->string('kind', 10)->default('text')->after('label');
            $table->boolean('fixed')->default(false)->after('kind');
        });
    }

    public function down(): void
    {
        Schema::table('text_slots', function (Blueprint $table) {
            $table->dropColumn(['kind', 'fixed']);
        });
    }
};
