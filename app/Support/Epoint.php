<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderAdjustment;
use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Paying by card, through ePoint.
 *
 * Everything travels as two form fields: `data`, a base64 JSON body, and
 * `signature`, which is base64(sha1(private_key . data . private_key)) taken
 * over the raw twenty bytes. The same pair comes back on the callback, so a
 * message that was not signed with our own key is simply not ours and is
 * thrown away — that check is the whole security of this.
 *
 * The keys are the owner's; they are typed into the admin and kept in the
 * settings table, never in the repository, which is public.
 */
class Epoint
{
    public const API = 'https://epoint.az/api/1/request';

    /**
     * The wallet widget: Google Pay on the web, Apple Pay on the web and in
     * an app. It answers with an address to put in an iframe, and the
     * customer pays inside it without leaving the shop.
     */
    public const WIDGET = 'https://epoint.az/api/1/token/widget';

    public const CURRENCY = 'AZN';

    /** Answers we are given for a payment. */
    public const SUCCESS = 'success';

    /** How long a payment may be on its way before the shop asks again. */
    public const IN_FLIGHT_MINUTES = 30;

    public static function publicKey(): string
    {
        return trim((string) Setting::get(Setting::EPOINT_PUBLIC_KEY));
    }

    /** The secret half of the pair. Kept encrypted, like the bot tokens. */
    public static function privateKey(): ?string
    {
        $stored = (string) Setting::get(Setting::EPOINT_PRIVATE_KEY);

        if (trim($stored) === '') {
            return null;
        }

        try {
            return trim(Crypt::decryptString($stored));
        } catch (Throwable) {
            return null;
        }
    }

    public static function savePrivateKey(?string $key): void
    {
        $key = trim((string) $key);
        Setting::put(Setting::EPOINT_PRIVATE_KEY, $key === '' ? '' : Crypt::encryptString($key));
    }

    /** Offered to a customer only when the owner has switched it on and given both keys. */
    public static function enabled(): bool
    {
        return Setting::get(Setting::EPOINT_ENABLED) === '1'
            && self::publicKey() !== ''
            && self::privateKey() !== null;
    }

    public static function sign(string $data): string
    {
        $key = (string) self::privateKey();

        return base64_encode(sha1($key . $data . $key, true));
    }

    /** True only for a body signed with our own key. */
    public static function verify(string $data, ?string $signature): bool
    {
        if (self::privateKey() === null || ! is_string($signature) || $signature === '') {
            return false;
        }

        return hash_equals(self::sign($data), $signature);
    }

    /** @return array<string, mixed> */
    public static function decode(string $data): array
    {
        $json = json_decode((string) base64_decode($data, true), true);

        return is_array($json) ? $json : [];
    }

    /**
     * A reference the gateway sees instead of the order's own number.
     *
     * A customer whose card is refused tries again, and a gateway that has
     * already seen this order_id may refuse the second attempt — so every
     * attempt carries its own reference and the order keeps the last one.
     */
    public static function reference(Order $order): string
    {
        return $order->id . '-' . now()->format('ymdHis');
    }

    /** The order a reference belongs to, whichever attempt it was. */
    public static function orderIdFrom(?string $reference): ?int
    {
        $id = (int) explode('-', (string) $reference)[0];

        return $id > 0 ? $id : null;
    }

    /**
     * A reference for money owed over a change made to an order that was
     * already paid for. It names the order first, so everything that reads a
     * reference keeps working, and then the adjustment — `12-d3-251004…` is
     * the third change to order 12.
     */
    public static function adjustmentReference(OrderAdjustment $adjustment): string
    {
        return $adjustment->order_id . '-d' . $adjustment->id . '-' . now()->format('ymdHis');
    }

    /**
     * Asks the gateway for a page worth just the difference.
     *
     * The order's own payment is never touched by this: the amount, the
     * reference and the confirmation all live on the adjustment, so the
     * handler that refuses a second payment on a paid order never sees it.
     */
    public static function startAdjustment(OrderAdjustment $adjustment, string $successUrl, string $errorUrl): ?string
    {
        if (! self::enabled() || ! $adjustment->isCharge() || $adjustment->amount <= 0) {
            return null;
        }

        $reference = self::adjustmentReference($adjustment);

        $payload = [
            'public_key' => self::publicKey(),
            'amount' => number_format((float) $adjustment->amount, 2, '.', ''),
            'currency' => self::CURRENCY,
            'language' => match (Locale::current()) {
                'ru' => 'ru',
                'en' => 'en',
                default => 'az',
            },
            'order_id' => $reference,
            'description' => 'Nefis.az sifariş #' . $adjustment->order_id . ' — əlavə',
            'success_redirect_url' => $successUrl,
            'error_redirect_url' => $errorUrl,
        ];

        $answer = self::post($payload);

        if (($answer['status'] ?? null) !== self::SUCCESS || empty($answer['redirect_url'])) {
            Log::warning('epoint: surcharge not started', [
                'order' => $adjustment->order_id,
                'adjustment' => $adjustment->id,
                'status' => $answer['status'] ?? null,
                'message' => $answer['message'] ?? null,
                'trace_id' => $answer['trace_id'] ?? null,
            ]);

            return null;
        }

        $adjustment->forceFill([
            'payment_method' => 'card',
            'epoint_ref' => $reference,
            'epoint_transaction' => $answer['transaction'] ?? null,
            'payment_started_at' => now(),
            'payment_asked_for' => $payload['amount'],
        ])->save();

        return $answer['redirect_url'];
    }

