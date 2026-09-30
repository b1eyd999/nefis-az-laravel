<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The shop's own list of work, and the notes beside it.
 *
 * Until now everything that had to be done lived in somebody's head or in a
 * message to himself: order the ribbon, answer the company that wrote, print
 * the star map for #29 before Friday. This is the place for it — on the
 * phone, where the owner actually is when he remembers.
 *
 * One table holds both: a task has a state and may have a day it is due, a
 * note is simply written down and stays.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 10)->default('task');       // task | note
            $table->string('title', 160);
            $table->text('body')->nullable();
            $table->string('status', 10)->default('todo');     // todo | doing | done
            $table->dateTime('due_at')->nullable();
            // Whose it is; nobody's means anybody's.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('done_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['kind', 'status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
