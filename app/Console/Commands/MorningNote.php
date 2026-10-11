<?php

namespace App\Console\Commands;

use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\Review;
use App\Models\Setting;
use App\Support\Backup;
use App\Support\Price;
use App\Support\Telegram;
use Illuminate\Console\Command;

/**
 * The day's work, on the owner's telephone, once each morning.
 *
 * The admin can answer every one of these questions, but only if somebody
 * opens it and asks. This is the one message that says, without being asked:
 * this many go out today, this many are already late, this much money is
 * still owed, and these are waiting for you to read them.
 */
class MorningNote extends Command
{
    protected $signature = 'shop:morning';

    protected $description = 'Send the owner the day: what goes out today, what is late, what is owed, what is waiting';

    public function handle(): int
    {
        if (Setting::get(Setting::MORNING_NOTE) !== '1') {
            $this->line('The morning note is switched off.');

            return self::SUCCESS;
        }

        if (! Telegram::on()) {
            $this->line('Telegram is not set up.');

            return self::SUCCESS;
        }

        /* Orders still being worked on: a cancelled one's day is not late,
           and a finished one's day is history. */
        $live = fn () => Order::whereNotIn('status', [...Order::OFF_THE_BOOKS, 'completed']);

        $today = $live()->whereDate('delivery_date', today())->get();
        $late = $live()->whereDate('delivery_date', '<', today())->get();
        $tomorrow = $live()->whereDate('delivery_date', today()->addDay())->count();

        $unpaid = Order::whereIn('status', ['awaiting_payment', 'payment_check'])
            ->whereNull('payment_confirmed_at')->get();

        $newToday = Order::whereNotNull('payment_confirmed_at')
            ->whereDate('payment_confirmed_at', today()->subDay())->get();

        $lines = ['☀️ <b>' . today()->format('d.m.Y') . '</b>', ''];

        $lines[] = $today->isEmpty()
            ? '📦 Bu gün çatdırılma yoxdur'
            : '📦 <b>Bu gün ' . $today->count() . '</b>: ' . self::which($today);

        if ($late->isNotEmpty()) {
            // First, because it is the only line that is somebody's complaint
            // waiting to be written.
            array_splice($lines, 2, 0, ['🔴 <b>Gecikən ' . $late->count() . '</b>: ' . self::which($late)]);
        }

        if ($tomorrow > 0) {
            $lines[] = '🗓 Sabah: ' . $tomorrow;
        }

        if ($unpaid->isNotEmpty()) {
            $lines[] = '💳 Ödəniş gözləyir: ' . $unpaid->count()
                . ' — ' . Price::format($unpaid->sum(fn (Order $o) => $o->total()));
        }

        if ($newToday->isNotEmpty()) {
            $lines[] = '✅ Dünən ödənilən: ' . $newToday->count()
                . ' — ' . Price::format($newToday->sum(fn (Order $o) => $o->total()));
        }

        /* Whether the database still has a copy of itself.
           Read off the folder rather than out of the database, and from a
           job that runs on a different schedule — so it still speaks when
           `db:backup` is the thing that stopped. A backup that quietly
           stopped working is worse than none, because the owner believes he
           has one. */
        if ($stale = self::backupTrouble()) {
            $lines[] = '🔴 ' . $stale;
        }

        // The two queues that only exist if somebody looks at them.
        $reviews = Review::waiting()->count();
        $messages = ContactMessage::waiting()->count();
        if ($reviews > 0) {
            $lines[] = '⭐ Yoxlanmayan rəy: ' . $reviews;
        }
        if ($messages > 0) {
            $lines[] = '✉️ Cavabsız mesaj: ' . $messages;
        }

        $lines[] = '';
        $lines[] = url('/admin/orders');

        Telegram::send(implode("\n", $lines));

        $this->line('Sent: ' . $today->count() . ' today, ' . $late->count() . ' late.');

        return self::SUCCESS;
    }

    /**
     * What is wrong with the backups, in one line, or nothing when they are
     * fine or switched off.
     *
     * Deliberately a file check and not a database one: the case worth
     * catching is the nightly job no longer running at all, and asking the
     * database whether it was copied would be asking the patient.
     */
    private static function backupTrouble(): ?string
    {
        if (! Backup::enabled()) {
            return null;
        }

        $newest = Backup::all()[0] ?? null;

        if (! $newest) {
            return 'Bazanın heç bir kopyası yoxdur — «Baza kopyaları» səhifəsinə baxın.';
        }

        // The job runs at 03:40; by the morning note a copy younger than a
        // day and a bit should be there.
        if ($newest['at']->lt(now()->subHours(26))) {
            return 'Bazanın son kopyası ' . $newest['at']->format('d.m.Y H:i')
                . ' tarixlidir — gecə kopyası alınmır.';
        }

        if (! Backup::whole($newest['path'])) {
            return 'Bazanın son kopyası yarımçıqdır: ' . $newest['name'];
        }

        return null;
    }

    /**
     * Which orders, briefly: the number and the time of day, so the owner can
     * see the shape of the run without opening anything.
     *
     * @param  \Illuminate\Support\Collection<int, Order>  $orders
     */
    private static function which(\Illuminate\Support\Collection $orders): string
    {
        return $orders->sortBy('delivery_slot')
            ->map(fn (Order $order) => '#' . $order->id
                . ($order->delivery_slot ? ' (' . $order->delivery_slot . ')' : ''))
            ->take(12)
            ->implode(', ')
            . ($orders->count() > 12 ? ' …' : '');
    }
}
