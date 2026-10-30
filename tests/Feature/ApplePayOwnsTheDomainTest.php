<?php

namespace Tests\Feature;

use App\Filament\Pages\SiteSettings;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Apple Pay stays hidden until Apple has fetched a file from this domain and
 * recognised what it finds there.
 *
 * ePoint registered nefis.az as the merchant domain and Apple answered
 * «Unable to access verification file on server» — the file had never been
 * put anywhere. It cannot simply go into public/: the deploy copies that
 * folder with `cp -R public/*`, which skips a name beginning with a dot, and
 * then removes public/ from the application directory altogether. So it is
 * served from a route, the way the Android file beside it always has been.
 *
 * Most of what is guarded here is the file itself, because its bytes are
 * what Apple signed and two entirely invisible things would change them:
 * git's own `* text=auto eol=lf`, which rewrites the endings of anything it
 * takes for text, and any editor that saves it a second time. Hence the
 * fingerprint — if a newer file arrives from ePoint, this test is meant to
 * fail until somebody puts the new fingerprint in deliberately.
 */
class ApplePayOwnsTheDomainTest extends TestCase
{
    use RefreshDatabase;

    /** Both addresses a verifier might ask for. */
    private const ADDRESSES = [
        '/.well-known/apple-developer-merchantid-domain-association',
        '/.well-known/apple-developer-merchantid-domain-association.txt',
    ];

    private const FILE = 'well-known/apple-developer-merchantid-domain-association.txt';

    /** The file ePoint sent on 2026-10-30, untouched. The 19 October one was
     *  replaced when they re-registered the domain; Apple looks for the newest. */
    private const FINGERPRINT = 'b22a797e00f82150132e0d04bbaf0bb4';

    private const BYTES = 5736;

    public function test_the_file_is_exactly_what_apple_issued(): void
    {
        $path = resource_path(self::FILE);

        $this->assertFileExists($path);
        $this->assertSame(self::BYTES, filesize($path),
            'the size moved: either the line endings were rewritten or the file was saved again');
        $this->assertSame(self::FINGERPRINT, md5_file($path),
            'these are not the bytes Apple signed — a new file from ePoint has to change this test too');
    }

    /** It is a certificate for this shop's domain, not somebody else's. */
    public function test_the_package_inside_names_nefis_az(): void
    {
        $base64 = preg_replace('/\s+/', '', file_get_contents(resource_path(self::FILE)));

        // Apple writes it url-safe, so the two odd letters come back first.
        $signed = base64_decode(strtr($base64, '-_', '+/'), false);

        $this->assertNotFalse($signed, 'the file does not decode as base64 at all');
        $this->assertSame(1, preg_match('/\{"teamId".*?\}/', $signed, $found),
            'the signed package carries no domain record');

        $record = json_decode($found[0], true);

        $this->assertSame('nefis.az', $record['domain']);
        $this->assertSame('BCCR67BJBD', $record['teamId'],
            'that is the Apple team this merchant was registered under');
    }

    /**
     * Both spellings hand back the file, with no redirect in between.
     *
     * Apple follows none, and «proxies and redirects are not enabled» is
     * half of what the failed verification asked us to confirm.
     */
    public function test_both_addresses_hand_back_the_file_itself(): void
    {
        $expected = file_get_contents(resource_path(self::FILE));

        foreach (self::ADDRESSES as $url) {
            $answer = $this->get($url);

            $answer->assertOk();
            $answer->assertHeader('Content-Type', 'text/plain; charset=utf-8');
            $this->assertSame($expected, $answer->getContent(),
                $url.' gives back something other than the file');
        }
    }

    /**
     * The answer is never offered to a shared cache.
     *
     * It leaves through the web group, which stamps the session cookie
     * onto it on the way out. «public» on that same response is how a
     * cache sitting in front of the site learns to hand one visitor's
     * session to the next.
     */
    public function test_the_answer_is_not_offered_to_shared_caches(): void
    {
        foreach (self::ADDRESSES as $url) {
            $kept = (string) $this->get($url)->headers->get('Cache-Control');

            $this->assertStringContainsString('private', $kept, $url);
            $this->assertStringNotContainsString('public', $kept,
                $url.' invites shared caches to keep a response that carries a cookie');
        }
    }

