<?php

use App\Models\DeliveryMethod;
use Illuminate\Database\Migrations\Migration;

/**
 * The three ways a box reaches its owner, said in Russian and English too.
 *
 * The pages were never at fault: /nece-isleyir and the checkout both ask the
 * model for a translation. The `i18n` column was simply empty, and the helper
 * falls back to the Azerbaijani column when it finds nothing — so a Russian
 * visitor read «Qapıya çatdırılma — 5 ₼, Yalnız Bakı daxilində» on the very
 * page that explains how to buy, with the heading above it translated.
 *
 * Matched on `type`, not on the name: the owner may rename a method in the
 * admin, but door is door. Nothing already written is touched — if he has
 * since typed his own wording for a language, that wording stays.
 */
return new class extends Migration
{
    /** @var array<string, array<string, array{name: string, description: string}>> */
    private const WORDS = [
        DeliveryMethod::DOOR => [
            'ru' => [
                'name' => 'Доставка до двери',
                'description' => 'Только по Баку',
            ],
            'en' => [
                'name' => 'Delivery to your door',
                'description' => 'Within Baku only',
            ],
        ],
        DeliveryMethod::POST => [
            'ru' => [
                'name' => 'Доставка почтой',
                'description' => 'В любое почтовое отделение Азербайджана',
            ],
            'en' => [
                'name' => 'Delivery by post',
                'description' => 'To any post office in Azerbaijan',
            ],
        ],
        DeliveryMethod::METRO => [
            'ru' => [
                'name' => 'Доставка к метро',
                'description' => 'Выдача на выбранной вами станции метро в Баку',
            ],
            'en' => [
                'name' => 'Delivery to a metro station',
                'description' => 'Handed over at the Baku metro station you choose',
            ],
        ],
    ];

    public function up(): void
    {
        foreach (DeliveryMethod::all() as $method) {
            $words = self::WORDS[$method->type] ?? null;
            if ($words === null) {
                continue;
            }

            $i18n = (array) ($method->i18n ?? []);
            $touched = false;

            foreach ($words as $locale => $fields) {
                foreach ($fields as $field => $text) {
                    // Only where nothing has been written yet.
                    if (blank(data_get($i18n, $locale . '.' . $field))) {
                        data_set($i18n, $locale . '.' . $field, $text);
                        $touched = true;
                    }
                }
            }

            if ($touched) {
                $method->forceFill(['i18n' => $i18n])->saveQuietly();
            }
        }
    }

    /**
     * Takes back only the exact sentences written above. Anything the owner
     * typed himself is left where it is.
     */
    public function down(): void
    {
        foreach (DeliveryMethod::all() as $method) {
            $words = self::WORDS[$method->type] ?? null;
            if ($words === null) {
                continue;
            }

            $i18n = (array) ($method->i18n ?? []);
            foreach ($words as $locale => $fields) {
                foreach ($fields as $field => $text) {
                    if (data_get($i18n, $locale . '.' . $field) === $text) {
                        unset($i18n[$locale][$field]);
                    }
                }
                if (empty($i18n[$locale])) {
                    unset($i18n[$locale]);
                }
            }

            $method->forceFill(['i18n' => $i18n ?: null])->saveQuietly();
        }
    }
};
