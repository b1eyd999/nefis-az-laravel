<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Support\Locale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The two faults the 2026-11-03 Search Console read turned up.
 *
 * Everything else in that report is either already mended and waiting for a
 * re-crawl (63 of the 75 «404» answer 301 today) or is Google describing the
 * end state rather than a fault. These two were real.
 */
class TheSearchConsoleSweep20261103Test extends TestCase
{
    use RefreshDatabase;

    private function chainBox(array $i18n): Product
    {
        return Product::create([
            'name' => 'Zəncirli Box',
            'slug' => 'zencirli-box',
            'is_active' => true,
            'price' => 10,
            'i18n' => $i18n,
        ]);
    }

    private function mend(): void
    {
        (require database_path(
            'migrations/2026_11_03_090000_the_chain_box_was_still_called_the_starry_one_in_two_languages.php'
        ))->up();
    }

    /**
     * A design renamed in Azerbaijani kept the old name in the other two.
     *
     * Its descriptions say «barbed wire» in all three languages while the
     * Russian and English names said «starry box» — the name of a different
     * design, long withdrawn. The stale names go; the descriptions stay.
     */
    public function test_the_stale_translated_names_are_taken_off(): void
    {
        $box = $this->chainBox([
            'ru' => ['name' => 'Звездная коробка', 'description' => 'Ваше фото в рамке из колючей проволоки.'],
            'en' => ['name' => 'Starry Box', 'description' => 'Your photo in a barbed wire frame.'],
        ]);

        $this->mend();
        $box->refresh();

        $this->assertNull(data_get($box->i18n, 'ru.name'));
        $this->assertNull(data_get($box->i18n, 'en.name'));
        $this->assertSame('Ваше фото в рамке из колючей проволоки.', data_get($box->i18n, 'ru.description'));
        $this->assertSame('Your photo in a barbed wire frame.', data_get($box->i18n, 'en.description'));
    }

    /** With the name gone, every language shows the owner's own. */
    public function test_all_three_languages_fall_back_to_the_azerbaijani_name(): void
    {
        $box = $this->chainBox([
            'ru' => ['name' => 'Звездная коробка'],
            'en' => ['name' => 'Starry Box'],
        ]);

        $this->mend();
        $box->refresh();

        foreach (Locale::all() as $locale) {
            app()->setLocale($locale);
            $this->assertSame('Zəncirli Box', $box->tr('name'), 'in ' . $locale);
        }
    }

    /** A name the owner has written himself since is not touched. */
    public function test_a_name_the_owner_wrote_is_left_alone(): void
    {
        $box = $this->chainBox(['ru' => ['name' => 'Колючая проволока']]);

        $this->mend();

        $this->assertSame('Колючая проволока', data_get($box->refresh()->i18n, 'ru.name'));
    }

    /** Said twice — a second deploy — it changes nothing more. */
    public function test_it_can_be_run_again(): void
    {
        $box = $this->chainBox(['ru' => ['name' => 'Звездная коробка'], 'en' => ['name' => 'Starry Box']]);

        $this->mend();
        $after = $box->refresh()->i18n;
        $this->mend();

        $this->assertSame($after, $box->refresh()->i18n);
    }

    /**
     * The map's own data routes are kept out of the crawl.
     *
     * `/lokasiya/axtar` and `/lokasiya/kafel/…` are what the box editor asks
     * for streets and tiles — the same kind of thing as `/xerite/` and
     * `/sohbet/`, which were listed, while this one was missed. Google was
     * crawling `/lokasiya/axtar?q=` and filing a 422 of JSON under «page
     * with redirect»; the tile route has one address per tile and no end.
     */
    public function test_the_map_data_routes_are_disallowed(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));

        $this->assertStringContainsString('Disallow: /lokasiya/', $robots);
        $this->assertStringContainsString('Disallow: /xerite/', $robots);
    }

    /**
     * And the rule stops at the slash.
     *
     * The location designs live at `/products/lokasiya/customize`, which is
     * the shop's own catalogue and must stay crawlable — a `Disallow:
     * /lokasiya` without the trailing slash would not have touched it
     * either, but the next person to tidy this file should see it written
     * down rather than work it out.
     */
    public function test_the_location_designs_are_still_crawlable(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));

        foreach (explode("\n", $robots) as $line) {
            $rule = trim(str_ireplace('Disallow:', '', $line));

            if (! str_starts_with(trim($line), 'Disallow:') || $rule === '') {
                continue;
            }

            $this->assertFalse(str_starts_with('/products/lokasiya/customize', $rule),
                $rule . ' would keep the location designs out of the index');
        }
    }
}
