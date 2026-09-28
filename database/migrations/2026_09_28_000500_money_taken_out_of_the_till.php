<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Money the owner takes out of the till, and what for.
 *
 * Until now every manat that came in stayed in "Kassa" for ever, even after
 * it had been handed out or spent on something private. A withdrawal is not
 * a cost of the business - it does not touch the profit - it only says that
 * this much is no longer in the drawer, and why.
 *
 * Rerun-safe: production is MySQL, which keeps DDL that ran before a failure.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('withdrawals')) {
            Schema::create('withdrawals', function (Blueprint $table) {
                $table->id();
                $table->date('taken_on');
                $table->decimal('amount', 10, 2);
                $table->string('purpose', 120);            // what the money went for
                $table->string('note')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();   // who took it
                $table->timestamps();
                $table->index('taken_on');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};
