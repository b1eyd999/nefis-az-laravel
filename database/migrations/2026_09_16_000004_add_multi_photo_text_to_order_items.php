<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->json('customer_photos')->nullable()->after('customer_photo');
            $table->json('custom_texts')->nullable()->after('custom_text');
        });

        foreach (DB::table('order_items')->select('id', 'customer_photo', 'custom_text')->get() as $row) {
            DB::table('order_items')->where('id', $row->id)->update([
                'customer_photos' => json_encode($row->customer_photo ? [$row->customer_photo] : []),
                'custom_texts' => json_encode($row->custom_text ? [$row->custom_text] : []),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['customer_photos', 'custom_texts']);
        });
    }
};
