<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The chat on the site. A visitor writes from the corner of the page, the
 * message goes to the shop's Telegram, and whatever the shop answers there
 * comes back into the same little window.
 *
 * A thread is the visitor's own key, kept in his session; the Telegram
 * message the shop replies to is remembered so the answer finds its way home.
 * Either side may send a picture: a screenshot of the box, a photo of a
 * receipt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->string('thread', 40)->index();
            $table->string('side', 10); // visitor | shop
            $table->text('body');
            // A screenshot says in one go what a paragraph tries to.
            $table->string('image')->nullable();
            $table->string('name', 120)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('page', 255)->nullable();
            $table->unsignedBigInteger('tg_message_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
