<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Where `back()` goes.
 *
 * Laravel remembers the last address asked for as the page to come back to.
 * The chat in the corner asks for new messages every few seconds from every
 * page on the site, so that address used to become "the page we were on" —
 * and deleting a line from the basket, or a wrong password, dropped the
 * customer onto a page of bare JSON: {"ok":true,"messages":[]}.
 */
class BackToThePageTest extends TestCase
{
    use RefreshDatabase;

    private function box(): Product
    {
        $box = Product::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true, 'price' => 20,
            'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/bg.webp', 'x' => 0, 'y' => 0,
            'width' => 969, 'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);

        return $box;
    }

    public function test_what_the_page_fetches_is_not_the_page_we_were_on(): void
    {
        $this->get(route('cart.index'))->assertOk();

        // Exactly as the widget asks, and as a browser that forgot the header would.
        $this->get(route('chat.poll') . '?after=0')->assertOk();
        $this->get(route('map.search') . '?q=Nizami')->assertOk();

        $this->assertSame(route('cart.index'), session()->previousUrl());
    }

    public function test_deleting_a_line_from_the_basket_comes_back_to_the_basket(): void
    {
        $box = $this->box();

        $this->post(route('cart.add'), ['product_id' => $box->id])->assertSessionHasNoErrors();
        $this->get(route('cart.index'))->assertOk()->assertSee($box->name, false);

        $id = array_key_first(session('cart_items', []));
        $this->assertNotNull($id, 'the basket has a line to delete');

        // The chat asks for messages in between, as it does on every page.
        $this->get(route('chat.poll') . '?after=0')->assertOk();

        $this->delete(route('cart.remove', $id))->assertRedirect(route('cart.index'));
    }

    public function test_a_wrong_password_comes_back_to_the_login_page(): void
    {
        $this->get(route('login'))->assertOk();
        $this->get(route('chat.poll') . '?after=0')->assertOk();

        $this->post(route('login'), ['login' => 'yoxdur@nefis.az', 'password' => 'səhv'])
            ->assertRedirect(route('login'));
    }

    public function test_the_chat_window_says_its_asking_is_a_background_one(): void
    {
        \App\Models\Setting::put(\App\Models\Setting::CHAT_ENABLED, true);
        \App\Support\ChatBot::saveToken('999:XYZ');
        \App\Models\Setting::put(\App\Models\Setting::CHAT_CHAT, '777111');

        $this->get('/')->assertOk()
            ->assertSee("'X-Requested-With':'XMLHttpRequest'", false);
    }

    /**
     * The same trap, and the worst of them: every page carries
     * `<link rel="manifest">`, so the browser asks for the manifest by
     * itself the moment the page loads — and that made the manifest "the
     * page we were on" everywhere on the site. A wrong password then
     * dropped the customer onto {"id":"nefis.az","name":…}.
     */
    public function test_what_the_browser_fetches_by_itself_is_not_the_page_either(): void
    {
        $this->get(route('login'))->assertOk();

        $this->get(route('manifest', ['lang' => 'az']))->assertOk();
        $this->get(route('sitemap'))->assertOk();
        $this->get(route('feed'))->assertOk();

        $this->assertSame(route('login'), session()->previousUrl());
    }

    public function test_a_wrong_password_after_the_manifest_comes_back_to_the_login_page(): void
    {
        $this->get(route('login'))->assertOk();
        // the browser, a moment later, on its own
        $this->get(route('manifest', ['lang' => 'az']))->assertOk();

        $this->post(route('login'), ['login' => 'nobody@example.com', 'password' => 'wrong'])
            ->assertRedirect(route('login'));
    }
}
