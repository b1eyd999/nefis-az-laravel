<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Captions no longer stop the customer at 60 characters (the owner asked for
 * the limit to go). 255 is only the field's own ceiling; a longer text still
 * shrinks to fit its slot on the box. Time captions (mm:ss) keep their 5.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('text_slots', function (Blueprint $table) {
            $table->unsignedInteger('max_length')->default(255)->change();
        });

        DB::table('text_slots')->where('kind', '!=', 'time')->where('max_length', '<', 255)->update(['max_length' => 255]);
    }

    public function down(): void
    {
        Schema::table('text_slots', function (Blueprint $table) {
            $table->unsignedInteger('max_length')->default(60)->change();
        });
    }
};
