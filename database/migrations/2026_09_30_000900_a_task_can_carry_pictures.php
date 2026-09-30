<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A picture with the work.
 *
 * Half of what has to be remembered is easier shown than written: the ribbon
 * to buy, the label on the box that came out wrong, the message a customer
 * sent. The photographs live with the task, in the shop's own folder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', fn (Blueprint $t) => $t->json('photos')->nullable()->after('body'));
    }

    public function down(): void
    {
        Schema::table('tasks', fn (Blueprint $t) => $t->dropColumn('photos'));
    }
};
