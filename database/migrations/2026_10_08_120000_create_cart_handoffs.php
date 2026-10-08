<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A basket the shop filled for a customer who could not fill it himself.
 *
 * Most of the shop's conversations begin in Instagram, and some of them stop
 * there: the customer sends the photograph, says what he wants written on the
 * box, and then does not get through the design page. The owner can now make
 * the box himself and send one address; what opens is the customer's own
 * basket, already full, and from there the ordinary checkout takes over.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cart_handoffs')) {
            return;
        }

        Schema::create('cart_handoffs', function (Blueprint $table) {
            $table->id();
            // What goes in the address. Long and random: whoever holds it
            // holds the basket, so it must not be guessable.
            $table->string('token', 40)->unique();
            // Whose basket this is, in the owner's own words: "Aygün, Instagram".
            $table->string('note', 120)->nullable();
            $table->json('items');
            $table->boolean('rush')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at')->nullable();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            // A basket priced today should not be opened in three months.
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_handoffs');
    }
};
