<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every spelling of an address but one leads to that one.
 *
 * A doubled slash used to serve a second, byte-identical copy of a page that
 * named itself as its own canonical — three home pages, none of them
 * pointing at the real one. Google counts that as duplicate content it has
 * to choose between, and the choice is not always the right page.
 */
class ADoubledSlashIsNotASecondPageTest extends TestCase
{
    use RefreshDatabase;

    /*
     * A note on what is NOT tested here. The address that started this,
     * https://nefis.az//, cannot be built in a test: Symfony reads a URI
     * beginning with two slashes as protocol-relative — host «dizaynlar»,
     * path «/» — and `Request::create('//')` throws outright. A doubled
     * slash at the END is lost too: the test client trims the address
     * before building the request. Only a doubled slash in the middle
     * survives both, so that is what these use; they reach the same branch.
     * The leading case is checked against the live site after deploying.
     */
    public function test_a_doubled_slash_leads_to_the_one_real_address(): void
    {
        $this->get('/ru//dizaynlar')->assertStatus(301)->assertRedirect(url('/ru/dizaynlar'));
        $this->get('/en///sirketler-ucun')->assertRedirect(url('/en/sirketler-ucun'));
        $this->get('/ru//products//alpen-gold//customize')
            ->assertRedirect(url('/ru/products/alpen-gold/customize'));
    }

    public function test_what_came_after_the_question_mark_survives(): void
    {
        $this->get('/ru//dizaynlar?utm_source=instagram&page=2')
            ->assertRedirect(url('/ru/dizaynlar') . '?utm_source=instagram&page=2');
    }

    public function test_an_ordinary_address_is_left_alone(): void
    {
        $this->get('/')->assertOk();
        $this->get('/dizaynlar')->assertOk();
        $this->get('/ru/dizaynlar')->assertOk();
        $this->get('/sirketler-ucun')->assertOk();
    }

    public function test_crawlers_are_told_to_leave_the_health_page_alone(): void
    {
        /* It answers 200, carries the shop's name in its title and says
           «Application up» — a page of this shop as far as Google is
           concerned. It is registered outside the web group, so no
           middleware of ours ever runs on it and a header is not an option;
           nothing links to it either, so refusing the crawl is enough. */
        $this->get('/up')->assertOk();

        $robots = file_get_contents(public_path('robots.txt'));
        $this->assertStringContainsString('Disallow: /up', $robots);
    }
}
