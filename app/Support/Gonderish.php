<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The customer's WhatsApp, written by the shop itself through Göndəriş
 * (gonderish.az) — the owner's own sending service, with the Nefis.az number
 * connected there.
 *
 * Göndəriş does not fire the message on the spot: it queues it behind the
 * number's pauses and limits, which is what keeps the number from being
 * banned. A failure here is logged and nothing else — an order must never
 * fail because a message could not be sent.
 *
 * The words are never written here. Every message is the one the shop already
 * sends by e-mail (CustomerNotice), in the language the order was placed in,
 * so the letter and the WhatsApp cannot drift apart and a Russian customer
 * does not get an Azerbaijani line.
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

    public static function onTheWayOn(): bool
    {
        return Setting::get(Setting::GONDERISH_ON_THE_WAY) === '1' && filled(self::key());
    }

    /**
     * Which status changes the owner wants the customer told about, as he
     * ticked them in the admin.
     *
     * Stored as one comma-joined string, like the other multi-value settings
     * here. Nothing is ticked until he ticks it: a new setting cannot be given
     * a default, because `Setting::get` remembers for ever and a `DEFAULTS`
     * entry added today never reaches a production row that already exists.
     */
    public static function statuses(): array
    {
        $saved = array_filter(array_map('trim', explode(',', (string) Setting::get(Setting::GONDERISH_STATUSES))));

        return array_values(array_intersect($saved, array_keys(Order::STATUSES)));
    }

    public static function saveStatuses(mixed $list): void
    {
        $list = array_values(array_intersect(
            array_map('strval', (array) $list),
            array_keys(Order::STATUSES),
        ));

        Setting::put(Setting::GONDERISH_STATUSES, implode(',', $list));
    }

    /** The order was just written down. */
    public static function placed(Order $order): void
    {
        if (self::onPlaced()) {
            self::announce($order);
        }
    }

    /** The money arrived, whichever way it was confirmed. */
    public static function paid(Order $order): void
    {
        if (self::onPaid()) {
            self::announce($order);
        }
    }

    /** The status moved — by the owner's hand, the list, or the payment flow. */
    public static function status(Order $order): void
    {
        if (filled(self::key()) && in_array($order->status, self::statuses(), true)) {
            self::announce($order);
        }
    }

    /**
     * The courier has left with the box.
     *
     * Its own slot and its own words: the courier's name and number and what
     * is still to be paid at the door, which no status line carries. Says
     * whether the message was handed to Göndəriş, because the owner is
     * standing in front of the screen when he taps it.
     */
    public static function onTheWay(Order $order): bool
    {
        return self::onTheWayOn() && self::send($order, 'way', CustomerNotice::onTheWayText($order));
    }

    /**
     * Whatever the order's status now says, in the customer's own language.
     *
     * The slot is the **status**, not the event that reached us, and that is
     * the whole point: confirming a payment both stamps `payment_confirmed_at`
     * and moves the status to `confirmed`, so two separate hooks fire within
     * one save. Keyed by event they would send two near-identical messages a
     * second apart; keyed by status the second one is simply dropped.
     */
    private static function announce(Order $order): bool
    {
        return self::send($order, 'st-'.$order->status, CustomerNotice::text($order));
    }

    /** One message per order and slot, here and at Göndəriş alike. */
    private static function send(Order $order, string $slot, string $text): bool
    {
        $to = CustomerNotice::phone($order->contact_phone ?: $order->user?->phone);

        /* The lock is taken before the request, not after: two confirmations
           arriving together must not both get as far as the API. Production
           runs `CACHE_STORE=database`, so it really does hold between
           requests. Göndəriş refuses a repeated `ref` as well — this only
           saves the round trip. */
        if (! $to || ! Cache::add(self::lock($order, $slot), 1, now()->addDays(30))) {
            return false;
        }

        try {
            $res = Http::timeout(8)->acceptJson()->withToken((string) self::key())
                ->post(self::URL.'/messages', [
                    'to' => $to,
                    'text' => $text,
                    'ref' => "nefis-{$order->id}-{$slot}",
                    'name' => $order->user?->name,
                ]);

            if ($res->successful()) {
                /* Accepted, but parked: the owner paused this number's API
                   queue in his cabinet, and nothing will move until he
                   resumes it. Göndəriş says so in the answer and nowhere
                   else, so it goes to the log the admin can read. */
                if (filled($warning = $res->json('warning'))) {
                    Log::warning('Göndəriş: '.$warning, ['order' => $order->id]);
                }

                return true;
            }

            Cache::forget(self::lock($order, $slot));
            Log::warning('Göndəriş: WhatsApp mesajı qəbul olunmadı — '.self::why($res), ['order' => $order->id]);
        } catch (Throwable $e) {
            Cache::forget(self::lock($order, $slot));
            Log::warning('Göndəriş: WhatsApp mesajı göndərilmədi: '.$e->getMessage(), ['order' => $order->id]);
        }

        return false;
    }

    private static function lock(Order $order, string $slot): string
    {
        return "gonderish:{$order->id}:{$slot}";
    }

    /**
     * What Göndəriş refused with, in words the owner can act on.
     *
     * Its two likeliest refusals are not about the shop's request at all —
     * the sending terms were never accepted in the cabinet, or the message
     * balance has run out — and both arrive as a bare error code.
     */
    private static function why(Response $res): string
    {
        $code = (string) $res->json('error');

        return match (true) {
            $res->status() === 401 => 'açar səhvdir.',
            $code === 'terms_not_accepted' => 'Göndəriş kabinetində göndərmə şərtləri qəbul edilməyib (orada bir dəfə hər hansı aksiyanı başladın).',
            $code === 'no_balance' => 'Göndəriş-də mesaj qalmayıb (balans 0).',
            $code === 'no_number' => 'Göndəriş-də WhatsApp nömrəsi qoşulu deyil.',
            $code === 'number_not_found' => 'Göstərilən nömrə bu hesaba aid deyil.',
            $code === 'bad_phone' => 'müştərinin nömrəsi oxunmadı.',
            $code === 'bad_text' => 'mesajın mətni qəbul olunmadı.',
            $res->status() === 429 => 'çox tez-tez göndərilir (dəqiqədə 120 sorğu limiti).',
            default => 'cavab '.$res->status().' '.($code ?: ''),
        };
    }

    /**
     * For the settings page: does the key work, and which number will write?
     *
     * `/me` answers the key, the number and the balance. It cannot answer
     * whether the sending terms were accepted — only an actual send is
     * refused for that — which is what the test message beside it is for.
     */
    public static function check(?string $key = null): array
    {
        $key = trim((string) ($key ?: self::key()));
        if ($key === '') {
            return [false, 'Açar yazılmayıb.'];
        }

        try {
            $res = Http::timeout(8)->acceptJson()->withToken($key)->get(self::URL.'/me');
        } catch (Throwable $e) {
            return [false, 'Göndəriş-ə qoşulmaq olmadı: '.$e->getMessage()];
        }

        if (! $res->successful()) {
            return [false, 'Göndəriş qəbul etmədi — '.self::why($res)];
        }

        $on = collect($res->json('numbers') ?? [])->firstWhere('connected', true);
        if (! $on) {
            return [false, 'Açar düzdür, amma Göndəriş-də WhatsApp nömrəsi qoşulu deyil.'];
        }

        $unlimited = (bool) $res->json('unlimited');
        $left = (int) $res->json('balance');
        if (! $unlimited && $left <= 0) {
            return [false, 'Nömrə qoşuludur, amma Göndəriş-də mesaj qalmayıb (balans 0) — mesajlar getməyəcək.'];
        }

        return [true, 'Hazırdır: mesajlar +'.$on['phone'].' nömrəsindən gedəcək'
            .($unlimited ? '' : ' (qalıq: '.$left.' mesaj)').'.'];
    }

    /**
     * The owner's own try, to a number he types.
     *
     * The only check that goes all the way through Göndəriş's own refusals,
     * so it is the one that finds an unaccepted terms page or a paused queue.
     * A fresh `ref` every time, or the second try would be taken for a repeat
     * of the first and silently dropped.
     */
    public static function test(string $phone): array
    {
        $to = CustomerNotice::phone($phone);
        if (! $to) {
            return [false, 'Nömrə oxunmadı. Məsələn: 0501234567.'];
        }
        if (blank($key = self::key())) {
            return [false, 'Əvvəlcə açarı yazıb «Saxla» düyməsini basın.'];
        }

        try {
            $res = Http::timeout(10)->acceptJson()->withToken($key)
                ->post(self::URL.'/messages', [
                    'to' => $to,
                    'text' => "Nefis.az — yoxlama mesajı.\nWhatsApp bildirişləri işləyir.",
                    'ref' => 'nefis-test-'.now()->valueOf(),
                ]);
        } catch (Throwable $e) {
            return [false, 'Göndərilmədi: '.$e->getMessage()];
        }

        if (! $res->successful()) {
            return [false, 'Göndərilmədi — '.self::why($res)];
        }

        return [true, filled($warning = $res->json('warning'))
            ? 'Növbəyə düşdü, amma: '.$warning
            : 'Göndərildi: +'.$to.' nömrəsinə yoxlama mesajı növbəyə düşdü. WhatsApp-ı yoxlayın.'];
    }
}
