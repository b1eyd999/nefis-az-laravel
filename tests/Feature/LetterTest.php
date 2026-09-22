<?php

namespace Tests\Feature;

use App\Filament\Pages\SiteSettings;
use App\Models\DeliveryMethod;
use App\Models\Material;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class LetterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function box(): Product
    {
        $box = Product::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true, 'price' => 5,
            'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/' . $box->id . '/bg.webp', 'x' => 0, 'y' => 0,
            'width' => 969, 'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);

        return $box;
    }

    private function checkout(User $user): Order
    {
        $this->actingAs($user)->post(route('checkout.store'), [
            'delivery_method_id' => DeliveryMethod::where('type', 'door')->value('id'),
            'contact_phone' => '1', 'delivery_address' => 'Bakı, Nizami küç. 5',
        ])->assertRedirect(route('orders.index'));

        return Order::latest('id')->firstOrFail();
    }

    public function test_a_letter_is_bought_on_its_own(): void
    {
        $this->get(route('letters.create'))->assertOk()
            ->assertSee('Polaroid məktub')
            ->assertSee('3 ₼')
            ->assertSee('class="polaroid', false);

        $user = User::factory()->create();
        $this->actingAs($user)->post(route('letters.store'), [
            'letter_text' => "Ad günün mübarək!\nSəni sevirəm",
            'letter_photo' => UploadedFile::fake()->image('biz.jpg', 600, 600),
            'quantity' => 2,
        ])->assertRedirect(route('cart.index'));

        $this->actingAs($user)->get(route('cart.index'))->assertOk()->assertSee('Polaroid məktub')->assertSee('6 ₼', false);

        $order = $this->checkout($user);
        $item = $order->items()->firstOrFail();
        $this->assertNull($item->product_id);
        $this->assertTrue($item->isLetterOnly());
        $this->assertSame(['Polaroid məktub', "Ad günün mübarək!\nSəni sevirəm", 3.0, 2], [$item->product_name, $item->letter_text, $item->letter_price, $item->quantity]);
        Storage::disk('public')->assertExists($item->letter_photo);
        $this->assertSame(6.0, $order->itemsTotal());

        // No box, so no box paper or glue comes out of stock.
        $this->assertSame(0.0, (float) $order->fresh()->materials_cost);
        $this->assertSame(0.0, (float) Material::where('name', 'Qutu kağızı')->value('stock'));

        $this->actingAs($user)->get(route('orders.index'))->assertOk()->assertSee('Polaroid məktub');
    }

    public function test_a_letter_needs_a_photo_or_words(): void
    {
        $this->actingAs(User::factory()->create())->post(route('letters.store'), ['letter_text' => ''])
            ->assertSessionHasErrors(['letter_text' => 'Şəkil və ya mətn əlavə edin.']);

        $this->actingAs(User::factory()->create())->post(route('letters.store'), ['letter_text' => str_repeat('a', 181)])
            ->assertSessionHasErrors('letter_text');
    }

    public function test_a_letter_goes_inside_a_box(): void
    {
        $box = $this->box();
        $this->get(route('products.customize', $box->slug))->assertSee('Qutunun içinə polaroid məktub qoy');

        $user = User::factory()->create();
        // Switched on but empty: say so.
        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $box->id, 'letter_on' => 1])
            ->assertSessionHasErrors('letter_text');

        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $box->id, 'letter_on' => 1, 'letter_text' => 'Sevgilərlə'])
            ->assertSessionHasNoErrors();
        $this->actingAs($user)->get(route('cart.index'))->assertSee('Polaroid məktub · Sevgilərlə')->assertSee('8 ₼', false);   // 5 + 3

        $item = $this->checkout($user)->items()->firstOrFail();
        $this->assertSame([$box->id, 'Sevgilərlə', 3.0], [(int) $item->product_id, $item->letter_text, $item->letter_price]);
        $this->assertFalse($item->isLetterOnly());
        $this->assertSame(8.0, $item->unitPrice());

        // Not switched on: whatever was typed is not sold.
        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $box->id, 'letter_text' => 'unudulmuş']);
        $this->assertNull(session('cart_items')[0]['letter'] ?? null);
    }

    public function test_the_owner_prices_it_or_takes_it_off_sale(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ADMIN]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(SiteSettings::class)
            ->fillForm(['letter_enabled' => true, 'letter_price' => 4.5, 'letter_max' => 120])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(['1', '4.5', '120'], [Setting::get(Setting::LETTER_ENABLED), Setting::get(Setting::LETTER_PRICE), Setting::get(Setting::LETTER_MAX)]);
        $this->get(route('letters.create'))->assertSee('4.50 ₼');

        Setting::put(Setting::LETTER_ENABLED, false);
        $this->get(route('letters.create'))->assertNotFound();
        $this->get(route('products.customize', $this->box()->slug))->assertDontSee('polaroid məktub qoy');
        $this->get(route('home'))->assertDontSee(route('letters.create'), false);
    }
}
