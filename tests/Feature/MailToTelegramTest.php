<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\MailToTelegram;
use App\Support\Telegram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * A letter at the shop's mailbox, read out in Telegram.
 *
 * Everything here is parsed by hand — ext-imap is not on the hosting — so
 * the shapes a real mailbox throws at it are what these tests are for:
 * encoded Azerbaijani subjects, quoted-printable bodies, an HTML-only
 * letter, a multipart one with a photograph attached.
 */
class MailToTelegramTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);
    }

    private function switchOn(): void
    {
        Telegram::saveToken('123:abc');
        Setting::put(Setting::TELEGRAM_CHAT, '-100500');
        Setting::put(Setting::MAIL_TELEGRAM, true);
    }

    public function test_it_reads_an_encoded_azerbaijani_subject(): void
    {
        $mail = MailToTelegram::read(
            "From: =?UTF-8?B?QXlnw7xuIE3JmWxpa292YQ==?= <aygun@example.com>\n"
            . "Subject: =?UTF-8?B?U2lmYXJpxZ8gaGFxccSxbmRh?=\n"
            . "Content-Type: text/plain; charset=UTF-8\n\n"
            . "Salam!\n"
        );

        $this->assertSame('Aygün Məlikova <aygun@example.com>', $mail['from']);
        $this->assertSame('Sifariş haqqında', $mail['subject']);
        $this->assertSame('Salam!', $mail['body']);
    }

    public function test_a_quoted_printable_body_comes_out_readable(): void
    {
        $mail = MailToTelegram::read(
            "From: a@b.az\nSubject: Test\n"
            . "Content-Type: text/plain; charset=UTF-8\n"
            . "Content-Transfer-Encoding: quoted-printable\n\n"
            . "Sabah=C9=99 haz=C4=B1r olar=C4=B1qm=C4=B1?\n"
        );

        $this->assertSame('Sabahə hazır olarıqmı?', $mail['body']);
    }

    public function test_a_folded_header_is_put_back_together(): void
    {
        $mail = MailToTelegram::read(
            "From: a@b.az\n"
            . "Subject: Bu uzun bir mövzudur\n və ikinci sətirdə davam edir\n"
            . "Content-Type: text/plain; charset=UTF-8\n\n"
            . "mətn\n"
        );

        $this->assertSame('Bu uzun bir mövzudur və ikinci sətirdə davam edir', $mail['subject']);
    }

    public function test_it_prefers_the_plain_part_and_lists_the_attachments(): void
    {
        $raw = "From: a@b.az\nSubject: Şəkil\n"
            . "Content-Type: multipart/mixed; boundary=\"XX\"\n\n"
            . "--XX\nContent-Type: multipart/alternative; boundary=\"YY\"\n\n"
            . "--YY\nContent-Type: text/plain; charset=UTF-8\n\nBu mətn hissəsidir.\n"
            . "--YY\nContent-Type: text/html; charset=UTF-8\n\n<p>Bu <b>HTML</b></p>\n"
            . "--YY--\n"
            . "--XX\nContent-Type: image/jpeg\nContent-Disposition: attachment; filename=\"qutu.jpg\"\n\n"
            . "AAAA\n--XX--\n";

        $mail = MailToTelegram::read($raw);

        $this->assertSame('Bu mətn hissəsidir.', $mail['body']);
        $this->assertSame(['qutu.jpg'], $mail['files']);
    }

    public function test_an_html_only_letter_is_read_as_words(): void
    {
        $mail = MailToTelegram::read(
            "From: a@b.az\nSubject: Test\n"
            . "Content-Type: text/html; charset=UTF-8\n\n"
            . "<style>p{color:red}</style><p>Birinci sətir</p><p>İkinci&nbsp;sətir</p>\n"
        );

        $this->assertStringContainsString('Birinci sətir', $mail['body']);
        $this->assertStringContainsString('İkinci sətir', $mail['body']);
        $this->assertStringNotContainsString('<p>', $mail['body']);
        $this->assertStringNotContainsString('color:red', $mail['body']);
    }

    public function test_a_very_long_letter_is_cut_to_a_card(): void
    {
        $mail = MailToTelegram::read(
            "From: a@b.az\nSubject: Uzun\nContent-Type: text/plain; charset=UTF-8\n\n"
            . str_repeat('ə', 5000)
        );

        $this->assertLessThanOrEqual(MailToTelegram::BODY + 1, mb_strlen($mail['body']));
        $this->assertLessThanOrEqual(MailToTelegram::MOST, mb_strlen(MailToTelegram::card($mail)));
    }

    public function test_it_sends_the_card_to_telegram(): void
    {
        $this->switchOn();

        $sent = MailToTelegram::announce(
            "From: Aygün <aygun@example.com>\nSubject: Sifariş\n"
            . "Content-Type: text/plain; charset=UTF-8\n\nSalam!\n"
        );

        $this->assertTrue($sent);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/sendMessage')
                && $request['chat_id'] === '-100500'
                && str_contains($request['text'], 'Yeni məktub')
                && str_contains($request['text'], 'Sifariş')
                && str_contains($request['text'], 'Salam!');
        });
    }

    public function test_its_own_chat_is_used_when_one_is_given(): void
    {
        $this->switchOn();
        Setting::put(Setting::MAIL_TELEGRAM_CHAT, '-100777');

        MailToTelegram::announce("From: a@b.az\nSubject: T\n\nmətn\n");

        Http::assertSent(fn ($request) => $request['chat_id'] === '-100777');
    }

    public function test_nothing_goes_out_while_it_is_switched_off(): void
    {
        Telegram::saveToken('123:abc');
        Setting::put(Setting::TELEGRAM_CHAT, '-100500');
        Setting::put(Setting::MAIL_TELEGRAM, false);

        $this->assertFalse(MailToTelegram::announce("From: a@b.az\nSubject: T\n\nmətn\n"));
        Http::assertNothingSent();
    }

    /** Holiday replies, mailing lists and bounces are left in the mailbox. */
    public function test_machine_letters_are_left_alone(): void
    {
        $this->switchOn();

        $this->assertFalse(MailToTelegram::announce("From: x@y.az\nAuto-Submitted: auto-replied\nSubject: Out of office\n\nnot here\n"));
        $this->assertFalse(MailToTelegram::announce("From: list@y.az\nPrecedence: bulk\nSubject: News\n\nbuy\n"));
        $this->assertFalse(MailToTelegram::announce("From: MAILER-DAEMON@y.az\nSubject: Undelivered\n\nfailed\n"));
        $this->assertFalse(MailToTelegram::announce("Subject: No sender\n\nnothing\n"));

        Http::assertNothingSent();
    }

    /**
     * The pipe must never fail: Exim reads its exit code, and a letter a
     * customer sent must not bounce because a notification did not go out.
     */
    public function test_rubbish_on_the_input_does_not_throw(): void
    {
        $this->switchOn();

        $this->assertFalse(MailToTelegram::announce(''));
        $this->assertIsBool(MailToTelegram::announce("\x00\x01 not a letter at all"));
    }

    public function test_the_pipe_script_says_what_runs_it_and_always_exits_zero(): void
    {
        $pipe = file_get_contents(base_path('mailpipe.php'));

        $this->assertStringContainsString('exit(0)', $pipe);
        $this->assertStringContainsString('php://stdin', $pipe);
        // The shebang is written at deploy time from the hosting's own PHP.
        $this->assertStringContainsString("printf '#!%s -q\\n' \"\$PHPBIN\" > \$APPPATH/mailpipe", file_get_contents(base_path('.cpanel.yml')));
        $this->assertStringContainsString('chmod 755 $APPPATH/mailpipe', file_get_contents(base_path('.cpanel.yml')));
    }
}
