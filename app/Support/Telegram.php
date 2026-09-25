<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Orders land in the owner's Telegram the moment they are placed, so he does
 * not have to watch the admin. The bot is his: he makes it in @BotFather,
 * writes to it once, and the token and chat live in admin → Tənzimləmələr.
 */
class Telegram
{
    private const API = 'https://api.telegram.org/bot';

    public static function token(): ?string
    {
        $stored = Setting::get(Setting::TELEGRAM_TOKEN);
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
        Setting::put(Setting::TELEGRAM_TOKEN, $token === '' ? '' : Crypt::encryptString($token));
    }

    public static function chat(): string
    {
        return trim((string) Setting::get(Setting::TELEGRAM_CHAT));
    }

    /**
     * The couriers have a bot of their own, so they see the deliveries and
     * nothing else. Without one the owner's bot carries the messages.
     */
    public static function courierToken(): ?string
    {
        $stored = Setting::get(Setting::TELEGRAM_COURIER_TOKEN);
        if (blank($stored)) {
            return self::token();
        }

        try {
            return Crypt::decryptString($stored);
        } catch (Throwable) {
            return self::token();
        }
    }

    public static function saveCourierToken(?string $token): void
    {
        $token = trim((string) $token);
        Setting::put(Setting::TELEGRAM_COURIER_TOKEN, $token === '' ? '' : Crypt::encryptString($token));
    }

    /** The couriers' group, if the owner has set one up. */
    public static function courierChat(): string
    {
        return trim((string) Setting::get(Setting::TELEGRAM_COURIER_CHAT));
    }

    public static function courierOn(): bool
    {
        return filled(self::courierToken()) && self::courierChat() !== '';
    }

    public static function on(): bool
    {
        return filled(self::token()) && self::chat() !== '';
    }

    /** Sends a message; a failure is written to the log, never shown to a customer. */
    public static function send(string $text, ?string $chat = null, ?string $token = null): bool
    {
        return self::post('sendMessage', ['text' => $text], $chat, $token) !== null;
    }

    /**
     * One call to the bot about a chat. Gives back what Telegram answered, so
     * a message can be remembered and changed later, or null if nothing went.
     *
     * @return array<string, mixed>|null
     */
    public static function post(string $method, array $payload, ?string $chat = null, ?string $token = null): ?array
    {
        $chat = trim((string) ($chat ?: self::chat()));
        $token = trim((string) ($token ?: self::token()));
        if ($token === '' || $chat === '') {
            return null;
        }

        try {
            $answer = Http::timeout(15)->post(self::API . $token . '/' . $method, $payload + [
                'chat_id' => $chat,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ]);

            if ($answer->successful()) {
                return (array) $answer->json('result', []);
            }

            Log::error('Telegram refused ' . $method . ': ' . $answer->body());
        } catch (Throwable $e) {
            Log::error('Telegram is unreachable: ' . $e->getMessage());
        }

        return null;
    }

