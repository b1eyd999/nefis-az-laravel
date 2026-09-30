<?php

namespace Tests\Feature;

use App\Models\DeliveryMethod;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The shop's own rules: three pages a customer, a bank and a payment gateway
 * all look for, and the footer links that lead to them.
 */
class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_three_pages_are_open_to_everyone(): void
    {
        $this->get(route('legal.terms'))->assertOk()->assertSee('İstifadə şərtləri');
        $this->get(route('legal.privacy'))->assertOk()->assertSee('Məxfilik siyasəti');
        $this->get(route('legal.refund'))->assertOk()->assertSee('Ödəniş və qaytarma şərtləri');
    }

    public function test_every_page_leads_to_them(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        foreach (['legal.terms', 'legal.privacy', 'legal.refund'] as $page) {
            $this->assertStringContainsString(route($page), $html);
        }
    }

    public function test_the_rules_say_what_the_shop_actually_does(): void
    {
        // The delivery ways and the statuses are read from the shop itself, so
        // a price changed in the admin cannot leave the page telling an old one.
        $door = DeliveryMethod::where('type', DeliveryMethod::DOOR)->firstOrFail();
        $door->update(['price' => 7.5, 'is_active' => true]);

        $this->get(route('legal.terms'))
            ->assertOk()
            ->assertSee($door->name)
            ->assertSee('7.50')
            ->assertSee('Vəsait qaytarıldı');
    }

    public function test_the_pages_name_the_seller(): void
    {
        Setting::put(Setting::LEGAL_NAME, '«Nefis» MMC');

        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('«Nefis» MMC')
            ->assertSee('1906837672');
    }

    public function test_they_are_not_offered_to_search_engines_as_designs(): void
    {
        // A rules page is a page, not a product: it must not pretend otherwise.
        $this->get(route('legal.terms'))->assertOk()->assertDontSee('"@type":"Product"', false);
    }
}