    /**
     * The visit is written down, so «did Apple come» has an answer here.
     *
     * Twice now the only record of it has been the hosting's raw access log,
     * which needs a cPanel sign-in, keeps a month, and cannot be read from a
     * session at all once that sign-in is gone.
     */
    public function test_a_visit_is_written_down_and_apple_is_recognised(): void
    {
        $this->assertSame('[]', Setting::get(Setting::APPLE_PAY_FETCHED));

        $this->withHeader('User-Agent', 'Mozilla/5.0 (Macintosh) AppleWebKit/605.1.15 ApplePay/1.0')
            ->get(self::ADDRESSES[0])->assertOk();

        $seen = json_decode((string) Setting::get(Setting::APPLE_PAY_FETCHED), true);

        $this->assertCount(1, $seen);
        $this->assertStringContainsString('ApplePay', $seen[0]['ua']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $seen[0]['at']);

        /* And the admin line marks it as Apple rather than «somebody else».
           The tick is what is asserted, not the word: the user agent printed
           underneath says «ApplePay» too, so looking for «Apple» anywhere in
           the line would pass even if nothing were ever recognised. */
        $this->assertStringContainsString('✓ Apple', SiteSettings::appleVisits());
        $this->assertStringNotContainsString('başqası', SiteSettings::appleVisits());
        $this->assertStringNotContainsString('hələ heç kim gəlməyib', SiteSettings::appleVisits());
    }

    /** Anyone else who opens the address is not reported as Apple. */
    public function test_somebody_else_is_not_passed_off_as_apple(): void
    {
        $this->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0) Chrome/130.0')
            ->get(self::ADDRESSES[0])->assertOk();

        $this->assertStringContainsString('başqası', SiteSettings::appleVisits());
        $this->assertStringNotContainsString('✓ Apple', SiteSettings::appleVisits());
    }

    /** With nothing recorded, the admin says so instead of showing an empty list. */
    public function test_the_admin_says_plainly_that_nobody_has_come(): void
    {
        $this->assertStringContainsString('hələ heç kim gəlməyib', SiteSettings::appleVisits());
    }

    /**
     * One line a minute, and never more than a dozen.
     *
     * The address is public and unauthenticated: a write per request would
     * let anyone turn a loop of fetches into database traffic, and an
     * unbounded list would grow in a settings row read on every request.
     */
    public function test_the_record_is_throttled_and_bounded(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->get(self::ADDRESSES[0])->assertOk();
        }

        $this->assertCount(1, json_decode((string) Setting::get(Setting::APPLE_PAY_FETCHED), true),
            'five fetches in the same minute wrote more than one line');

        // A long enough list is cut, newest kept.
        $long = [];
        for ($i = 0; $i < 30; $i++) {
            $long[] = ['at' => '2026-10-30 10:0'.($i % 10), 'ip' => '1.1.1.1', 'ua' => 'line '.$i];
        }
        Setting::put(Setting::APPLE_PAY_FETCHED, json_encode($long));

        Cache::forget('apple-pay-fetch-note');
        $this->get(self::ADDRESSES[0])->assertOk();

        $seen = json_decode((string) Setting::get(Setting::APPLE_PAY_FETCHED), true);

        $this->assertCount(12, $seen);
        $this->assertSame('line 0', $seen[1]['ua'], 'the newest line went on top and the oldest fell off');
    }

    /**
     * A settings row holding something other than a list of visits is survived.
     *
     * Plain nonsense decodes to null and casts to an empty array by itself,
     * so the case that actually matters is valid JSON whose entries are not
     * visits — those have to be dropped rather than kept and later read as
     * if they had an «at» and a «ua».
     */
    public function test_entries_that_are_not_visits_are_dropped(): void
    {
        foreach (['not json at all', '[1, 2, "three"]', '{"a": 1}', 'null'] as $rubbish) {
            Setting::put(Setting::APPLE_PAY_FETCHED, $rubbish);
            Cache::forget('apple-pay-fetch-note');

            $this->get(self::ADDRESSES[0])->assertOk();

            $seen = json_decode((string) Setting::get(Setting::APPLE_PAY_FETCHED), true);

            $this->assertIsArray($seen, $rubbish);
            $this->assertNotSame([], $seen, $rubbish.': the visit itself was lost');

            foreach ($seen as $visit) {
                $this->assertIsArray($visit, $rubbish.': something that is not a visit was kept');
                $this->assertArrayHasKey('at', $visit, $rubbish);
            }

            // And the admin can render it without reaching into a scalar.
            $this->assertStringContainsString('son gələnlər', SiteSettings::appleVisits(), $rubbish);
        }
    }

    /** A closed shop is still this shop. */
    public function test_the_ownership_files_answer_while_the_shop_is_closed(): void
    {
        Setting::put(Setting::MAINTENANCE, '1');

        // The switch really is on.
        $this->get('/')->assertStatus(503);

        foreach ([...self::ADDRESSES, '/.well-known/assetlinks.json'] as $url) {
            $this->get($url)->assertOk();
        }
    }
}
