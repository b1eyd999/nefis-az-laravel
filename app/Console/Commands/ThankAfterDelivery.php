<?php

namespace App\Console\Commands;

use App\Mail\AfterSale;
use App\Models\Order;
use App\Models\PromoCode;
use App\Models\Setting;
use App\Support\CustomerNotice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * The letter that follows a handover: thank you, how was it, and a code off
 * the next box.
 *
 * Three things in one letter on purpose. The thanks is the reason to open it,
 * the review is what the shop needs, and the code is what makes it worth the
 * customer's minute — asked for on its own, a review from a chocolate shop
 * is a favour.
 *
 * A day after the handover rather than the same hour: a box handed over at
 * six in the evening is being given to somebody that evening, and a letter
 * asking how it went arrives before the gift does.
 *
 * One per order, ever, and the code is made for that one customer: one use,
 * his own, with an end date.
 */
class ThankAfterDelivery extends Command
{
    protected $signature = 'orders:thank {--hours= : how long after the handover the letter goes}';

    protected $description = 'Thank the customer after the handover, ask for a review, and send a code off the next box';

    public function handle(): int
    {
        if (Setting::get(Setting::AFTER_SALE) !== '1') {
            $this->line('The after-sale letter is switched off.');

            return self::SUCCESS;
        }

        if (! CustomerNotice::emailOn()) {
            $this->line('Customer e-mail is switched off.');

            return self::SUCCESS;
        }

        $after = $this->option('hours') !== null
            ? max(0, (int) $this->option('hours'))
            : max(0, (int) Setting::get(Setting::AFTER_SALE_AFTER));

        $percent = max(0, min(100, (float) Setting::get(Setting::AFTER_SALE_PERCENT)));
        $days = max(0, (int) Setting::get(Setting::AFTER_SALE_DAYS));

        $handed = Order::whereNotNull('delivered_at')
            ->whereNull('thanked_at')
            ->where('delivered_at', '<', now()->subHours($after))
            // Not an order that was given back: there is nothing to thank for.
            ->whereNotIn('status', Order::OFF_THE_BOOKS)
            ->with('user')
            ->get();

        $sent = 0;
        foreach ($handed as $order) {
            $to = trim((string) $order->user?->email);

            if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
                // Marked, so an address that cannot be written to is not
                // tried again every hour from now on.
                $order->forceFill(['thanked_at' => now()])->save();

                continue;
            }

            /* His own code: one use, and an end date, so an old letter cannot
               be passed around as a standing discount. Made before the letter
               so the letter cannot name a code that does not exist. */
            $code = null;
            if ($percent > 0) {
                $code = PromoCode::create([
                    'code' => PromoCode::make(),
                    'percent' => $percent,
                    'is_active' => true,
                    'max_uses' => 1,
                    'ends_at' => $days > 0 ? now()->addDays($days)->endOfDay() : null,
                    'note' => 'Sifariş #' . $order->id . ' — çatdırılmadan sonra',
                ])->code;
            }

            try {
                Mail::mailer(CustomerNotice::mailer())->to($to)
                    ->locale(CustomerNotice::locale($order))
                    ->send(new AfterSale($order, $code, $percent, $days));

                $order->forceFill(['thanked_at' => now(), 'thanks_promo' => $code])->save();
                $sent++;
            } catch (\Throwable $e) {
                /* The letter did not go, so the order is left unmarked and
                   will be tried again — but the code it would have carried is
                   taken back, or every failed attempt would leave a live
                   discount nobody was ever told about. */
                if ($code) {
                    PromoCode::where('code', $code)->delete();
                }
                Log::error('Təşəkkür məktubu göndərilmədi: ' . $e->getMessage(), ['order' => $order->id]);
            }
        }

        $this->line($sent === 0
            ? 'Nobody to thank.'
            : 'Thanked ' . $sent . ' customer(s).');

        return self::SUCCESS;
    }
}
