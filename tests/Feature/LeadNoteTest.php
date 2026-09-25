<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Before a customer starts filling in a box he is told how long it takes and
 * what it costs to be served before the others, so neither is a surprise at
 * the end. Both numbers are the owner's, and a fee of nothing means the shop
 * is not offering the queue jump at all.
 */
class LeadNoteTest extends TestCase
{
    use RefreshDatabase;

    private function box(): Product
    {
        $box = Product::create(['name' => 'Love Story', 'slug' => 'love-story', 'is_active' => true, 'price' => 4.90,
            'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/bg.webp', 'x' => 0, 'y' => 0, 'width' => 969,
            'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);
        $box->photoSlots()->create(['label' => null, 'x' => 0, 'y' => 0, 'width' => 400, 'height' => 500,
            'rotation' => 0, 'shape' => 'rectangle', 'sort_order' => 0]);

        return $box;
    }

    public function test_the_page_says_how_long_it_takes_and_what_hurrying_costs(): void
    {
        Setting::put(Setting::DELIVERY_LEAD_DAYS, 2);
        Setting::put(Setting::RUSH_FEE, 3);

        $this->get(route('products.customize', $this->box()->slug))->assertOk()
            ->assertSee('Sifariş 2 gün ərzində hazırlanır.')
            ->assertSee('«Təcili hazırlansın» seçsəniz (+3 ₼)', false);
    }

    public function test_the_owner_changes_both_numbers(): void
    {
        Setting::put(Setting::DELIVERY_LEAD_DAYS, 4);
        Setting::put(Setting::RUSH_FEE, 5.5);

        $this->get(route('products.customize', $this->box()->slug))->assertOk()
            ->assertSee('Sifariş 4 gün ərzində hazırlanır.')
            ->assertSee('(+5.50 ₼)', false);
    }

    public function test_no_fee_means_no_offer(): void
    {
        Setting::put(Setting::RUSH_FEE, 0);

        $this->get(route('products.customize', $this->box()->slug))->assertOk()
            ->assertSee('Sifariş 2 gün ərzində hazırlanır.')
            ->assertDontSee('Təcili hazırlansın');
    }

    public function test_the_customer_reads_it_in_his_own_language(): void
    {
        Setting::put(Setting::SITE_LANGUAGES, 'az,ru,en');
        Setting::put(Setting::RUSH_FEE, 3);

        $this->get(route('ru.products.customize', $this->box()->slug))->assertOk()
            ->assertSee('Заказ готовится в течение 2 дней.')
            ->assertSee('«Срочно» (+3 ₼)', false);
    }
}
