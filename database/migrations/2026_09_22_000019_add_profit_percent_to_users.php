<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each staff member's share of the profit now belongs to their account, set
 * by the admin together with the role, so a manager can see their own
 * balance. The shares kept so far in the settings, by name, are handed to the
 * accounts with those names.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'profit_percent')) {
            Schema::table('users', function (Blueprint $table) {
                $table->decimal('profit_percent', 5, 2)->default(0)->after('role');
            });
        }

        $shares = json_decode((string) DB::table('settings')->where('key', 'profit_shares')->value('value'), true);
        if (! is_array($shares) || DB::table('users')->where('profit_percent', '>', 0)->exists()) {
            return;
        }

        $users = DB::table('users')->whereIn('role', ['admin', 'manager'])->orWhere('is_admin', true)->get(['id', 'name']);
        foreach ($shares as $share) {
            $name = mb_strtolower(trim((string) ($share['name'] ?? '')));
            if ($name === '') {
                continue;
            }
            // "Vüqar" matches the account "Vüqar" or "Vüqar Məmmədov".
            $user = $users->first(fn ($u) => mb_strtolower(trim($u->name)) === $name)
                ?? $users->first(fn ($u) => str_starts_with(mb_strtolower(trim($u->name)) . ' ', $name . ' '));
            if ($user) {
                DB::table('users')->where('id', $user->id)->update(['profit_percent' => round((float) $share['percent'], 2)]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('profit_percent');
        });
    }
};
