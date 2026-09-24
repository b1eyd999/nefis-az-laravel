<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which language the customer ordered in. Letters and messages follow it, so
 * someone who bought on the Russian pages is not written to in Azerbaijani.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', fn (Blueprint $t) => $t->string('locale', 5)->default('az')->after('status'));
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('locale'));
    }
};
