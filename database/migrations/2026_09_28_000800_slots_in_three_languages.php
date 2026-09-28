<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The names a design gives its photo windows and text fields ("Hədiyyə
 * veriləcək şəxsin adı") are what a customer is asked for on the ordering
 * form — and on a Spotify-style box they are the instructions that decide
 * what gets printed. They had nowhere to be written in another language.
 * Same shape as everywhere else: {"ru": {"label": "…"}, "en": {…}}.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['photo_slots', 'text_slots'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->json('i18n')->nullable());
        }
    }

    public function down(): void
    {
        foreach (['photo_slots', 'text_slots'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('i18n'));
        }
    }
};
