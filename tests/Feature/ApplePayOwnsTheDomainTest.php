<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    /** The file ePoint sent on 2026-10-22, untouched. */
    private const FINGERPRINT = 'b29994f86c188c8536b6d156c0c7bc86';

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
