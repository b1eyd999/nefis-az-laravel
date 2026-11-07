<?php

namespace Tests\Feature;

use App\Models\LivePhoto;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Setting;
use App\Models\User;
use App\Support\Telegram;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A printed box outlives the link behind it.
 *
 * All six of the shop's live photos stopped playing on the same day: the
 * files behind their Yandex Disk links were gone, every one answering
 * `DiskNotFoundError`. Nobody knew until a customer pointed a telephone at a
 * box, because the site had deleted its own copy the moment the upload
 * reported success, and nothing ever asked again afterwards.
 *
 * Three things are asked of the shop here. It must not let go of its copy
 * until the link has actually answered. It must play its own copy when the
 * link stops answering. And it must notice, once a day, and say so.
 */
class ALiveVideoIsNotLostWithItsLinkTest extends TestCase
{
    use RefreshDatabase;

    private const LINK = 'https://disk.yandex.ru/i/abc123';

    private const CLIP = 'a video, more or less';

    /** Whether Yandex is answering for the public link this moment. */
    private bool $serves = true;

    /** What Yandex says is wrong, when it is not answering. */
    private string $reason = 'DiskNotFoundError';

    /** How many messages had gone out when we last looked. */
    private int $seen = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Cache::flush();
        $this->fakeYandex();
    }

    /**
     * One fake for the whole test, flipped by a flag.
     *
     * Calling Http::fake() a second time does not replace the first set, it
     * adds to it, and the earliest matching stub wins - so re-faking «now it
     * works» left the «not found» from before still answering, and the test
     * said the link never came back when the code was right.
     */
    private function fakeYandex(): void
    {
        $size = strlen(self::CLIP);

        Http::fake(function ($request) use ($size) {
            $url = $request->url();

            if (str_contains($url, 'public/resources/download')) {
                return $this->serves
                    ? Http::response(['href' => 'https://downloader.disk.yandex.ru/clip.mp4'])
                    : Http::response(['error' => 'DiskNotFoundError'], 404);
            }
            /* The metadata endpoint beside it, which is the one that tells
               the truth when the download allowance is spent. */
            if (str_contains($url, 'public/resources')) {
                return $this->serves
                    ? Http::response(['name' => 'clip.mp4', 'size' => $size])
                    : Http::response(['error' => $this->reason], 404);
            }
            if (str_contains($url, '/disk/resources/upload')) {
                return Http::response(['href' => 'https://uploader.disk.yandex.ru/put']);
            }
            // The owner's own side: what he has published, and reading one back.
            if (str_contains($url, '/disk/resources/public')) {
                return Http::response(['items' => [
                    ['path' => 'disk:/Nefis/clip.mp4', 'public_url' => self::LINK],
                ]]);
            }
            if (str_contains($url, '/disk/resources/download')) {
                return Http::response(['href' => 'https://downloader.disk.yandex.ru/own/clip.mp4']);
            }
            if (str_contains($url, 'downloader.disk.yandex.ru/own')) {
                return Http::response(self::CLIP);
            }
            if (str_contains($url, '/disk/resources/publish')) {
                return Http::response([], 200);
            }
            if (str_contains($url, '/disk/resources')) {
                return Http::response(['size' => $size, 'public_url' => self::LINK]);
            }

            return Http::response([], 200);
        });
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

    /** A video sitting on the hosting, as a customer's upload leaves it. */
    private function onTheHosting(): LivePhoto
    {
        $live = $this->live();
        Storage::disk('public')->put('live/clip.mp4', self::CLIP);
        $live->forceFill(['video_path' => 'live/clip.mp4'])->saveQuietly();

        return $live->refresh();
    }

    /** Yandex answers for the public link, or does not. */
    private function yandexServes(bool $ok): void
    {
        $this->serves = $ok;
        // A link that worked is remembered for half an hour; a test moving
        // between the two states has to forget it.
        Cache::flush();
    }

    // ------------------------------------------------- the copy is let go late

    /**
     * The upload says it worked; the link says nothing. The file stays.
     *
     * This is the whole of it: an upload reporting success is not a second
     * copy, and the hosting's own was deleted on that word alone.
     */
    public function test_the_hosting_keeps_its_copy_while_the_link_does_not_answer(): void
    {
        Setting::put(Setting::YANDEX_TOKEN, Crypt::encryptString('token'));
        $live = $this->onTheHosting();
        $this->yandexServes(false);

        $this->assertFalse($live->pushVideo());

        $live->refresh();
        $this->assertNull($live->video_url, 'the link was written down though it does not work');
        $this->assertSame('live/clip.mp4', $live->video_path);
        $this->assertTrue(Storage::disk('public')->exists('live/clip.mp4'));
        $this->assertNotNull($live->pushError);
    }

    /**
     * The Disk gets a copy; this site keeps its own.
     *
     * A public Yandex link is rationed - every telephone pointed at a
     * printed box spends some of it - and when the shop's ran out, six
     * boxes went dark at once while the files sat safe on the Disk. The
     * Disk is the archive; the hosting is what answers.
     */
    public function test_the_disk_gets_a_copy_and_the_hosting_keeps_one(): void
    {
        Setting::put(Setting::YANDEX_TOKEN, Crypt::encryptString('token'));
        $live = $this->onTheHosting();
        $this->yandexServes(true);

        $this->assertTrue($live->pushVideo());

        $live->refresh();
        $this->assertSame(self::LINK, $live->video_url);
        $this->assertSame('live/clip.mp4', $live->video_path);
        $this->assertTrue(Storage::disk('public')->exists('live/clip.mp4'));
        $this->assertNull($live->video_broken_at);
    }

    /** With both to hand, the box is served from here and not from Yandex. */
    public function test_the_hosting_copy_is_preferred_over_the_link(): void
    {
        $live = $this->onTheHosting();
        $live->forceFill(['video_url' => self::LINK])->saveQuietly();
        $this->yandexServes(true);

        $this->get('/canli/' . $live->code . '/video')
            ->assertRedirect()
            ->assertRedirectContains('live/clip.mp4');
    }

    /**
     * A video left only on the Disk is brought back with the owner's token.
     *
     * This is the repair for the six that were already stranded: the public
     * side was refusing everything, while the owner reading his own Disk
     * was never rationed at all.
     */
    public function test_a_stranded_video_is_pulled_back_onto_the_hosting(): void
    {
        Setting::put(Setting::YANDEX_TOKEN, Crypt::encryptString('token'));

        $live = $this->live();
        $live->forceFill(['video_url' => self::LINK, 'video_broken_at' => now()])->saveQuietly();

        $this->artisan('live:pull')->assertSuccessful();

        $live->refresh();
        $this->assertSame('live/' . $live->code . '.mp4', $live->video_path);
        $this->assertTrue(Storage::disk('public')->exists($live->video_path));
        $this->assertSame(self::CLIP, Storage::disk('public')->get($live->video_path));
        // Still the archive, and no longer counted as broken.
        $this->assertSame(self::LINK, $live->video_url);
        $this->assertNull($live->video_broken_at);

        // And now the box plays even with the public side still refusing.
        $this->yandexServes(false);
        $this->get('/canli/' . $live->code . '/video')
            ->assertRedirect()
            ->assertRedirectContains($live->video_path);
    }

    /** An old yadi.sk link names the same file as its disk.yandex.ru twin. */
    public function test_both_spellings_of_a_link_find_the_same_file(): void
    {
        $this->assertSame(
            \App\Support\YandexDisk::linkKey('https://yadi.sk/i/M7DpNGtcJvtU8Q'),
            \App\Support\YandexDisk::linkKey('https://disk.yandex.ru/i/M7DpNGtcJvtU8Q'),
        );
    }

    // ------------------------------------------------------- the box still works

    /** The link died, the copy is here: the box plays. */
    public function test_a_dead_link_falls_back_to_the_hosting(): void
    {
        $live = $this->onTheHosting();
        $live->forceFill(['video_url' => self::LINK])->saveQuietly();
        $this->yandexServes(false);

        $this->get('/canli/' . $live->code . '/video')
            ->assertRedirect()
            ->assertRedirectContains('live/clip.mp4');
    }

    /** With no copy here, a working link is still what plays. */
    public function test_a_live_link_plays_where_there_is_no_copy(): void
    {
        $live = $this->live();
        $live->forceFill(['video_url' => self::LINK])->saveQuietly();
        $this->yandexServes(true);

        $this->get('/canli/' . $live->code . '/video')
            ->assertRedirect()
            ->assertRedirectContains('downloader.disk.yandex.ru');
    }

    /** Nothing anywhere is the only case that refuses. */
    public function test_with_no_copy_and_no_link_it_says_so(): void
    {
        $live = $this->live();
        $live->forceFill(['video_url' => self::LINK])->saveQuietly();
        $this->yandexServes(false);

        $this->get('/canli/' . $live->code . '/video')->assertStatus(503);
    }

    // ---------------------------------------------------------- it is noticed

    public function test_checking_writes_down_when_it_broke_and_when_it_mended(): void
    {
        $live = $this->live();
        $live->forceFill(['video_url' => self::LINK])->saveQuietly();

        $this->yandexServes(false);
        $this->assertFalse($live->checkVideo());
        $broke = $live->refresh()->video_broken_at;
        $this->assertNotNull($broke);
        $this->assertNotNull($live->video_checked_at);

        // Still broken tomorrow: the first time it broke is the useful one.
        $this->assertFalse($live->checkVideo());
        $this->assertEquals($broke, $live->refresh()->video_broken_at);

        $this->yandexServes(true);
        $this->assertTrue($live->checkVideo());
        $this->assertNull($live->refresh()->video_broken_at);
    }

    /**
     * Told once, not every morning.
     *
     * A warning that arrives daily stops being read, and the day it matters
     * it is one of thirty.
     */
    public function test_the_owner_is_told_once_when_it_breaks_and_once_when_it_returns(): void
    {
        Setting::put(Setting::TELEGRAM_TOKEN, Crypt::encryptString('bot-token'));
        Setting::put(Setting::TELEGRAM_CHAT, '-100500');
        $this->assertTrue(Telegram::on());

        $live = $this->live();
        $live->forceFill(['video_url' => self::LINK])->saveQuietly();

        $this->yandexServes(false);
        $this->artisan('live:check')->assertSuccessful();
        $this->assertSame(1, $this->messagesSent(), 'the first break was not reported');

        $this->yandexServes(false);
        $this->artisan('live:check')->assertSuccessful();
        $this->assertSame(0, $this->messagesSent(), 'the same break was reported twice');

        $this->yandexServes(true);
        $this->artisan('live:check')->assertSuccessful();
        $this->assertSame(1, $this->messagesSent(), 'nobody was told it came back');
    }

    /**
     * The message says what Yandex says, not what it looks like.
     *
     * With the account's download allowance spent, every link answers
     * «not found» on the download endpoint - and reading that literally
     * sent the owner hunting for files that were on his Disk the whole
     * time. The metadata endpoint beside it names the real reason.
     */
    public function test_a_spent_download_allowance_is_not_reported_as_a_missing_file(): void
    {
        Setting::put(Setting::TELEGRAM_TOKEN, Crypt::encryptString('bot-token'));
        Setting::put(Setting::TELEGRAM_CHAT, '-100500');

        $live = $this->live();
        $live->forceFill(['video_url' => self::LINK])->saveQuietly();

        $this->reason = 'DiskResourceDownloadLimitExceededError';
        $this->yandexServes(false);
        $this->artisan('live:check')->assertSuccessful();

        $said = $this->lastMessage();
        $this->assertStringContainsString('limiti dolub', $said);
        $this->assertStringNotContainsString('silinib', $said);
    }

    /** A link that really is gone still says so. */
    public function test_a_missing_file_is_still_reported_as_missing(): void
    {
        Setting::put(Setting::TELEGRAM_TOKEN, Crypt::encryptString('bot-token'));
        Setting::put(Setting::TELEGRAM_CHAT, '-100500');

        $live = $this->live();
        $live->forceFill(['video_url' => self::LINK])->saveQuietly();

        $this->reason = 'DiskNotFoundError';
        $this->yandexServes(false);
        $this->artisan('live:check')->assertSuccessful();

        $this->assertStringContainsString('tapılmadı', $this->lastMessage());
    }

    /** What the message says is what he has to do about it. */
    public function test_the_message_names_the_box_and_where_to_mend_it(): void
    {
        Setting::put(Setting::TELEGRAM_TOKEN, Crypt::encryptString('bot-token'));
        Setting::put(Setting::TELEGRAM_CHAT, '-100500');

        $live = $this->live();
        $live->forceFill(['video_url' => self::LINK])->saveQuietly();
        $this->yandexServes(false);

        $this->artisan('live:check')->assertSuccessful();

        $said = $this->lastMessage();
        $this->assertStringContainsString('Toy videosu', $said);
        $this->assertStringContainsString($live->code, $said);
        $this->assertStringContainsString('/admin/live-photos', $said);
        // The natural guess is the token, and the token has nothing to do with it.
        $this->assertStringContainsString('Paylaş', $said);
    }

    /** And the owner sees which box is carrying a dead code, where he works. */
    public function test_the_admin_list_marks_a_broken_one(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $live = $this->live();
        $live->forceFill(['video_url' => self::LINK])->saveQuietly();

        $this->get('/admin/live-photos')->assertOk()->assertDontSee('Link işləmir');

        $this->yandexServes(false);
        $live->checkVideo();

        $this->get('/admin/live-photos')->assertOk()->assertSee('Link işləmir');
    }

    /**
     * The order number is said once.
     *
     * A live photo made with an order is already titled «Sifariş #17, …»,
     * and the message added «(sifariş #17)» after it - which reads as the
     * machine having lost count.
     */
    public function test_the_order_number_is_not_printed_twice(): void
    {
        Setting::put(Setting::TELEGRAM_TOKEN, Crypt::encryptString('bot-token'));
        Setting::put(Setting::TELEGRAM_CHAT, '-100500');

        $order = Order::create(['user_id' => User::factory()->create()->id, 'status' => 'confirmed',
            'locale' => 'az', 'contact_phone' => '1', 'delivery_address' => 'x']);
        $item = $order->items()->create(['product_name' => 'Test', 'customer_photos' => [],
            'custom_texts' => [], 'quantity' => 1]);

        $live = $this->live(['title' => 'Sifaris #' . $order->id . ', Cici bebe sari']);
        $live->forceFill(['video_url' => self::LINK, 'order_item_id' => $item->id])->saveQuietly();

        $this->yandexServes(false);
        $this->artisan('live:check')->assertSuccessful();

        $said = $this->lastMessage();
        $this->assertSame(1, substr_count($said, '#' . $order->id), $said);
    }

    // ------------------------------------------------------------ the viewer

    /** The customer is told, rather than left holding a box at a black screen. */
    public function test_the_page_carries_a_line_for_a_video_that_will_not_play(): void
    {
        $live = $this->onTheHosting();
        $live->forceFill(['video_url' => self::LINK])->saveQuietly();

        $this->get('/canli/' . $live->code)->assertOk()
            ->assertSee('id="gone"', false)
            ->assertSee('videosu hazırda açılmır')
            // Said by a plain script, so a failed module or CDN cannot eat it.
            ->assertSee("clip.addEventListener('error'", false);
    }

    // ---------------------------------------------------------------- helpers

    /** How many Telegram messages have gone out since this was last asked. */
    private function messagesSent(): int
    {
        $all = collect(Http::recorded())
            ->filter(fn ($pair) => str_contains($pair[0]->url(), 'api.telegram.org')
                && str_contains($pair[0]->url(), 'sendMessage'))
            ->count();

        $new = $all - $this->seen;
        $this->seen = $all;

        return $new;
    }

    private function lastMessage(): string
    {
        $call = collect(Http::recorded())
            ->last(fn ($pair) => str_contains($pair[0]->url(), 'sendMessage'));

        return (string) ($call[0]->data()['text'] ?? '');
    }
}
