<?php

namespace App\Support;

use App\Models\Setting;

/**
 * A letter that arrives at the shop's mailbox, read out in Telegram.
 *
 * The owner lives in Telegram: orders, chat messages and company enquiries
 * all land there, and the mailbox is the one place he has to remember to
 * open. cPanel hands the whole message to this class on standard input (see
 * mailpipe.php and the forwarder it is wired to), and what comes out is a
 * short card: who wrote, about what, and the first lines of it. The letter
 * itself still goes to the mailbox — the pipe is an extra delivery, not a
 * replacement — so nothing can be lost here by a misreading.
 *
 * Everything is parsed by hand. ext-imap is not installed on the hosting and
 * there is no composer step on deploy, so a dependency would have to travel
 * in the repository for the sake of reading a handful of headers.
 */
class MailToTelegram
{
    /** Telegram refuses a message over 4096 characters. */
    public const MOST = 3500;

    /** How much of the letter is read out. */
    public const BODY = 1200;

    /** Whether a letter in the mailbox is announced at all. */
    public static function on(): bool
    {
        return Setting::get(Setting::MAIL_TELEGRAM) === '1';
    }

    /** Which chat hears about it; blank means the one the orders go to. */
    public static function chat(): string
    {
        $own = trim((string) Setting::get(Setting::MAIL_TELEGRAM_CHAT));

        return $own !== '' ? $own : Telegram::chat();
    }

