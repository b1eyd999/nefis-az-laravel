<?php

namespace Tests\Feature;

use App\Models\GiftPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A gift page's slug is a translated word, so the same page is
 * /hediyye/zz-sevgiliye, /ru/podarki/zz-devushke and /en/gifts/zz-romantic-gift.
 *
 * The route finds a page by slug whatever language the address is in, and
 * used to refuse the mismatch with a 404. Search Console had collected
 * sixty-six of those — a prefix swapped without the word after it, and links
 * from before the slugs were translated. Every one of them names a page that
 * exists, so the answer is a redirect to where it lives.
 */
class GiftSlugAcrossLanguagesTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: GiftPage, 1: GiftPage, 2: GiftPage} az, ru, en */
    private function family(): array
    {
        $az = GiftPage::create([
            'locale' => 'az', 'slug' => 'zz-sevgiliye', 'menu_label' => 'Sevgiliyə',
            'title' => 'Sevgiliyə hədiyyə', 'is_active' => true,
        ]);
        $ru = GiftPage::create([
            'locale' => 'ru', 'slug' => 'zz-devushke', 'menu_label' => 'Девушке',
            'title' => 'Подарок девушке', 'is_active' => true, 'alt_of' => $az->id,
        ]);
        $en = GiftPage::create([
            'locale' => 'en', 'slug' => 'zz-romantic-gift', 'menu_label' => 'Romantic',
            'title' => 'A romantic gift', 'is_active' => true, 'alt_of' => $az->id,
        ]);

        return [$az, $ru, $en];
    }

    public function test_each_language_answers_on_its_own_word(): void
    {
        $this->family();

        $this->get('/hediyye/zz-sevgiliye')->assertOk();
        $this->get('/ru/podarki/zz-devushke')->assertOk();
        $this->get('/en/gifts/zz-romantic-gift')->assertOk();
    }

    public function test_a_slug_from_another_language_is_sent_to_the_right_page(): void
    {
        $this->family();

        // What Search Console was full of: the prefix swapped, the word not.
        $this->get('/en/gifts/zz-sevgiliye')->assertRedirect('/en/gifts/zz-romantic-gift');
        $this->get('/ru/podarki/zz-sevgiliye')->assertRedirect('/ru/podarki/zz-devushke');
        $this->get('/hediyye/zz-romantic-gift')->assertRedirect('/hediyye/zz-sevgiliye');
        $this->get('/en/gifts/zz-devushke')->assertRedirect('/en/gifts/zz-romantic-gift');

        // And it is a permanent one, so the old address stops being asked for.
        $this->get('/en/gifts/zz-sevgiliye')->assertStatus(301);
    }

    public function test_with_no_twin_in_that_language_it_lands_on_the_hub(): void
    {
        $az = GiftPage::create([
            'locale' => 'az', 'slug' => 'zz-tekbasina', 'menu_label' => 'Tək',
            'title' => 'Təkbaşına', 'is_active' => true,
        ]);

        // No English twin exists, so the English reader gets the English hub
        // rather than a dead address.
        $this->get('/en/gifts/' . $az->slug)->assertRedirect('/en/gifts');
    }

    public function test_a_switched_off_page_is_still_a_404(): void
    {
        [$az] = $this->family();
        $az->update(['is_active' => false]);

        $this->get('/hediyye/zz-sevgiliye')->assertNotFound();
    }

    public function test_the_redirect_does_not_bounce_back_and_forth(): void
    {
        $this->family();

        // One hop, and the place it lands answers for itself.
        $first = $this->get('/en/gifts/zz-sevgiliye');
        $first->assertStatus(301);
        $this->get($first->headers->get('Location'))->assertOk();
    }
}
