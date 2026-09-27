<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A catalogue poster that has gone from the disk while the database still
 * names it. Nothing complains — the column is filled in — and the shop quietly
 * serves a broken picture to every visitor whose browser has not cached the
 * old one. The deploy's own command has to notice that and fetch it again.
 */
class PosterRecoveryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * poster_image is not fillable — only the import writes it — so the test
     * sets it the same way the model does.
     */
    private function design(array $attributes = []): Product
    {
        $design = Product::create(['name' => $attributes['name'] ?? 'Dark Spotify',
            'slug' => 'dark-spotify-' . uniqid(), 'is_active' => true, 'price' => 4.90]);
        $design->forceFill($attributes)->saveQuietly();

        return $design->refresh();
    }

    /** Yandex answers with a real, small JPEG, so no network is touched. */
    private function pretendYandexHasIt(): void
    {
        $canvas = imagecreatetruecolor(120, 150);
        ob_start();
        imagejpeg($canvas);
        $bytes = ob_get_clean();
        imagedestroy($canvas);

        Http::fake([
            'cloud-api.yandex.net/v1/disk/public/resources/download*' => Http::response(['href' => 'https://downloader.example/poster.jpg']),
            'cloud-api.yandex.net/*' => Http::response(['type' => 'file', 'name' => 'poster.jpg',
                'size' => strlen($bytes), 'mime_type' => 'image/jpeg']),
            'downloader.example/*' => Http::response($bytes, 200, ['Content-Type' => 'image/jpeg']),
        ]);
    }

    public function test_a_poster_whose_file_vanished_is_downloaded_again(): void
    {
        Storage::fake('public');
        $this->pretendYandexHasIt();

        $design = $this->design(['poster_url' => 'https://disk.yandex.ru/i/abc123',
            'poster_image' => 'boxes/155/poster-gone.webp']);

        // The column says there is a picture; the disk says otherwise.
        $this->assertFalse(Storage::disk('public')->exists($design->poster_image));

        $this->artisan('products:fetch-posters')
            ->expectsOutputToContain('Poster back: Dark Spotify')
            ->assertSuccessful();

        $design->refresh();
        $this->assertNotSame('boxes/155/poster-gone.webp', $design->poster_image);
        $this->assertTrue(Storage::disk('public')->exists($design->poster_image));
    }

    public function test_a_poster_that_is_where_it_says_it_is_costs_nothing(): void
    {
        Storage::fake('public');
        $this->pretendYandexHasIt();

        $design = $this->design(['poster_url' => 'https://disk.yandex.ru/i/abc123',
            'poster_image' => 'boxes/158/poster-here.webp']);
        Storage::disk('public')->put($design->poster_image, 'a picture');

        $this->artisan('products:fetch-posters')->expectsOutput('No posters to fetch.')->assertSuccessful();

        // Untouched: the deploy must not re-download the whole catalogue each time.
        $this->assertSame('boxes/158/poster-here.webp', $design->fresh()->poster_image);
        $this->assertSame('a picture', Storage::disk('public')->get($design->poster_image));
    }

    public function test_a_design_with_no_link_left_is_named_rather_than_passed_over(): void
    {
        Storage::fake('public');

        $orphan = $this->design(['poster_url' => null, 'poster_image' => 'boxes/160/poster-gone.webp',
            'name' => 'Cici bebe sarı']);

        $this->artisan('products:fetch-posters')
            ->expectsOutputToContain('Poster missing and no link: Cici bebe sarı (#' . $orphan->id . ')')
            ->assertSuccessful();
    }
}
