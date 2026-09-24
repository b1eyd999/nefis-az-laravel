<?php

namespace Tests\Feature;

use App\Filament\Resources\ChocolateResource\Pages\ListChocolates;
use App\Models\Chocolate;
use App\Models\Market;
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
        $this->actingAs($user)->post(route('checkout.store'), ['delivery_method_id' => \App\Models\DeliveryMethod::where('type', 'door')->value('id'),
            'contact_phone' => '1', 'delivery_address' => 'Bakı, Nizami küç. 5'])
            ->assertRedirect(route('orders.index'));

        $item = Order::firstOrFail()->items()->firstOrFail();
        $this->assertSame([$bar->id, 'Kr/O Alenka Süd Şokoladı 90qr (90 q)', 3.9], [(int) $item->chocolate_id, $item->chocolate_name, $item->chocolate_price]);
        $this->assertSame(7.9, $item->unitPrice());
        $this->actingAs($user)->get(route('orders.index'))->assertSee('Kr/O Alenka Süd Şokoladı 90qr (90 q)')->assertSee('15.80 ₼', false);
    }

    public function test_bars_know_their_brand_from_the_name_unless_the_owner_set_one(): void
    {
        $names = [
            'Kr/O Alenka Süd Şokoladı 90qr' => 'Alenka', 'Südlü şokolad Победа şəkərsiz, 36% kakao, 100q' => 'Pobeda',
            'Şokolad BIanka Milk, 100 q' => 'Bianca', 'Fıstıqlı şokolad, Hamlet, 105 qram' => 'Hamlet',
            'Südlü şokolad Ferrero Rocher Hazelnut, 90 q' => 'Ferrero', 'Nestle Milk Filling 90qr' => 'Nestlé',
            'Şokolad Ritte Sport Ganze Mandel 100q' => 'Ritter Sport', 'Alpen Gold Fındıq və Uzum 100qr' => 'Alpen Gold',
            'Şekersiz şokolad, tünd, 100 q' => null,
        ];
        foreach ($names as $name => $brand) {
            $this->assertSame($brand, Chocolate::create(['name' => $name, 'base_price' => 3])->brand, $name);
        }

        $bar = Chocolate::create(['name' => 'Milka Oreo 92 qr', 'brand' => 'Mondelez', 'base_price' => 3]);
        $bar->update(['name' => 'Milka Oreo Plitka 92 qr']);
        $this->assertSame('Mondelez', $bar->fresh()->brand, "the owner's brand stays");
    }

    public function test_the_customer_sees_one_brand_at_a_time_top_brands_first(): void
    {
        $box = $this->box();
        $alenka = Chocolate::create(['name' => 'Kr/O Alenka Süd Şokoladı 90qr', 'base_price' => 3]);
        Chocolate::create(['name' => 'Milka Bubbles 90 qr', 'base_price' => 4]);
        Chocolate::create(['name' => 'Milka Oreo 92 qr', 'base_price' => 4]);
        Chocolate::create(['name' => 'Bianca Dark 100 q', 'base_price' => 4]);
        Chocolate::create(['name' => 'Şekersiz şokolad, tünd, 100 q', 'base_price' => 2]);
        $chips = function (string $html) {
            preg_match_all('/class="choc-brand([^"]*)"\s*data-brand="([^"]*)"/', $html, $m);

            return array_map(fn ($class, $brand) => $brand . (str_contains($class, 'top') ? '*' : ''), $m[1], $m[2]);
        };

        // Milka is a top brand by default (Alpen Gold too, but there is none): first, orange and open.
        $html = $this->get(route('products.customize', $box->slug))->assertOk()->getContent();
        $this->assertSame(['Milka*', 'Alenka', 'Bianca', 'Digər'], $chips($html), 'top brands, then A–Z, the unbranded last');
        $this->assertMatchesRegularExpression('/class="choc-brand top active"\s*data-brand="Milka"/', $html);
        $this->assertMatchesRegularExpression('/class="choc-group" data-brand="Milka"\s*>/', $html);
        $this->assertMatchesRegularExpression('/class="choc-group" data-brand="Alenka"\s*hidden/', $html);
        $this->assertStringContainsString('Milka <span>2</span>', $html);

        // The owner's own top list, in his order.
        Setting::put(Setting::TOP_BRANDS, json_encode(['Bianca', 'Alenka']));
        $html = $this->get(route('products.customize', $box->slug))->getContent();
        $this->assertSame(['Bianca*', 'Alenka*', 'Milka', 'Digər'], $chips($html));

        // Back from a failed add, the chosen bar's brand is the one open.
        $html = $this->withSession(['_old_input' => ['chocolate_id' => (string) $alenka->id]])
            ->get(route('products.customize', $box->slug))->getContent();
        $this->assertMatchesRegularExpression('/class="choc-brand top active has-pick"\s*data-brand="Alenka"/', $html);
        $this->assertMatchesRegularExpression('/value="' . $alenka->id . '"[^>]*\s+checked/', $html);
    }

    public function test_the_owner_picks_the_top_brands_in_the_panel(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListChocolates::class)
            ->assertActionHasLabel('top-brands', 'Top markalar')
            ->callAction('top-brands', ['brands' => ['Nestlé', ' Milka ', 'Nestlé']])
            ->assertHasNoActionErrors();

        $this->assertSame(['Nestlé', 'Milka'], Setting::topBrands());
    }

    public function test_bars_are_grouped_by_the_shop_they_come_from(): void
    {
        Http::fake(['b7x9kq.arazmarket.az/*' => Http::response($this->png())]);
        ArazMarket::sync([$this->listed(7538, 'Milka Plitka Şokolad Fındıqlı 90 qr', '4.50', '2.69', 40)]);

        $araz = Market::where('importer', 'arazmarket')->firstOrFail();
        $this->assertSame('Araz Market', $araz->name);
        $this->assertTrue($araz->canSync());
        $this->assertSame($araz->id, Chocolate::firstOrFail()->market_id);

        // A shop without an importer: its bars are added by hand.
        $bravo = Market::create(['name' => 'Bravo', 'slug' => 'bravo']);
        Chocolate::create(['market_id' => $bravo->id, 'name' => 'Bravo Süd Şokoladı 100 q', 'base_price' => 2.5]);
        Chocolate::create(['name' => 'Öz şokoladım', 'base_price' => 2]);
        $this->assertFalse($bravo->canSync());

        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListChocolates::class)
            ->assertSee(['Hamısı', 'Araz Market', 'Bravo', 'Marketsiz'])
            ->set('activeTab', 'market-' . $bravo->id)
            ->assertSee('Bravo Süd Şokoladı 100 q')
            ->assertDontSee('Milka Plitka Şokolad Fındıqlı 90 qr');

        $this->get('/admin/markets')->assertOk()->assertSee('Araz Market')->assertSee('Avtomatik')->assertSee('Bravo');

        // Removing a shop keeps its bars.
        $bravo->delete();
        $this->assertNull(Chocolate::where('name', 'Bravo Süd Şokoladı 100 q')->first()->market_id);
    }

    public function test_a_bar_the_owner_deleted_is_not_brought_back_by_the_next_import(): void
    {
        Http::fake(['b7x9kq.arazmarket.az/*' => Http::response($this->png())]);
        $listing = [
            $this->listed(7550, 'Milka Bubbles Kapuçino Şokolad 97 qr', '5.40', '2.99', 45),
            $this->listed(2699, 'Kr/O Alenka Süd Şokoladı 90qr', '3.00', '3.00'),
        ];
        ArazMarket::sync($listing);
        Chocolate::where('source_id', '7550')->firstOrFail()->delete();

        $r = ArazMarket::sync($listing);

        $this->assertSame(['found' => 2, 'created' => 0, 'updated' => 1, 'missing' => 0], $r);
        $this->assertSame(['Kr/O Alenka Süd Şokoladı 90qr'], Chocolate::pluck('name')->all());
        $this->assertSame(1, Chocolate::onlyTrashed()->count());

        // It can be brought back by hand.
        Chocolate::onlyTrashed()->firstOrFail()->restore();
        $this->assertSame(2, Chocolate::count());
    }

    public function test_the_bars_the_owner_deleted_before_soft_deletes_stay_out(): void
    {
        Http::fake(['b7x9kq.arazmarket.az/*' => Http::response($this->png())]);
        // A catalogue that had been imported, then lost Merci for good.
        ArazMarket::sync([$this->listed(2699, 'Kr/O Alenka Süd Şokoladı 90qr', '3.00', '3.00')]);

        $migration = require database_path('migrations/2026_09_19_000010_soft_delete_chocolates_and_add_birmarket.php');
        $migration->up();
        $migration->up(); // harmless twice

        $this->assertSame(['1593', '1600', '2279', '939'], Chocolate::onlyTrashed()->orderBy('source_id')->pluck('source_id')->all());

        $r = ArazMarket::sync([
            $this->listed(2699, 'Kr/O Alenka Süd Şokoladı 90qr', '3.00', '3.00'),
            $this->listed(1600, 'Merci Fındıq və Badam ilə 100 qr', '7.20', '7.20'),
            $this->listed(939, 'Babaevskiy Lyuks 90 qr', '3.50', '3.50'),
        ]);
        $this->assertSame(0, $r['created']);
        $this->assertSame(['Kr/O Alenka Süd Şokoladı 90qr'], Chocolate::pluck('name')->all());
    }

    public function test_birmarket_bars_are_read_from_its_catalogue_and_the_rest_left_out(): void
    {
        $offer = fn (float $now, float $before = 0, string $seller = 'ROSSMANN') => [
            'retail_price' => $now, 'old_price' => $before,
            'seller' => ['marketing_name' => ['id' => 1, 'name' => $seller]],
        ];
        $item = fn (int $id, string $name, array $o) => ['id' => $id, 'name' => $name, 'slugged_name' => 'bar-' . $id,
            'status' => 'active', 'default_offer' => $o, 'category_id' => 2600,
            'main_img' => ['medium' => 'https://strgimgr.umico.az/img/product/840/' . $id . '.jpeg']];

        Http::fake([
            'mp-catalog.umico.az/*' => Http::response(['products' => [
                $item(919158, 'Südlü şokolad Ferrero Rocher Hazelnut, 90 q', $offer(3.99, 8.99)),
                $item(2834009, 'Şokolad Bianca 72% Dark, 100 q', $offer(7.8, 8.1, 'ZEFIR AVROPA ŞİRNİYYATI')),
                $item(1710063, 'Plitka şokolad Yummy, 100 q', $offer(5, 0, 'Qərb Şirniyyatı')),
                $item(1829862, 'Fındıqlı şokoladlı konfet Messori, 100 q', $offer(8.07, 9)),
                $item(961160, 'Şokolad Toblerone White, 100 q', $offer(7.34, 8.9)),
                $item(191827, 'Şokolad Chikalab,tünd,fındıqlı,100 q, 4 əd', $offer(51.5, 57.2)),
                $item(916108, 'Vafli Forum Qaymaqlı, 100 q', $offer(7.8, 9.15)),
                $item(1234, 'Şokolad Alpen Gold 85 q', $offer(2.5)),
            ], 'meta' => ['total' => 8]]),
            'strgimgr.umico.az/*' => Http::response($this->png()),
        ]);

        $r = \App\Support\Birmarket::sync();

        $this->assertSame(['found' => 3, 'created' => 3, 'updated' => 0, 'missing' => 0], $r);
        $bir = Market::where('importer', 'birmarket')->firstOrFail();
        $ferrero = Chocolate::where('source', 'birmarket')->where('source_id', '919158')->firstOrFail();
        $this->assertSame([$bir->id, 8.99, 3.99, 56, 'ROSSMANN', 90.0],
            [$ferrero->market_id, $ferrero->base_price, $ferrero->sale_price, $ferrero->sale_percent, $ferrero->seller, $ferrero->weight_g]);
        $this->assertSame('https://birmarket.az/product/919158-bar-919158', $ferrero->source_url);
        $this->assertNull(Chocolate::where('source_id', '1710063')->first()->sale_price, 'no old price, no promotion');
        Storage::disk('public')->assertExists($ferrero->image);
    }

    public function test_bravo_bars_are_read_from_its_wolt_shelf(): void
    {
        // Wolt counts in qəpiks and gives the weight either in the name or in its own unit.
        $item = fn (string $id, string $name, int $price, int $before = 0, ?string $unit = null) => [
            'id' => $id, 'name' => $name, 'price' => $price, 'original_price' => $before ?: null,
            'unit_info' => $unit, 'disabled_info' => null, 'barcode_gtin' => '86000' . $id,
            'images' => [['url' => 'https://imageproxy.wolt.com/assets/' . $id]],
        ];

        Http::fake([
            'consumer-api.wolt.com/*' => Http::sequence()
                ->push(['items' => [
                    $item('a1', 'Milka Südlü Şokolad 90qr', 269, 449),
                    $item('a2', 'Monamo Südlü Plitka', 199, 349, '85 g'),          // too light for the box
                    $item('a3', 'Nestle Bitter Tünd Şokolad 70% 90qr', 249),
                ], 'metadata' => ['next_page_token' => 'page2']])
                ->push(['items' => [
                    $item('b1', 'Ritter Sport Südlü 100 q', 399),
                    $item('b2', 'Lindt Excellence 300qr', 1699),                    // too heavy
                ], 'metadata' => ['next_page_token' => null]]),
            'imageproxy.wolt.com/*' => Http::response($this->png()),
        ]);

        $r = \App\Support\Bravo::sync();

        $this->assertSame(['found' => 3, 'created' => 3, 'updated' => 0, 'missing' => 0], $r);
        $bravo = Market::where('importer', 'bravo')->firstOrFail();
        $milka = Chocolate::where('source', 'bravo')->where('source_id', 'a1')->firstOrFail();
        $this->assertSame([$bravo->id, 4.49, 2.69, 40, 'Bravo', 90.0],
            [$milka->market_id, $milka->base_price, $milka->sale_price, $milka->sale_percent, $milka->seller, $milka->weight_g]);
        $this->assertNull(Chocolate::where('source_id', 'a3')->first()->sale_price, 'no price before, no promotion');
        Storage::disk('public')->assertExists($milka->image);
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
