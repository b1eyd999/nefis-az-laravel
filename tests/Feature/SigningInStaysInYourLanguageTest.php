<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Where a signed-out visitor lands when he opens a page that needs an
 * account.
 *
 * Laravel's own answer is the Azerbaijani sign-in page, whatever language
 * the visitor was reading. A Russian customer following the link in his
 * order e-mail was dropped onto a page in a language he had not chosen —
 * and since the sign-in page is where he then carries on from, the whole
 * shop turned Azerbaijani around him.
 */
class SigningInStaysInYourLanguageTest extends TestCase
{
    use RefreshDatabase;

    /** Every page behind the door, in each of the three languages. */
    public static function pagesBehindTheDoor(): array
    {
        return [
            'profile' => ['hesabim'],
            'orders' => ['orders'],
        ];
    }

    /**
     * @dataProvider pagesBehindTheDoor
     */
    public function test_a_guest_is_sent_to_the_sign_in_page_of_his_own_language(string $path): void
    {
        $this->get('/' . $path)->assertRedirect(url('/login'));
        $this->get('/ru/' . $path)->assertRedirect(url('/ru/login'));
        $this->get('/en/' . $path)->assertRedirect(url('/en/login'));
    }

    public function test_the_azerbaijani_shop_is_unchanged(): void
    {
        // The default language keeps the unprefixed address it always had.
        $this->get('/hesabim')->assertRedirect(url('/login'));
    }

    public function test_an_address_that_only_looks_like_a_language_is_not_treated_as_one(): void
    {
        /* The first segment is read off the address, so a page whose own
           first word happens to be short must not be mistaken for a
           language prefix. «dizaynlar» is not «ru». */
        $this->get('/hesabim')->assertRedirect(url('/login'));
        $this->get('/de/hesabim')->assertNotFound();
    }

    /**
     * @dataProvider pagesBehindTheDoor
     */
    public function test_a_signed_in_customer_is_not_redirected_at_all(string $path): void
    {
        $this->actingAs(User::factory()->create(['role' => User::CUSTOMER, 'is_admin' => false]));

        foreach (['', '/ru', '/en'] as $prefix) {
            $this->get($prefix . '/' . $path)->assertOk();
        }
    }

    public function test_after_signing_in_he_comes_back_to_the_page_he_asked_for(): void
    {
        // Laravel remembers the intended address; the language prefix is
        // part of it, so he returns to the Russian page, not the default.
        $this->get('/ru/orders')->assertRedirect(url('/ru/login'));

        $this->assertSame(url('/ru/orders'), session('url.intended'));
    }
}
