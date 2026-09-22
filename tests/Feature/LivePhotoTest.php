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
            ->assertSee('const PIC = 1.25;', false)->assertSee('Bu şəkil canlanır');
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

    /** The owner's own disk: making the folder, the upload, sharing and the public link. */
    private function fakeOwnerDisk(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();

            return match (true) {
                str_contains($url, '/resources/upload') => Http::response(['href' => 'https://uploader1.disk.yandex.net/upload/abc', 'method' => 'PUT']),
                str_starts_with($url, 'https://uploader1.disk.yandex.net') => Http::response('', 201),
                str_contains($url, '/resources/publish') => Http::response(['href' => 'https://cloud-api.yandex.net/v1/disk/resources?path=x']),
                str_contains($url, 'fields=public_url') => Http::response(['public_url' => 'https://yadi.sk/i/customerVid1']),
                str_contains($url, '/v1/disk/resources?') && $request->method() === 'PUT' => Http::response(['href' => 'x'], 201),
                parse_url($url, PHP_URL_PATH) === '/v1/disk/' => Http::response(['user' => ['display_name' => 'Nefis Şokolad'], 'total_space' => 10737418240, 'used_space' => 1073741824]),
                str_contains($url, 'public/resources/download') => Http::response(['href' => 'https://downloader.disk.yandex.ru/disk/customer.mp4']),
                default => Http::response([], 404),
            };
        });
    }

    private function mind(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('target.mind', "\x82\xa1v\x02" . str_repeat('m', 200));
    }

    private function placeOrder(User $user): Order
    {
        $this->actingAs($user)->post(route('checkout.store'), [
            'delivery_method_id' => DeliveryMethod::where('type', 'door')->value('id'),
            'contact_phone' => '1', 'delivery_address' => 'Bakı, Nizami küç. 5',
        ])->assertRedirect(route('orders.index'));

        return Order::latest('id')->firstOrFail();
    }

    public function test_a_customer_makes_a_live_photo_on_their_own(): void
    {
        $this->fakeOwnerDisk();
        \App\Support\YandexDisk::saveToken('y0_owner_token');

        $this->get(route('home'))->assertSee(route('live.create'), false)->assertSee('Canlı şəkil');
        $this->get(route('live.create'))->assertOk()
            ->assertSee('Canlı şəkil')->assertSee('5 ₼')->assertSee('live-target.js', false)
            ->assertSee('name="ar_photo"', false)->assertSee('name="ar_video"', false);

        $user = User::factory()->create();
        $this->actingAs($user)->post(route('live.store'), [])->assertSessionHasErrors([
            'ar_photo' => 'Canlanacaq şəkli yükləyin.', 'ar_video' => 'Canlı şəkil üçün videonu yükləyin.',
        ]);

        $this->actingAs($user)->post(route('live.store'), [
            'ar_photo' => UploadedFile::fake()->image('biz.jpg', 600, 800),
            'ar_video' => UploadedFile::fake()->create('film.mp4', 3000, 'video/mp4'),
            'ar_mind' => $this->mind(),
        ])->assertRedirect(route('cart.index'));
        $this->actingAs($user)->get(route('cart.index'))->assertSee('Canlı şəkil')->assertSee('QR kodla çap olunur')->assertSee('5 ₼');

        $order = $this->placeOrder($user);
        $item = $order->items()->firstOrFail();
        $this->assertSame([null, 'Canlı şəkil', 5.0, 5.0], [$item->product_id, $item->product_name, $item->ar_price, $order->itemsTotal()]);

        // Made by itself: the picture, the camera's data, and the video moved on to Yandex Disk.
        $live = $item->livePhotos()->firstOrFail();
        $this->assertStringStartsWith('live/' . $live->id . '/', $live->target_image);
        $this->assertStringStartsWith('live/' . $live->id . '/', $live->target_mind);
        Storage::disk('public')->assertExists([$live->target_image, $live->target_mind]);
        $this->assertSame(1.3333, $live->aspect());
        $this->assertSame(['https://yadi.sk/i/customerVid1', null, 'yandex'], [$live->video_url, $live->video_path, $live->videoPlace()]);
        $this->assertSame([], Storage::disk('public')->allFiles('cart-videos'));
        $this->assertTrue($live->isReady());
        Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://uploader1.disk.yandex.net') && $r->hasHeader('Content-Type', 'application/octet-stream'));
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'OAuth y0_owner_token'));

        $this->get('/canli/' . $live->code)->assertOk()->assertSee('Kameranı aç');
        $this->get(route('live.video', $live->code))->assertRedirect('https://downloader.disk.yandex.ru/disk/customer.mp4');

        // The customer's own link shows once the order is paid for.
        $this->actingAs($user)->get(route('orders.index'))->assertSee('Canlı şəklə bax')->assertSee($live->url());
        $order->update(['status' => 'awaiting_payment']);
        $this->actingAs($user)->get(route('orders.index'))->assertDontSee('Canlı şəklə bax');

        // The admin sees it on the order line, ready to print.
        $this->admin();
        Livewire::test(ItemsRelationManager::class, ['ownerRecord' => $order, 'pageClass' => EditOrder::class])
            ->assertSee('Canlı şəkil (AR) — ayrıca')->assertSee('Kamera üçün hazırdır')->assertSee('Video Yandex Diskdə')->assertSee('QR kod və ayarlar')
            ->assertDontSee('AR yarat');
    }

    public function test_without_yandex_disk_the_video_waits_on_the_hosting(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('live.store'), [
            'ar_photo' => UploadedFile::fake()->image('biz.jpg', 600, 800),
            'ar_video' => UploadedFile::fake()->create('film.mp4', 300, 'video/mp4'),
            'ar_mind' => UploadedFile::fake()->createWithContent('target.mind', 'not a mind file'),
        ])->assertRedirect(route('cart.index'));
        $live = $this->placeOrder($user)->items()->firstOrFail()->livePhotos()->firstOrFail();

        // Something that is not MindAR's data is dropped: the owner prepares it by hand.
        $this->assertNull($live->target_mind);
        $this->assertSame('hosting', $live->videoPlace());
        Storage::disk('public')->assertExists($live->video_path);
        $this->get(route('live.video', $live->code))->assertRedirect(\App\Support\Media::url($live->video_path));

        $this->artisan('live:push')->expectsOutputToContain('Yandex Disk is not connected')->assertSuccessful();

        // The owner connects his disk; the waiting video moves.
        $this->fakeOwnerDisk();
        $this->admin();
        Livewire::test(ListLivePhotos::class)
            ->callAction('yandex', ['token' => 'y0_owner_token', 'folder' => 'Canlı videolar'])
            ->assertHasNoActionErrors()
            ->assertNotified('Yandex Disk qoşuldu');
        $this->assertTrue(\App\Support\YandexDisk::hasToken());
        $this->assertNotSame('y0_owner_token', Setting::get(Setting::YANDEX_TOKEN));   // kept encrypted
        $this->assertSame('y0_owner_token', \App\Support\YandexDisk::token());

        $this->artisan('live:push')->expectsOutputToContain('moved to Yandex Disk: 1 of 1')->assertSuccessful();
        $live->refresh();
        $this->assertSame(['yandex', 'https://yadi.sk/i/customerVid1'], [$live->videoPlace(), $live->video_url]);
        Http::assertSent(fn ($r) => str_contains(urldecode($r->url()), 'disk:/Canlı videolar/sifaris-'));
    }

    public function test_a_token_yandex_refuses_is_not_kept(): void
    {
        Http::fake(['cloud-api.yandex.net/*' => Http::response(['error' => 'UnauthorizedError'], 401)]);
        $this->admin();
        Livewire::test(ListLivePhotos::class)
            ->callAction('yandex', ['token' => 'wrong', 'folder' => 'X'])
            ->assertNotified('Token işləmədi');
        $this->assertFalse(\App\Support\YandexDisk::hasToken());
    }

    public function test_customers_add_a_live_photo_to_their_box(): void
    {
        $box = $this->box();
        $this->admin();
        Livewire::test(ListLivePhotos::class)
            ->callAction('sale', ['enabled' => true, 'price' => 7.5])
            ->assertHasNoActionErrors();
        $this->assertSame(['1', '7.5'], [Setting::get(Setting::AR_ENABLED), Setting::get(Setting::AR_PRICE)]);

        $user = User::factory()->create();
        $this->actingAs($user)->get(route('products.customize', $box->slug))->assertOk()
            ->assertSee('Canlı şəkil (AR)')->assertSee('7.50 ₼')->assertSee('live-target.js', false)->assertSee('window.nefisDesign', false);

        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $box->id, 'ar_on' => 1])
            ->assertSessionHasErrors(['ar_video' => 'Canlı video üçün videonu yükləyin, ya da bu seçimi söndürün.']);
        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $box->id, 'ar_on' => 1,
            'ar_video' => UploadedFile::fake()->create('film.pdf', 100, 'application/pdf')])
            ->assertSessionHasErrors(['ar_video' => 'Video MP4, MOV və ya WEBM olmalıdır.']);

        // The page sends the box's design as the picture, with the camera's data.
        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $box->id, 'ar_on' => 1,
            'ar_video' => UploadedFile::fake()->create('film.mp4', 2048, 'video/mp4'),
            'ar_photo' => UploadedFile::fake()->image('design.jpg', 485, 948),
            'ar_mind' => $this->mind()])
            ->assertSessionHasNoErrors();
        $this->actingAs($user)->get(route('cart.index'))->assertSee('Canlı şəkil (AR)')->assertSee('12.50 ₼');   // 5 + 7.50

        $order = $this->placeOrder($user);
        $item = $order->items()->firstOrFail();
        $this->assertSame([7.5, 12.5], [$item->ar_price, $item->unitPrice()]);
        $live = $item->livePhotos()->firstOrFail();
        $this->assertSame('Sifariş #' . $order->id . ' — Test', $live->title);
        $this->assertTrue($live->isReady());   // the video plays from the hosting until Yandex Disk is connected
        $this->assertSame(1.9546, $live->aspect());
        $this->actingAs($user)->get(route('orders.index'))->assertSee('Canlı şəkil (AR)')->assertSee('Canlı şəklə bax');

        $this->admin();
        Livewire::test(ItemsRelationManager::class, ['ownerRecord' => $order, 'pageClass' => EditOrder::class])
            ->assertSee('qutunun üzərində')->assertSee('Video hostinqdə');
        $this->get('/admin/live-photos')->assertOk()->assertSee('Hostinqdə')->assertSee('Yandex Diski qoşun');
    }

    public function test_the_owner_still_makes_one_for_an_old_order_line(): void
    {
        $box = $this->box();
        $order = Order::create(['user_id' => User::factory()->create()->id, 'status' => 'pending', 'contact_phone' => '1', 'delivery_address' => 'x']);
        $item = $order->items()->create(['product_id' => $box->id, 'product_name' => 'Test', 'customer_photos' => [], 'custom_texts' => [],
            'quantity' => 1, 'ar_video' => 'cart-videos/old.mp4', 'ar_price' => 5]);

        $this->admin();
        Livewire::test(ItemsRelationManager::class, ['ownerRecord' => $order, 'pageClass' => EditOrder::class])
            ->assertSee('Videonu yüklə')->assertSee('AR yarat')->assertSee('order_item=' . $item->id, false);
        Livewire::withQueryParams(['order_item' => $item->id])->test(CreateLivePhoto::class)
            ->assertFormSet(['title' => 'Sifariş #' . $order->id . ' — Test', 'order_item_id' => $item->id]);
    }

    public function test_switched_off_there_is_nothing_to_buy(): void
    {
        Setting::put(Setting::AR_ENABLED, false);
        $this->get(route('live.create'))->assertNotFound();
        $this->post(route('live.store'))->assertNotFound();
        $this->get(route('home'))->assertDontSee(route('live.create'), false);
        $this->get(route('products.customize', $this->box()->slug))->assertDontSee('Canlı şəkil (AR)');
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
