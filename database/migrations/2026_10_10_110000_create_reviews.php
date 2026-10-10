<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a customer says once the box is in his hands.
 *
 * Tied to the order, not merely to the person: only somebody whose order was
 * handed over may write one, so a review on this site is always a review by
 * a customer. One per order, and the owner reads it before anybody else sees
 * it — a hand-made gift goes wrong in ways worth answering privately first.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reviews')) {
            return;
        }

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Which design it is about, when the order held one box. Kept so
            // the design's own page can show its own stars; null for an order
            // of several designs, or when the design is later deleted.
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('stars');
            $table->text('body')->nullable();
            // The customer's own photograph of the box. Worth more than
            // anything the shop can shoot itself.
            $table->string('photo')->nullable();
            // What the visitor is shown as the writer's name: his own first
            // name by default, and he may ask for less.
            $table->string('shown_name')->nullable();
            $table->string('locale', 5)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            // The shop's answer, shown under the review.
            $table->text('reply')->nullable();
            $table->timestamps();

            $table->index(['approved_at', 'id']);
            $table->index(['product_id', 'approved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