    /**
     * Asks for a payment page and gives back the address to send the customer
     * to, or null when the gateway would not start one.
     */
    public static function start(Order $order, string $successUrl, string $errorUrl): ?string
    {
        if (! self::enabled()) {
            return null;
        }

        $reference = self::reference($order);

        $payload = [
            'public_key' => self::publicKey(),
            'amount' => number_format($order->total(), 2, '.', ''),
            'currency' => self::CURRENCY,
            'language' => match (Locale::current()) {
                'ru' => 'ru',
                'en' => 'en',
                default => 'az',
            },
            'order_id' => $reference,
            'description' => 'Nefis.az sifariş #' . $order->id,
            'success_redirect_url' => $successUrl,
            'error_redirect_url' => $errorUrl,
        ];

        $answer = self::post($payload);

        if (($answer['status'] ?? null) !== self::SUCCESS || empty($answer['redirect_url'])) {
            Log::warning('epoint: payment not started', [
                'order' => $order->id,
                'status' => $answer['status'] ?? null,
                'message' => $answer['message'] ?? null,
                'trace_id' => $answer['trace_id'] ?? null,
            ]);

            return null;
        }

        $order->forceFill([
            'payment_method' => 'card',
            'epoint_ref' => $reference,
            'epoint_transaction' => $answer['transaction'] ?? null,
            // The bank sends the customer back before it answers our server,
            // so from here until the answer arrives the shop must not offer
            // to take his money a second time.
            'payment_started_at' => now(),
            'payment_asked_for' => $payload['amount'],
        ])->save();

        return $answer['redirect_url'];
    }

    /**
     * The address of the wallet widget for this order.
     *
     * The same money as start(), asked for differently: instead of sending
     * the customer to the bank's page, the shop is given a small page of its
     * own to show in an iframe, where Google Pay and Apple Pay appear if the
     * telephone has them. The widget takes no redirect addresses — what ends
     * the payment is the signed callback, exactly as before, and the message
     * the iframe posts back is only permission to stop waiting on screen.
     *
     * The order is stamped the same way a card payment stamps it, so the
     * callback finds it by the same reference and the shop will not offer to
     * take the money twice while the bank's answer is on its way.
     */
    public static function wallet(Order $order): ?string
    {
        if (! self::enabled()) {
            return null;
        }

        $reference = self::reference($order);

        $answer = self::post([
            'public_key' => self::publicKey(),
            'amount' => number_format($order->total(), 2, '.', ''),
            'order_id' => $reference,
            'description' => 'Nefis.az sifariş #' . $order->id,
        ], self::WIDGET);

        if (($answer['status'] ?? null) !== self::SUCCESS || empty($answer['widget_url'])) {
            Log::warning('epoint: wallet widget not opened', [
                'order' => $order->id,
                'status' => $answer['status'] ?? null,
                'message' => $answer['message'] ?? null,
            ]);

            return null;
        }

        $order->forceFill([
            'payment_method' => 'card',
            'epoint_ref' => $reference,
            'payment_started_at' => now(),
            'payment_asked_for' => number_format($order->total(), 2, '.', ''),
        ])->save();

        return $answer['widget_url'];
    }

    /**
     * Asks the gateway for a payment page worth one manat and gives back its
     * answer. Nothing is charged — a page is only offered — so this is a way
     * to find out whether the keys in the admin are the right ones without
     * placing an order and without anyone typing a card number.
     *
     * @return array<string, mixed>
     */
    public static function check(): array
    {
        if (self::publicKey() === '' || self::privateKey() === null) {
            return ['status' => 'error', 'message' => 'Açarlar yazılmayıb.'];
        }

        return self::post([
            'public_key' => self::publicKey(),
            'amount' => '1.00',
            'currency' => self::CURRENCY,
            'language' => 'az',
            'order_id' => 'yoxlama-' . now()->format('ymdHis'),
            'description' => 'Nefis.az: açarların yoxlanması',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function post(array $payload, string $url = self::API): array
    {
        $data = base64_encode((string) json_encode($payload, JSON_UNESCAPED_UNICODE));

        try {
            $response = Http::asForm()->timeout(20)->post($url, [
                'data' => $data,
                'signature' => self::sign($data),
            ]);

            return is_array($response->json()) ? $response->json() : [];
        } catch (\Throwable $e) {
            // The customer is told the card did not work; the reason stays here.
            Log::warning('epoint: request failed', ['url' => $url, 'error' => $e->getMessage()]);

            return [];
        }
    }
}
