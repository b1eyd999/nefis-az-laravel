<?php

namespace Tests\Feature;

use App\Filament\Resources\WrappingResource\Pages\CreateWrapping;
use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Wrapping;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class WrappingTest extends TestCase
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

    private function wrap(string $name, float $price, array $extra = []): Wrapping
    {
        return Wrapping::create($extra + ['name' => $name, 'pattern' => 'wrappings/' . strtolower($name) . '.png', 'price' => $price,
            'ribbon' => Wrapping::SATIN, 'ribbon_color' => '#F3D3B4', 'pattern_scale' => 0.5]);
    }

    public function test_the_owner_adds_a_wrap_with_its_paper_and_ribbon(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ADMIN]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->get('/admin/wrappings')->assertOk()->assertSee('Qablaşdırma');

        Livewire::test(CreateWrapping::class)
            ->fillForm([
                'name' => 'Ürəklər', 'price' => 2, 'ribbon' => Wrapping::SATIN, 'ribbon_color' => '#F3D3B4', 'pattern_scale' => 0.45,
                'pattern' => UploadedFile::fake()->image('urekler.png', 400, 400),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $wrap = Wrapping::firstOrFail();
        $this->assertSame(['Ürəklər', 2.0, 'satin', 0.45], [$wrap->name, $wrap->price, $wrap->ribbon, $wrap->pattern_scale]);
        Storage::disk('public')->assertExists($wrap->pattern);

        $this->get('/admin/wrappings/' . $wrap->id . '/edit')->assertOk()->assertSee('wrap-render.js');
        $this->actingAs(User::factory()->create(['role' => User::MANAGER]))->get('/admin/wrappings')->assertForbidden();
    }

    public function test_the_customer_sees_the_papers_grouped_by_price(): void
    {
        $this->wrap('Ürəklər', 2);
        $this->wrap('Hədiyyələr', 2);
        $this->wrap('Kraft', 3, ['ribbon' => Wrapping::TWINE, 'ribbon_color' => '#D8B27A']);
        $this->wrap('Gizli', 3, ['is_active' => false]);

        $html = $this->get(route('products.customize', $this->box()->slug))->assertOk()
            ->assertSee('Hədiyyə qablaşdırması')
            ->assertSee('Qablaşdırmasız')
            ->assertSee(['Ürəklər', 'Hədiyyələr', 'Kraft'])
            ->assertDontSee('Gizli')
            ->assertSee('js/wrap-render.js', false)
            ->getContent();

        preg_match_all('/<div class="wrap-price">([^<]+)<\/div>/', $html, $groups);
        $this->assertSame(['+2 ₼', '+3 ₼'], $groups[1]);
        $this->assertStringContainsString('data-ribbon="twine"', $html);
    }

    public function test_the_wraps_have_a_page_of_their_own_in_the_menu(): void
    {
        // No wraps yet: no menu item.
        $this->get(route('home'))->assertOk()->assertDontSee(route('wrappings.index'), false);

        $this->wrap('Ürəklər', 2);
        $this->wrap('Kraft', 3, ['ribbon' => Wrapping::TWINE, 'ribbon_color' => '#D8B27A']);

        $this->get(route('home'))->assertSee('>Qablaşdırma</a>', false);
        $html = $this->get(route('wrappings.index'))->assertOk()
            ->assertSee(['Qablaşdırma', 'Ürəklər', 'Kraft', 'Kəndir (cut)', 'Dizayn seç'])
            ->assertSee('js/gift-box.js', false)
            ->getContent();
        $this->assertSame(2, substr_count($html, 'class="wr-card" data-gift-open'), 'each box opens the 360° viewer');
        $this->assertStringContainsString('data-ribbon="twine"', $html);
    }

    public function test_the_wrap_goes_into_the_price_and_the_order(): void
    {
        $box = $this->box();
        $wrap = $this->wrap('Ürəklər', 2);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $box->id, 'wrapping_id' => $wrap->id, 'quantity' => 2])
            ->assertSessionHasNoErrors();
        $this->actingAs($user)->get(route('cart.index'))->assertSee('Ürəklər')->assertSee('14 ₼', false);   // 2 × (5 + 2)

        $this->actingAs($user)->post(route('checkout.store'), [
            'delivery_method_id' => DeliveryMethod::where('type', 'door')->value('id'),
            'contact_phone' => '1', 'delivery_address' => 'Bakı, Nizami küç. 5',
        ])->assertRedirect(route('orders.index'));

        $item = Order::firstOrFail()->items()->firstOrFail();
        $this->assertSame([$wrap->id, 'Ürəklər', 2.0], [(int) $item->wrapping_id, $item->wrapping_name, $item->wrapping_price]);
        $this->assertSame(7.0, $item->unitPrice());

        // A price change later does not touch what was ordered.
        $wrap->update(['price' => 9]);
        $this->assertSame(7.0, $item->fresh()->unitPrice());
        $this->actingAs($user)->get(route('orders.index'))->assertSee('Ürəklər');
    }

    public function test_a_hidden_wrap_cannot_be_ordered_and_none_is_free(): void
    {
        $box = $this->box();
        $hidden = $this->wrap('Gizli', 3, ['is_active' => false]);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $box->id, 'wrapping_id' => $hidden->id])
            ->assertSessionHasErrors(['wrapping_id' => 'Seçdiyiniz qablaşdırma artıq yoxdur, başqasını seçin.']);

        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $box->id, 'wrapping_id' => ''])
            ->assertSessionHasNoErrors();
        $this->actingAs($user)->get(route('cart.index'))->assertSee('5 ₼', false)->assertDontSee('Qablaşdırma:');
    }
}
