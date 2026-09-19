<?php

namespace Tests\Feature;

use App\Filament\Resources\ChocolateResource\Pages\ListChocolates;
use App\Models\Chocolate;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Support\ArazMarket;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ChocolateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function listed(int $id, string $title, string $regular, string $now, ?int $pct = null, int $category = 635): array
    {
        return ['id' => $id, 'title' => $title, 'slug' => 'bar-' . $id, 'barcode' => '100' . $id,
            'sales_price' => $regular, 'discount_price' => $now, 'is_discount' => $pct !== null, 'discount_percent' => $pct,
            'category_id' => $category, 'images' => ['https://b7x9kq.arazmarket.az/storage/products/' . $id . '.png']];
    }

    /** A category page the way Next.js ships it: JSON split over escaped script chunks. */
    private function page(array $products, int $lastPage = 1): string
    {
        $rsc = '5:["$","$L22",null,{"page_type":"categories","data":{"products":' . json_encode($products, JSON_UNESCAPED_UNICODE)
            . ',"discount_products":[{"id":1,"title":"Colgate 100 ml","category_id":4334}],"pagination":{"current_page":1,"last_page":' . $lastPage . ',"total":47}}}]';
        $chunks = str_split($rsc, (int) ceil(strlen($rsc) / 3));

        return '<html><body>' . implode('', array_map(
            fn ($c) => '<script>self.__next_f.push([1,' . json_encode($c, JSON_UNESCAPED_UNICODE) . '])</script>', $chunks)) . '</body></html>';
    }

    private function png(): string
    {
        $im = imagecreatetruecolor(40, 80);
        ob_start();
        imagepng($im);

        return ob_get_clean();
    }

    public function test_the_page_is_read_and_only_90_to_105_gram_bars_are_taken(): void
    {
        $products = [
            $this->listed(7538, 'Milka Plitka Şokolad Fındıqlı 90 qr', '4.50', '2.69', 40),
            $this->listed(2607, 'Schogetten Tund Şokolad 100qr', '6.00', '6.00'),
            $this->listed(7681, 'Milka Fındıq Südlü Plitka Şokolad 300qr', '13.90', '7.99', 43),
            $this->listed(7708, 'Milka Plitka Şokolad Lu Südlü 87 qr', '5.40', '2.99', 45),
            $this->listed(1372, 'Club 4 Paws Nəm İt Yemi 100qr', '1.30', '1.15', 12, 1923),
        ];
        [$items, $lastPage] = ArazMarket::parse($this->page($products, 2));

        $this->assertSame(2, $lastPage);
        $this->assertCount(5, $items);
        $this->assertSame('Milka Plitka Şokolad Fındıqlı 90 qr', $items[0]['title']);
        $this->assertSame([7538, 2607], array_column(array_values(array_filter($items, [ArazMarket::class, 'isBoxBar'])), 'id'));
        $this->assertSame(97.0, ArazMarket::grams('Milka Bubbles Kapuçino Şokolad 97 qr'));
        $this->assertSame(100.0, ArazMarket::grams('Merci Südlü 100 qr'));
    }

    public function test_syncing_keeps_the_owners_edits_and_flags_bars_the_shop_dropped(): void
    {
        Http::fake(['b7x9kq.arazmarket.az/*' => Http::response($this->png())]);
        $first = [
            $this->listed(7538, 'Milka Plitka Şokolad Fındıqlı 90 qr', '4.50', '2.69', 40),
            $this->listed(2699, 'Kr/O Alenka Süd Şokoladı 90qr', '3.00', '3.00'),
        ];
        $r = ArazMarket::sync($first);
        $this->assertSame(['found' => 2, 'created' => 2, 'updated' => 0, 'missing' => 0], $r);

        $milka = Chocolate::where('source_id', '7538')->firstOrFail();
        $this->assertSame([4.5, 2.69, 40, 90.0], [$milka->base_price, $milka->sale_price, $milka->sale_percent, $milka->weight_g]);
        Storage::disk('public')->assertExists($milka->image);

        // The owner renames it and sets a markup of its own; the promotion ends.
        $milka->update(['name' => 'Milka fındıqlı', 'markup_percent' => 50, 'is_active' => false]);
        $r = ArazMarket::sync([$this->listed(7538, 'Milka Plitka Şokolad Fındıqlı 90 qr', '4.60', '4.60')]);

        $milka->refresh();
        $this->assertSame(['found' => 1, 'created' => 0, 'updated' => 1, 'missing' => 1], $r);
        $this->assertSame(['Milka fındıqlı', 50, false, 4.6, null], [$milka->name, $milka->markup_percent, $milka->is_active, $milka->base_price, $milka->sale_price]);
        $this->assertFalse(Chocolate::where('source_id', '2699')->first()->in_source);

        // A page with no bars at all changes nothing.
        $this->expectException(\RuntimeException::class);
        ArazMarket::sync([$this->listed(7681, 'Milka 300qr', '13.90', '7.99', 43)]);
    }

    public function test_the_customer_price_is_the_shop_price_plus_the_markup(): void
    {
        $bar = Chocolate::create(['name' => 'Alenka', 'base_price' => 3.00, 'sale_price' => 2.49]);

        $this->assertSame(3.9, $bar->price(), '3 ₼ + the default 30 %');

        Setting::put(Setting::CHOCOLATE_MARKUP, 50);
        $this->assertSame(4.5, $bar->fresh()->price());

        $bar->update(['markup_percent' => 10]);
        $this->assertSame(3.3, $bar->fresh()->price(), 'a bar may have its own markup');

        Setting::put(Setting::CHOCOLATE_FROM_SALE, true);
        $this->assertSame(2.74, $bar->fresh()->price(), 'priced from the promotion when asked');
    }

    private function box(): Product
    {
        $box = Product::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true, 'price' => 4,
            'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/' . $box->id . '/bg.webp', 'x' => 0, 'y' => 0,
            'width' => 969, 'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);

        return $box;
    }

    public function test_the_customer_picks_a_bar_and_its_price_goes_into_the_order(): void
    {
        $box = $this->box();
        $bar = Chocolate::create(['name' => 'Kr/O Alenka Süd Şokoladı 90qr', 'weight_g' => 90, 'base_price' => 3.00]);
        Chocolate::create(['name' => 'Gizli', 'base_price' => 1, 'is_active' => false]);

        $this->get(route('products.customize', $box->slug))
            ->assertOk()
            ->assertSee('Qutunun içindəki şokolad')
            ->assertSee('Kr/O Alenka Süd Şokoladı 90qr')
            ->assertSee('+3.90 ₼')
            ->assertDontSee('Gizli');

        $user = User::factory()->create();
        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $box->id])
            ->assertSessionHasErrors('chocolate_id');

        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $box->id, 'chocolate_id' => $bar->id, 'quantity' => 2])
            ->assertSessionHasNoErrors();
        $this->actingAs($user)->get(route('cart.index'))->assertSee('15.80 ₼', false);

        // A later price change does not touch what is in the cart.
        $bar->update(['base_price' => 5]);
        $this->actingAs($user)->post(route('checkout.store'), ['contact_phone' => '1', 'delivery_address' => 'Bakı'])
            ->assertRedirect(route('orders.index'));

        $item = Order::firstOrFail()->items()->firstOrFail();
        $this->assertSame([$bar->id, 'Kr/O Alenka Süd Şokoladı 90qr (90 q)', 3.9], [(int) $item->chocolate_id, $item->chocolate_name, $item->chocolate_price]);
        $this->assertSame(7.9, $item->unitPrice());
        $this->actingAs($user)->get(route('orders.index'))->assertSee('Kr/O Alenka Süd Şokoladı 90qr (90 q)')->assertSee('15.80 ₼', false);
    }

    public function test_the_owner_sets_the_common_markup_in_the_panel(): void
    {
        Chocolate::create(['name' => 'Alenka', 'base_price' => 3.00]);
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->get('/admin/chocolates')->assertOk()->assertSee('Alenka')->assertSee('3.90 ₼');

        Livewire::test(ListChocolates::class)
            ->callAction('markup', ['markup' => 40, 'from_sale' => false])
            ->assertHasNoActionErrors();

        $this->assertSame('40', Setting::get(Setting::CHOCOLATE_MARKUP));
        $this->assertSame(4.2, Chocolate::first()->price());
    }
}
