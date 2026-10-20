<?php

namespace Tests\Feature;

use App\Support\CorporatePage;
use App\Support\ImageStore;
use App\Support\Seo;
use Illuminate\Support\Facades\Storage;
use ReflectionMethod;
use Tests\TestCase;

/**
 * A walk over the live site found four things. Each of them is here.
 *
 * The heaviest was a gift wrap uploaded as a print master twelve thousand
 * pixels square: GD wants four bytes a pixel before it will look at a
 * picture, which is six hundred megabytes, and running out of memory is not
 * an exception on the way to a catch block — it is the end of the process.
 * So the small copies were never written, every design page went on serving
 * the three-megabyte original as a forty-pixel swatch, and the deploy said
 * it had succeeded.
 */
class TheFourThingsTheSiteCheckFoundTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private static function inside(string $name, array $args): mixed
    {
        $m = new ReflectionMethod(ImageStore::class, $name);
        $m->setAccessible(true);

        return $m->invokeArgs(null, $args);
    }

    /** A real JPEG of this size, written to the fake public disk. */
    private function jpeg(string $path, int $w, int $h): string
    {
        $im = imagecreatetruecolor($w, $h);
        imagefilledrectangle($im, 0, 0, $w, $h, imagecolorallocate($im, 70, 30, 110));
        imagefilledellipse($im, (int) ($w / 2), (int) ($h / 2), (int) ($w / 2), (int) ($h / 2),
            imagecolorallocate($im, 240, 200, 60));
        ob_start();
        imagejpeg($im, null, 85);
        Storage::disk('public')->put($path, (string) ob_get_clean());
        imagedestroy($im);

        return $path;
    }

    /**
     * A JPEG whose header claims to be enormous.
     *
     * The real thing cannot be made here — building it would cost the same
     * six hundred megabytes this guard exists to refuse — so a small one has
     * the two numbers in its SOF0 marker rewritten. getimagesize reads
     * exactly those bytes, which is what the guard reads too.
     */
    private function jpegClaiming(string $path, int $w, int $h): string
    {
        $this->jpeg($path, 8, 8);
        $disk = Storage::disk('public');
        $bytes = (string) $disk->get($path);

        $at = 2;
        while ($at < strlen($bytes) - 9) {
            if (ord($bytes[$at]) !== 0xFF) {
                $at++;

                continue;
            }
            $marker = ord($bytes[$at + 1]);
            // SOF0..SOF3, SOF5..SOF7, SOF9..: height then width, both 16-bit.
            if ($marker >= 0xC0 && $marker <= 0xCF && ! in_array($marker, [0xC4, 0xC8, 0xCC], true)) {
                $bytes = substr_replace($bytes, pack('n', $h), $at + 5, 2);
                $bytes = substr_replace($bytes, pack('n', $w), $at + 7, 2);
                $disk->put($path, $bytes);

                $read = getimagesize($disk->path($path));
                $this->assertSame([$w, $h], [$read[0], $read[1]], 'the header was not rewritten');

                return $path;
            }
            $at += 2 + unpack('n', substr($bytes, $at + 2, 2))[1];
        }

        $this->fail('no SOF marker in the JPEG this test wrote');
    }

    /** The picture that ate the deploy is refused before GD is asked. */
    public function test_a_master_too_big_for_gd_is_never_handed_to_it(): void
    {
        $huge = $this->jpegClaiming('wrappings/huge.jpg', 12320, 12320);

        $this->assertTrue(self::inside('tooBigForGd', [Storage::disk('public')->path($huge)]),
            'twelve thousand pixels square is 579 MB before the copy is even started');

        /* And the call survives, which is the whole point. Where Imagick is
           installed — the hosting and the runner both have it — it goes on
           and makes the copy GD could not. Where it is not, there is
           nothing further to try and the page keeps serving the master:
           heavy, but there, and the deploy is not killed on its way past. */
        $made = ImageStore::smaller($huge, 400, true);

        if (class_exists(\Imagick::class)) {
            $this->assertSame('wrappings/huge@400.webp', $made,
                'Imagick is here and should have made it');
            $this->assertLessThan(Storage::disk('public')->size($huge) * 4,
                strlen((string) Storage::disk('public')->get($made)));
        } else {
            $this->assertNull($made);
            $this->assertFalse(Storage::disk('public')->exists('wrappings/huge@400.webp'));
        }

        /* Take the guard out and this file does not fail — it kills the
           whole run, with the very words the hosting saw:

             Fatal error: Allowed memory size of 536870912 bytes exhausted
             in app/Support/ImageStore.php on line 286

           because GD allocates from the header before it decodes a single
           pixel. That is the proof the guard is needed and it is checked by
           this test existing at all; the lines below only add a civil
           message if someone moves the guard to after GD instead of
           deleting it. Both positions are made sure of as numbers first: in
           PHP a strpos that found nothing is false, and false is less than
           every positive number. */
        $src = file_get_contents(app_path('Support/ImageStore.php'));
        $guard = strpos($src, 'if (self::tooBigForGd(');
        $gd = strpos($src, 'imagecreatefromjpeg($disk->path($path))');
        $this->assertIsInt($guard, 'the guard is gone from smaller()');
        $this->assertIsInt($gd);
        $this->assertLessThan($gd, $guard, 'GD is asked before the size is looked at');
    }

    /** An ordinary picture is not refused, and is still made smaller. */
    public function test_an_ordinary_master_still_goes_the_quick_way(): void
    {
        $ok = $this->jpeg('wrappings/plain.jpg', 1400, 1000);

        $this->assertFalse(self::inside('tooBigForGd', [Storage::disk('public')->path($ok)]));

        $small = ImageStore::smaller($ok, 400, true);
        $this->assertSame('wrappings/plain@400.webp', $small);
        [$w, $h] = getimagesizefromstring((string) Storage::disk('public')->get($small));
        $this->assertSame(400, max($w, $h));
        $this->assertSame(286, min($w, $h), 'and it keeps its shape');
    }

    /**
     * Where Imagick is installed it does what GD cannot: told the size first,
     * it throws away most of a JPEG while decoding. That is the path a
     * twelve-thousand-pixel master takes on the hosting.
     */
    public function test_imagick_makes_the_copy_gd_could_not(): void
    {
        if (! class_exists(\Imagick::class)) {
            $this->markTestSkipped('no Imagick here; the hosting and CI have it');
        }

        $big = $this->jpeg('wrappings/big.jpg', 2400, 1800);
        $small = self::inside('smallerWithImagick',
            [Storage::disk('public'), $big, 'wrappings/big@900.webp', 900]);

        $this->assertSame('wrappings/big@900.webp', $small);
        $bytes = (string) Storage::disk('public')->get($small);
        [$w, $h] = getimagesizefromstring($bytes);
        $this->assertSame(900, max($w, $h));
        $this->assertLessThan(Storage::disk('public')->size($big), strlen($bytes),
            'the whole point is that it is lighter than the master');
    }

    /** A copy that is already there is never made twice. */
    public function test_a_copy_that_exists_is_simply_returned(): void
    {
        $ok = $this->jpeg('wrappings/once.jpg', 1200, 1200);
        $first = ImageStore::smaller($ok, 400, true);
        $was = Storage::disk('public')->get($first);

        $this->assertSame($first, ImageStore::smaller($ok, 400, false));
        $this->assertSame($was, Storage::disk('public')->get($first));
    }

    /**
     * «…paste the song's link and.» is what Google printed under an English
     * design. A description may not end on a word the sentence was only
     * leaning on.
     */
    public function test_a_description_never_ends_on_a_leaning_word(): void
    {
        // Each one long enough that it is really cut, and cut where the
        // sentence is leaning on a small word.
        $cases = [
            "Your photograph fills the whole box, with the green Spotify panel beneath it: paste the song's link and the code appears on the lid of the box, ready for anyone to scan",
            'Zərif bənövşəyi çiçək çərçivəsi, uzun mətn yeri və körpələr üçün yumşaq rənglər ilə hazırlanmış bir qutu və içində bir dilim süd şokoladı gözləyir',
            'Коробка с вашей фотографией, именем и короткой подписью, которую можно выбрать самому, и с лентой того цвета, который вы укажете при заказе',
        ];

        foreach ($cases as $text) {
            $cut = Seo::snippet($text, 105);
            $this->assertLessThan(mb_strlen($text), mb_strlen($cut), 'this case is not even being cut');
            $last = mb_strtolower(rtrim((string) last(preg_split('/\s+/u', rtrim($cut, ' .'))), ' .,;:'));
            $leaning = (new \ReflectionClass(Seo::class))->getConstant('LEANING');

            $this->assertNotContains($last, $leaning, 'ends on «' . $last . '»: ' . $cut);
            $this->assertStringEndsWith('.', $cut);
            $this->assertGreaterThan(40, mb_strlen($cut), 'and it did not eat the description: ' . $cut);
        }
    }

    /** What it did well before, it still does. */
    public function test_a_whole_sentence_is_still_preferred_and_short_text_left_alone(): void
    {
        $this->assertSame('Bir cümlə.', Seo::snippet('Bir cümlə.', 155));
        $this->assertSame('Qısa mətn', Seo::snippet('Qısa mətn', 155));
        $this->assertSame('', Seo::snippet(null));

        $two = 'Birinci cümlə burada bitir. İkinci cümlə isə çox-çox uzundur və kəsiləcək, çünki yerə sığmır.';
        $this->assertSame('Birinci cümlə burada bitir.', Seo::snippet($two, 60));
    }

    /**
     * The corporate page prints its colour names through __(), and every name
     * it can print has somewhere to go. Five of the eight were translated and
     * unused, because the view passed the raw Azerbaijani straight out.
     */
    public function test_the_corporate_colours_are_translated_and_the_view_asks_for_it(): void
    {
        $view = file_get_contents(resource_path('views/corporate/index.blade.php'));
        $this->assertStringContainsString("title=\"{{ __(\$color['name']) }}\"", $view);
        $this->assertStringContainsString("<span>{{ __(\$color['name']) }}</span>", $view);

        foreach (CorporatePage::COLORS as $colour) {
            foreach (['ru', 'en'] as $lang) {
                $this->assertArrayHasKey($colour['name'], $this->words($lang),
                    $colour['name'] . ' has no ' . $lang . ' name');
            }
        }
    }

    /**
     * Every sentence the corporate page ships with has a Russian and an
     * English one. The opening pitch did not: it is two sentences joined in
     * the source across two lines, and only the first line was ever
     * translated, so the key never matched and the whole paragraph — which is
     * also the page's Google snippet — fell back to Azerbaijani.
     */
    public function test_every_line_the_corporate_page_ships_with_is_translated(): void
    {
        $missing = [];
        $look = function ($value) use (&$look, &$missing) {
            if (is_array($value)) {
                array_walk($value, fn ($v) => $look($v));

                return;
            }
            if (! is_string($value) || is_numeric($value) || ! preg_match('/\p{L}{3}/u', $value)) {
                return;
            }
            foreach (['ru', 'en'] as $lang) {
                if (! array_key_exists($value, $this->words($lang))) {
                    $missing[] = $lang . ': ' . $value;
                }
            }
        };
        $look(CorporatePage::DEFAULTS);

        $this->assertSame([], $missing, "the corporate page would print these in Azerbaijani:\n"
            . implode("\n", array_map(fn ($m) => '  ' . mb_substr($m, 0, 110), $missing)));
    }

    private function words(string $lang): array
    {
        static $cache = [];

        return $cache[$lang] ??= json_decode(file_get_contents(lang_path($lang . '.json')), true);
    }
}
