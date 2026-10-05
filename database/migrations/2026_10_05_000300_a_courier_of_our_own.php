<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A courier the shop knows by name.
 *
 * Until now a courier was whoever tapped the message in the Telegram group:
 * the order kept his Telegram name as plain text and nothing more. He could
 * not be given an order, could not be asked where he was, and the shop had no
 * way of telling one from another.
 *
 * So he becomes an account of his own. The owner hands him an order, the order
 * remembers whose it is, and while he is driving — and only while he chooses
 * to — his phone leaves a trail the owner can watch. The old `courier_name`
 * stays beside it: orders taken from the group still have nothing else, and
 * every screen that shows it keeps working.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('courier_id')->nullable()->after('courier_name')
                ->constrained('users')->nullOnDelete();
            // Said out loud to the customer: the box has left the workshop.
            $table->timestamp('on_the_way_at')->nullable()->after('courier_message_id');
        });

        Schema::table('users', function (Blueprint $table) {
            /* Sharing a position is something the courier switches on when he
               sets off, and it runs out by itself. A column rather than a
               guess from the last ping: he must be able to see that it is on,
               and the owner must be able to see that it is not. */
            $table->timestamp('sharing_until')->nullable()->after('profit_percent');
        });

        /* Where he was, a point at a time. Kept for a short while only — this
           is for finding a courier who is out now, not a record of anybody's
           day; `App\Support\CourierTrail` throws the old ones away. */
        Schema::create('courier_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            // What the phone itself thinks of the reading, in metres.
            $table->unsignedSmallInteger('accuracy')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_positions');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('sharing_until');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('courier_id');
            $table->dropColumn('on_the_way_at');
        });
    }
};