    /** A call that belongs to no chat: webhooks, and answers to a tap. */
    public static function call(string $method, array $payload = [], ?string $token = null): ?array
    {
        $token = trim((string) ($token ?: self::courierToken()));
        if ($token === '') {
            return null;
        }

        try {
            $answer = Http::timeout(15)->post(self::API . $token . '/' . $method, $payload);

            return $answer->successful() ? (array) $answer->json('result', []) : null;
        } catch (Throwable $e) {
            Log::error('Telegram is unreachable: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * The chat of whoever last wrote to the bot — how the owner's own chat is
     * found without him hunting for numbers.
     *
     * @throws RuntimeException with a message for the owner
     */
    public static function findChat(?string $token = null): string
    {
        $token = trim((string) ($token ?: self::token()));
        if ($token === '') {
            throw new RuntimeException('Əvvəlcə botun tokenini yazın.');
        }

        try {
            $answer = Http::timeout(15)->get(self::API . $token . '/getUpdates');
        } catch (Throwable $e) {
            throw new RuntimeException('Telegram cavab vermir: ' . $e->getMessage());
        }

        if (! $answer->successful()) {
            throw new RuntimeException('Token düzgün deyil (Telegram: ' . $answer->status() . ').');
        }

        foreach (array_reverse((array) $answer->json('result', [])) as $update) {
            $chat = $update['message']['chat'] ?? $update['channel_post']['chat'] ?? null;
            if (isset($chat['id'])) {
                return (string) $chat['id'];
            }
        }

        throw new RuntimeException('Botdan heç bir mesaj görünmür. Telegramda bota "/start" yazın və yenidən yoxlayın.');
    }

    /** A new order, written the way the owner wants to read it on a phone. */
    public static function order(Order $order): void
    {
        $order->loadMissing(['items', 'user']);

        $lines = ['🍫 <b>Yeni sifariş #' . $order->id . '</b>', ''];

        foreach ($order->items as $item) {
            $extras = array_filter([
                $item->chocolate_name,
                $item->wrapping_name ? 'kağız: ' . $item->wrapping_name : null,
                $item->letter_price ? 'polaroid məktub' : null,
                $item->ar_price ? 'canlı şəkil' : null,
            ]);

            $lines[] = '• ' . e($item->product_name) . ' × ' . $item->quantity
                . ', ' . Price::format($item->unitPrice() * $item->quantity)
                . ($extras ? "\n   <i>" . e(implode(', ', $extras)) . '</i>' : '');
        }

        $lines[] = '';
        $lines[] = '💰 <b>Cəmi: ' . Price::format($order->total()) . '</b>';
        if ($order->delivery_name) {
            $lines[] = '🚚 ' . e($order->delivery_name) . ', ' . Price::format((float) $order->delivery_price);
        }
        if ($order->delivery_date) {
            $lines[] = '🗓 ' . e(DeliveryTime::day($order->delivery_date) . ($order->delivery_slot ? ', ' . $order->delivery_slot : ''));
        }
        foreach (array_filter([
            '👤' => $order->recipient_name ?: $order->user?->name,
            '📞' => $order->contact_phone,
            '📍' => $order->delivery_address ?: $order->metro_station,
            '📝' => $order->note,
        ]) as $icon => $value) {
            $lines[] = $icon . ' ' . e($value);
        }

        $lines[] = '';
        $lines[] = url('/admin/orders/' . $order->id . '/edit');

        self::send(implode("\n", $lines));
    }

    /**
     * An order that is made, written for whoever takes it out: who, where,
     * when and the number to ring — nothing about money or the design.
     */
    public static function courier(Order $order): bool
    {
        if (! self::courierOn()) {
            return false;
        }

        $sent = self::post('sendMessage', [
            'text' => self::courierText($order),
            // Whoever is free taps it, and his name then stands under the address.
            'reply_markup' => json_encode(['inline_keyboard' => [[
                ['text' => '🚴 Mən götürürəm', 'callback_data' => 'take:' . $order->id],
            ]]], JSON_UNESCAPED_UNICODE),
        ], self::courierChat(), self::courierToken());

        if ($sent && isset($sent['message_id'])) {
            $order->forceFill([
                'courier_chat_id' => (string) ($sent['chat']['id'] ?? self::courierChat()),
                'courier_message_id' => (int) $sent['message_id'],
            ])->saveQuietly();
        }

        return $sent !== null;
    }

    /** What the couriers read: who, where, when, and who took it. */
    public static function courierText(Order $order): string
    {
        $order->loadMissing('user');

        $lines = ['📦 <b>Sifariş #' . $order->id . ' hazırdır</b>', ''];

        // The day always stands there, even when the customer chose none, so
        // a courier never wonders whether it was simply left out.
        $when = $order->delivery_date
            ? DeliveryTime::day($order->delivery_date) . ($order->delivery_slot ? ', ' . $order->delivery_slot : '')
            : 'vaxt seçilməyib, müştəri ilə dəqiqləşdirin';

        foreach (array_filter([
            '👤' => $order->recipient_name ?: $order->user?->name,
            '📞' => $order->contact_phone ?: $order->user?->phone,
            '🗓' => $when,
            '🚚' => $order->delivery_name,
            '📍' => $order->deliverySummary(),
            '📝' => $order->note,
        ]) as $icon => $value) {
            $lines[] = $icon . ' ' . e($value);
        }

        if ($map = $order->mapUrl()) {
            $lines[] = '';
            $lines[] = '🗺 ' . $map;
        }
        if ($order->courier_name) {
            $lines[] = '';
            $lines[] = '🚴 <b>Götürdü:</b> ' . e($order->courier_name);
        }

        return implode("\n", $lines);
    }

    /**
     * A courier tapped the message: his name goes under the text and the
     * button disappears, so the others see it is taken.
     */
    public static function courierTaken(Order $order): void
    {
        if (! $order->courier_chat_id || ! $order->courier_message_id) {
            return;
        }

        self::call('editMessageText', [
            'chat_id' => $order->courier_chat_id,
            'message_id' => $order->courier_message_id,
            'text' => self::courierText($order),
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ], self::courierToken());
    }

    /** The secret Telegram sends back with every tap, made once and kept. */
    public static function hookSecret(): string
    {
        $secret = trim((string) Setting::get(Setting::TELEGRAM_HOOK_SECRET));
        if ($secret === '') {
            $secret = bin2hex(random_bytes(16));
            Setting::put(Setting::TELEGRAM_HOOK_SECRET, $secret);
        }

        return $secret;
    }

    /** Tells Telegram where to call when a courier taps a message. */
    public static function watchTaps(): bool
    {
        return self::call('setWebhook', [
            'url' => route('telegram.courier', self::hookSecret()),
            'secret_token' => self::hookSecret(),
            'allowed_updates' => json_encode(['callback_query']),
            'drop_pending_updates' => true,
        ], self::courierToken()) !== null;
    }

    public static function stopWatching(): bool
    {
        return self::call('deleteWebhook', ['drop_pending_updates' => false], self::courierToken()) !== null;
    }

    /** Whether Telegram is calling us, and about what. */
    public static function tapStatus(): ?array
    {
        $info = self::call('getWebhookInfo', [], self::courierToken());

        return is_array($info) ? $info : null;
    }

    /** The customer says he has paid and uploads the receipt. */
    public static function receipt(Order $order): void
    {
        self::send('🧾 <b>Sifariş #' . $order->id . '</b> üçün ödəniş çeki yükləndi, ' . Price::format($order->total())
            . "\n" . url('/admin/orders/' . $order->id . '/edit'));
    }
}
