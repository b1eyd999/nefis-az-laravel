<?php

namespace App\Support;

use App\Models\Setting;

/**
 * The words on the live photo's page. They are the shop's own sentences, so
 * the owner rewrites them in the admin; what he leaves empty keeps the
 * wording the page was written with. A text he has not touched is still
 * shown in the customer's language, because the Azerbaijani sentence is the
 * key the translations are kept under.
 */
class LivePage
{
    /** The page as it reads until the owner changes it. */
    public const DEFAULTS = [
        'eyebrow' => 'Yeni · AR',
        'title' => 'Canlı şəkil',
        'lede' => 'Şəklinizi canlandırın: QR kodu oxudub telefonu şəklə tutanda, üstündə sizin videonuz oynayır, heç bir tətbiq yükləmədən.',
        'preview' => 'Şəkil və video seçin, burada necə canlanacağını görəcəksiniz',
        'caption' => 'Telefonda belə görünəcək: kamera şəkli tanıyır və video onun üzərinə düşür.',
        'photo_label' => 'Canlanacaq şəkil',
        'photo_button' => 'Şəkil seçin',
        'photo_hint' => 'Bu şəkil QR kodla birlikdə çap olunur. Aydın, detallı şəkillər kamera tərəfindən daha yaxşı tanınır.',
        'video_label' => 'Video',
        'video_hint' => 'Ən yaxşısı 10–30 saniyəlik video. Öz ölçüsündə (məs. 9:16), kəsilmədən oynayır.',
        'price_label' => 'Qiymət',
        'button' => 'Səbətə at',
        'step1' => 'Şəkli və videonu yükləyirsiniz, qalanını sistem özü hazırlayır.',
        'step2' => 'Şəkli QR kodla birlikdə çap edib sifarişinizlə göndəririk.',
        'step3' => 'Hədiyyəni alan QR kodu oxudur, telefonu şəklə tutur, video şəklin üstündə oynayır.',
    ];

    /** @return array<string, string> */
    public static function page(): array
    {
        $saved = json_decode((string) Setting::get(Setting::LIVE_PAGE), true);

        return array_merge(self::DEFAULTS, array_filter(is_array($saved) ? $saved : [], fn ($v) => is_string($v) && trim($v) !== ''));
    }

    /** One line of the page, in the language the customer is reading. */
    public static function text(string $key): string
    {
        return __(self::page()[$key] ?? '');
    }
}
