<?php

namespace Tests\Feature;

use App\Support\CorporatePage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The three photographs the company's own logo is pasted into.
 *
 * They are drawn onto a canvas whose shape the stylesheet fixes, and the
 * photograph is stretched to fill it — so a picture of the wrong shape is
 * not letterboxed, it is distorted, and nothing on the page says so. Two of
 * these arrived padded with a black band at the foot, which the page drew
 * faithfully for weeks: a black strip under a café table on a cream page.
 *
 * The corner points are fractions of the photograph itself, so they belong
 * to that exact picture. A photograph swapped without new corners puts the
 * box somewhere else in the frame.
 */
class TheCorporateScenePhotosTest extends TestCase
{
    use RefreshDatabase;

    /** The shape the stylesheet gives the frame: `aspect-ratio: 20 / 21`. */
    private const RATIO = 20 / 21;

    private function photo(array $scene): \GdImage
    {
        $path = public_path($scene['image']);

        $this->assertFileExists($path, $scene['image'].' is listed but not on disk');

        return imagecreatefromjpeg($path);
    }

    public function test_every_scene_names_a_photograph_and_four_corners(): void
    {
        $this->assertCount(3, CorporatePage::SCENES);

        foreach (CorporatePage::SCENES as $scene) {
            $this->assertNotEmpty($scene['caption']);
            $this->assertCount(4, $scene['corners'], $scene['image'].' needs four corners');

            foreach ($scene['corners'] as [$x, $y]) {
                $this->assertGreaterThan(0, $x, $scene['image'].': a corner is off the left edge');
                $this->assertLessThan(1, $x, $scene['image'].': a corner is off the right edge');
                $this->assertGreaterThan(0, $y, $scene['image'].': a corner is above the picture');
                $this->assertLessThan(1, $y, $scene['image'].': a corner is below the picture');
            }
        }
    }

    /**
     * All three are the shape the frame expects.
     *
     * The canvas is given a fixed aspect ratio and the photograph is drawn
     * across the whole of it, so a picture of another shape comes out
     * squashed rather than fitted.
     */
    public function test_each_photograph_is_the_shape_of_its_frame(): void
    {
        foreach (CorporatePage::SCENES as $scene) {
            $photo = $this->photo($scene);
            $ratio = imagesx($photo) / imagesy($photo);

            $this->assertEqualsWithDelta(self::RATIO, $ratio, 0.01,
                $scene['image'].' is '.imagesx($photo).'x'.imagesy($photo)
                    .', which the frame would have to stretch');

            imagedestroy($photo);
        }
    }

    /**
     * No photograph carries a black band.
     *
     * This is what went wrong: two were padded out to a taller canvas with
     * black, and the page drew the padding along with the picture.
     */
    public function test_no_photograph_has_a_black_band_at_its_edges(): void
    {
        foreach (CorporatePage::SCENES as $scene) {
            $photo = $this->photo($scene);
            $width = imagesx($photo);
            $height = imagesy($photo);

            foreach (['üst' => 0, 'alt' => $height - 1] as $edge => $row) {
                $dark = 0;
                $looked = 0;

                for ($x = 0; $x < $width; $x += 20) {
                    $colour = imagecolorat($photo, $x, $row);
                    $sum = (($colour >> 16) & 0xFF) + (($colour >> 8) & 0xFF) + ($colour & 0xFF);

                    $looked++;
                    $dark += $sum <= 24 ? 1 : 0;
                }

                $this->assertLessThan($looked, $dark,
                    $scene['image'].': the whole '.$edge.' edge is black — the picture is padded, '
                        .'and the page draws the padding');
            }

            imagedestroy($photo);
        }
    }

    /**
     * The page asks for each photograph by a versioned address.
     *
     * These are served with a week of cache. Replacing a picture at the same
     * address leaves everyone who visited in the last week looking at the old
     * one — and, worse, at the old one stretched into whatever shape the
     * frame has since been given. The stamp changes with every release.
     */
    public function test_each_photograph_is_asked_for_by_a_versioned_address(): void
    {
        $page = $this->get('/sirketler-ucun');

        $page->assertOk();

        foreach (CorporatePage::SCENES as $scene) {
            $this->assertStringContainsString($scene['image'].'?v=', $page->getContent(),
                $scene['image'].' is linked without a version, so a replacement will not reach anyone');
        }
    }

    /**
     * The box sits inside the picture with room around it.
     *
     * A corner within a hair of the edge means the printed face is half out
     * of frame, which reads as a mistake rather than as a photograph.
     */
    public function test_the_box_is_not_pressed_against_an_edge(): void
    {
        foreach (CorporatePage::SCENES as $scene) {
            foreach ($scene['corners'] as [$x, $y]) {
                $this->assertGreaterThan(0.02, min($x, $y), $scene['image'].': the box touches an edge');
                $this->assertLessThan(0.98, max($x, $y), $scene['image'].': the box touches an edge');
            }
        }
    }
}
