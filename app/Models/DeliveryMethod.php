<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A way an order reaches the customer, priced by the owner in the admin.
 * The type decides what checkout asks for:
 *   door   the address (Baku only) and a phone number
 *   post   the recipient's name and surname, a phone number and the
 *          post office's index
 *   metro  the metro station and a phone number
 */
class DeliveryMethod extends Model
{
    public const DOOR = 'door';

    public const POST = 'post';

    public const METRO = 'metro';

    public const TYPES = [
        self::DOOR => 'Qapıya (yalnız Bakı)',
        self::POST => 'Poçt',
        self::METRO => 'Metro',
    ];

    /** Baku's metro stations, as offered until the owner edits the list. */
    public const BAKU_METRO = [
        'İçərişəhər', 'Sahil', '28 May', 'Cəfər Cabbarlı', 'Gənclik', 'Nəriman Nərimanov', 'Bakmil', 'Ulduz', 'Koroğlu',
        'Qara Qarayev', 'Neftçilər', 'Xalqlar Dostluğu', 'Əhmədli', 'Həzi Aslanov', 'Xətai', 'Nizami',
        'Elmlər Akademiyası', 'İnşaatçılar', '20 Yanvar', 'Memar Əcəmi', 'Nəsimi', 'Azadlıq prospekti', 'Dərnəgül',
        'Avtovağzal', '8 Noyabr', 'Xocəsən',
    ];

    protected $fillable = ['type', 'name', 'description', 'price', 'is_active', 'sort_order', 'options'];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'is_active' => 'boolean',
            'options' => 'array',
        ];
    }

    public function scopeShown(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    /** The stations a metro delivery can go to. */
    public function stations(): array
    {
        return array_values(array_filter(array_map('trim', (array) ($this->options['stations'] ?? self::BAKU_METRO))));
    }

    /** A post-office index as written on letters: AZ and four digits. */
    public static function normalizeIndex(string $index): ?string
    {
        return preg_match('/^\s*(?:AZ)?\s*-?\s*(\d{4})\s*$/i', $index, $m) ? 'AZ' . $m[1] : null;
    }
}
