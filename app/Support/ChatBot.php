<?php

namespace App\Support;

use App\Models\ChatMessage;
use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * The chat on the site, carried by a Telegram bot of its own.
 *
 * A visitor writes in the corner of the page; the message goes to the shop's
 * Telegram. The shop answers by replying to that message, and the answer
 * comes back into the same window, because the Telegram message the shop
 * replied to is remembered next to the visitor's thread.
 */
class ChatBot
{
    public static function token(): ?string
    {
        $stored = Setting::get(Setting::CHAT_TOKEN);
        if (blank($stored)) {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (Throwable) {
            return null;
        }
    }

    public static function saveToken(?string $token): void
    {
        $token = trim((string) $token);
        Setting::put(Setting::CHAT_TOKEN, $token === '' ? '' : Crypt::encryptString($token));
    }

    /** Where the shop reads the messages: a person, or a group. */
    public static function chat(): string
    {
        return trim((string) Setting::get(Setting::CHAT_CHAT));
    }

    public static function enabled(): bool
    {
        return Setting::get(Setting::CHAT_ENABLED) === '1' && filled(self::token()) && self::chat() !== '';
    }

    /** The word in the address of the hook, so only Telegram can knock. */
    public static function hookSecret(): string
    {
        $secret = trim((string) Setting::get(Setting::CHAT_HOOK));
        if ($secret === '') {
            $secret = Str::random(32);
            Setting::put(Setting::CHAT_HOOK, $secret);
        }

        return $secret;
    }

    /** Telegram starts sending what the shop writes back. */
    public static function watch(): bool
    {
        return Telegram::call('setWebhook', [
            'url' => route('telegram.chat', self::hookSecret()),
            'secret_token' => self::hookSecret(),
            'allowed_updates' => ['message'],
        ], self::token()) !== null;
    }

    public static function stopWatching(): bool
    {
        return Telegram::call('deleteWebhook', ['drop_pending_updates' => false], self::token()) !== null;
    }

    /** @return array<string, mixed>|null what Telegram says about the hook */
    public static function status(): ?array
    {
        $info = Telegram::call('getWebhookInfo', [], self::token());

        return is_array($info) ? $info : null;
    }

    /**
     * Carries a visitor's line to Telegram and remembers which message it
     * became, so an answer to it finds the thread again.
     */
    public static function forward(ChatMessage $message): bool
    {
        if (! self::enabled()) {
            return false;
        }

        $who = trim((string) $message->name);
        $phone = trim((string) $message->phone);
        $head = '💬 ' . ($who !== '' ? $who : 'Saytdan mesaj');
        if ($phone !== '') {
            $head .= ' · ' . $phone;
        }

        $text = $head . "\n\n" . $message->body;
        if ($message->page) {
            $text .= "\n\n" . $message->page;
        }
        $text .= "\n\nCavab vermək üçün bu mesaja cavab (reply) yazın.";

        // A screenshot goes as a picture, with the words under it.
        $sent = $message->image
            ? self::sendPhoto($message->image, $text)
            : Telegram::post('sendMessage', ['text' => $text], self::chat(), self::token());
        if (! $sent) {
            return false;
        }

        $message->forceFill(['tg_message_id' => $sent['message_id'] ?? null])->save();

        return true;
    }

    /** Sends a picture the visitor attached, with his words as the caption. */
    private static function sendPhoto(string $path, string $caption): ?array
    {
        $file = Storage::disk('public')->path($path);
        if (! is_file($file)) {
            return null;
        }

        try {
            $answer = Http::timeout(30)
                ->attach('photo', file_get_contents($file), basename($file))
                ->post('https://api.telegram.org/bot' . self::token() . '/sendPhoto', [
                    'chat_id' => self::chat(),
                    'caption' => Str::limit($caption, 1000, ''),
                ]);

            if ($answer->successful()) {
                return (array) $answer->json('result', []);
            }

            Log::error('Telegram refused the chat picture: ' . $answer->body());
        } catch (Throwable $e) {
            Log::error('Telegram is unreachable: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * A picture the shop sent back: Telegram keeps it, so it is fetched once
     * and kept with us, where the window can show it.
     */
    private static function keepPhoto(array $message): ?string
    {
        $photos = $message['photo'] ?? [];
        $id = is_array($photos) && $photos ? (end($photos)['file_id'] ?? null) : null;
        if (! $id) {
            return null;
        }

        $file = Telegram::call('getFile', ['file_id' => $id], self::token());
        $path = $file['file_path'] ?? null;
        if (! $path) {
            return null;
        }

        try {
            $answer = Http::timeout(30)->get('https://api.telegram.org/file/bot' . self::token() . '/' . $path);
            if (! $answer->successful()) {
                return null;
            }
        } catch (Throwable $e) {
            Log::error('The chat picture did not come: ' . $e->getMessage());

            return null;
        }

        $name = 'chat/' . Str::uuid() . '.' . (pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg');
        Storage::disk('public')->put($name, $answer->body());

        return $name;
    }

    /**
     * What the shop wrote back. Telegram hands over the whole update; only a
     * reply to one of our own messages says anything, and it goes into that
     * visitor's thread.
     */
    public static function answer(array $update): ?ChatMessage
    {
        $message = $update['message'] ?? null;
        if (! is_array($message)) {
            return null;
        }

        $body = trim((string) ($message['text'] ?? $message['caption'] ?? ''));
        $replyTo = $message['reply_to_message']['message_id'] ?? null;
        $hasPhoto = ! empty($message['photo']);

        if (! $replyTo || ($body === '' && ! $hasPhoto)) {
            return null;
        }

        $asked = ChatMessage::where('tg_message_id', $replyTo)->first();
        if (! $asked) {
            return null;
        }

        return ChatMessage::create([
            'thread' => $asked->thread,
            'side' => ChatMessage::SHOP,
            'body' => Str::limit($body, 2000, ''),
            'image' => $hasPhoto ? self::keepPhoto($message) : null,
        ]);
    }
}
