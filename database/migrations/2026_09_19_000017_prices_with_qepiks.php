<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Box prices were whole manats in the database, so 4.90 ₼ was saved as 5.
 * They now keep their qəpiks, as the chocolate and delivery prices already do.
 */
return new class extends Migration
{
    private const COLUMNS = ['products' => 'price', 'order_items' => 'price'];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $column) {
            if (str_contains(strtolower(Schema::getColumnType($table, $column)), 'int')) {
                Schema::table($table, fn (Blueprint $t) => $t->decimal($column, 10, 2)->nullable()->change());
            }
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $table => $column) {
            Schema::table($table, fn (Blueprint $t) => $t->unsignedInteger($column)->nullable()->change());
        }
    }
};
