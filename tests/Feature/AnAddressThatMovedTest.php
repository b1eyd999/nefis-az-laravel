<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductSlug;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What happens to the old address when a design is renamed.
 *
 * The owner renames designs in the admin and the slug follows the name. Up
 * to now the old address simply died: Google had it indexed, customers had
 * it in messages, and all of it landed on an error page — which is why the
 * «Not found (404)» report kept refilling after it was cleared.
 *
 * The rule these tests pin down is narrow on purpose. A design that moved is
 * forwarded permanently. A design that is switched off, or was never there,
 * still answers 404 — because that is the truthful answer, and a redirect to
 * something vaguely similar is the soft-404 Google penalises.
 */
class AnAddressThatMovedTest extends TestCase
{
    use RefreshDatabase;

    private function design(string $slug, array $extra = []): Product
    {
        return Product::create($extra + [
            'name' => 'Qutu',
            'slug' => $slug,
            'is_active' => true,
            'price' => 10,
        ]);
    }

    public function test_renaming_a_design_writes_the_old_address_down(): void
    {
        $product = $this->design('kohne-ad');

        $this->assertSame(0, ProductSlug::count());

        $product->update(['slug' => 'yeni-ad']);

        $this->assertSame(1, ProductSlug::count());
        $this->assertSame($product->id, ProductSlug::where('slug', 'kohne-ad')->value('product_id'));
    }

    public function test_the_old_address_leads_to_the_new_one(): void
    {
        $product = $this->design('kohne-ad');
        $product->update(['slug' => 'yeni-ad']);

        $this->get('/products/kohne-ad/customize')
            ->assertStatus(301)
            ->assertRedirect(url('/products/yeni-ad/customize'));
    }

    public function test_a_design_renamed_twice_forwards_from_both_of_its_old_addresses(): void
    {
        $product = $this->design('bir');
        $product->update(['slug' => 'iki']);
        $product->update(['slug' => 'uc']);

        $this->get('/products/bir/customize')->assertRedirect(url('/products/uc/customize'));
        $this->get('/products/iki/customize')->assertRedirect(url('/products/uc/customize'));
    }

    public function test_the_visitor_stays_in_the_language_he_came_in(): void
    {
        $product = $this->design('kohne-ad');
        $product->update(['slug' => 'yeni-ad']);

        $this->get('/ru/products/kohne-ad/customize')
            ->assertRedirect(url('/ru/products/yeni-ad/customize'));
        $this->get('/en/products/kohne-ad/customize')
            ->assertRedirect(url('/en/products/yeni-ad/customize'));
    }

    public function test_what_came_after_the_question_mark_is_carried_over(): void
    {
        // Links shared from a campaign arrive with their tags on. Dropping
        // them would make the move look like untagged traffic.
        $product = $this->design('kohne-ad');
        $product->update(['slug' => 'yeni-ad']);

        $this->get('/products/kohne-ad/customize?utm_source=instagram&x=1')
            ->assertRedirect(url('/products/yeni-ad/customize') . '?utm_source=instagram&x=1');
    }

    public function test_a_name_taken_back_stops_being_a_forwarding_address(): void
    {
        /* Renamed and then renamed back. If the old row survived, the live
           address would also be a forwarding address and send itself in a
           circle, which a browser shows as «too many redirects». */
        $product = $this->design('kohne-ad');
        $product->update(['slug' => 'yeni-ad']);
        $product->update(['slug' => 'kohne-ad']);

        $this->assertSame(0, ProductSlug::where('slug', 'kohne-ad')->count());
        $this->assertSame(1, ProductSlug::where('slug', 'yeni-ad')->count());

        $this->get('/products/kohne-ad/customize')->assertStatus(404);   // blank design
        $this->get('/products/yeni-ad/customize')->assertRedirect(url('/products/kohne-ad/customize'));
    }

    public function test_an_address_freed_by_one_design_and_taken_by_another_belongs_to_the_new_one(): void
    {
        $first = $this->design('paylasilan');
        $first->update(['slug' => 'birinci']);

        $second = $this->design('ikinci');
        $second->update(['slug' => 'paylasilan']);

        // The forwarding row the first design left behind must be gone: the
        // address is somebody's real address again.
        $this->assertSame(0, ProductSlug::where('slug', 'paylasilan')->count());
        $this->get('/products/paylasilan/customize')->assertStatus(404);  // blank, but found
    }

    public function test_a_design_switched_off_still_answers_404(): void
    {
        /* Nothing to forward to: the page is gone on purpose. Google wants
           the 404 and drops the address; a redirect to the catalogue would
           be a soft 404 and is worse than the error it hides. */
        $product = $this->design('kohne-ad');
        $product->update(['slug' => 'yeni-ad']);
        $product->update(['is_active' => false]);

        $this->get('/products/kohne-ad/customize')->assertStatus(404);
        $this->get('/products/yeni-ad/customize')->assertStatus(404);
    }

    public function test_an_address_no_design_ever_had_is_still_404(): void
    {
        $this->design('var-olan');

        $this->get('/products/hec-vaxt-olmayib/customize')->assertStatus(404);
    }

    public function test_deleting_a_design_takes_its_old_addresses_with_it(): void
    {
        $product = $this->design('kohne-ad');
        $product->update(['slug' => 'yeni-ad']);

        $this->assertSame(1, ProductSlug::count());

        $product->delete();

        $this->assertSame(0, ProductSlug::count());
        $this->get('/products/kohne-ad/customize')->assertStatus(404);
    }

    public function test_the_owner_can_write_in_an_address_that_was_lost_before_this_existed(): void
    {
        /* Designs renamed before the shop started remembering have dead
           addresses nobody can mend from the code — only the owner knows
           which design replaced which. The admin's «Köhnə ünvanlar» field is
           how he says so. */
        $product = $this->design('indiki-ad');

        $product->rememberOldSlugs(['kohne-ad', 'daha-kohne']);

        $this->get('/products/kohne-ad/customize')->assertRedirect(url('/products/indiki-ad/customize'));
        $this->get('/products/daha-kohne/customize')->assertRedirect(url('/products/indiki-ad/customize'));

        $product->forgetOldSlugs(['daha-kohne']);

        $this->get('/products/kohne-ad/customize')->assertRedirect(url('/products/indiki-ad/customize'));
        $this->get('/products/daha-kohne/customize')->assertStatus(404);
    }

    public function test_he_cannot_point_a_design_at_itself_or_steal_a_live_address(): void
    {
        $mine = $this->design('menimki');
        $other = $this->design('basqasinin');

        // Its own address would be a redirect to itself — a loop in a browser.
        $mine->rememberOldSlugs(['menimki']);
        $this->assertSame(0, ProductSlug::count());

        // And an address another design really answers to is not free to take.
        $mine->rememberOldSlugs(['basqasinin']);
        $this->assertSame(0, ProductSlug::count());
        $this->assertTrue($other->exists);
    }

    public function test_the_english_corporate_address_google_holds_leads_to_the_real_page(): void
    {
        // /en/for-companies has never existed; three English gift pages used
        // to link to it and Google indexed it.
        $this->get('/en/for-companies')
            ->assertStatus(301)
            ->assertRedirect(url('/en/sirketler-ucun'));

        $this->get('/ru/for-companies')->assertRedirect(url('/ru/sirketler-ucun'));
        $this->get('/for-companies')->assertRedirect(url('/sirketler-ucun'));
    }
}
