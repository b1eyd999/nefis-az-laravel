<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\WhatsAppChat;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * WhatsApp, through Meta's own Cloud API.
 *
 * The shop talks to one address and Meta pushes everything back to one hook.
 * Two things are worth knowing before reading further.
 *
 * The number used here belongs to the API and stops working in the ordinary
 * WhatsApp application. That is why the shop keeps two numbers: this one for
 * the panel, the other for the telephone in the owner's hand. Meta's own
 * «Coexistence», which lets a number do both, is open to solution partners
 * only and not to a shop running its own integration.
 *
 * And WhatsApp lets a business answer freely for twenty-four hours after the
 * customer last wrote, and not a minute longer. Outside that only a message
 * Meta has approved in advance may be sent. Everything here is built for
 * answering inside the day, which is what a shop actually does.
 */
class WhatsApp
{
    private const API = 'https://graph.facebook.com/v21.0';

    public static function enabled(): bool
    {
        return Setting::get(Setting::WHATSAPP_ENABLED) === '1'
            && filled(self::phoneId())
            && filled(self::token());
    }

    public static function phoneId(): string
    {
        return trim((string) Setting::get(Setting::WHATSAPP_PHONE_ID));
    }

    /** The number as a customer would dial it. */
    public static function number(): string
    {
        return trim((string) Setting::get(Setting::WHATSAPP_NUMBER));
    }

    public static function token(): ?string
    {
        return self::secret(Setting::WHATSAPP_TOKEN);
    }

    public static function verifyWord(): ?string
    {
        return self::secret(Setting::WHATSAPP_VERIFY);
    }

    public static function appSecret(): ?string
    {
        return self::secret(Setting::WHATSAPP_APP_SECRET);
    }

    public static function saveToken(?string $value): void
    {
        self::keep(Setting::WHATSAPP_TOKEN, $value);
    }

    public static function saveVerifyWord(?string $value): void
    {
        self::keep(Setting::WHATSAPP_VERIFY, $value);
    }

    public static function saveAppSecret(?string $value): void
    {
        self::keep(Setting::WHATSAPP_APP_SECRET, $value);
    }

    /** Kept encrypted, like every other key of the shop's own. */
    private static function keep(string $key, ?string $value): void
    {
        $value = trim((string) $value);
        Setting::put($key, $value === '' ? '' : Crypt::encryptString($value));
    }

