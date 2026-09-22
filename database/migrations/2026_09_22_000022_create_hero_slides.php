<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The home page's opening banner, now slides the owner writes himself. The
 * wording the page had becomes the first slide, so nothing changes until he
 * edits it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hero_slides')) {
            return;
        }

        Schema::create('hero_slides', function (Blueprint $table) {
            $table->id();
            $table->string('eyebrow')->nullable();
            $table->text('title');
            $table->text('text')->nullable();
            $table->string('button1_label')->nullable();
            $table->string('button1_url')->nullable();
            $table->string('button2_label')->nullable();
            $table->string('button2_url')->nullable();
            $table->json('badges')->nullable();
            $table->string('image')->nullable();
            $table->string('image_fit', 10)->default('cover');     // cover | contain
            $table->string('ribbon')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        DB::table('hero_slides')->insert([
            'eyebrow' => 'Nefis Şokolad Evi',
            'title' => "Hər Hədiyyə\nBir Xatirəyə Dönsün.",
            'text' => 'Öz şəklinizi, öz sözünüzü seçin — biz onu sevdiklərinizə hədiyyə edəcəyiniz ən nəfis şokolad qutusuna çeviririk.',
            'button1_label' => 'İndi Sifariş Ver',
            'button1_url' => '#collections',
            'button2_label' => 'Dizaynlara Bax',
            'button2_url' => '/dizaynlar',
            'badges' => json_encode(['Premium Şokolad', '100% Fərdi Dizayn', 'Sürətli Çatdırılma'], JSON_UNESCAPED_UNICODE),
            'ribbon' => 'Fərdi Hədiyyə',
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('hero_slides');
    }
};
