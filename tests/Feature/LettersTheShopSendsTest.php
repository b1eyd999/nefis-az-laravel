<?php

namespace Tests\Feature;

use App\Mail\AfterSale;
use App\Mail\PaymentReminder;
use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\Product;
use App\Models\PromoCode;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The two letters the shop sends without anybody pressing anything: one
 * reminder to somebody who left the payment page, and a thank-you once the
 * box is in his hands.
 *
 * Each goes exactly once per order — a reminder sent every hour is a reason
 * to block the sender.
 */
class LettersTheShopSendsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function box(): Product
    {
        $box = Product::firstWhere('slug', 'test');
        if ($box) {
            return $box;
        }

        $box = Product::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true, 'price' => 20,
            'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/bg.webp', 'x' => 0, 'y' => 0,
            'width' => 969, 'height' => 1895, 'rotation' => 0, 'opacity' => 100,
            'placement' => 'above', 'sort_order' => 0]);

        return $box;
    }

    private function order(array $over = []): Order
    {
        $user = User::factory()->create(['email' => 'musteri' . Order::max('id') . '@example.com']);
        $box = $this->box();

        $order = Order::create(array_replace([
            'user_id' => $user->id,
            'status' => 'awaiting_payment',
            'delivery_method_id' => DeliveryMethod::where('type', 'door')->value('id'),
            'delivery_type' => 'door',
            'delivery_name' => 'Qapıya',
            'delivery_price' => 0,
            'contact_phone' => '1',
            'delivery_address' => 'Bakı',
        ], $over));
        $order->items()->create([
            'product_id' => $box->id, 'product_name' => $box->name,
            'customer_photos' => [], 'custom_texts' => [], 'quantity' => 1,
        ]);

        return $order->fresh('items');
    }

    // ---- the reminder ----

    public function test_one_reminder_goes_out_and_only_one(): void
    {
        $order = $this->order();
        $order->forceFill(['created_at' => now()->subHours(5)])->save();

        $this->artisan('orders:remind-unpaid')->assertSuccessful();

        Mail::assertSent(PaymentReminder::class, 1);
        $this->assertNotNull($order->fresh()->payment_reminded_at);

        // Run it again and nothing more goes.
        $this->artisan('orders:remind-unpaid')->assertSuccessful();
        Mail::assertSent(PaymentReminder::class, 1);
    }

    public function test_a_fresh_order_is_left_alone(): void
    {
        $this->order();

        $this->artisan('orders:remind-unpaid')->assertSuccessful();

        Mail::assertNotSent(PaymentReminder::class);
    }

    /** Paid, or with a receipt on file, means there is nothing to remind. */
    public function test_a_paid_order_and_one_with_a_receipt_are_left_alone(): void
    {
        $paid = $this->order(['status' => 'confirmed']);
        $paid->forceFill(['created_at' => now()->subHours(9), 'payment_confirmed_at' => now()])->save();

        $checking = $this->order();
        $checking->forceFill(['created_at' => now()->subHours(9), 'payment_receipt' => 'cek.jpg'])->save();

        $this->artisan('orders:remind-unpaid')->assertSuccessful();

        Mail::assertNotSent(PaymentReminder::class);
    }

    public function test_the_reminder_can_be_switched_off(): void
    {
        Setting::put(Setting::PAYMENT_REMIND_AFTER, '0');
        $order = $this->order();
        $order->forceFill(['created_at' => now()->subHours(9)])->save();

        $this->artisan('orders:remind-unpaid')->assertSuccessful();

        Mail::assertNotSent(PaymentReminder::class);
        $this->assertNull($order->fresh()->payment_reminded_at);
    }

    /**
     * A letter that would arrive after the order had been cancelled is worse
     * than none: it offers something that is already gone.
     */
    public function test_it_refuses_to_remind_later_than_the_order_lives(): void
    {
        Setting::put(Setting::PAYMENT_WINDOW_HOURS, '24');
        Setting::put(Setting::PAYMENT_REMIND_AFTER, '30');
        $order = $this->order();
        $order->forceFill(['created_at' => now()->subHours(31)])->save();

        $this->artisan('orders:remind-unpaid')->assertSuccessful();

        Mail::assertNotSent(PaymentReminder::class);
    }

    // ---- the thank-you ----

    public function test_the_thank_you_goes_a_day_after_the_handover(): void
    {
        $order = $this->order(['status' => 'confirmed']);
        $order->forceFill(['status' => 'completed'])->save();

        // The same hour: too early, the gift may not have been given yet.
        $this->artisan('orders:thank')->assertSuccessful();
        Mail::assertNotSent(AfterSale::class);

        $order->forceFill(['delivered_at' => now()->subHours(26)])->save();
        $this->artisan('orders:thank')->assertSuccessful();

        Mail::assertSent(AfterSale::class, 1);
        $this->assertNotNull($order->fresh()->thanked_at);

        // Again, and nothing more.
        $this->artisan('orders:thank')->assertSuccessful();
        Mail::assertSent(AfterSale::class, 1);
    }

    /** The code is his own: one use, with an end date, named on the order. */
    public function test_it_carries_a_code_made_for_that_one_customer(): void
    {
        Setting::put(Setting::AFTER_SALE_PERCENT, '15');
        Setting::put(Setting::AFTER_SALE_DAYS, '30');

        $order = $this->order(['status' => 'confirmed']);
        $order->forceFill(['status' => 'completed', 'delivered_at' => now()->subHours(26)])->save();

        $this->artisan('orders:thank')->assertSuccessful();

        $code = PromoCode::firstOrFail();
        $this->assertSame(15.0, (float) $code->percent);
        $this->assertSame(1, (int) $code->max_uses);
        $this->assertTrue($code->is_active);
        $this->assertNotNull($code->ends_at);
        $this->assertTrue($code->ends_at->isAfter(now()->addDays(29)));
        $this->assertStringContainsString('#' . $order->id, (string) $code->note);
        $this->assertSame($code->code, $order->fresh()->thanks_promo);

        Mail::assertSent(AfterSale::class, fn (AfterSale $m) => $m->promo === $code->code);
    }

    /** A percent of 0 sends the letter without a code at all. */
    public function test_with_no_discount_the_letter_still_asks_for_a_review(): void
    {
        Setting::put(Setting::AFTER_SALE_PERCENT, '0');

        $order = $this->order(['status' => 'confirmed']);
        $order->forceFill(['status' => 'completed', 'delivered_at' => now()->subHours(26)])->save();

        $this->artisan('orders:thank')->assertSuccessful();

        $this->assertSame(0, PromoCode::count());
        Mail::assertSent(AfterSale::class, fn (AfterSale $m) => $m->promo === null);

        // And the letter itself still invites him to say how it went.
        $this->assertStringContainsString(__('Rəy yazın'),
            (new AfterSale($order->fresh()))->render());
    }

    /** An order that was given back is nothing to thank for. */
    public function test_a_cancelled_order_is_not_thanked(): void
    {
        $order = $this->order(['status' => 'confirmed']);
        $order->forceFill(['status' => 'completed', 'delivered_at' => now()->subHours(26)])->save();
        $order->forceFill(['status' => 'cancelled'])->save();

        $this->artisan('orders:thank')->assertSuccessful();

        Mail::assertNotSent(AfterSale::class);
    }

    public function test_the_thank_you_can_be_switched_off(): void
    {
        Setting::put(Setting::AFTER_SALE, '0');
        $order = $this->order(['status' => 'confirmed']);
        $order->forceFill(['status' => 'completed', 'delivered_at' => now()->subHours(26)])->save();

        $this->artisan('orders:thank')->assertSuccessful();

        Mail::assertNotSent(AfterSale::class);
        $this->assertNull($order->fresh()->thanked_at);
    }

    /** Neither letter goes while customer e-mail is switched off entirely. */
    public function test_both_obey_the_customer_email_switch(): void
    {
        Setting::put(Setting::NOTIFY_EMAIL, '0');

        $unpaid = $this->order();
        $unpaid->forceFill(['created_at' => now()->subHours(9)])->save();
        $done = $this->order(['status' => 'confirmed']);
        $done->forceFill(['status' => 'completed', 'delivered_at' => now()->subHours(26)])->save();

        $this->artisan('orders:remind-unpaid')->assertSuccessful();
        $this->artisan('orders:thank')->assertSuccessful();

        Mail::assertNotSent(PaymentReminder::class);
        Mail::assertNotSent(AfterSale::class);
    }

    /** Both are on the schedule, so the cPanel cron picks them up. */
    public function test_both_are_on_the_schedule(): void
    {
        $commands = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->map(fn ($event) => $event->command ?? '')
            ->implode(' ');

        $this->assertStringContainsString('orders:remind-unpaid', $commands);
        $this->assertStringContainsString('orders:thank', $commands);
    }
}
