<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Two fingers on the box.
 *
 * «Я хочу масштабировать на самом сенсоре, на коробке, с пальцами» — the
 * sliders under the photograph were exact and nobody used them, because the
 * customer is looking at his own face inside the window and the slider is
 * somewhere below the box.
 *
 * The gesture itself — the arithmetic AND the event wiring — is checked by
 * driving the real code: tests/Js/pinch.cjs lifts the block out of the view
 * and presses fingers onto it. What is checked here is the handful of
 * things that live outside that block and would let the feature rot
 * silently: the CSS the gesture depends on, and the two sliders it has to
 * agree with.
 */
class PinchToZoomTest extends TestCase
{
    private function source(): string
    {
        return file_get_contents(resource_path('views/products/customize.blade.php'));
    }

    /**
     * The gesture, driven for real: the piece of photograph between the
     * fingers stays between the fingers, a pinch on the street map does not
     * walk the printed address down the road, a thumb resting on the page is
     * not adopted as a second finger, and a stray third finger does not kill
     * the gesture.
     */
    public function test_the_pinch_itself_behaves(): void
    {
        if (! Process::run('node --version')->successful()) {
            /* On a machine without node this is the only test of the gesture
               that does not run, and the rest of this file is substring
               matching — so on the runner that gates production it must be a
               failure and not a shrug. .github/workflows/deploy.yml installs
               node for exactly this. */
            $this->assertFalse((bool) getenv('CI'),
                'the deploy runner must have node, or the gesture ships unchecked');
            $this->markTestSkipped('no node here to run the gesture through');
        }

        $ran = Process::path(base_path())->run('node tests/Js/pinch.cjs');

        $this->assertTrue($ran->successful(), $ran->output() . $ran->errorOutput());
        $this->assertStringContainsString('all good', $ran->output());
    }

    /**
     * One finger over a window moves the photograph; one finger anywhere
     * else on the box is the page's, to scroll. That only holds while the
     * canvas keeps the browser's own touch-action: with `none`, the browser
     * decides first and the whole preview — most of a telephone screen —
     * stops scrolling the page.
     *
     * touch-action is inherited down the tree, so it is no use looking for
     * the canvas's own id: the house already writes this as
     * `.brush-stage canvas{ touch-action:none }` thirty lines away, and the
     * preview canvas sits inside `<div class="stage" id="stage">`.
     */
    public function test_the_box_does_not_take_the_page_away_from_the_finger(): void
    {
        $this->assertStringContainsString('<div class="stage" id="stage"', $this->source(),
            'the wrapper this test watches has been renamed');

        $found = 0;
        foreach ([public_path('css/site.css'), resource_path('views/products/customize.blade.php')] as $file) {
            $css = file_get_contents($file);
            // Declarations only: the gesture's own comments say the words too.
            preg_match_all('/touch-action\s*:/', $css, $hits, PREG_OFFSET_CAPTURE);
            foreach ($hits[0] as [$_, $at]) {
                $found++;
                // Back to the end of the rule before it: what is left is this one.
                $opens = strrpos(substr($css, 0, $at), '}');
                $rule = trim(substr($css, $opens === false ? 0 : $opens + 1, $at - ($opens === false ? 0 : $opens + 1)));
                /* As whole selectors: `.gv-stage` and `.brush-stage` both
                   carry touch-action:none quite rightly, and both contain
                   the letters of `.stage`. */
                foreach (['preview-canvas', '#stage', '.stage'] as $reaches) {
                    $this->assertSame(0, preg_match('/(?<![\w-])' . preg_quote($reaches, '/') . '(?![\w-])/', $rule),
                        'the design preview must keep the browser’s own one-finger scroll: ' . $rule);
                }
            }
        }

        $this->assertGreaterThan(0, $found,
            'no touch-action anywhere: this test has stopped reading what it thinks it reads');
    }

    /**
     * The pinch and the slider are two ways of setting the same number, so
     * they have to stop in the same place — or a pinch writes a figure the
     * slider cannot show and the next touch of it throws the photograph
     * back.
     *
     * With one exception, which is in the view and in pinch.cjs: a window
     * that frames the face by itself writes a scale of its own, above the
     * slider's top, and the pinch must not drag that down.
     */
    public function test_the_pinch_stops_where_the_sliders_stop(): void
    {
        $src = $this->source();

        $this->assertSame(1, preg_match('/class="zoom-range" min="(\d+)" max="(\d+)"/', $src, $zoom));
        $this->assertSame(1, preg_match('/var MIN_SCALE = ([\d.]+), MAX_SCALE = ([\d.]+);/', $src, $pinch));

        $this->assertSame((float) $zoom[1] / 100, (float) $pinch[1]);
        $this->assertSame((float) $zoom[2] / 100, (float) $pinch[2]);

        $this->assertSame(1, preg_match('/class="rotate-range" min="(-?\d+)" max="(\d+)"/', $src, $turn));
        $this->assertSame(['-180', '180'], [$turn[1], $turn[2]],
            'wrapDeg hands back -180..180 and the slider has to take it');
    }

    /**
     * The fingers move the photograph; the two sliders are the same numbers
     * written down. If they are not kept in step, touching one afterwards
     * sends the picture back to whatever it showed before the pinch.
     *
     * pinch.cjs cannot see this — it has no sliders to look at — so it is
     * pinned here.
     */
    public function test_the_sliders_follow_the_fingers(): void
    {
        $src = $this->source();
        $at = strpos($src, 'function movePinch');
        $this->assertIsInt($at);

        // movePinch's own body, to the next function and not a byte further.
        $ends = strpos($src, 'function wrapDeg', $at);
        $this->assertIsInt($ends);
        $body = substr($src, $at, $ends - $at);
        $this->assertStringContainsString('showSliders(pinch.slot);', $body);

        $slidersAt = strpos($src, 'function showSliders');
        $this->assertIsInt($slidersAt);
        $sliders = substr($src, $slidersAt, 600);
        foreach (['.zoom-range', '.rotate-range'] as $slider) {
            $this->assertStringContainsString($slider, $sliders);
        }
    }

    /**
     * Every contact on the page is in `touches`; only those that began on
     * this canvas are in `targetTouches`. Reading the first adopts a thumb
     * resting on the slider below the box as the second finger of a pinch —
     * and that thumb's touchend goes to the slider, not here, so the pinch
     * would never end.
     *
     * pinch.cjs proves the behaviour; this makes sure the block does not
     * quietly grow a second reader of the wrong list.
     */
    public function test_only_the_fingers_on_this_canvas_are_counted(): void
    {
        $src = $this->source();
        $at = strpos($src, 'function onCanvas');
        $this->assertIsInt($at);

        $block = substr($src, $at, 6000);
        $this->assertStringContainsString('return e.targetTouches;', $block);
        $this->assertSame(0, preg_match('/\be\.touches\b/', $block),
            'the gesture must read targetTouches and nothing else');
    }
}
