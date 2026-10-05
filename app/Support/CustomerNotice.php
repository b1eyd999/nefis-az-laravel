<?php

namespace App\Support;

use App\Mail\OrderChanged;
use App\Mail\OrderMessage;
use App\Mail\OrderStatus;
use App\Models\Order;
use App\Models\OrderAdjustment;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * What the customer hears from the shop while the box is being made. A change
 * of status leaves by e-mail on its own; the same words are one tap away on
 * WhatsApp, because here people read that sooner than their mail.
 */
class CustomerNotice
{
    /** Who the letters come from — the shop's own address on its domain. */
    public const FROM = 'info@nefis.az';

    /** What each status means to the person waiting for the box. */
    public const LINES = [
        'awaiting_payment' => 'Sifarişiniz qeydə alındı. Ödəniş gözlənilir.',
        'payment_check' => 'Çekinizi aldıq, ödənişi yoxlayırıq.',
        'pending' => 'Sifarişiniz qeydə alındı və növbəyə düşdü.',
        'confirmed' => 'Ödəniş təsdiqləndi, sifarişiniz hazırlanır.',
        'ready' => 'Sifarişiniz hazırdır, kuryer yola düşəndə sizinlə əlaqə saxlayacaq.',
        'completed' => 'Sifarişiniz hazırdır və təhvil verildi. Nuş olsun!',
        'cancelled' => 'Sifarişiniz ləğv edildi.',
        'refunded' => 'Vəsaiti geri qaytardıq. Məbləğ bankınızdan asılı olaraq 1–7 iş gününə kartınıza düşəcək.',
    ];

    /** Whether the last letter went out, for the screen the owner is looking at. */
    public static ?bool $sent = null;

    public static function emailOn(): bool
    {
        return Setting::get(Setting::NOTIFY_EMAIL) === '1';
    }

    public static function line(Order $order): string
    {
        return isset(self::LINES[$order->status])
            ? __(self::LINES[$order->status])
            : __('Sifarişinizin statusu: :status.', ['status' => $order->statusLabel()]);
    }

    /** The language this order was placed in; letters follow it. */
    public static function locale(Order $order): string
    {
        $locale = (string) ($order->locale ?: Locale::DEFAULT);

        return in_array($locale, Locale::all(), true) ? $locale : Locale::DEFAULT;
    }

    /** Runs something as if the customer's own page were being drawn. */
    private static function inTheirLanguage(Order $order, callable $what): mixed
    {
        $was = app()->getLocale();
        app()->setLocale(self::locale($order));

        try {
            return $what();
        } finally {
            app()->setLocale($was);
        }
    }

    /**
     * Where the customer carries on: the payment page, or his orders.
     *
     * Built with the order's own language in the address. A letter written in
     * Russian used to end at an Azerbaijani page, because `route()` names the
     * unprefixed route and that one carries `locale:az`.
     */
    public static function link(Order $order): string
    {
        return self::routed($order, $order->awaitsPayment() ? 'orders.pay' : 'orders.index',
            $order->awaitsPayment() ? ['order' => $order->id] : []);
    }

    /** The page where money owed over a change is paid. */
    public static function adjustmentLink(OrderAdjustment $adjustment): string
    {
        return self::routed($adjustment->order, 'orders.extra.show',
            ['order' => $adjustment->order_id, 'adjustment' => $adjustment->id]);
    }

    /** A route of the shop in the language the order was placed in. */
    private static function routed(Order $order, string $name, array $parameters = []): string
    {
        $locale = self::locale($order);

        return route($locale === Locale::DEFAULT ? $name : $locale.'.'.$name, $parameters);
    }

    /** The whole message, the way both the letter and WhatsApp carry it. */
    public static function text(Order $order): string
    {
        return self::inTheirLanguage($order, fn () => self::message($order));
    }

    private static function message(Order $order): string
    {
        $lines = ['Nefis.az, sifariş #'.$order->id, self::line($order)];

        if ($order->delivery_date) {
            $lines[] = 'Çatdırılma: '.DeliveryTime::day($order->delivery_date)
                .($order->delivery_slot ? ', '.$order->delivery_slot : '');
        }
        if ($order->total() > 0) {
            $lines[] = 'Məbləğ: '.Price::format($order->total());
        }
        $lines[] = self::link($order);

        return implode("\n", $lines);
    }

    /** The letter about a change made to an order already paid for. */
    public static function changed(OrderAdjustment $adjustment): bool
    {
        $order = $adjustment->order;
        $to = trim((string) $order->user?->email);

        if (! self::emailOn() || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return self::$sent = false;
        }

        try {
            Mail::mailer(self::mailer())->to($to)->locale(self::locale($order))
                ->send(new OrderChanged($adjustment));

            return self::$sent = true;
        } catch (Throwable $e) {
            Log::error('Dəyişiklik e-poçtu göndərilmədi: '.$e->getMessage(), ['order' => $order->id]);

            return self::$sent = false;
        }
    }

