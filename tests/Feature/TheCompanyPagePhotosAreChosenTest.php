<?php

namespace Tests\Feature;

use App\Models\Scene;
use App\Support\CorporatePage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The owner chooses the photographs on the company page himself.
 *
 * They used to be three constants with four corner points each, so there was
 * no answer to «where do I replace these». They are scenes now — the same
 * editor he builds product mockups in — and the page takes the background and
 * the place the design sits.
 *
 * With nothing chosen the page shows what it has always shown, which is the
 * part that must not break: the shop does not go blank because a feature was
 * added and nobody used it yet.
 */
class TheCompanyPagePhotosAreChosenTest extends TestCase
{
    use RefreshDatabase;

    /** A scene the way the editor leaves one: a photograph and a design's place. */
    private function scene(array $extra = []): Scene
    {
        return Scene::create($extra + [
            'name' => 'Kafedə',
            'background' => 'scenes/kafe.jpg',
            'width' => 1000,
            'height' => 1500,
            'elements' => [
                ['id' => 'a', 'type' => 'image', 'image' => 'scenes/prop.png'],
                ['id' => 'b', 'type' => 'design', 'corners' => [[200, 300], [600, 300], [600, 1200], [200, 1200]]],
            ],
            'is_active' => true,
            'on_corporate' => true,
            'sort_order' => 0,
        ]);
    }

    public function test_with_nothing_chosen_the_page_keeps_the_three_it_has_always_had(): void
    {
        $scenes = CorporatePage::scenes();

        $this->assertCount(3, $scenes);
        $this->assertSame('Qəhvənin yanında, kafedə', $scenes[0]['caption']);
        $this->assertStringContainsString('scene-cafe.jpg', $scenes[0]['url']);
    }

    public function test_a_chosen_scene_replaces_them(): void
    {
        $this->scene();

        $scenes = CorporatePage::scenes();

        $this->assertCount(1, $scenes);
        $this->assertSame('Kafedə', $scenes[0]['caption']);
        $this->assertStringContainsString('scenes/kafe.jpg', $scenes[0]['url']);
    }

    /**
     * The corners arrive as fractions of the photograph.
     *
     * The editor keeps them in the scene's own pixels. Handed over unchanged
     * they would be read as fractions and the box would land in the top left
     * corner, hundreds of times too small.
     */
    public function test_the_corners_are_turned_into_fractions(): void
    {
        $this->scene();

        $corners = CorporatePage::scenes()[0]['corners'];

        $this->assertSame([0.2, 0.2], $corners[0]);
        $this->assertSame([0.6, 0.2], $corners[1]);
        $this->assertSame([0.6, 0.8], $corners[2]);
        $this->assertSame([0.2, 0.8], $corners[3]);
    }

    /** The photograph's own shape travels with it, so the frame cannot stretch it. */
    public function test_each_scene_carries_its_own_shape(): void
    {
        $this->scene();

        $this->assertSame('1000 / 1500', CorporatePage::scenes()[0]['ratio']);
    }

    /** A week of cache means a replaced photograph needs a new address. */
    public function test_the_address_carries_a_stamp_that_moves_when_the_scene_does(): void
    {
        $scene = $this->scene();

        $this->assertStringContainsString('v='.$scene->updated_at->getTimestamp(), CorporatePage::scenes()[0]['url']);

        $this->travel(5)->minutes();
        $scene->touch();

        $this->assertStringContainsString('v='.$scene->fresh()->updated_at->getTimestamp(),
            CorporatePage::scenes()[0]['url']);
    }

    /** A scene with nowhere to put the design is passed over, not drawn empty. */
    public function test_a_scene_without_a_place_for_the_design_is_skipped(): void
    {
        $this->scene(['elements' => [['id' => 'a', 'type' => 'image', 'image' => 'scenes/prop.png']]]);

        // Nothing usable was chosen, so the built-in three stand.
        $this->assertCount(3, CorporatePage::scenes());
    }

    public function test_a_scene_without_a_photograph_is_skipped(): void
    {
        $this->scene(['background' => null]);

        $this->assertCount(3, CorporatePage::scenes());
    }

    /** Hiding a scene takes it off the company page too. */
    public function test_a_hidden_scene_is_not_shown(): void
    {
        $this->scene(['is_active' => false]);

        $this->assertCount(3, CorporatePage::scenes());
    }

    /** A scene built for products stays out until it is marked. */
    public function test_a_scene_not_marked_for_the_company_page_stays_out(): void
    {
        $this->scene(['on_corporate' => false]);

        $this->assertCount(3, CorporatePage::scenes());
    }

    public function test_the_owners_order_is_kept(): void
    {
        $this->scene(['name' => 'İkinci', 'sort_order' => 2]);
        $this->scene(['name' => 'Birinci', 'sort_order' => 1]);

        $this->assertSame(['Birinci', 'İkinci'], array_column(CorporatePage::scenes(), 'caption'));
    }

    /** And the page really draws what was chosen. */
    public function test_the_page_draws_the_chosen_photograph(): void
    {
        $this->scene();

        $page = $this->get('/sirketler-ucun');

        $page->assertOk();
        $page->assertSee('scenes/kafe.jpg', false);
        $page->assertSee('aspect-ratio:1000 / 1500', false);
        $page->assertDontSee('scene-cafe.jpg', false);
    }
}
