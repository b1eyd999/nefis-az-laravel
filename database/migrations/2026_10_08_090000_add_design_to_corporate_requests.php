<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a company's own finished artwork is kept.
 *
 * Until now the request carried a logo and we drew the box; a company with a
 * designer of its own sends the box already drawn, and that file has to
 * travel with the request rather than arrive separately by mail.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('corporate_requests', 'design')) {
            return;
        }

        Schema::table('corporate_requests', function (Blueprint $table) {
            $table->string('design')->nullable()->after('logo');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('corporate_requests', 'design')) {
            return;
        }

        Schema::table('corporate_requests', function (Blueprint $table) {
            $table->dropColumn('design');
        });
    }
};
