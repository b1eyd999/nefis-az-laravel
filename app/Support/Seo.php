<?php

namespace App\Support;

use App\Models\DeliveryMethod;
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
        $url = preg_replace('#^(https?://)www\.#i', '$1', $url ?? url()->current());

        /* And https, because the shop is only ever served over it. The
           address bar is redirected by the server, but a page naming itself
           over plain http tells a crawler the opposite of what the redirect
           does. Not in a local run, where there is no certificate. */
        if (app()->environment('production')) {
            $url = preg_replace('#^http://#i', 'https://', $url);
        }

        return $url;
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
    /** The shop's own name, said the same way wherever it is named. */
    public const SHOP = 'Nefis Şokolad Evi';

    public static function organization(): array
    {
        $home = url('/');
        $phone = Contact::has() ? Contact::dial() : null;

        return ['@graph' => [
            array_filter([
                '@type' => 'OnlineStore',
                '@id' => $home . '#store',
                'name' => self::SHOP,
                'alternateName' => 'Nefis',
                'url' => $home,
                'logo' => asset('images/logo.png'),
                'image' => asset('images/og-nefis.jpg'),
                'description' => 'Öz şəkliniz və sözlərinizlə fərdi şokolad qutuları, ad günü, sevgiliyə, 8 Mart və hər münasibətə hədiyyə.',
                'areaServed' => ['@type' => 'Country', 'name' => 'Azərbaycan'],
                'address' => ['@type' => 'PostalAddress', 'addressLocality' => 'Bakı', 'addressCountry' => 'AZ'],
                'currenciesAccepted' => 'AZN',
                'telephone' => $phone,
                'contactPoint' => $phone ? [
                    '@type' => 'ContactPoint',
                    'contactType' => 'customer service',
                    'telephone' => $phone,
                    'availableLanguage' => \App\Support\Locale::published(),
                ] : null,
                'sameAs' => [self::INSTAGRAM],
            ]),
            [
                '@type' => 'WebSite',
                '@id' => $home . '#website',
                'url' => $home,
                'name' => 'Nefis',
                // One site, read in three languages; the block is shared by all of them.
                'inLanguage' => \App\Support\Locale::published(),
                'publisher' => ['@id' => $home . '#store'],
            ],
        ]];
    }

    /** @param array<int, array{0: string, 1: string}> $trail name and URL of each step */
    /**
     * What Google asks of every offer besides the price: how the thing ships
     * and whether it can come back. Without these two the Search Console
     * writes to the owner about "missing fields" on his own products.
     *
     * @return array<string, mixed>
     */
    public static function shipping(): array
    {
        /* The region declared here is the whole country, so the rate beside
           it has to be one that holds across the whole country. Taking the
           minimum over every method made the markup say delivery is free to
           all of Azerbaijan — true only of handing a box over at a Baku
           metro station, and not of anything that actually travels. The
           cheapest method that reaches the rest of the country is what is
           quoted; metro's own price is still shown on the page itself. */
        $reaching = DeliveryMethod::shown()
            ->whereIn('type', [DeliveryMethod::DOOR, DeliveryMethod::POST])
            ->min('price');
        $cheapest = (float) ($reaching ?? DeliveryMethod::shown()->min('price') ?? 0);
        $lead = max(1, DeliveryTime::leadDays());

        return [
            '@type' => 'OfferShippingDetails',
            'shippingRate' => [
                '@type' => 'MonetaryAmount',
                'value' => number_format($cheapest, 2, '.', ''),
                'currency' => 'AZN',
            ],
            'shippingDestination' => [
                '@type' => 'DefinedRegion',
                'addressCountry' => 'AZ',
            ],
            'deliveryTime' => [
                '@type' => 'ShippingDeliveryTime',
                // The shop makes the box by hand first, then hands it over.
                'handlingTime' => [
                    '@type' => 'QuantitativeValue',
                    'minValue' => 1,
                    'maxValue' => $lead,
                    'unitCode' => 'DAY',
                ],
                'transitTime' => [
                    '@type' => 'QuantitativeValue',
                    'minValue' => 0,
                    'maxValue' => 3,
                    'unitCode' => 'DAY',
                ],
            ],
        ];
    }

    /**
     * Every box carries someone's own photo and words, so it cannot be sold
     * on to anyone else; what arrives damaged is replaced, which is not a
     * return in the vocabulary Google reads.
     *
     * @return array<string, mixed>
     */
    public static function returns(): array
    {
        return [
            '@type' => 'MerchantReturnPolicy',
            'applicableCountry' => 'AZ',
            'returnPolicyCategory' => 'https://schema.org/MerchantReturnNotPermitted',
            /* The category alone says «no returns», which is true of a
               finished box with someone's face on it and untrue of the rest:
               an order not yet started is cancelled and refunded, and so is
               anything the shop got wrong. The link is where those terms are
               written out, so the short answer is not the only one a reader
               ever sees. */
            'merchantReturnLink' => route('legal.refund'),
        ];
    }

    /**
     * A snippet Google can show whole: cut on a space, never through a word,
     * and ending in a full stop rather than three dots mid-syllable.
     */
    public static function snippet(?string $text, int $limit = 155): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', (string) $text));

        if ($text === '' || mb_strlen($text) <= $limit) {
            return $text;
        }

        $cut = mb_substr($text, 0, $limit);

        // A whole sentence when one fits — "…dosta və." reads like a fault —
        // and only then the last whole word.
        if (preg_match('/^(.*[.!?])(?=\s|$)/su', $cut, $m) && mb_strlen($m[1]) >= (int) ($limit * 0.45)) {
            return trim($m[1]);
        }

        $space = mb_strrpos($cut, ' ');

        return rtrim($space ? mb_substr($cut, 0, $space) : $cut, " ,.;:—-") . '.';
    }

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

    /**
     * How the shop stands, from its own reviews.
     *
     * Said about the shop itself rather than about a product, because that
     * is what the reviews are about: a customer rates the box he was sent,
     * the handover and the shop, not a line in a catalogue. Only approved
     * reviews are counted, so the figure is the one on the page.
     */
    public static function rating(float $average, int $count): array
    {
        return [
            '@type' => 'Organization',
            'name' => 'Nefis.az',
            'url' => url('/'),
            'aggregateRating' => [
                '@type' => 'AggregateRating',
                'ratingValue' => (string) $average,
                'reviewCount' => $count,
                'bestRating' => '5',
                'worstRating' => '1',
            ],
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
     * Google Analytics, when the owner has set a measurement id. Left out
     * while developing, so local visits never reach his numbers.
     */
    public static function analyticsTag(): HtmlString
    {
        $id = self::measurementId(Setting::get(Setting::SEO_ANALYTICS));

        if ($id === '' || app()->isLocal()) {
            return new HtmlString('');
        }

        /* Google is not fetched here. The queue is opened and the id put
           where the notice strip can find it; the tag itself is loaded by
           public/js/cookie-notice.js, and only once the visitor has said yes.
           Anything the shop measures in the meantime sits in dataLayer and
           either flushes on consent or is never sent at all — which is what
           a refusal is supposed to mean. */
        return new HtmlString(<<<HTML
        <script>
          window.dataLayer = window.dataLayer || [];
          window.gtag = window.gtag || function(){dataLayer.push(arguments);};
          window.NefisGA = '{$id}';
        </script>
        HTML);
    }

    /**
     * The measurement id alone, whether the owner pasted just it or the whole
     * snippet Google shows (G-… for Analytics 4, GT-… for a tag, UA-… old).
     */
    public static function measurementId(?string $value): string
    {
        return preg_match('/\b(G|GT|UA)-[A-Z0-9-]{4,20}\b/i', (string) $value, $m) ? strtoupper($m[0]) : '';
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
