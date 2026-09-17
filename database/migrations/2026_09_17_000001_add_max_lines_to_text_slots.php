<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How many lines the caption may occupy. Longer text shrinks to fit rather
     * than wrapping into whatever sits below it.
     */
    public function up(): void
    {
        Schema::table('text_slots', function (Blueprint $table) {
            $table->unsignedTinyInteger('max_lines')->default(1)->after('max_length');
        });
    }

    public function down(): void
    {
        Schema::table('text_slots', function (Blueprint $table) {
            $table->dropColumn('max_lines');
        });
    }
};
