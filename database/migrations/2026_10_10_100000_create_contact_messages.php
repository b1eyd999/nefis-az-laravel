<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What somebody writes from the contact page.
 *
 * It is sent to Telegram the moment it arrives, but it is written down as
 * well: a message that exists only in a chat is a message that is lost the
 * first time the bot's token is changed or the phone is wiped. The owner
 * works through the list and ticks each one off.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contact_messages')) {
            return;
        }

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->text('message');
            // Which order it is about, when the writer says so.
            $table->string('about')->nullable();
            $table->string('locale', 5)->nullable();
            // Answered or not; who, and when.
            $table->timestamp('answered_at')->nullable();
            $table->foreignId('answered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['answered_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