    /**
     * Take the whole message and tell Telegram about it.
     *
     * Answers false when nothing was sent — switched off, or a letter of the
     * kind nobody wants read out — and never throws: the caller is a mail
     * pipe, and a pipe that fails bounces the customer's letter back to him.
     */
    public static function announce(string $raw): bool
    {
        try {
            if (! self::on()) {
                return false;
            }

            $mail = self::read($raw);

            if (self::isMachine($mail['headers'])) {
                return false;
            }

            return Telegram::send(self::card($mail), self::chat());
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * The card as it reads in Telegram.
     *
     * @param  array{headers: array<string, string>, from: string, subject: string, date: string, body: string, files: string[]}  $mail
     */
    public static function card(array $mail): string
    {
        $lines = ['✉️ <b>Yeni məktub</b>', ''];

        if ($mail['from'] !== '') {
            $lines[] = '<b>' . e($mail['from']) . '</b>';
        }
        if ($mail['subject'] !== '') {
            $lines[] = '📨 ' . e($mail['subject']);
        }
        if ($mail['date'] !== '') {
            $lines[] = '🕘 ' . e($mail['date']);
        }
        if ($mail['files']) {
            $lines[] = '📎 ' . e(implode(', ', array_slice($mail['files'], 0, 5)));
        }
        if ($mail['body'] !== '') {
            $lines[] = '';
            $lines[] = '<i>' . e($mail['body']) . '</i>';
        }

        $text = implode("\n", $lines);

        return mb_strlen($text) > self::MOST ? mb_substr($text, 0, self::MOST - 1) . '…' : $text;
    }

    /**
     * The parts of a letter this is interested in.
     *
     * @return array{headers: array<string, string>, from: string, subject: string, date: string, body: string, files: string[]}
     */
    public static function read(string $raw): array
    {
        $raw = str_replace("\r\n", "\n", $raw);
        [$head, $body] = array_pad(explode("\n\n", $raw, 2), 2, '');
        $headers = self::headers($head);

        return [
            'headers' => $headers,
            'from' => self::words($headers['from'] ?? ''),
            'subject' => self::words($headers['subject'] ?? ''),
            'date' => trim($headers['date'] ?? ''),
            'body' => self::body($headers, (string) $body),
            'files' => self::files((string) $body),
        ];
    }

    /**
     * Header names lower-cased, folded lines joined back together.
     *
     * @return array<string, string>
     */
    protected static function headers(string $head): array
    {
        $out = [];
        $name = null;

        foreach (explode("\n", $head) as $line) {
            // A header may be folded over several lines; a continuation
            // starts with a space or a tab and belongs to the one before it.
            if ($name !== null && $line !== '' && (str_starts_with($line, ' ') || str_starts_with($line, "\t"))) {
                $out[$name] .= ' ' . trim($line);

                continue;
            }
            if (! str_contains($line, ':')) {
                continue;
            }
            [$key, $value] = explode(':', $line, 2);
            $name = strtolower(trim($key));
            // Only the first of a repeated header: the later ones are the
            // relays the letter passed through.
            $out[$name] ??= trim($value);
        }

        return $out;
    }

    /**
     * Encoded words (=?UTF-8?B?…?=) turned back into text.
     *
     * A subject written in Azerbaijani arrives encoded nearly every time, and
     * a card reading "=?UTF-8?B?U2lmYXJpxZ8=?=" tells the owner nothing.
     */
    public static function words(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $out = preg_replace_callback(
            '/=\?([^?]+)\?([bBqQ])\?([^?]*)\?=/',
            function (array $m): string {
                $text = $m[2] === 'B' || $m[2] === 'b'
                    ? (string) base64_decode($m[3], true)
                    : quoted_printable_decode(str_replace('_', ' ', $m[3]));

                return self::utf8($text, $m[1]);
            },
            $value
        );

        // Encoded words may sit side by side with only whitespace between
        // them, and that whitespace is not part of the text.
        $out = (string) preg_replace('/\?=\s+=\?/', '?==?', (string) $out);

        return trim(preg_replace('/\s+/u', ' ', (string) $out) ?? '');
    }

    /** The readable part of the letter, decoded and cut to length. */
    protected static function body(array $headers, string $body): string
    {
        /* The header is read twice over: lower-cased to see what kind of part
           this is, and as it was written to pull the boundary out of it. A
           boundary is case-sensitive, and lower-casing the whole line turned
           --XX into --xx, which matches nothing — the letter then came out as
           its own raw source, headers and all. */
        $raw = $headers['content-type'] ?? 'text/plain';
        $type = strtolower($raw);

        if (str_contains($type, 'multipart/') && preg_match('/boundary="?([^";\s]+)"?/i', $raw, $m)) {
            $part = self::part($body, $m[1]);
            if ($part !== null) {
                [$headers, $body] = $part;
                $raw = $headers['content-type'] ?? 'text/plain';
                $type = strtolower($raw);
            }
        }

        $text = self::decode($body, strtolower($headers['content-transfer-encoding'] ?? ''));
        $text = self::utf8($text, self::charset($raw));

        if (str_contains($type, 'text/html')) {
            $text = self::fromHtml($text);
        }

        $text = trim(preg_replace("/\n{3,}/", "\n\n", str_replace("\r", '', $text)) ?? '');

        return mb_strlen($text) > self::BODY ? mb_substr($text, 0, self::BODY) . '…' : $text;
    }

    /**
     * The part worth reading out: plain text where there is any, else HTML.
     *
     * @return array{0: array<string, string>, 1: string}|null
     */
    protected static function part(string $body, string $boundary): ?array
    {
        $chunks = explode('--' . $boundary, $body);
        $html = null;

        foreach ($chunks as $chunk) {
            $chunk = ltrim($chunk, "\n");
            if ($chunk === '' || str_starts_with($chunk, '--')) {
                continue;
            }
            [$head, $rest] = array_pad(explode("\n\n", $chunk, 2), 2, '');
            $headers = self::headers($head);
            // As written for the boundary, lower-cased for the comparisons.
            $raw = $headers['content-type'] ?? '';
            $type = strtolower($raw);

            // A part offered as a file is never the letter itself.
            if (str_contains(strtolower($headers['content-disposition'] ?? ''), 'attachment')) {
                continue;
            }
            // Nested alternatives: the plain text usually lives one deeper.
            if (str_contains($type, 'multipart/') && preg_match('/boundary="?([^";\s]+)"?/i', $raw, $m)) {
                $inner = self::part($rest, $m[1]);
                if ($inner !== null) {
                    return $inner;
                }

                continue;
            }
            if (str_contains($type, 'text/plain')) {
                return [$headers, $rest];
            }
            if ($html === null && str_contains($type, 'text/html')) {
                $html = [$headers, $rest];
            }
        }

        return $html;
    }

    /** The names of whatever was attached. @return string[] */
    protected static function files(string $body): array
    {
        preg_match_all('/filename\*?=(?:"([^"]+)"|([^\s;]+))/i', $body, $m, PREG_SET_ORDER);

        $names = [];
        foreach ($m as $hit) {
            $name = self::words(trim($hit[1] ?: ($hit[2] ?? '')));
            if ($name !== '' && ! in_array($name, $names, true)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    protected static function decode(string $body, string $encoding): string
    {
        return match (true) {
            str_contains($encoding, 'base64') => (string) base64_decode(preg_replace('/\s+/', '', $body) ?? '', false),
            str_contains($encoding, 'quoted-printable') => quoted_printable_decode($body),
            default => $body,
        };
    }

    protected static function charset(string $type): string
    {
        return preg_match('/charset="?([^";\s]+)"?/i', $type, $m) ? $m[1] : 'UTF-8';
    }

    /** Whatever it was written in, read out as UTF-8. */
    protected static function utf8(string $text, string $charset): string
    {
        $charset = strtoupper(trim($charset)) ?: 'UTF-8';
        if ($charset === 'UTF-8' || $charset === 'UTF8') {
            return $text;
        }

        $out = @iconv($charset, 'UTF-8//TRANSLIT', $text);

        return $out === false ? $text : $out;
    }

    /** An HTML-only letter, read as the person wrote it. */
    protected static function fromHtml(string $html): string
    {
        $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
        $html = preg_replace('#<br\s*/?>#i', "\n", $html) ?? $html;
        $html = preg_replace('#</(p|div|tr|li|h[1-6])>#i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // &nbsp; comes back as U+00A0, which no ordinary space pattern matches
        // and which reads as two words stuck together in a Telegram card.
        $text = str_replace(["\xC2\xA0", "\xE2\x80\xAF"], ' ', $text);

        return trim(preg_replace('/[ \t]+/', ' ', $text) ?? '');
    }

    /**
     * Letters nobody wants read out: holiday replies, mailing lists and the
     * shop's own delivery reports. A real person's letter has none of these.
     *
     * @param  array<string, string>  $headers
     */
    public static function isMachine(array $headers): bool
    {
        $auto = strtolower($headers['auto-submitted'] ?? '');
        if ($auto !== '' && $auto !== 'no') {
            return true;
        }
        if (strtolower($headers['precedence'] ?? '') !== '' &&
            in_array(strtolower($headers['precedence']), ['bulk', 'junk', 'list'], true)) {
            return true;
        }
        if (isset($headers['list-unsubscribe']) || isset($headers['x-autoreply'])) {
            return true;
        }
        // A bounce carries an empty envelope sender.
        $from = strtolower($headers['from'] ?? '');

        return $from === '' || str_contains($from, 'mailer-daemon');
    }
}
