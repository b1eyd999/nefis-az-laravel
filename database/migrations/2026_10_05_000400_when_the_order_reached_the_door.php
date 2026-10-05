<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The hour the box was handed over.
 *
 * "Tamamlandı" said that it had been, but not when: the only trace was
 * `updated_at`, which moves again the moment anybody edits the order
 * afterwards. The courier confirms the handover on his own screen and that
 * moment is written down here, for the shop to show and for nobody to have to
 * remember.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('delivered_at')->nullable()->after('on_the_way_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('delivered_at');
        });
    }
};
