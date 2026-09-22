<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\HtmlString;

/**
 * What search engines read besides the page itself: structured data
 * (schema.org JSON-LD) and the ownership codes of Google, Yandex and Bing.
 */
class Seo
{
    public const INSTAGRAM = 'https://www.instagram.com/nefis.az/';

    /** Setting key => the meta tag name each search engine checks. */
    public const VERIFICATION = [
        Setting::SEO_GOOGLE => 'google-site-verification',
        Setting::SEO_YANDEX => 'yandex-verification',
        Setting::SEO_BING => 'msvalidate.01',
    ];

    /**
     * The one address a page is named by. The site answers on www.nefis.az
     * too (the server redirects it), so the "www." never belongs in a
     * canonical link, a sitemap or a link preview.
     */
    public static function canonical(?string $url = null): string
    {
        return preg_replace('#^(https?://)www\.#i', '$1', $url ?? url()->current());
    }

    public static function jsonLd(array $data): HtmlString
    {
        $json = json_encode(
            ['@context' => 'https://schema.org'] + $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
        );

        return new HtmlString('<script type="application/ld+json">' . $json . '</script>');
    }

    /** The shop itself, with the site, for the home page. */
    public static function organization(): array
    {
        $home = url('/');

        return ['@graph' => [
            [
                '@type' => 'OnlineStore',
                '@id' => $home . '#store',
                'name' => 'Nefis Şokolad Evi',
                'alternateName' => 'Nefis',
                'url' => $home,
                'logo' => asset('images/logo.png'),
                'image' => asset('images/og-nefis.jpg'),
                'description' => 'Öz şəkliniz və sözlərinizlə fərdi şokolad qutuları — ad günü, sevgiliyə, 8 Mart və hər münasibətə hədiyyə.',
                'areaServed' => ['@type' => 'Country', 'name' => 'Azərbaycan'],
                'address' => ['@type' => 'PostalAddress', 'addressLocality' => 'Bakı', 'addressCountry' => 'AZ'],
                'currenciesAccepted' => 'AZN',
                'sameAs' => [self::INSTAGRAM],
            ],
            [
                '@type' => 'WebSite',
                '@id' => $home . '#website',
                'url' => $home,
                'name' => 'Nefis',
                'inLanguage' => 'az',
                'publisher' => ['@id' => $home . '#store'],
            ],
        ]];
    }

    /** @param array<int, array{0: string, 1: string}> $trail name and URL of each step */
    public static function breadcrumbs(array $trail): array
    {
        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn (array $step, int $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $step[0],
                'item' => $step[1],
            ], $trail, array_keys($trail)),
        ];
    }

    /** @param array<int, array{q: string, a: string}> $questions */
    public static function faq(array $questions): array
    {
        return [
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn (array $f) => [
                '@type' => 'Question',
                'name' => $f['q'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
            ], array_values($questions)),
        ];
    }

    /** The meta tags that prove the site is the owner's in Search Console, Yandex Webmaster and Bing. */
    public static function verificationTags(): HtmlString
    {
        $tags = '';
        foreach (self::VERIFICATION as $key => $name) {
            $code = Setting::get($key);
            if (filled($code)) {
                $tags .= '<meta name="' . $name . '" content="' . e($code) . '">' . "\n";
            }
        }

        return new HtmlString($tags);
    }

    /**
     * The code alone, whether the owner pasted just it or the whole tag
     * (<meta name="google-site-verification" content="abc…" />).
     */
    public static function cleanCode(?string $value): string
    {
        $value = trim((string) $value);
        if (preg_match('/content\s*=\s*["\']([^"\']+)["\']/i', $value, $m)) {
            $value = $m[1];
        }

        return preg_replace('/[^A-Za-z0-9_\-=.]/', '', $value);
    }
}