    /** The same words, for the WhatsApp button beside the change. */
    public static function changeText(OrderAdjustment $adjustment): string
    {
        return self::inTheirLanguage($adjustment->order, function () use ($adjustment) {
            $lines = [__('Nefis.az, sifariş #').$adjustment->order_id];
            $lines[] = $adjustment->reason ?: __('Sifarişiniz dəyişdi.');
            $lines[] = $adjustment->isCharge()
                ? __('Əlavə ödəniş: :sum', ['sum' => Price::format((float) $adjustment->amount)])
                : __('Sizə qaytarılacaq: :sum', ['sum' => Price::format((float) $adjustment->amount)]);
            if ($adjustment->isCharge()) {
                $lines[] = self::adjustmentLink($adjustment);
            }

            return implode('
', $lines);
        });
    }

    /**
     * A phone as WhatsApp wants it: digits with the country code. People write
     * their number every way there is — 050…, +994 50…, 994 50… — and an
     * unreadable one simply means no button.
     */
    public static function phone(?string $raw): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $raw) ?? '';

        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '0')) {
            $digits = '994'.ltrim($digits, '0');
        } elseif (strlen($digits) === 9) {
            $digits = '994'.$digits;
        }

        return strlen($digits) >= 11 && strlen($digits) <= 15 ? $digits : null;
    }

    /** The chat with this customer, with the message already typed out. */
    public static function whatsapp(Order $order): ?string
    {
        $phone = self::phone($order->contact_phone ?: $order->user?->phone);

        return $phone ? 'https://wa.me/'.$phone.'?text='.rawurlencode(self::text($order)) : null;
    }

    /** Sends the letter; a mail problem is logged, never thrown at the owner. */
    public static function email(Order $order): bool
    {
        $to = trim((string) $order->user?->email);

        if (! self::emailOn() || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return self::$sent = false;
        }

        try {
            Mail::mailer(self::mailer())->to($to)->locale(self::locale($order))->send(new OrderStatus($order));

            return self::$sent = true;
        } catch (Throwable $e) {
            Log::error('Sifariş e-poçtu göndərilmədi: '.$e->getMessage(), ['order' => $order->id]);

            return self::$sent = false;
        }
    }

    /**
     * The courier has left with the box.
     *
     * The owner's own tap rather than anything automatic: he is the one who
     * knows the man has actually driven off. The words are the same ones the
     * WhatsApp button beside it carries, in the language the order was placed
     * in, and the courier's name and number go with them so the customer knows
     * who is about to ring his bell.
     */
    public static function onTheWay(Order $order): ?string
    {
        $subject = self::inTheirLanguage($order, fn () => __('Nefis.az, kuryer yoldadır'));

        return self::write($order, $subject.' — #'.$order->id, self::onTheWayText($order));
    }

    /** The same message, for the WhatsApp button and for the letter alike. */
    public static function onTheWayText(Order $order): string
    {
        return self::inTheirLanguage($order, function () use ($order) {
            $lines = [__('Nefis.az, sifariş #').$order->id, __('Kuryeriniz yola düşdü.')];

            $courier = $order->courierLabel();
            $phone = $order->courier?->phone;
            if ($courier) {
                $lines[] = __('Kuryer: :name', ['name' => $courier.($phone ? ', '.$phone : '')]);
            }

            $collect = $order->isPaidFor()
                ? $order->outstanding()
                : round($order->total() + $order->outstanding(), 2);
            if ($collect > 0.009) {
                $lines[] = __('Qapıda ödəniləcək: :sum', ['sum' => Price::format($collect)]);
            }

            return implode(chr(10), $lines);
        });
    }

    /** The courier's message to this customer, already typed out. */
    public static function onTheWayWhatsapp(Order $order): ?string
    {
        $phone = self::phone($order->contact_phone ?: $order->user?->phone);

        return $phone ? 'https://wa.me/'.$phone.'?text='.rawurlencode(self::onTheWayText($order)) : null;
    }

    /**
     * A letter the owner writes himself from the order's page. His words go
     * out as he typed them, in the language the order was placed in, and a
     * mail problem comes back to him rather than into a log nobody reads.
     */
    public static function write(Order $order, string $subject, string $body): ?string
    {
        $to = trim((string) $order->user?->email);

        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return 'Müştərinin e-poçtu yoxdur.';
        }

        try {
            Mail::mailer(self::mailer())->to($to)->locale(self::locale($order))
                ->send(new OrderMessage($order, $subject, $body));

            return null;
        } catch (Throwable $e) {
            Log::error('Sifariş məktubu göndərilmədi: '.$e->getMessage(), ['order' => $order->id]);

            return $e->getMessage();
        }
    }

    /** The settings page's own try; gives back what went wrong, or nothing. */
    public static function test(string $to): ?string
    {
        try {
            Mail::mailer(self::mailer())->raw(
                "Nefis.az, yoxlama məktubu.\n\nE-poçt bildirişləri işləyir: sifarişin statusu dəyişəndə müştəriyə belə bir məktub gedəcək.",
                fn ($m) => $m->to($to)->subject('Nefis.az, yoxlama')->from(self::FROM, 'Nefis.az'),
            );

            return null;
        } catch (Throwable $e) {
            Log::error('Yoxlama e-poçtu göndərilmədi: '.$e->getMessage());

            return $e->getMessage();
        }
    }

    /**
     * Nothing was ever set up for mail on the hosting, so the default writes
     * letters to the log. Where the server has a sendmail of its own — every
     * cPanel host does — that is what delivers them; on a laptop that has
     * none, the letter goes on being written to the log.
     */
    /** Which mailer actually works on this hosting; others send through it too. */
    public static function mailer(): string
    {
        $default = (string) config('mail.default');

        if ($default !== 'log') {
            return $default;
        }
        $binary = strtok((string) config('mail.mailers.sendmail.path'), ' ');

        return $binary && @is_executable($binary) ? 'sendmail' : 'log';
    }
}
