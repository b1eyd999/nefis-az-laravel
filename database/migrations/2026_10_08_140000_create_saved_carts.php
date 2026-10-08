<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The basket of somebody who has signed in, kept where the session cannot
 * lose it.
 *
 * A basket lived in the session alone: the customer spent ten minutes making
 * a box, left it until the evening, came back on another telephone — and the
 * basket was empty, with all of that work gone. The session is still what the
 * page reads; this is what fills it again.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('saved_carts')) {
            return;
        }

        Schema::create('saved_carts', function (Blueprint $table) {
            $table->id();
            // One basket per customer: the newest state of it, not a history.
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('items');
            $table->boolean('rush')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_carts');
    }
};
