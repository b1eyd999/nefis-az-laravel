<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The three photographs on the company page become the owner's to choose.
 *
 * They were three constants in the code with four corner points each, so
 * «where do I replace these photographs» had no answer but «you cannot».
 * The shop already has the thing needed to answer it — the scene editor,
 * where a photograph is uploaded and the design's place is dragged into
 * position — so a scene only needs to be able to say it belongs there too.
 *
 * Nothing is marked by this migration: with no scene chosen, the page keeps
 * showing the three it has always shown.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scenes', function (Blueprint $table) {
            $table->boolean('on_corporate')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('scenes', function (Blueprint $table) {
            $table->dropColumn('on_corporate');
        });
    }
};
