<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PosterTest extends TestCase
{
    use RefreshDatabase;

    private const LINK = 'https://disk.yandex.ru/i/F1t9fiD1-CBTXg';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    /** The poster as Yandex Disk shares it: a 1080×1350 PNG. */
    private function fakeYandex(string $type = 'file', string $mime = 'image/png'): void
    {
        $im = imagecreatetruecolor(1080, 1350);
        imagefill($im, 0, 0, imagecolorallocate($im, 250, 200, 110));
        ob_start();
        imagepng($im);
        $png = ob_get_clean();

        Http::fake([
            'cloud-api.yandex.net/v1/disk/public/resources/download*' => Http::response(['href' => 'https://downloader.disk.yandex.ru/disk/qara']),
            'cloud-api.yandex.net/v1/disk/public/resources*' => Http::response(['type' => $type, 'name' => 'qara spotify.png', 'size' => strlen($png), 'mime_type' => $mime]),
            'downloader.disk.yandex.ru/*' => Http::response($png, 200, ['Content-Type' => 'image/png']),
        ]);
    }

    private function box(array $attributes = []): Product
    {
        return Product::create($attributes + ['name' => 'Dark Spotify', 'slug' => 'dark-spotify', 'is_active' => true, 'category' => 'spotify']);
    }

    public function test_a_yandex_link_becomes_the_catalogue_poster(): void
    {
        $this->fakeYandex();
        $box = $this->box();

        Livewire::test(EditProduct::class, ['record' => $box->getRouteKey()])
            ->fillForm(['poster_url' => self::LINK])
            ->call('save')
            ->assertHasNoFormErrors();

        $box->refresh();
        $this->assertSame(self::LINK, $box->poster_url);
        $this->assertStringStartsWith('boxes/' . $box->id . '/poster-', $box->poster_image);
        Storage::disk('public')->assertExists($box->poster_image);
        $this->assertSame([1080, 1350], array_slice(getimagesize(Storage::disk('public')->path($box->poster_image)), 0, 2));

        $this->get(route('designs.index'))->assertSee('/storage/' . $box->poster_image, false);

        // Saving again does not fetch it again; clearing the link drops it.
        Http::fake(fn () => throw new \RuntimeException('no second download'));
        Livewire::test(EditProduct::class, ['record' => $box->getRouteKey()])->fillForm(['name' => 'Dark Spotify'])->call('save')->assertHasNoFormErrors();
        $old = $box->poster_image;
        Livewire::test(EditProduct::class, ['record' => $box->getRouteKey()])->fillForm(['poster_url' => null])->call('save')->assertHasNoFormErrors();
        $this->assertNull($box->fresh()->poster_image);
        Storage::disk('public')->assertMissing($old);
    }

    public function test_a_new_product_can_start_with_its_poster(): void
    {
        $this->fakeYandex();

        Livewire::test(CreateProduct::class)
            ->fillForm(['name' => 'Dark Spotify', 'slug' => 'dark-spotify', 'poster_url' => self::LINK])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertNotNull(Product::where('slug', 'dark-spotify')->first()->poster_image);
    }

    public function test_links_go_in_the_poster_field_not_the_page_address(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm(['name' => 'Dark Spotify', 'slug' => self::LINK])
            ->call('create')
            ->assertHasFormErrors(['slug' => 'regex']);

        Livewire::test(CreateProduct::class)
            ->fillForm(['name' => 'Dark Spotify', 'slug' => 'dark-spotify', 'poster_url' => 'https://example.com/a.png'])
            ->call('create')
            ->assertHasFormErrors(['poster_url']);

        $this->assertSame(0, Product::count());
    }

    public function test_a_folder_link_or_a_non_picture_is_refused_with_a_reason(): void
    {
        $box = $this->box();

        $this->fakeYandex('dir');
        Livewire::test(EditProduct::class, ['record' => $box->getRouteKey()])
            ->fillForm(['poster_url' => 'https://disk.yandex.ru/d/AbC-123'])
            ->call('save')
            ->assertNotified('Poster yüklənmədi');
        $this->assertNull($box->fresh()->poster_url, 'nothing saved');

        $this->fakeYandex('file', 'application/pdf');
        Livewire::test(EditProduct::class, ['record' => $box->getRouteKey()])
            ->fillForm(['poster_url' => self::LINK])
            ->call('save')
            ->assertNotified('Poster yüklənmədi');
        $this->assertNull($box->fresh()->poster_image);
    }

    public function test_the_deploy_fetches_posters_still_missing(): void
    {
        $this->fakeYandex();
        $box = $this->box(['poster_url' => self::LINK]);
        $done = $this->box(['name' => 'Başqa', 'slug' => 'basqa', 'poster_url' => self::LINK]);
        $done->forceFill(['poster_image' => 'boxes/9/poster-x.webp'])->save();

        Artisan::call('products:fetch-posters');

        $this->assertNotNull($box->fresh()->poster_image);
        $this->assertSame('boxes/9/poster-x.webp', $done->fresh()->poster_image);
        Http::assertSentCount(3);   // one product: details, download link, file
    }
}
