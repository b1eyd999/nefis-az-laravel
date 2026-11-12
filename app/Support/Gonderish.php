<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The customer's WhatsApp, written by the shop itself through Göndəriş
 * (gonderish.az) — the owner's own sending service, with the Nefis.az number
 * connected there. Two moments: the order is placed, and the money is in.
 *
 * Göndəriş does not fire the message on the spot: it queues it behind the
 * number's pauses and limits, which is what keeps the number from being
 * banned. A failure here is logged and nothing else — an order must never
 * fail because a message could not be sent.
 */
class Gonderish
{
    public const URL = 'https://gonderish.az/api/v1';

    public static function key(): ?string
    {
        $stored = Setting::get(Setting::GONDERISH_KEY);
        if (blank($stored)) {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (Throwable) {
            return null;
        }
    }

    public static function saveKey(?string $value): void
    {
        $value = trim((string) $value);
        Setting::put(Setting::GONDERISH_KEY, $value === '' ? '' : Crypt::encryptString($value));
    }

    public static function onPlaced(): bool
    {
        return Setting::get(Setting::GONDERISH_PLACED) === '1' && filled(self::key());
    }

    public static function onPaid(): bool
    {
        return Setting::get(Setting::GONDERISH_PAID) === '1' && filled(self::key());
    }

    /** The order was just written down. */
    public static function placed(Order $order): void
    {
        if (self::onPlaced()) {
            self::send($order, 'placed');
        }
    }

    /** The money arrived, whichever way it was confirmed. */
    public static function paid(Order $order): void
    {
        if (self::onPaid()) {
            self::send($order, 'paid');
        }
    }

    /** One message per order and moment — a repeated callback must not write twice. */
    private static function send(Order $order, string $moment): void
    {
        $to = CustomerNotice::phone($order->contact_phone ?: $order->user?->phone);
        if (! $to || ! Cache::add("gonderish:{$order->id}:{$moment}", 1, now()->addDays(30))) {
            return;
        }

        try {
            $res = Http::timeout(8)->acceptJson()->withToken((string) self::key())
                ->post(self::URL.'/messages', [
                    'to' => $to,
                    'text' => CustomerNotice::text($order),
                    'ref' => "nefis-{$order->id}-{$moment}",
                    'name' => $order->user?->name,
                ]);
            if (! $res->successful()) {
                Cache::forget("gonderish:{$order->id}:{$moment}");
                Log::warning('Göndəriş: WhatsApp mesajı qəbul olunmadı', ['order' => $order->id, 'status' => $res->status(), 'error' => $res->json('error')]);
            }
        } catch (Throwable $e) {
            Cache::forget("gonderish:{$order->id}:{$moment}");
            Log::warning('Göndəriş: WhatsApp mesajı göndərilmədi: '.$e->getMessage(), ['order' => $order->id]);
        }
    }

    /** For the settings page: does the key work, and which number will write? */
    public static function check(?string $key = null): string
    {
        $key = trim((string) ($key ?: self::key()));
        if ($key === '') {
            return 'Açar yazılmayıb.';
        }
        try {
            $res = Http::timeout(8)->acceptJson()->withToken($key)->get(self::URL.'/me');
        } catch (Throwable $e) {
            return 'Göndəriş-ə qoşulmaq olmadı: '.$e->getMessage();
        }
        if ($res->status() === 401) {
            return 'Açar səhvdir (Göndəriş qəbul etmədi).';
        }
        $numbers = collect($res->json('numbers') ?? []);
        $on = $numbers->firstWhere('connected', true);

        return $on
            ? 'Hazırdır: mesajlar +'.$on['phone'].' nömrəsindən gedəcək.'
            : 'Açar düzdür, amma Göndəriş-də WhatsApp nömrəsi qoşulu deyil.';
    }
}
