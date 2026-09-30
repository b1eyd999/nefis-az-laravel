<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductCategoryResource\Pages\CreateProductCategory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The catalogue's shelves: the owner puts them up, names them, arranges them.
 */
class CategoryTest extends TestCase
{
    use RefreshDatabase;

    private function design(string $name, string $slug, ?string $category): Product
    {
        $product = Product::create([
            'name' => $name, 'slug' => $slug, 'is_active' => true,
            'category' => $category, 'price' => 10,
        ]);
        // Something to draw, or the catalogue treats it as unfinished.
        $product->photoSlots()->create(['label' => 'Şəkil', 'x' => 0, 'y' => 0,
            'width' => 900, 'height' => 900, 'rotation' => 0, 'shape' => 'rectangle']);

        return $product;
    }

    public function test_the_shop_starts_with_the_shelves_it_had(): void
    {
        $this->assertSame(
            ['sokolad', 'poster', 'love-is', 'xerite', 'spotify'],
            ProductCategory::inOrder()->pluck('slug')->all()
        );
    }

    public function test_the_catalogue_is_arranged_the_way_the_owner_arranged_it(): void
    {
        $this->design('Qara qutu', 'qara-qutu', 'sokolad');
        $this->design('Ulduz poster', 'ulduz-poster', 'xerite');

        // He renames one shelf and drags it to the front.
        ProductCategory::where('slug', 'xerite')->update(['name' => 'Ulduz xəritələri', 'sort_order' => -1]);

        $page = $this->get(route('designs.index'))->assertOk();
        $html = $page->getContent();

        $this->assertStringContainsString('Ulduz xəritələri', $html);
        $this->assertLessThan(
            strpos($html, 'data-filter="sokolad"'),
            strpos($html, 'data-filter="xerite"'),
            'the shelf he moved to the front should be drawn first'
        );
    }

    public function test_a_design_on_a_shelf_that_is_gone_is_still_shown(): void
    {
        // The bug this replaced: the page walked the list of shelves, so a
        // design filed under anything else was counted and never drawn.
        $this->design('Zarafat', 'zarafat-qutu', 'zarafat');
        $this->design('Adsız', 'adsiz-qutu', null);
        $this->design('Qara qutu', 'qara-qutu', 'sokolad');

        $page = $this->get(route('designs.index'))->assertOk();
        $html = $page->getContent();

        // The count in the chip and the cards on the page are the same three.
        $this->assertStringContainsString('Hamısı (3)', $html);
        $this->assertSame(3, substr_count($html, 'class="d-card"'));
        $this->assertStringContainsString('Zarafat', $html);
        $this->assertStringContainsString('Adsız', $html);
        $this->assertStringContainsString('data-filter="basqa"', $html);
    }

    public function test_a_switched_off_shelf_keeps_its_designs_on_the_page(): void
    {
        $this->design('Love qutusu', 'love-qutusu', 'love-is');
        ProductCategory::where('slug', 'love-is')->update(['is_active' => false]);

        $html = $this->get(route('designs.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('data-filter="love-is"', $html);
        $this->assertStringContainsString('Love qutusu', $html, 'the design itself must not disappear');
        $this->assertStringContainsString('data-filter="basqa"', $html);
    }

    public function test_the_owner_adds_a_shelf_in_the_panel(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($admin)->test(CreateProductCategory::class)
            ->fillForm(['name' => 'Ad günü', 'slug' => 'ad-gunu', 'is_active' => true, 'sort_order' => 9])
            ->call('create')
            ->assertHasNoFormErrors();

        $shelf = ProductCategory::where('slug', 'ad-gunu')->firstOrFail();
        $this->assertSame('Ad günü', $shelf->label());

        $this->design('Tort qutusu', 'tort-qutusu', 'ad-gunu');
        $this->assertStringContainsString('Ad günü', $this->get(route('designs.index'))->getContent());
    }

    public function test_the_panel_page_is_the_admins_alone(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/admin/product-categories')->assertOk()->assertSee('Kateqoriyalar');

        $manager = User::factory()->create(['role' => User::MANAGER]);
        $this->actingAs($manager)->get('/admin/product-categories')->assertForbidden();
    }

    public function test_a_shelf_still_in_use_cannot_be_deleted_by_accident(): void
    {
        $this->design('Qara qutu', 'qara-qutu', 'sokolad');

        $used = ProductCategory::where('slug', 'sokolad')->firstOrFail();
        $free = ProductCategory::where('slug', 'poster')->firstOrFail();

        $this->assertTrue($used->products()->exists());
        $this->assertFalse($free->products()->exists());
    }

    public function test_the_product_form_still_offers_a_shelf_that_is_switched_off(): void
    {
        ProductCategory::where('slug', 'poster')->update(['is_active' => false]);

        // Otherwise editing a design filed under it would quietly clear it.
        $this->assertArrayHasKey('poster', Product::categories(false));
        $this->assertArrayNotHasKey('poster', Product::categories());
    }
}
