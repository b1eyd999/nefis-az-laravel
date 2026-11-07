<?php

namespace Tests\Feature;

use App\Models\LivePhoto;
use App\Models\Setting;
use App\Support\R2;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The videos move to Cloudflare R2, and the page reads them from there.
 *
 * Yandex is what failed: a public link there is somebody else's allowance,
 * the owner's Disk filled up, and every printed box went dark at once with
 * the files safe and unreachable. R2 charges nothing for being read and
 * does not stop. The order of preference is the lesson: R2, then this
 * site's own copy, and Yandex last of all.
 *
 * The signing is this shop's own - the whole AWS library is a hundred
 * megabytes for four calls, and `vendor/` is committed here - so the
 * arithmetic is checked against AWS's own published test vector.
 */
class TheVideosMoveToCloudflareTest extends TestCase
{
    use RefreshDatabase;

    private const PUBLIC_BASE = 'https://pub-abc123.r2.dev';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function connected(): void
    {
        Setting::put(Setting::R2_ACCOUNT, 'acc0unt');
        Setting::put(Setting::R2_BUCKET, 'nefis-video');
        Setting::put(Setting::R2_KEY, 'AKIDEXAMPLE');
        Setting::put(Setting::R2_SECRET, Crypt::encryptString('secret-key'));
        Setting::put(Setting::R2_PUBLIC, self::PUBLIC_BASE);
    }

    private function live(array $extra = []): LivePhoto
    {
        return LivePhoto::create($extra + [
            'title' => 'Toy videosu',
            'target_image' => 'live/pic.png',
            'target_mind' => 'live/target.mind',
            'is_active' => true,
        ]);
    }

    // ------------------------------------------------------------ the keys

    public function test_it_is_off_until_all_four_keys_are_there(): void
    {
        $this->assertFalse(R2::configured());

        Setting::put(Setting::R2_ACCOUNT, 'acc0unt');
        Setting::put(Setting::R2_BUCKET, 'nefis-video');
        Setting::put(Setting::R2_KEY, 'AKIDEXAMPLE');
        $this->assertFalse(R2::configured(), 'it called itself ready without the secret');

        R2::saveSecret('secret-key');
        $this->assertTrue(R2::configured());
    }

    /** The secret is not readable in the settings table. */
    public function test_the_secret_is_kept_encrypted(): void
    {
        R2::saveSecret('secret-key');

        $this->assertNotSame('secret-key', Setting::get(Setting::R2_SECRET));
        $this->assertStringNotContainsString('secret-key', (string) Setting::get(Setting::R2_SECRET));
        $this->assertSame('secret-key', R2::secret());
    }

    /** A secret that will not decrypt is no secret, not a crash. */
    public function test_rubbish_in_the_secret_reads_as_nothing(): void
    {
        Setting::put(Setting::R2_SECRET, 'not-encrypted-at-all');

        $this->assertSame('', R2::secret());
        $this->assertFalse(R2::configured());
    }

    // --------------------------------------------------------- the signing

    /**
     * AWS's own test vector for the signing key.
     *
     * Taken from the Signature Version 4 test suite: the key derived for
     * 30 August 2015, us-east-1, the service «iam», with the example
     * secret. If this matches, the chain is right; if it drifts, every
     * request R2 refuses would otherwise look like a wrong password.
     */
    public function test_the_signing_arithmetic_matches_the_published_vector(): void
    {
        $key = R2::signingKey('wJalrXUtnFEMI/K7MDENG+bPxRfiCYEXAMPLEKEY', '20150830', 'us-east-1', 'iam');

        $this->assertSame(
            'c4afb1cc5771d871763a393e44b703571b55cc28424d1a5e86da6ed3c154a4b9',
            bin2hex($key),
        );

        $this->assertSame(
            '5d672d79c15b13162d9279b0855cfba6789a8edb4c82c400e06b5924a6f2b5d7',
            R2::signature($key, "AWS4-HMAC-SHA256\n20150830T123600Z\n"
                . "20150830/us-east-1/iam/aws4_request\n"
                . 'f536975d06c0309214f805bb90ccff089219ecd68b2577efef23edd43b7e1a59'),
        );
    }

    // --------------------------------------------------------- the carrying

    public function test_a_video_on_the_hosting_is_put_into_the_bucket(): void
    {
        $this->connected();
        Http::fake(['*' => Http::response('', 200)]);

        $live = $this->live();
        Storage::disk('public')->put('live/clip.mp4', 'a video');
        $live->forceFill(['video_path' => 'live/clip.mp4'])->saveQuietly();

        $this->artisan('live:r2')->assertSuccessful();

        $live->refresh();
        $this->assertSame('live/' . $live->code . '.mp4', $live->video_r2);
        // The hosting keeps its own: three places, after what Yandex cost.
        $this->assertSame('live/clip.mp4', $live->video_path);

        Http::assertSent(function ($request) use ($live) {
            return $request->method() === 'PUT'
                && str_contains($request->url(), 'acc0unt.r2.cloudflarestorage.com/nefis-video/live/' . $live->code . '.mp4')
                && str_starts_with($request->header('Authorization')[0] ?? '', 'AWS4-HMAC-SHA256 Credential=AKIDEXAMPLE/')
                && ($request->header('x-amz-content-sha256')[0] ?? '') === hash('sha256', 'a video');
        });
    }

    public function test_with_no_keys_nothing_is_carried(): void
    {
        $live = $this->live();
        Storage::disk('public')->put('live/clip.mp4', 'a video');
        $live->forceFill(['video_path' => 'live/clip.mp4'])->saveQuietly();

        $this->artisan('live:r2')->assertSuccessful();

        $this->assertNull($live->refresh()->video_r2);
    }

    // --------------------------------------------------------- the reading

    public function test_the_page_reads_the_bucket_first(): void
    {
        $this->connected();

        $live = $this->live();
        Storage::disk('public')->put('live/clip.mp4', 'a video');
        $live->forceFill([
            'video_r2' => 'live/abc.mp4',
            'video_path' => 'live/clip.mp4',
            'video_url' => 'https://disk.yandex.ru/i/whatever',
        ])->saveQuietly();

        $this->get('/canli/' . $live->code . '/video')
            ->assertRedirect(self::PUBLIC_BASE . '/live/abc.mp4');

        $this->assertSame('r2', $live->videoPlace());
    }

    /** With nothing in the bucket, this site's own copy still answers. */
    public function test_without_the_bucket_the_hosting_answers(): void
    {
        $live = $this->live();
        Storage::disk('public')->put('live/clip.mp4', 'a video');
        $live->forceFill(['video_path' => 'live/clip.mp4'])->saveQuietly();

        $this->get('/canli/' . $live->code . '/video')
            ->assertRedirect()
            ->assertRedirectContains('live/clip.mp4');
    }

    /**
     * A bucket key with no public address is not an address.
     *
     * The owner can paste his keys and forget the «Publik ünvan», and a
     * redirect to nowhere would be a black screen on a printed box.
     */
    public function test_a_bucket_with_no_public_address_falls_through(): void
    {
        $this->connected();
        Setting::put(Setting::R2_PUBLIC, '');

        $live = $this->live();
        Storage::disk('public')->put('live/clip.mp4', 'a video');
        $live->forceFill(['video_r2' => 'live/abc.mp4', 'video_path' => 'live/clip.mp4'])->saveQuietly();

        $this->get('/canli/' . $live->code . '/video')
            ->assertRedirect()
            ->assertRedirectContains('live/clip.mp4');
    }
}
