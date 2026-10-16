<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The three pages a customer reads before handing over a card number.
 *
 * Terms, privacy and payment-and-refunds were the only pages on the shop
 * whose Russian and English versions printed Azerbaijani: ninety-two of
 * their strings had never been added to the two translation files, so about
 * three quarters of the prose on /ru and /en was the Azerbaijani original.
 *
 * It is not only discourteous. Three near-identical pages are what Google
 * calls duplicate content, and these are the nine URLs whose hreflang was
 * broken as well, so it had no way of being told they are one page in three
 * languages.
 *
 * These tests do not check the wording — they check that there IS a
 * translation, by refusing to let a page of Azerbaijani appear under /ru or
 * /en again.
 */
class TheLegalPagesSpeakThreeLanguagesTest extends TestCase
{
    use RefreshDatabase;

    /** Lines of real prose the page shows, chrome excluded. */
    private function prose(string $url): array
    {
        $html = (string) $this->get($url)->assertOk()->getContent();

        // Only the page's own article, so the shared header and footer —
        // which are translated already — cannot mask a missed string.
        preg_match_all('~<(h1|h2|h3|p|li)[^>]*>([^<]{15,})~u', $html, $found, PREG_SET_ORDER);

        return array_values(array_unique(array_map(
            fn (array $m) => trim(html_entity_decode($m[2])),
            $found
        )));
    }

    public static function pages(): array
    {
        return [
            'terms' => ['/qaydalar'],
            'privacy' => ['/mexfilik'],
            'refund' => ['/odenis-ve-qaytarma'],
        ];
    }

    /**
     * @dataProvider pages
     */
    public function test_the_russian_page_is_not_the_azerbaijani_one(string $path): void
    {
        $az = $this->prose($path);
        $ru = $this->prose('/ru' . $path);

        $this->assertNotSame([], $az, 'no prose found at all — the selector is wrong, not the page');

        $shared = array_intersect($az, $ru);

        $this->assertLessThanOrEqual(
            2,
            count($shared),
            "$path: " . count($shared) . ' of ' . count($az)
                . " lines are still Azerbaijani on /ru — first: " . (reset($shared) ?: '')
        );
    }

    /**
     * @dataProvider pages
     */
    public function test_the_english_page_is_not_the_azerbaijani_one(string $path): void
    {
        $az = $this->prose($path);
        $en = $this->prose('/en' . $path);

        $shared = array_intersect($az, $en);

        $this->assertLessThanOrEqual(
            2,
            count($shared),
            "$path: " . count($shared) . ' of ' . count($az)
                . " lines are still Azerbaijani on /en — first: " . (reset($shared) ?: '')
        );
    }

    public function test_the_numbers_the_shop_fills_in_itself_survive_translation(): void
    {
        /* Two of the translated strings carry a placeholder the shop
           replaces at render time — how many of one design may be ordered,
           and how many days a box takes. A translation that dropped the
           colon-word would print the word itself to a customer. */
        foreach (['', '/ru', '/en'] as $prefix) {
            $html = (string) $this->get($prefix . '/qaydalar')->assertOk()->getContent();

            $this->assertStringNotContainsString(':most', $html);
            $this->assertStringNotContainsString(':days', $html);
        }
    }

    /**
     * @dataProvider pages
     */
    public function test_each_language_sets_its_own_html_lang(string $path): void
    {
        foreach (['' => 'az', '/ru' => 'ru', '/en' => 'en'] as $prefix => $lang) {
            $this->get($prefix . $path)
                ->assertOk()
                ->assertSee('<html lang="' . $lang . '"', false);
        }
    }
}
