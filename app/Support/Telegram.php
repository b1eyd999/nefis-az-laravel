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

    public static function on(): bool
    {
        return filled(self::token()) && self::chat() !== '';
    }

    /** Sends a message; a failure is written to the log, never shown to a customer. */
    public static function send(string $text): bool
    {
        if (! self::on()) {
            return false;
        }

        try {
            $answer = Http::timeout(15)->post(self::API . self::token() . '/sendMessage', [
                'chat_id' => self::chat(),
                'text' => $text,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ]);

            if ($answer->successful()) {
                return true;
            }

            Log::error('Telegram refused the message: ' . $answer->body());
        } catch (Throwable $e) {
            Log::error('Telegram is unreachable: ' . $e->getMessage());
        }

        return false;
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
                . ' — ' . Price::format($item->unitPrice() * $item->quantity)
                . ($extras ? "\n   <i>" . e(implode(', ', $extras)) . '</i>' : '');
        }

        $lines[] = '';
        $lines[] = '💰 <b>Cəmi: ' . Price::format($order->total()) . '</b>';
        if ($order->delivery_name) {
            $lines[] = '🚚 ' . e($order->delivery_name) . ' — ' . Price::format((float) $order->delivery_price);
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

    /** The customer says he has paid and uploads the receipt. */
    public static function receipt(Order $order): void
    {
        self::send('🧾 <b>Sifariş #' . $order->id . '</b> üçün ödəniş çeki yükləndi — ' . Price::format($order->total())
            . "\n" . url('/admin/orders/' . $order->id . '/edit'));
    }
}
