<?php

namespace Tests\Feature;

use App\Filament\Resources\LivePhotoResource\Pages\CreateLivePhoto;
use App\Filament\Resources\LivePhotoResource\Pages\ListLivePhotos;
use App\Filament\Resources\OrderResource\Pages\EditOrder;
use App\Filament\Resources\OrderResource\RelationManagers\ItemsRelationManager;
use App\Models\DeliveryMethod;
use App\Models\LivePhoto;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class LivePhotoTest extends TestCase
{
    use RefreshDatabase;

    private const LINK = 'https://disk.yandex.ru/i/abcVideo123';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /** Yandex Disk's answer about the link, and the download address it hands out. */
    private function fakeYandex(string $mime = 'video/mp4', string $type = 'file'): void
    {
        Http::fake([
            'cloud-api.yandex.net/v1/disk/public/resources/download*' => Http::response(['href' => 'https://downloader.disk.yandex.ru/disk/abc/video.mp4']),
            'cloud-api.yandex.net/v1/disk/public/resources*' => Http::response(['type' => $type, 'name' => 'video.mp4', 'mime_type' => $mime]),
        ]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['role' => User::ADMIN]);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return $admin;
    }

    private function live(bool $ready = true): LivePhoto
    {
        Storage::disk('public')->put('live/pic.png', UploadedFile::fake()->image('pic.png', 800, 1000)->getContent());
        $live = LivePhoto::create(['title' => 'Aysel', 'target_image' => 'live/pic.png', 'video_url' => self::LINK, 'is_active' => true]);
        if ($ready) {
            Storage::disk('public')->put('live/1/target.mind', 'mind-data');
            $live->forceFill(['target_mind' => 'live/1/target.mind'])->saveQuietly();
        }

        return $live;
    }

    private function box(): Product
    {
        $box = Product::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true, 'price' => 5,
            'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/' . $box->id . '/bg.webp', 'x' => 0, 'y' => 0,
            'width' => 969, 'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);

        return $box;
    }

    public function test_the_admin_makes_a_live_photo_from_a_yandex_video(): void
    {
        $this->fakeYandex();
        $this->admin();
        $this->get('/admin/live-photos')->assertOk()->assertSee('Canlı şəkil yarat');

        Livewire::test(CreateLivePhoto::class)
            ->fillForm(['title' => 'Aysel', 'video_url' => self::LINK, 'target_image' => [UploadedFile::fake()->image('pic.jpg', 800, 1000)]])
            ->call('create')
            ->assertHasNoFormErrors();

        $live = LivePhoto::firstOrFail();
        $this->assertMatchesRegularExpression('/^[a-z0-9]{8}$/', $live->code);
        $this->assertSame([self::LINK, true, null], [$live->video_url, $live->is_active, $live->target_mind]);
        $this->assertSame(1.25, $live->aspect());
        Storage::disk('public')->assertExists($live->target_image);

        // The edit page carries the preparation step and the QR code's link.
        $this->get('/admin/live-photos/' . $live->id . '/edit')->assertOk()
            ->assertSee('Hədəfi hazırla')->assertSee(route('live.show', $live->code))->assertSee('SVG yüklə');
    }

    public function test_a_link_that_is_not_a_video_is_refused(): void
    {
        $this->admin();

        Livewire::test(CreateLivePhoto::class)
            ->fillForm(['title' => 'X', 'video_url' => 'https://example.com/video.mp4', 'target_image' => [UploadedFile::fake()->image('pic.jpg')]])
            ->call('create')
            ->assertHasFormErrors(['video_url']);

        $this->fakeYandex('image/jpeg');
        Livewire::test(CreateLivePhoto::class)
            ->fillForm(['title' => 'X', 'video_url' => self::LINK, 'target_image' => [UploadedFile::fake()->image('pic.jpg')]])
            ->call('create')
            ->assertNotified('Video linki yoxlanmadı');

        Http::fake(['cloud-api.yandex.net/*' => Http::response(['type' => 'dir', 'name' => 'Videolar'])]);
        Livewire::test(CreateLivePhoto::class)
            ->fillForm(['title' => 'X', 'video_url' => self::LINK, 'target_image' => [UploadedFile::fake()->image('pic.jpg')]])
            ->call('create')
            ->assertNotified('Video linki yoxlanmadı');

        $this->assertSame(0, LivePhoto::count());
    }

    public function test_a_new_picture_needs_preparing_again(): void
    {
        $live = $this->live();
        Storage::disk('public')->put('live/new.png', UploadedFile::fake()->image('new.png', 500, 500)->getContent());

        $live->update(['target_image' => 'live/new.png']);

        $this->assertNull($live->fresh()->target_mind);
        Storage::disk('public')->assertMissing('live/1/target.mind');
        $this->assertFalse($live->fresh()->isReady());

        $live->fresh()->delete();
        Storage::disk('public')->assertMissing('live/new.png');
    }

    public function test_only_the_admin_stores_the_tracking_data(): void
    {
        $live = $this->live(false);
        $mind = UploadedFile::fake()->create('target.mind', 300, 'application/octet-stream');

        $this->post(route('live.mind', $live), ['mind' => $mind])->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => User::MANAGER]))
            ->post(route('live.mind', $live), ['mind' => $mind])->assertForbidden();
        $this->assertNull($live->fresh()->target_mind);

        $this->actingAs(User::factory()->create(['role' => User::ADMIN]))
            ->postJson(route('live.mind', $live), ['mind' => $mind])->assertOk()->assertJson(['ok' => true]);
        $path = $live->fresh()->target_mind;
        $this->assertStringStartsWith('live/' . $live->id . '/target-', $path);
        Storage::disk('public')->assertExists($path);
        $this->assertTrue($live->fresh()->isReady());
    }

    public function test_the_qr_code_opens_the_camera_page(): void
    {
        $live = $this->live();

        $this->get('/canli/' . $live->code)->assertOk()
            ->assertSee('Kameranı aç')
            ->assertSee(route('live.video', $live->code))
            ->assertSee('mindar-image-three', false)
            ->assertSee('live\/1\/target.mind', false)
            ->assertSee('height:1250px', false);
        $this->assertSame(1, $live->fresh()->views);

        // Not prepared yet: a friendly wait, nothing counted.
        $waiting = $this->live(false);
        $this->get('/canli/' . $waiting->code)->assertOk()->assertSee('Canlı şəkil hazırlanır')->assertDontSee('Kameranı aç');
        $this->assertSame(0, $waiting->fresh()->views);

        // Switched off, or no such code.
        $live->update(['is_active' => false]);
        $this->get('/canli/' . $live->code)->assertNotFound();
        $this->get('/canli/nosuchcode')->assertNotFound();
    }

    public function test_the_video_streams_from_yandex_disk(): void
    {
        Http::fake(fn ($request) => str_contains($request->url(), 'gone')
            ? Http::response([], 404)
            : Http::response(['href' => 'https://downloader.disk.yandex.ru/disk/abc/video.mp4']));
        $live = $this->live();

        $this->get(route('live.video', $live->code))->assertRedirect('https://downloader.disk.yandex.ru/disk/abc/video.mp4');
        $this->get(route('live.video', $live->code))->assertRedirect('https://downloader.disk.yandex.ru/disk/abc/video.mp4');
        Http::assertSentCount(1);   // the address is kept for a while

        // Yandex does not answer: the page says the video is not there now.
        $other = LivePhoto::create(['title' => 'B', 'target_image' => 'live/pic.png', 'video_url' => 'https://disk.yandex.ru/i/gone', 'is_active' => true]);
        $this->get(route('live.video', $other->code))->assertStatus(503);
    }

    public function test_the_camera_page_works_while_the_site_is_closed(): void
    {
        $live = $this->live();
        Setting::put(Setting::MAINTENANCE, true);

        $this->get(route('home'))->assertStatus(503);
        $this->get('/canli/' . $live->code)->assertOk()->assertSee('Kameranı aç');
    }

    public function test_customers_order_a_live_video_with_their_box(): void
    {
        $box = $this->box();
        $this->get(route('products.customize', $box->slug))->assertDontSee('Canlı video (AR)');

        $this->admin();
        Livewire::test(ListLivePhotos::class)
            ->callAction('sale', ['enabled' => true, 'price' => 7.5])
            ->assertHasNoActionErrors();
        $this->assertSame(['1', '7.5'], [Setting::get(Setting::AR_ENABLED), Setting::get(Setting::AR_PRICE)]);

        $user = User::factory()->create();
        $this->actingAs($user)->get(route('products.customize', $box->slug))->assertOk()
            ->assertSee('Canlı video (AR)')->assertSee('7.50 ₼');

        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $box->id, 'ar_on' => 1])
            ->assertSessionHasErrors(['ar_video' => 'Canlı video üçün videonu yükləyin, ya da bu seçimi söndürün.']);
        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $box->id, 'ar_on' => 1,
            'ar_video' => UploadedFile::fake()->create('film.pdf', 100, 'application/pdf')])
            ->assertSessionHasErrors(['ar_video' => 'Video MP4, MOV və ya WEBM olmalıdır.']);

        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $box->id, 'ar_on' => 1,
            'ar_video' => UploadedFile::fake()->create('film.mp4', 2048, 'video/mp4')])
            ->assertSessionHasNoErrors();
        $this->actingAs($user)->get(route('cart.index'))->assertSee('Canlı video (AR)')->assertSee('12.50 ₼');   // 5 + 7.50

        $this->actingAs($user)->post(route('checkout.store'), [
            'delivery_method_id' => DeliveryMethod::where('type', 'door')->value('id'),
            'contact_phone' => '1', 'delivery_address' => 'Bakı, Nizami küç. 5',
        ])->assertRedirect(route('orders.index'));

        $item = Order::latest('id')->firstOrFail()->items()->firstOrFail();
        $this->assertSame(7.5, $item->ar_price);
        $this->assertSame(12.5, $item->unitPrice());
        Storage::disk('public')->assertExists($item->ar_video);
        $this->actingAs($user)->get(route('orders.index'))->assertSee('Canlı video (AR)');

        // The admin sees the video on the order line and makes the live photo from it.
        $this->admin();
        Livewire::test(ItemsRelationManager::class, ['ownerRecord' => $item->order, 'pageClass' => EditOrder::class])
            ->assertSee('Videonu yüklə')->assertSee('AR yarat')->assertSee('order_item=' . $item->id, false);

        $this->get('/admin/live-photos/create?order_item=' . $item->id)->assertOk();
        Livewire::withQueryParams(['order_item' => $item->id])->test(CreateLivePhoto::class)
            ->assertFormSet(['title' => 'Sifariş #' . $item->order_id . ' — Test', 'order_item_id' => $item->id]);

        $live = LivePhoto::create(['title' => 'X', 'target_image' => 'live/pic.png', 'video_url' => self::LINK, 'order_item_id' => $item->id]);
        Livewire::test(ItemsRelationManager::class, ['ownerRecord' => $item->order, 'pageClass' => EditOrder::class])
            ->assertSee('Canlı şəkil: X')->assertDontSee('AR yarat');
        $this->assertSame($item->id, $live->orderItem->id);
    }

    public function test_unchecked_the_video_is_not_added(): void
    {
        Setting::put(Setting::AR_ENABLED, true);
        $box = $this->box();

        $this->actingAs(User::factory()->create())->post(route('cart.add'), ['product_id' => $box->id,
            'ar_video' => UploadedFile::fake()->create('film.mp4', 100, 'video/mp4')])->assertSessionHasNoErrors();
        $this->assertNull(session('cart_items')[0]['ar'] ?? null);

        Setting::put(Setting::AR_ENABLED, false);
        $this->post(route('cart.add'), ['product_id' => $box->id, 'ar_on' => 1])->assertSessionHasNoErrors();
        $this->assertNull(session('cart_items')[1]['ar'] ?? null);
    }
}
