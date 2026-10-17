<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The shop answers on two lines now: the business number it bought and the
 * one it has always used. Both are on WhatsApp.
 *
 * Only the first is the main one, and that is the whole point of these
 * tests. A WhatsApp button can carry one number, and so can the telephone
 * Google reads out of the page — so those stay on the main line while the
 * second is shown beside it everywhere a person reads rather than taps.
 */
class TheShopHasTwoNumbersTest extends TestCase
{
    use RefreshDatabase;

    private const MAIN = '+994 55 383 86 89';

    private const OLD = '+994 99 230 80 50';

    private function both(): void
    {
        Setting::put(Setting::CONTACT_PHONE, Contact::clean(self::MAIN));
        Setting::put(Setting::CONTACT_PHONE_2, Contact::clean(self::OLD));
    }

    public function test_both_numbers_are_read_back_the_way_the_country_writes_them(): void
    {
        $this->both();

        $this->assertSame('+994 55 383 86 89', Contact::display());
        $this->assertSame('+994 99 230 80 50', Contact::display2());
        $this->assertSame('+994553838689', Contact::dial());
        $this->assertSame('+994992308050', Contact::dial2());
        $this->assertSame('https://wa.me/994553838689', Contact::whatsapp());
        $this->assertSame('https://wa.me/994992308050', Contact::whatsapp2());
    }

    public function test_spacing_and_dashes_do_not_matter_but_the_country_code_does(): void
    {
        // clean() keeps the digits it is given, which is why the field says
        // «write it with the country code». Spacing is free; a local 0XX is
        // not the same number and is not silently turned into one.
        foreach (['+994 55 383 86 89', '994553838689', '+994-55-383-86-89', '994 55 3838689'] as $typed) {
            Setting::put(Setting::CONTACT_PHONE_2, Contact::clean($typed));
            $this->assertSame('+994 55 383 86 89', Contact::display2(), "typed as: $typed");
        }
    }

    public function test_the_second_number_is_shown_beside_the_first(): void
    {
        $this->both();

        $this->get('/')->assertOk()
            ->assertSee('+994 55 383 86 89')
            ->assertSee('+994 99 230 80 50');

        // The contact page, where a customer goes to find a number.
        $this->get('/elaqe')->assertOk()
            ->assertSee('+994 55 383 86 89')
            ->assertSee('+994 99 230 80 50')
            ->assertSee('https://wa.me/994553838689', false)
            ->assertSee('https://wa.me/994992308050', false);

        // And the block the three legal pages share.
        $this->get('/qaydalar')->assertOk()
            ->assertSee('+994 55 383 86 89')
            ->assertSee('+994 99 230 80 50');
    }

    public function test_the_whatsapp_button_and_google_get_the_main_number_only(): void
    {
        $this->both();

        $html = (string) $this->get('/')->assertOk()->getContent();

        // The telephone in the structured data — one number, the main one.
        preg_match_all('~"telephone":"([^"]+)"~', $html, $found);
        $this->assertNotSame([], $found[1], 'no telephone in the structured data at all');
        foreach ($found[1] as $tel) {
            $this->assertSame('+994553838689', $tel);
        }
    }

    public function test_with_no_second_number_nothing_changes(): void
    {
        Setting::put(Setting::CONTACT_PHONE, Contact::clean(self::MAIN));
        Setting::put(Setting::CONTACT_PHONE_2, '');

        $this->assertFalse(Contact::has2());
        $this->get('/')->assertOk()->assertSee('+994 55 383 86 89')->assertDontSee('+994 99 230 80 50');
    }

    public function test_the_same_number_entered_twice_is_shown_once(): void
    {
        /* Typed into both fields by mistake. Two identical rows look
           careless, and the second would link to the first. */
        Setting::put(Setting::CONTACT_PHONE, Contact::clean(self::MAIN));
        Setting::put(Setting::CONTACT_PHONE_2, Contact::clean('994 55 383 86 89'));

        $this->assertFalse(Contact::has2());

        /* The number is still on the page, of course — in the header, the
           footer and the ways. What must not happen is a SECOND row for it:
           the «İkinci nömrə» line is the one the duplicate would create. */
        $this->get('/elaqe')->assertOk()
            ->assertSee('+994 55 383 86 89')
            ->assertDontSee('İkinci nömrə');
    }
}
