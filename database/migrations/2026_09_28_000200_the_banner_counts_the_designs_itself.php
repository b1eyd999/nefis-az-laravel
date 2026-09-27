<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The banner said "26 dizayn" because that is what the catalogue held the day
 * it was written; a design was added the same week and the front door was
 * already out of date. The badge now carries "{dizayn}" and the page fills in
 * how many designs are actually on sale.
 *
 * Only the wording this project put there is replaced - if the owner has since
 * written his own badges, they stay as he wrote them.
 */
return new class extends Migration
{
    private const WAS = [
        'az' => ['4.90 ₼-dən', '26 dizayn', 'Bakı üzrə çatdırılma'],
        'ru' => ['от 4.90 ₼', '26 дизайнов', 'доставка по Баку'],
        'en' => ['from 4.90 ₼', '26 designs', 'delivery in Baku'],
    ];
    private const NOW = [
        'az' => ['4.90 ₼-dən', '{dizayn} dizayn', 'Bakı üzrə çatdırılma'],
        'ru' => ['от 4.90 ₼', '{dizayn} дизайнов', 'доставка по Баку'],
        'en' => ['from 4.90 ₼', '{dizayn} designs', 'delivery in Baku'],
    ];

    public function up(): void
    {
        $this->swap(self::WAS, self::NOW);
    }

    public function down(): void
    {
        $this->swap(self::NOW, self::WAS);
    }

    private function swap(array $from, array $to): void
    {
        foreach (DB::table('hero_slides')->get() as $slide) {
            $badges = json_decode((string) $slide->badges, true) ?: [];
            $i18n = json_decode((string) $slide->i18n, true) ?: [];
            $changed = false;

            if ($badges === $from['az']) {
                $badges = $to['az'];
                $changed = true;
            }

            foreach (['ru', 'en'] as $locale) {
                if (($i18n[$locale]['badges'] ?? null) === $from[$locale]) {
                    $i18n[$locale]['badges'] = $to[$locale];
                    $changed = true;
                }
            }

            if ($changed) {
                DB::table('hero_slides')->where('id', $slide->id)->update([
                    'badges' => json_encode($badges, JSON_UNESCAPED_UNICODE),
                    'i18n' => json_encode($i18n, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
            }
        }
    }
};
