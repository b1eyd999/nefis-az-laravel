<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which courier took the delivery. He says so by tapping the message in the
 * group; the message itself is remembered, so his name can be written under
 * it for everyone else to see.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('courier_name')->nullable()->after('materials_cost');
            $table->timestamp('courier_taken_at')->nullable()->after('courier_name');
            $table->string('courier_chat_id')->nullable()->after('courier_taken_at');
            $table->unsignedBigInteger('courier_message_id')->nullable()->after('courier_chat_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['courier_name', 'courier_taken_at', 'courier_chat_id', 'courier_message_id']);
        });
    }
};
