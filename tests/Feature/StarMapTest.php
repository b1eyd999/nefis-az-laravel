<?php

namespace Tests\Feature;

use App\Filament\Resources\OrderResource\Pages\EditOrder;
use App\Filament\Resources\OrderResource\RelationManagers\ItemsRelationManager;
use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\PhotoSlot;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The sky over one place on one night, printed on a box.
 *
 * A window of the design is told to hold the stars instead of a photograph;
 * the customer then names the date, the hour and the place, and nothing is
 * uploaded. What the order keeps is those numbers — never a picture — so the
 * workshop can draw the same sky again at printing size whenever it likes.
 */
class StarMapTest extends TestCase
{
    use RefreshDatabase;

    private function box(bool $alsoPhoto = false): Product
    {
        $box = Product::create(['name' => 'Ulduz', 'slug' => 'ulduz', 'is_active' => true, 'price' => 9,
            'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/' . $box->id . '/bg.webp', 'x' => 0, 'y' => 0,
            'width' => 969, 'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);

        if ($alsoPhoto) {
            $box->photoSlots()->create(['label' => 'Sizin şəkil', 'x' => 40, 'y' => 1200, 'width' => 400, 'height' => 400,
                'rotation' => 0, 'shape' => 'rectangle', 'sort_order' => 0]);
        }

        $box->photoSlots()->create(['label' => null, 'fill' => PhotoSlot::SKY, 'sky_style' => 'navy', 'sky_ring' => true,
            'x' => 84, 'y' => 120, 'width' => 800, 'height' => 800, 'rotation' => 0, 'shape' => 'ellipse',
            'sort_order' => $alsoPhoto ? 1 : 0]);

        return $box;
    }

    public function test_the_design_page_asks_for_the_night_and_not_for_a_photograph(): void
    {
        $box = $this->box();

        $html = $this->get(route('products.customize', $box->slug))->assertOk()
            ->assertSee('Ulduz xəritəsi')
            ->assertSee('name="star_date"', false)
            ->assertSee('name="star_time"', false)
            ->assertSee('js/star-map.js', false)
            // The window holds the sky, so no file is asked for at all.
            ->assertDontSee('name="photos[0]"', false)
            ->getContent();

        $this->assertStringContainsString('"fill":"sky"', $html);
        $this->assertStringContainsString('"skyStyle":"navy"', $html);
        $this->assertStringContainsString('Bakı', $html);
    }

    public function test_a_design_without_one_is_left_alone(): void
    {
        $plain = Product::create(['name' => 'Adi', 'slug' => 'adi', 'is_active' => true, 'price' => 9,
            'template_width' => 969, 'template_height' => 1895]);
        $plain->layers()->create(['name' => 'BG', 'image' => 'boxes/bg.webp', 'x' => 0, 'y' => 0, 'width' => 969,
            'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);
        $plain->photoSlots()->create(['label' => null, 'x' => 0, 'y' => 0, 'width' => 969, 'height' => 1000,
            'rotation' => 0, 'shape' => 'rectangle', 'sort_order' => 0]);

        $this->get(route('products.customize', $plain->slug))->assertOk()
            ->assertDontSee('name="star_date"', false)
            ->assertDontSee('js/star-map.js', false)
            ->assertSee('name="photos[0]"', false);
    }

    public function test_the_order_keeps_the_night_and_the_workshop_can_print_it(): void
    {
        Storage::fake('public');
        $box = $this->box(true);
        $user = User::factory()->create();

        // The photograph is for the first window only; the second is the sky.
        $this->actingAs($user)->post(route('cart.add'), [
            'product_id' => $box->id,
            'photos' => [UploadedFile::fake()->image('me.jpg', 60, 60)],
            'star_date' => '2019-06-08', 'star_time' => '23:15',
            'star_lat' => 38.7925, 'star_lon' => 48.4797, 'star_place' => 'Lənkəran',
        ])->assertSessionHasNoErrors();

        $this->actingAs($user)->post(route('checkout.store'), [
            'delivery_method_id' => DeliveryMethod::where('type', 'door')->value('id'),
            'contact_phone' => '1', 'delivery_address' => 'Bakı, Nizami küç. 5',
        ])->assertSessionHasNoErrors();

        $item = Order::latest('id')->firstOrFail()->items()->firstOrFail();
        $this->assertSame(['2019-06-08', '23:15', 'Lənkəran', 4], [
            $item->star_map['date'], $item->star_map['time'], $item->star_map['place'], $item->star_map['tz'],
        ]);
        // One photograph, one name for it: the sky window is in neither list.
        $this->assertCount(1, $item->customer_photos);
        $this->assertSame(['1. Sizin şəkil'], $item->photo_labels);

        $staff = User::factory()->create(['is_admin' => true]);
        $this->actingAs($staff)->get(route('star.print', $item))->assertOk()
            ->assertSee('08.06.2019')->assertSee('23:15')
            // Escaped the way the page prints it: the marks for minutes and seconds.
            ->assertSee(\App\Support\Sky::coordinates(38.7925, 48.4797))
            ->assertSee('js/star-data.js', false);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ItemsRelationManager::class, ['ownerRecord' => $item->order, 'pageClass' => EditOrder::class])
            ->assertSee('Ulduz xəritəsi')->assertSee('Lənkəran');
    }

    public function test_a_night_nobody_named_is_refused(): void
    {
        $box = $this->box();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $box->id])
            ->assertSessionHasErrors(['star_date', 'star_time', 'star_lat', 'star_lon']);

        $this->actingAs($user)->post(route('cart.add'), [
            'product_id' => $box->id, 'star_date' => '2019-06-08', 'star_time' => '23:15',
            'star_lat' => 200, 'star_lon' => 48.4797,
        ])->assertSessionHasErrors('star_lat');
    }

    public function test_only_staff_may_open_the_printing_page(): void
    {
        Storage::fake('public');
        $box = $this->box();
        $order = Order::create(['user_id' => User::factory()->create()->id, 'status' => 'pending',
            'contact_phone' => '1', 'delivery_address' => 'x']);
        $plain = $order->items()->create(['product_id' => $box->id, 'product_name' => 'Ulduz',
            'customer_photos' => [], 'custom_texts' => [], 'quantity' => 1]);
        $withSky = $order->items()->create(['product_id' => $box->id, 'product_name' => 'Ulduz',
            'customer_photos' => [], 'custom_texts' => [], 'quantity' => 1,
            'star_map' => ['date' => '2020-01-01', 'time' => '20:00', 'lat' => 40.3777, 'lon' => 49.892, 'tz' => 4, 'place' => 'Bakı']]);

        $this->actingAs(User::factory()->create())->get(route('star.print', $withSky))->assertForbidden();
        // Nothing to print for a line that carries no sky.
        $this->actingAs(User::factory()->create(['is_admin' => true]))->get(route('star.print', $plain))->assertNotFound();
    }

    public function test_the_owner_sets_the_window_to_the_sky_in_the_editor(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);
        $box = Product::create(['name' => 'Redaktor', 'slug' => 'redaktor', 'is_active' => true]);

        $image = $this->actingAs($admin)
            ->post(route('box.asset', $box->slug), ['file' => UploadedFile::fake()->image('bg.png', 969, 1895)], ['Accept' => 'application/json'])
            ->json('image');

        $this->actingAs($admin)->postJson(route('box.save', $box->slug), [
            'layers' => [['name' => 'BG', 'image' => $image, 'x' => 0, 'y' => 0, 'width' => 969, 'height' => 1895,
                'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'locked' => true]],
            'photos' => [['label' => null, 'fill' => 'sky', 'sky_style' => 'crimson', 'sky_ring' => 0, 'shape' => 'heart',
                'x' => 100, 'y' => 200, 'width' => 700, 'height' => 700, 'rotation' => 0]],
            'texts' => [],
        ])->assertOk();

        $slot = $box->fresh()->photoSlots()->firstOrFail();
        $this->assertSame(['sky', 'crimson', 'heart'], [$slot->fill, $slot->sky_style, $slot->shape]);
        $this->assertFalse($slot->sky_ring);

        // A colour the drawing does not know is not stored.
        $this->actingAs($admin)->postJson(route('box.save', $box->slug), [
            'layers' => [['name' => 'BG', 'image' => $image, 'x' => 0, 'y' => 0, 'width' => 969, 'height' => 1895,
                'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'locked' => true]],
            'photos' => [['label' => null, 'fill' => 'sky', 'sky_style' => 'neon', 'shape' => 'ellipse',
                'x' => 0, 'y' => 0, 'width' => 700, 'height' => 700, 'rotation' => 0]],
            'texts' => [],
        ])->assertStatus(422);
    }
}
