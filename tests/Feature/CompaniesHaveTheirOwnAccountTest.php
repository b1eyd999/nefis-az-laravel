<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A company writes from the corporate page, the shop answers, and only then
 * is there an account. What is drawn for that company carries its logo and
 * has no business on the public shelf.
 *
 * So a design can be marked «yalnız biznes hesablar üçün». It then leaves
 * the catalogue, the home page, the sitemap and the feed, and nobody but
 * that kind of account — or the shop itself, which usually fills the basket
 * for them — can put it in one.
 *
 * A business account is a customer and nothing more: no panel, no staff
 * rights, and it signs in at the ordinary door like everybody else.
 */
class CompaniesHaveTheirOwnAccountTest extends TestCase
{
    use RefreshDatabase;

    private function design(string $slug, bool $forCompanies = false): Product
    {
        $box = Product::create([
            'name' => $forCompanies ? 'Loqolu mini şokolad' : 'Adi qutu',
            'slug' => $slug, 'is_active' => true, 'business_only' => $forCompanies,
            'category' => 'sokolad', 'price' => 7.5,
            'template_width' => 969, 'template_height' => 1895,
        ]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/' . $slug . '/bg.webp',
            'x' => 0, 'y' => 0, 'width' => 969, 'height' => 1895, 'rotation' => 0,
            'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);

        return $box;
    }

    private function company(): User
    {
        return User::factory()->create(['role' => User::BUSINESS, 'name' => 'ABC MMC']);
    }

    /** A company is a customer: the panel is not its door and neither is staff. */
    public function test_a_business_account_is_a_customer_and_not_staff(): void
    {
        $company = $this->company();

        $this->assertTrue($company->isBusiness());
        $this->assertFalse($company->isStaff());
        $this->assertFalse($company->isAdmin());
        $this->assertFalse($company->isCourier());
        $this->assertFalse((bool) $company->is_admin);
        $this->assertFalse($company->signsInAtThePanel(),
            'it signs in at the ordinary door, like every other customer');
        $this->assertFalse($company->canAccessPanel(\Filament\Facades\Filament::getPanel('admin')));
        $this->assertSame('Biznes', $company->roleLabel());
    }

    /** The public shelf does not carry somebody else's logo. */
    public function test_a_company_design_is_off_every_public_list(): void
    {
        $plain = $this->design('adi-qutu');
        $theirs = $this->design('abc-mmc', true);

        foreach (['/', '/dizaynlar'] as $url) {
            $page = $this->get($url)->assertSuccessful();
            $page->assertSee($plain->name, false);
            $page->assertDontSee($theirs->name, false, $url . ' shows a company design');
        }

        $this->get('/sitemap.xml')->assertSuccessful()
            ->assertSee('/products/adi-qutu/customize', false)
            ->assertDontSee('abc-mmc', false);

        $this->get('/feed.xml')->assertSuccessful()->assertDontSee('abc-mmc', false);
    }

    /** And a stranger cannot reach it by its address either. */
    public function test_a_stranger_cannot_open_a_company_design(): void
    {
        $theirs = $this->design('abc-mmc', true);

        $this->get('/products/abc-mmc/customize')->assertNotFound();
        $this->actingAs(User::factory()->create())
            ->get('/products/abc-mmc/customize')->assertNotFound();

        $this->post(route('cart.add'), ['product_id' => $theirs->id, 'quantity' => 1])
            ->assertNotFound();
        $this->assertSame([], \App\Support\Cart::items());
    }

    /** The company itself opens it, and buys it. */
    public function test_the_company_opens_its_own_design_and_can_order_it(): void
    {
        $theirs = $this->design('abc-mmc', true);
        $company = $this->company();

        $this->actingAs($company)->get('/products/abc-mmc/customize')->assertSuccessful();

        $this->actingAs($company)->post(route('cart.add'), ['product_id' => $theirs->id, 'quantity' => 100])
            ->assertSessionHasNoErrors();

        $this->actingAs($company)->get('/cart')->assertSuccessful()->assertSee($theirs->name, false);
    }

    /** And it sees it on the shelf, because the basket is filled on that page. */
    public function test_the_company_sees_its_own_design_in_the_catalogue(): void
    {
        $this->design('adi-qutu');
        $theirs = $this->design('abc-mmc', true);

        $this->actingAs($this->company())->get('/dizaynlar')
            ->assertSuccessful()->assertSee($theirs->name, false);
    }

    /** The shop fills the basket for them, so staff may order it too. */
    public function test_the_shop_can_order_it_for_them(): void
    {
        $theirs = $this->design('abc-mmc', true);

        foreach ([User::ADMIN, User::MANAGER] as $role) {
            $staff = User::factory()->create(['role' => $role]);
            $this->assertTrue($theirs->openTo($staff), $role . ' must be able to fill their basket');
        }

        $this->actingAs(User::factory()->create(['role' => User::ADMIN]))
            ->post(route('cart.add'), ['product_id' => $theirs->id, 'quantity' => 100])
            ->assertSessionHasNoErrors();
    }

    /** A switched-off design is shut to everybody, company or not. */
    public function test_switching_a_design_off_shuts_it_for_the_company_as_well(): void
    {
        $theirs = $this->design('abc-mmc', true);
        $theirs->forceFill(['is_active' => false])->save();

        $this->assertFalse($theirs->openTo($this->company()));
        $this->assertFalse($theirs->openTo(User::factory()->create(['role' => User::ADMIN])));
        $this->actingAs($this->company())->get('/products/abc-mmc/customize')->assertNotFound();
    }

    /** Nothing about this changes an ordinary design for an ordinary customer. */
    public function test_an_ordinary_design_is_untouched(): void
    {
        $plain = $this->design('adi-qutu');

        $this->assertTrue($plain->openTo(null));
        $this->get('/products/adi-qutu/customize')->assertSuccessful();
        $this->post(route('cart.add'), ['product_id' => $plain->id, 'quantity' => 1])
            ->assertSessionHasNoErrors();
        $this->assertCount(1, \App\Support\Cart::items());
    }

    /**
     * The basket stopped at twenty while the corporate page asked for a
     * hundred as its minimum — so the one customer that page was written
     * for could not buy at all.
     */
    public function test_a_company_may_order_the_hundred_the_page_asks_for(): void
    {
        $box = $this->design('adi-qutu');

        $this->post(route('cart.add'), ['product_id' => $box->id, 'quantity' => 100])
            ->assertSessionHasErrors('quantity');
        $this->assertSame(20, \App\Support\Cart::most(null));

        $company = $this->company();
        $this->actingAs($company)->post(route('cart.add'), ['product_id' => $box->id, 'quantity' => 100])
            ->assertSessionHasNoErrors();

        $this->assertSame(100, \App\Support\Cart::items()[0]['quantity']);
        $this->assertSame(500, \App\Support\Cart::most($company));
    }

    /** But not without end: five hundred of one design is still the ceiling. */
    public function test_even_a_company_has_a_ceiling(): void
    {
        $box = $this->design('adi-qutu');

        $this->actingAs($this->company())
            ->post(route('cart.add'), ['product_id' => $box->id, 'quantity' => 5000])
            ->assertSessionHasErrors('quantity');
    }

    /** Nobody hands himself the role on the way in. */
    public function test_signing_up_cannot_make_anybody_a_company(): void
    {
        $this->post(route('register'), [
            'name' => 'Z', 'email' => 'yeni@misal.az', 'phone' => '+994557778899',
            'password' => 'parol12345', 'password_confirmation' => 'parol12345',
            'role' => User::BUSINESS,
        ]);

        $this->assertSame(User::CUSTOMER, User::where('email', 'yeni@misal.az')->firstOrFail()->role);
    }
}
