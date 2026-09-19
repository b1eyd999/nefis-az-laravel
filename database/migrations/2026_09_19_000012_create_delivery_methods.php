<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * How an order reaches the customer, picked at checkout: to the door (Baku
 * only), by post (name, phone, post-office index) or to a metro station.
 * The owner sets each one's price in the admin; an order keeps the method,
 * its price and the recipient's details as they were when ordered.
 *
 * Rerun-safe: production is MySQL, which keeps DDL that ran before a failure.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('delivery_methods')) {
            Schema::create('delivery_methods', function (Blueprint $table) {
                $table->id();
                $table->string('type', 20)->unique(); // door | post | metro
                $table->string('name');
                $table->string('description')->nullable();
                $table->decimal('price', 8, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->json('options')->nullable();  // metro: {"stations": [...]}
                $table->timestamps();
            });
        }

        $now = now();
        foreach ([
            ['door', 'Qapıya çatdırılma', 'Yalnız Bakı daxilində', 1, null],
            ['post', 'Poçt ilə çatdırılma', 'Azərbaycanın istənilən poçt şöbəsinə', 2, null],
            ['metro', 'Metroya çatdırılma', 'Seçdiyiniz Bakı metro stansiyasında təhvil', 3, json_encode(['stations' => \App\Models\DeliveryMethod::BAKU_METRO], JSON_UNESCAPED_UNICODE)],
        ] as [$type, $name, $description, $sort, $options]) {
            if (! DB::table('delivery_methods')->where('type', $type)->exists()) {
                DB::table('delivery_methods')->insert([
                    'type' => $type, 'name' => $name, 'description' => $description, 'price' => 0,
                    'is_active' => true, 'sort_order' => $sort, 'options' => $options, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        foreach ([
            'delivery_method_id' => fn (Blueprint $t) => $t->unsignedBigInteger('delivery_method_id')->nullable()->index(),
            'delivery_type' => fn (Blueprint $t) => $t->string('delivery_type', 20)->nullable(),
            'delivery_name' => fn (Blueprint $t) => $t->string('delivery_name')->nullable(),
            'delivery_price' => fn (Blueprint $t) => $t->decimal('delivery_price', 8, 2)->nullable(),
            'recipient_name' => fn (Blueprint $t) => $t->string('recipient_name')->nullable(),
            'postal_index' => fn (Blueprint $t) => $t->string('postal_index', 20)->nullable(),
            'metro_station' => fn (Blueprint $t) => $t->string('metro_station')->nullable(),
        ] as $column => $add) {
            if (! Schema::hasColumn('orders', $column)) {
                Schema::table('orders', $add);
            }
        }
    }

    public function down(): void
    {
        foreach (['delivery_method_id', 'delivery_type', 'delivery_name', 'delivery_price', 'recipient_name', 'postal_index', 'metro_station'] as $column) {
            if (Schema::hasColumn('orders', $column)) {
                Schema::table('orders', function (Blueprint $t) use ($column) {
                    if ($column === 'delivery_method_id') {
                        $t->dropIndex(['delivery_method_id']);
                    }
                    $t->dropColumn($column);
                });
            }
        }
        Schema::dropIfExists('delivery_methods');
    }
};
