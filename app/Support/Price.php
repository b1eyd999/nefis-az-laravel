<?php

namespace App\Support;

/**
 * Prices shown on the site: whole manats stay whole ("4 ₼"), anything with
 * qəpik shows two decimals ("7.90 ₼").
 */
class Price
{
    public static function format(float|int|string|null $amount): string
    {
        $amount = round((float) $amount, 2);

        return (fmod($amount, 1.0) == 0.0 ? number_format($amount, 0, '.', ' ') : number_format($amount, 2, '.', ' ')) . ' ₼';
    }
}
