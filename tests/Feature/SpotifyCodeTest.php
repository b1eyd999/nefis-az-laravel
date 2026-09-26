<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Support\Cart;
use App\Support\SpotifyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Boxes built around a song: the customer pastes the link Spotify gave him,
 * and the scannable code goes on the box. Only the designs the owner marks
 * ask for it.
 */
class SpotifyCodeTest extends TestCase
{
    use RefreshDatabase;

    private const ID = '1301WleyT98MSxVHPZCA6M';

    /** A design the customer can actually open: without artwork it is not one. */
    private function box(bool $song): Product
    {
        $box = Product::create(['name' => 'Spotify qutusu', 'slug' => 'spotify-qutusu', 'is_active' => true,
            'price' => 12.90, 'spotify_code' => $song, 'template_width' => 3508, 'template_height' => 2480]);
        $box->layers()->create(['name' => 'Qutu', 'image' => 'boxes/art.webp', 'x' => 0, 'y' => 0,
            'width' => 3508, 'height' => 2480, 'rotation' => 0, 'opacity' => 100, 'placement' => 'below', 'sort_order' => 0]);

        return $box;
    }

    public function test_every_shape_of_link_a_customer_can_paste_comes_down_to_one(): void
    {
        $want = 'spotify:track:' . self::ID;

        // The share button's link, the same with the country prefix the app
        // adds abroad, the same with the tracking tail, and the app's own uri.
        $this->assertSame($want, SpotifyCode::uri('https://open.spotify.com/track/' . self::ID));
        $this->assertSame($want, SpotifyCode::uri('https://open.spotify.com/intl-az/track/' . self::ID));
        $this->assertSame($want, SpotifyCode::uri('https://open.spotify.com/track/' . self::ID . '?si=8f2a1b3c4d5e6f70'));
        $this->assertSame($want, SpotifyCode::uri('  spotify:track:' . self::ID . '  '));
        $this->assertSame($want, SpotifyCode::uri('http://play.spotify.com/track/' . self::ID));
    }

    public function test_an_album_a_playlist_and_a_podcast_are_codes_too(): void
    {
        $this->assertSame('spotify:album:' . self::ID, SpotifyCode::uri('https://open.spotify.com/album/' . self::ID));
        $this->assertSame('spotify:playlist:' . self::ID, SpotifyCode::uri('https://open.spotify.com/playlist/' . self::ID));
        $this->assertSame('Pleylist', SpotifyCode::kindLabel('spotify:playlist:' . self::ID));
        $this->assertSame('Mahnı', SpotifyCode::kindLabel('spotify:track:' . self::ID));
    }

    public function test_anything_that_is_not_spotify_is_refused(): void
    {
        foreach ([
            '',
            '   ',
            'https://youtube.com/watch?v=dQw4w9WgXcQ',
            'https://open.spotify.com/track/tooshort',
            'https://open.spotify.com/user/' . self::ID,          // a person, not something to play
            'spotify:track:' . self::ID . 'X',                     // one character too many
            'javascript:alert(1)',
            'https://evil.example/open.spotify.com/track/' . self::ID,
        ] as $junk) {
            $this->assertNull(SpotifyCode::uri($junk), "should have refused: $junk");
            $this->assertFalse(SpotifyCode::isValid($junk));
        }
    }

    public function test_the_picture_is_asked_for_in_a_shape_spotify_answers(): void
    {
        $uri = 'spotify:track:' . self::ID;

        $this->assertSame('https://scannables.scdn.co/uri/plain/svg/ffffff/black/640/' . $uri,
            SpotifyCode::image($uri));
        $this->assertSame('https://scannables.scdn.co/uri/plain/png/000000/white/1024/' . $uri,
            SpotifyCode::image($uri, 'png', '#000000', 'white', 1024));

        // Their png stops at 1024 across and answers 500 above it, so nothing
        // may ask for more; a colour that is not a colour falls back to white.
        $this->assertStringContainsString('/png/ffffff/black/1024/', SpotifyCode::image($uri, 'png', 'nonsense', 'black', 4000));
        $this->assertSame('https://open.spotify.com/track/' . self::ID, SpotifyCode::link($uri));
    }

    public function test_only_a_design_built_around_a_song_asks_for_one(): void
    {
        $this->box(true);
        $this->get(route('products.customize', 'spotify-qutusu'))->assertOk()
            ->assertSee('name="spotify_uri"', false)
            ->assertSee('Spotify mahnısı');

        Product::where('slug', 'spotify-qutusu')->update(['spotify_code' => false]);
        $this->get(route('products.customize', 'spotify-qutusu'))->assertOk()
            ->assertDontSee('name="spotify_uri"', false);
    }

    public function test_the_song_travels_from_the_page_to_the_basket(): void
    {
        $box = $this->box(true);

        $this->post(route('cart.add'), ['product_id' => $box->id,
            'spotify_uri' => 'https://open.spotify.com/intl-az/track/' . self::ID . '?si=abc'])
            ->assertSessionHasNoErrors();

        $this->assertSame('spotify:track:' . self::ID, Cart::items()[0]['spotify']);
    }

    public function test_a_link_that_is_not_spotify_is_sent_back_with_a_word_about_it(): void
    {
        $box = $this->box(true);

        $this->post(route('cart.add'), ['product_id' => $box->id, 'spotify_uri' => 'https://youtu.be/dQw4w9WgXcQ'])
            ->assertSessionHasErrors('spotify_uri');

        $this->assertSame([], Cart::items());
    }

    public function test_a_design_that_never_asked_for_a_song_does_not_get_one(): void
    {
        // Nothing on the page sends this field; somebody posting it by hand
        // must not put a code on a box whose artwork has no room for it.
        $box = $this->box(false);

        $this->post(route('cart.add'), ['product_id' => $box->id,
            'spotify_uri' => 'https://open.spotify.com/track/' . self::ID])
            ->assertSessionHasNoErrors();

        $this->assertNull(Cart::items()[0]['spotify']);
    }

    public function test_the_song_reaches_the_order_and_the_owner_sees_its_code(): void
    {
        $box = $this->box(true);
        $this->post(route('cart.add'), ['product_id' => $box->id,
            'spotify_uri' => 'spotify:track:' . self::ID])->assertSessionHasNoErrors();

        $order = Order::create(['user_id' => \App\Models\User::factory()->create()->id,
            'status' => 'pending', 'contact_phone' => '+994 55 555 55 55']);
        $line = $order->items()->create(['product_id' => $box->id, 'product_name' => $box->name,
            'customer_photos' => [], 'custom_texts' => [], 'quantity' => 1, 'price' => $box->price,
            'spotify_uri' => Cart::items()[0]['spotify']]);

        $this->assertSame('spotify:track:' . self::ID, $line->fresh()->spotify_uri);
        $this->assertSame('https://open.spotify.com/track/' . self::ID, $line->spotifyLink());
        $this->assertStringContainsString('/uri/plain/png/', $line->spotifyImage());

        $shown = view('filament.order-item-fields', ['getRecord' => fn () => $line->fresh()])->render();
        $this->assertStringContainsString('Spotify kodu', $shown);
        $this->assertStringContainsString('scannables.scdn.co', $shown);
        $this->assertStringContainsString('open.spotify.com/track/' . self::ID, $shown);
    }
}
