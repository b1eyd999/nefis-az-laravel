<?php

namespace App\Support;

use App\Mail\OrderStatus;
use App\Models\Order;
use App\Models\Setting;
use App\Support\Locale;
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
        'payment_check' => 'Çekinizi aldıq — ödənişi yoxlayırıq.',
        'pending' => 'Sifarişiniz qeydə alındı və növbəyə düşdü.',
        'confirmed' => 'Ödəniş təsdiqləndi — sifarişiniz hazırlanır.',
        'ready' => 'Sifarişiniz hazırdır — kuryer yola düşəndə sizinlə əlaqə saxlayacaq.',
        'completed' => 'Sifarişiniz hazırdır və təhvil verildi. Nuş olsun!',
        'cancelled' => 'Sifarişiniz ləğv edildi.',
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

    /** Where the customer carries on: the payment page, or his orders. */
    public static function link(Order $order): string
    {
        return $order->awaitsPayment() ? route('orders.pay', $order) : route('orders.index');
    }

    /** The whole message, the way both the letter and WhatsApp carry it. */
    public static function text(Order $order): string
    {
        return self::inTheirLanguage($order, fn () => self::message($order));
    }

    private static function message(Order $order): string
    {
        $lines = ['Nefis.az — sifariş #' . $order->id, self::line($order)];

        if ($order->delivery_date) {
            $lines[] = 'Çatdırılma: ' . DeliveryTime::day($order->delivery_date)
                . ($order->delivery_slot ? ', ' . $order->delivery_slot : '');
        }
        if ($order->total() > 0) {
            $lines[] = 'Məbləğ: ' . Price::format($order->total());
        }
        $lines[] = self::link($order);

        return implode("\n", $lines);
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
            $digits = '994' . ltrim($digits, '0');
        } elseif (strlen($digits) === 9) {
            $digits = '994' . $digits;
        }

        return strlen($digits) >= 11 && strlen($digits) <= 15 ? $digits : null;
    }

    /** The chat with this customer, with the message already typed out. */
    public static function whatsapp(Order $order): ?string
    {
        $phone = self::phone($order->contact_phone ?: $order->user?->phone);

        return $phone ? 'https://wa.me/' . $phone . '?text=' . rawurlencode(self::text($order)) : null;
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
            Log::error('Sifariş e-poçtu göndərilmədi: ' . $e->getMessage(), ['order' => $order->id]);

            return self::$sent = false;
        }
    }

    /** The settings page's own try; gives back what went wrong, or nothing. */
    public static function test(string $to): ?string
    {
        try {
            Mail::mailer(self::mailer())->raw(
                "Nefis.az — yoxlama məktubu.\n\nE-poçt bildirişləri işləyir: sifarişin statusu dəyişəndə müştəriyə belə bir məktub gedəcək.",
                fn ($m) => $m->to($to)->subject('Nefis.az — yoxlama')->from(self::FROM, 'Nefis.az'),
            );

            return null;
        } catch (Throwable $e) {
            Log::error('Yoxlama e-poçtu göndərilmədi: ' . $e->getMessage());

            return $e->getMessage();
        }
    }

    /**
     * Nothing was ever set up for mail on the hosting, so the default writes
     * letters to the log. Where the server has a sendmail of its own — every
     * cPanel host does — that is what delivers them; on a laptop that has
     * none, the letter goes on being written to the log.
     */
    private static function mailer(): string
    {
        $default = (string) config('mail.default');

        if ($default !== 'log') {
            return $default;
        }
        $binary = strtok((string) config('mail.mailers.sendmail.path'), ' ');

        return $binary && @is_executable($binary) ? 'sendmail' : 'log';
    }
}
