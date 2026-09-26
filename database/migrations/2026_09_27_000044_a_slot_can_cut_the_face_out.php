<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Some designs are a hole with a body around it: the customer's face sits on
 * a baby, a knight, a cartoon. For those the photo has to arrive without its
 * background, and nobody is going to cut it out by hand — so the slot says
 * "this one is a face", and the browser does the cutting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photo_slots', function (Blueprint $table) {
            $table->boolean('cutout')->default(false)->after('shape');
        });
    }

    public function down(): void
    {
        Schema::table('photo_slots', function (Blueprint $table) {
            $table->dropColumn('cutout');
        });
    }
};
