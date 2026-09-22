<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gift wrapping: the whole box goes into patterned paper tied with a satin
 * ribbon or jute twine. Each wrap is its own paper and ribbon; the customer
 * sees the wrapped box on the mockups before choosing it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wrappings')) {
            Schema::create('wrappings', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('pattern');                              // the paper, flat, as printed
                $table->decimal('price', 8, 2)->default(0);
                $table->string('ribbon', 10)->default('satin');         // satin | twine | none
                $table->string('ribbon_color', 7)->default('#F3D3B4');
                $table->decimal('pattern_scale', 4, 2)->default(0.5);   // one tile's width, as a share of the box
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'wrapping_id')) {
                $table->foreignId('wrapping_id')->nullable()->after('chocolate_cost')->constrained()->nullOnDelete();
                $table->string('wrapping_name')->nullable()->after('wrapping_id');
                $table->decimal('wrapping_price', 8, 2)->nullable()->after('wrapping_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('wrapping_id');
            $table->dropColumn(['wrapping_name', 'wrapping_price']);
        });
        Schema::dropIfExists('wrappings');
    }
};
