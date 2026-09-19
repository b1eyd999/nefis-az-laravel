<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Users get a role instead of a yes/no admin flag: customer, manager (the
 * orders only) or admin (everything). `is_admin` stays, kept in step with
 * the role, because the editors and older code check it.
 *
 * Rerun-safe: production is MySQL, which keeps DDL that ran before a failure.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'role')) {
            Schema::table('users', fn (Blueprint $t) => $t->string('role', 20)->default('customer')->after('email'));
        }
        DB::table('users')->where('is_admin', true)->update(['role' => 'admin']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'role')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('role'));
        }
    }
};
