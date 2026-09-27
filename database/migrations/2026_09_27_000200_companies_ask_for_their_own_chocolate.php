<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A company asking for chocolates with its own logo on them. Not an order:
 * the price depends on how many and on what the logo needs, so this is the
 * conversation's first line — enough for the owner to answer with a figure
 * without writing back to ask the obvious.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('corporate_requests', function (Blueprint $table) {
            $table->id();
            $table->string('company');
            $table->string('person')->nullable();
            $table->string('phone');
            $table->string('email')->nullable();
            $table->unsignedInteger('quantity');
            // The logo as they uploaded it, and the colour they picked for
            // the box — so the owner can see what they already had in mind.
            $table->string('logo')->nullable();
            $table->string('box_color')->nullable();
            $table->string('slogan')->nullable();
            $table->string('qr_target')->nullable();
            $table->text('note')->nullable();
            $table->string('status')->default('new');
            $table->timestamps();

            $table->index(['status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corporate_requests');
    }
};
