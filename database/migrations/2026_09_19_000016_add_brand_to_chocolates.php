<?php

use App\Support\ChocolateBrand;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bars get a brand, so the customer picks Milka, Nestlé, Alpen Gold… first
 * instead of scrolling one long list. Existing bars get theirs from the name.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('chocolates', 'brand')) {
            Schema::table('chocolates', function (Blueprint $table) {
                $table->string('brand', 60)->nullable()->after('name')->index();
            });
        }

        foreach (DB::table('chocolates')->whereNull('brand')->get(['id', 'name']) as $bar) {
            if ($brand = ChocolateBrand::detect($bar->name)) {
                DB::table('chocolates')->where('id', $bar->id)->update(['brand' => $brand]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('chocolates', function (Blueprint $table) {
            $table->dropIndex(['brand']);
            $table->dropColumn('brand');
        });
    }
};
