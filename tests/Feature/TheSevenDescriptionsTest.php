<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The descriptions written for the seven designs that had none.
 *
 * Two things are worth pinning down. The first is that a deploy must never
 * walk over a description the owner has since written himself — this
 * migration runs on every deploy, and the admin is where he edits these.
 * The second is the shape of the text itself: three languages, each really
 * written rather than the Azerbaijani copied across, and none of them
 * promising anything the shop has not said.
 */
class TheSevenDescriptionsTest extends TestCase
{
    use RefreshDatabase;

    private const FILE = 'data/design-descriptions-2026-10.php';

    private const MIGRATION = 'migrations/2026_10_30_090000_the_seven_designs_without_a_description_get_one.php';

    /** @return array<string, array<string, string>> */
    private function written(): array
    {
        return require database_path(self::FILE);
    }

    private function fill(): void
    {
        (require database_path(self::MIGRATION))->up();
    }

    private function design(string $slug, array $extra = []): Product
    {
        return Product::create($extra + [
            'name' => 'Qutu', 'slug' => $slug, 'category' => 'qutular',
            'is_active' => true, 'price' => 10,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        ProductCategory::create(['slug' => 'qutular', 'name' => 'Qutular', 'is_active' => true, 'sort_order' => 0]);
    }

    public function test_seven_designs_are_named_and_each_speaks_three_languages(): void
    {
        $written = $this->written();

        $this->assertCount(7, $written);

        foreach ($written as $slug => $said) {
            $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $slug);
            $this->assertSame(['az', 'ru', 'en'], array_keys($said), $slug);

            foreach ($said as $locale => $text) {
                $this->assertGreaterThan(80, mb_strlen($text), $slug.'/'.$locale.' is too short to tell anyone anything');
                $this->assertLessThan(320, mb_strlen($text), $slug.'/'.$locale.' is longer than a catalogue card');
            }

            // Three written texts, not one copied into three places.
            $this->assertNotSame($said['az'], $said['ru'], $slug);
            $this->assertNotSame($said['az'], $said['en'], $slug);
            $this->assertNotSame($said['ru'], $said['en'], $slug);
        }
    }

    public function test_an_empty_description_is_filled_in_all_three_languages(): void
    {
        $design = $this->design('bir-urek');
        $said = $this->written()['bir-urek'];

        $this->fill();
        $design->refresh();

        $this->assertSame($said['az'], $design->description);
        $this->assertSame($said['ru'], $design->translationsFor('ru')['description']);
        $this->assertSame($said['en'], $design->translationsFor('en')['description']);
    }

    /** The deploy runs this every time; what he wrote himself stays his. */
    public function test_a_description_the_owner_wrote_is_left_alone(): void
    {
        $design = $this->design('hekaye', ['description' => 'Öz yazdığım mətn.']);

        $this->fill();
        $design->refresh();

        $this->assertSame('Öz yazdığım mətn.', $design->description);
        $this->assertArrayNotHasKey('description', $design->translationsFor('ru'),
            'an untouched design must not get a translation of a text it does not have');
    }

    /**
     * setTranslations replaces everything written in a language, so the
     * description has to be merged into what is already there — or a design
     * with a Russian name loses it the moment it gets a Russian description.
     */
    public function test_a_translation_of_another_field_survives(): void
    {
        $design = $this->design('kinder-yeni-il');
        $design->setTranslations('ru', ['name' => 'Киндер Новый год']);
        $design->save();

        $this->fill();
        $design->refresh();

        $this->assertSame('Киндер Новый год', $design->translationsFor('ru')['name']);
        $this->assertSame($this->written()['kinder-yeni-il']['ru'], $design->translationsFor('ru')['description']);
    }

    /** Running it twice changes nothing, and a missing design is not an error. */
    public function test_it_survives_a_second_run_and_a_catalogue_that_has_none_of_them(): void
    {
        $this->fill();                       // not one of the seven exists yet
        $this->assertSame(0, Product::count());

        $design = $this->design('kinder-yeni-il-vol-2');
        $this->fill();
        $this->fill();
        $design->refresh();

        $this->assertSame($this->written()['kinder-yeni-il-vol-2']['az'], $design->description);
    }

    /** What the shop has not said, a description must not say either. */
    public function test_nothing_is_promised_that_the_shop_has_not_said(): void
    {
        $banned = [
            'gün ərzində' => 'no delivery time is promised here',
            'pulsuz' => 'nothing is free on a catalogue card',
            'бесплатно' => 'the same, in Russian',
            'free delivery' => 'the same, in English',
            'ən yaxşı hədiyyə' => 'a superlative the shop does not claim',
            'endirim' => 'a discount that is not ours to announce',
            'скидк' => 'the same, in Russian',
            'discount' => 'the same, in English',
            '8 ədəd' => 'the number of bars is the customer’s own choice',
            '8 bars' => 'the same, in English',
        ];

        foreach ($this->written() as $slug => $said) {
            $all = mb_strtolower(implode(' ', $said));

            foreach ($banned as $phrase => $why) {
                $this->assertStringNotContainsString(mb_strtolower($phrase), $all, $slug.': '.$why);
            }
        }
    }

    /**
     * A description says what the customiser really offers.
     *
     * «Hekayə» has two photo windows and no text field; the «Alyonka» pair
     * take a photograph and nothing else. Saying otherwise would send a
     * customer looking for a box to type his name on.
     */
    public function test_a_design_with_no_text_field_does_not_offer_one(): void
    {
        $noText = ['alyonka-yn', 'alyonka-yn-2', 'bir-urek', 'hekaye'];
        $written = $this->written();

        foreach ($noText as $slug) {
            $this->assertStringNotContainsString('bir sətir', $written[$slug]['az'],
                $slug.' has no text field, so it must not offer a line to write on');
            $this->assertStringNotContainsString('строка', mb_strtolower($written[$slug]['ru']), $slug);
        }

        // And the three that do have one say so.
        foreach (['kinder-yeni-il', 'kinder-yeni-il-vol-2', 'kinder-yeni-il-vol-3'] as $slug) {
            $this->assertStringContainsString('bir sətir', $written[$slug]['az'], $slug.' has a line and should say so');
        }
    }
}
