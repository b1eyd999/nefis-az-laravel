<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WhatsApp inside the shop's own admin.
 *
 * Until now a customer's WhatsApp question could only be answered from the
 * owner's telephone, which meant the owner himself. These two tables hold
 * the conversations so that anybody he trusts can answer from the panel, and
 * so that he can see afterwards who answered what.
 *
 * One row per message, and one row per person he is talking to. The thread
 * key is the customer's own number as WhatsApp writes it — digits only, no
 * plus — because that is the only name WhatsApp gives a conversation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_chats', function (Blueprint $table) {
            $table->id();
            $table->string('wa_id', 32)->unique();          // his number, digits only
            $table->string('name', 120)->nullable();        // as he set it in WhatsApp
            $table->string('phone', 40)->nullable();        // the same, written for people
            /* When he last wrote. WhatsApp lets a business answer freely for
               twenty-four hours after that and not a minute longer, so this
               is what the panel reads to say whether the door is still open. */
            $table->timestamp('last_incoming_at')->nullable();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->unsignedInteger('unread')->default(0);
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->string('wa_id', 32)->index();
            $table->string('direction', 3);                 // in | out
            /* WhatsApp's own id for the message. Unique, because the same
               notice is delivered again whenever our answer is slow, and a
               customer must not see his question twice. */
            $table->string('wam_id', 128)->nullable()->unique();
            $table->string('type', 24)->default('text');
            $table->text('body')->nullable();
            $table->string('media_path')->nullable();
            $table->string('media_mime', 100)->nullable();
            $table->string('media_name')->nullable();
            // sent · delivered · read · failed, for what the shop sends
            $table->string('status', 16)->nullable();
            $table->text('error')->nullable();
            // Who in the shop wrote it, so the owner can see later.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['wa_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_chats');
    }
};
