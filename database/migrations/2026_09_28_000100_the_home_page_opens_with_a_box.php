<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A banner for the home page.
 *
 * The page has been opening straight on the catalogue because there was no
 * slide at all: a visitor met a grid of designs without being told what the
 * shop actually sells. This puts one slide in front of it - the product
 * photographed in the shop's own studio scene, the three things a buyer asks
 * first (price, how many designs, delivery) and two buttons.
 *
 * It only ever adds itself when the owner has no slide of his own, and it is
 * his to edit or switch off afterwards in "Ana sehife slaydlari" - running the
 * migration again will not bring it back or overwrite his words.
 */
return new class extends Migration
{
    private const IMAGE = 'hero/hero-sekilli-qutu.webp';

    public function up(): void
    {
        if (DB::table('hero_slides')->exists()) {
            return;
        }

        DB::table('hero_slides')->insert([
            'eyebrow' => 'Fərdi hədiyyə',
            'title' => "Şəklinizlə\nşokolad qutusu",
            'text' => 'Dizaynı seçin, şəklinizi yükləyin, sözünüzü yazın — qalanını biz edirik. '
                . 'Sifariş 2 gün ərzində hazırlanır.',
            'button1_label' => 'Dizaynlara bax',
            'button1_url' => '/dizaynlar',
            'button2_label' => 'Necə işləyir',
            'button2_url' => '#how',
            'badges' => json_encode(['4.90 ₼-dən', '26 dizayn', 'Bakı üzrə çatdırılma'], JSON_UNESCAPED_UNICODE),
            'image' => self::IMAGE,
            'image_fit' => 'cover',
            'ribbon' => null,
            'is_active' => true,
            'sort_order' => 1,
            'i18n' => json_encode([
                'ru' => [
                    'eyebrow' => 'Персональный подарок',
                    'title' => "Шоколадная коробка\nс вашим фото",
                    'text' => 'Выберите дизайн, загрузите фото, напишите свои слова — остальное сделаем мы. '
                        . 'Заказ готовится 2 дня.',
                    'button1_label' => 'Смотреть дизайны',
                    'button2_label' => 'Как это работает',
                    'badges' => ['от 4.90 ₼', '26 дизайнов', 'доставка по Баку'],
                ],
                'en' => [
                    'eyebrow' => 'A gift of your own',
                    'title' => "A chocolate box\nwith your photo",
                    'text' => 'Pick a design, upload your photo, write your own words - we do the rest. '
                        . 'Orders are ready in 2 days.',
                    'button1_label' => 'See the designs',
                    'button2_label' => 'How it works',
                    'badges' => ['from 4.90 ₼', '26 designs', 'delivery in Baku'],
                ],
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Only the slide this migration made.
        DB::table('hero_slides')->where('image', self::IMAGE)->delete();
    }
};