    private static function secret(string $key): ?string
    {
        $stored = Setting::get($key);
        if (blank($stored)) {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Is this notice really from Meta?
     *
     * Everything Meta sends is signed with the app secret. Without this check
     * the hook is a public address at which anybody could write whatever he
     * liked into the shop's conversations.
     */
    public static function signed(string $body, ?string $header): bool
    {
        $secret = self::appSecret();
        if (blank($secret) || blank($header)) {
            return false;
        }

        $theirs = Str::after((string) $header, 'sha256=');
        $ours = hash_hmac('sha256', $body, $secret);

        return hash_equals($ours, $theirs);
    }

    /**
     * Answer somebody who has written.
     *
     * Returns WhatsApp's own id for the message, or null when it did not go.
     * The row is written either way: an answer that failed must still be
     * visible in the panel, or the shop goes on believing it replied.
     */
    public static function reply(WhatsAppChat $chat, string $text, ?int $userId = null): ?WhatsAppMessage
    {
        $text = trim($text);
        if ($text === '' || ! self::enabled()) {
            return null;
        }

        $line = WhatsAppMessage::create([
            'wa_id' => $chat->wa_id,
            'direction' => WhatsAppMessage::OUT,
            'type' => 'text',
            'body' => $text,
            'status' => 'sending',
            'user_id' => $userId,
            'sent_at' => now(),
        ]);

        try {
            $answer = Http::withToken((string) self::token())
                ->timeout(20)
                ->post(self::API . '/' . self::phoneId() . '/messages', [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $chat->wa_id,
                    'type' => 'text',
                    'text' => ['preview_url' => true, 'body' => $text],
                ]);
        } catch (Throwable $e) {
            $line->forceFill(['status' => 'failed', 'error' => Str::limit($e->getMessage(), 400)])->save();

            return $line;
        }

        if (! $answer->successful()) {
            $why = (string) ($answer->json('error.message') ?? $answer->body());
            // The number is not written out: the log is plain text on a
            // shared host, and the conversation is already in the panel.
            Log::warning('whatsapp: the answer did not go', ['chat' => $chat->id, 'why' => $why]);
            $line->forceFill(['status' => 'failed', 'error' => Str::limit($why, 400)])->save();

            return $line;
        }

        $line->forceFill([
            'status' => 'sent',
            'wam_id' => $answer->json('messages.0.id'),
        ])->save();

        $chat->forceFill(['last_message_at' => now()])->save();

        return $line;
    }

    /**
     * A file somebody sent, brought onto the shop's own disk.
     *
     * Meta keeps it for a few days and behind a token, so a link into their
     * store is no use in a panel somebody opens next week.
     */
    /** As much of somebody else's file as the shop is willing to hold. */
    public const MEDIA_MB = 24;

    public static function fetchMedia(string $mediaId): ?array
    {
        $token = self::token();
        if (blank($token)) {
            return null;
        }

        try {
            $about = Http::withToken($token)->timeout(20)->get(self::API . '/' . $mediaId);
            if (! $about->successful() || blank($about->json('url'))) {
                return null;
            }

            /* Meta takes a document of a hundred megabytes, and this answer
               is held whole in memory before it is written. On a shared host
               that is the end of the request — and Meta, seeing no 200,
               sends the same notice again, so it would be the end of every
               retry too. What is too big is left with Meta; the message
               still arrives, saying what kind of thing it was. */
            $size = (int) ($about->json('file_size') ?? 0);
            if ($size > self::MEDIA_MB * 1024 * 1024) {
                Log::warning('whatsapp: a file too big to keep', ['media' => $mediaId, 'bytes' => $size]);

                return null;
            }

            $url = (string) $about->json('url');

            // Only from Meta's own store: the token below travels with it.
            $host = (string) (parse_url($url, PHP_URL_HOST) ?: '');
            if (! preg_match('/(^|\\.)(fbcdn\\.net|facebook\\.com|fbsbx\\.com|whatsapp\\.net)$/i', $host)) {
                Log::warning('whatsapp: a file offered from somewhere else', ['host' => $host]);

                return null;
            }

            $file = Http::withToken($token)->timeout(60)->get($url);
            if (! $file->successful()) {
                return null;
            }
        } catch (Throwable $e) {
            Log::warning('whatsapp: a file did not come', ['media' => $mediaId, 'why' => $e->getMessage()]);

            return null;
        }

        $mime = (string) ($about->json('mime_type') ?: $file->header('Content-Type'));
        $path = 'whatsapp/' . now()->format('Y/m') . '/' . Str::lower(Str::random(16)) . self::endingFor($mime);

        if (Storage::disk('public')->put($path, $file->body()) === false) {
            return null;
        }

        return ['path' => $path, 'mime' => $mime];
    }

    /** A name a browser will open rather than download as a mystery. */
    private static function endingFor(string $mime): string
    {
        return match (true) {
            str_contains($mime, 'jpeg') => '.jpg',
            str_contains($mime, 'png') => '.png',
            str_contains($mime, 'webp') => '.webp',
            str_contains($mime, 'gif') => '.gif',
            str_contains($mime, 'mp4') => '.mp4',
            str_contains($mime, 'ogg') => '.ogg',
            str_contains($mime, 'mpeg') => '.mp3',
            str_contains($mime, 'pdf') => '.pdf',
            default => '.bin',
        };
    }

    /** The number written the way people write it here, if it can be. */
    public static function written(string $waId): string
    {
        return Contact::az('+' . $waId) ?: '+' . $waId;
    }
}
