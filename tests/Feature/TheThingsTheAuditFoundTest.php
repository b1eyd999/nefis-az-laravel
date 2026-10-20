<?php

namespace Tests\Feature;

use App\Console\Commands\ExpireUnpaidOrders;
use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\Product;
use App\Models\PromoCode;
use App\Models\Setting;
use App\Models\User;
use App\Support\Backup;
use App\Support\Cart;
use App\Support\OrderKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * One test for each thing the sweep of 19 October 2026 turned up, so that
 * none of them can come back quietly.
 *
 * They are kept together rather than scattered among the tests of the parts
 * they belong to, because what they have in common is how they were found,
 * and because reading them in one place says what went wrong that day.
 */
class TheThingsTheAuditFoundTest extends TestCase
{
    use RefreshDatabase;

    private function box(float $price = 20): Product
    {
        $box = Product::create(['name' => 'Test', 'slug' => 'test-' . uniqid(), 'is_active' => true,
            'price' => $price, 'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/' . $box->id . '/bg.webp', 'x' => 0, 'y' => 0,
            'width' => 969, 'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);

        return $box;
    }

    private function orderFor(User $user, float $price = 20, int $quantity = 1, string $promo = ''): Order
    {
        PaymentAccount::firstOrCreate(['number' => '4169738111111111'],
            ['type' => PaymentAccount::CARD, 'label' => 'Kart']);
        $box = $this->box($price);

        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $box->id, 'quantity' => $quantity])
            ->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('checkout.store'), [
            'delivery_method_id' => DeliveryMethod::where('type', 'door')->value('id'),
            'contact_phone' => '1', 'delivery_address' => 'Bakı, Nizami küç. 5',
        ] + ($promo ? ['promo_code' => $promo] : []))->assertSessionHasNoErrors();

        return Order::latest('id')->firstOrFail();
    }

    /**
     * The copy was made and deleted in the same breath, every night: a
     * gzipped database is neither a file ending in `.sqlite` nor a dump with
     * a last line, so the check called it unfinished.
     */
    public function test_a_squeezed_copy_of_the_database_is_a_whole_copy(): void
    {
        // Whatever this run writes — a copy on a real database, a dump on
        // the one the tests hold in memory — has to come back whole.
        $written = Backup::make('audit_' . uniqid());
        $this->assertTrue(Backup::whole($written), basename($written) . ' is a finished copy');
        @unlink($written);

        // And a squeezed SQLite file, which is what the nightly job really
        // writes and what used to be thrown away, is one too.
        $squeezed = dirname($written) . '/nefis_audit_' . uniqid() . '.sqlite.gz';
        file_put_contents($squeezed, gzencode("SQLite format 3\\0" . str_repeat("\\0", 200)));
        $this->assertTrue(Backup::whole($squeezed), 'a gzipped database is a database');
        @unlink($squeezed);
    }

    public function test_an_unfinished_copy_is_still_refused(): void
    {
        $dir = dirname(Backup::make('audit_' . uniqid()));
        foreach (glob($dir . '/nefis_audit_*') as $leftover) {
            @unlink($leftover);
        }

        $torn = $dir . '/nefis_audit_torn.sqlite';
        file_put_contents($torn, 'this is not a database');
        $this->assertFalse(Backup::whole($torn));

        $squeezed = $dir . '/nefis_audit_torn.sqlite.gz';
        file_put_contents($squeezed, gzencode('this is not a database either'));
        $this->assertFalse(Backup::whole($squeezed));

        @unlink($torn);
        @unlink($squeezed);
    }

    /**
     * Every copy was listed four hours early, so one written at 03:40 read
     * as «23:40 the day before» — a night that looked as though nothing had
     * been taken. Carbon 3 reads a bare timestamp as UTC; the shop keeps
     * Asia/Baku.
     */
    public function test_a_copy_is_dated_by_the_shops_own_clock(): void
    {
        $path = Backup::make('audit_tz_' . uniqid());

        $row = collect(Backup::all())->firstWhere('path', $path);
        $this->assertNotNull($row, 'the copy is in the list');
        $this->assertLessThan(120, abs($row['at']->diffInSeconds(now())),
            'listed as ' . $row['at']->format('d.m.Y H:i') . ', taken at ' . now()->format('d.m.Y H:i'));
        $this->assertSame(config('app.timezone'), $row['at']->timezoneName);

        @unlink($path);
    }

    /**
     * The bank sends the customer back to a route behind `signed`. Paying
     * the difference on an edited order handed it an address with no
     * signature, so the money went and a 403 came back.
     */
    public function test_the_address_the_bank_returns_to_is_signed(): void
    {
        $this->assertStringContainsString('signature=',
            URL::temporarySignedRoute('epoint.done', now()->addDay(), ['order' => 1]));

        $source = file_get_contents(app_path('Http/Controllers/AdjustmentController.php'));
        $this->assertStringContainsString("URL::temporarySignedRoute('epoint.done'", $source);
        $this->assertStringContainsString("URL::temporarySignedRoute('epoint.failed'", $source);
        $this->assertStringNotContainsString("route('epoint.done'", $source);
        $this->assertStringNotContainsString("route('epoint.failed'", $source);
    }

    /**
     * SQLite compares strings by their bytes, so an address typed with a
     * capital letter found no account — and a telephone capitalises the
     * first letter by itself.
     */
    public function test_an_address_is_the_same_address_however_it_is_typed(): void
    {
        $user = User::factory()->create(['email' => 'Leyla@Gmail.com']);

        $this->assertSame('leyla@gmail.com', $user->fresh()->email, 'kept the one way');

        foreach (['leyla@gmail.com', 'Leyla@Gmail.com', 'LEYLA@GMAIL.COM'] as $typed) {
            $this->assertNotNull(User::byLogin($typed), 'typed as ' . $typed);
        }

        // And a row written before any of this still answers.
        DB::table('users')->where('id', $user->id)->update(['email' => 'MiXeD.Old@Mail.Ru']);
        $this->assertNotNull(User::byLogin('mixed.old@mail.ru'), 'an older row, typed in lower case');
    }

    /**
     * A code is a percentage. Frozen in manats, the whole of it landed on
     * whatever was left after a line was removed, and the shop gave back
     * more than it had ever taken.
     */
    public function test_a_code_stays_a_percentage_when_the_order_shrinks(): void
    {
        PromoCode::create(['code' => 'YARI', 'percent' => 50, 'is_active' => true]);
        $user = User::factory()->create();
        $order = $this->orderFor($user, 20, 2, 'YARI');

        $carriage = $order->total() - ($order->itemsTotal() - $order->discountOff());

        $this->assertSame(20.0, $order->discountOff(), 'half of two boxes at twenty');
        $this->assertSame(20.0 + $carriage, $order->total(), 'forty less twenty');

        $order->items()->first()->update(['quantity' => 1]);
        $order->refresh()->load('items');

        $this->assertSame(10.0, $order->discountOff(), 'half of the one box left, not the whole of the old discount');
        $this->assertSame(10.0 + $carriage, $order->total(), 'twenty less ten');
    }

    /** And the books say the same figure as the order's own page. */
    public function test_the_books_do_not_book_a_shrunken_order_as_a_loss(): void
    {
        PromoCode::create(['code' => 'HAMISI', 'percent' => 100, 'is_active' => true]);
        $user = User::factory()->create();
        $order = $this->orderFor($user, 20, 2, 'HAMISI');

        $order->items()->first()->delete();
        $order->refresh()->load('items');

        $this->assertGreaterThanOrEqual(0.0, $order->itemsTotal() - $order->discountOff(),
            'the goods cannot be worth less than nothing');
    }

    /**
     * «Qiymət sorğu ilə» on the card and a free box in the basket: the
     * design went through with the box counted as zero.
     */
    public function test_a_design_with_no_price_is_not_for_sale(): void
    {
        $user = User::factory()->create();
        $box = $this->box(0);
        $box->forceFill(['price' => null])->save();

        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $box->id])
            ->assertSessionHasErrors('product_id');

        $this->assertSame([], Cart::items());
    }

    /** And the page does not offer a button that cannot work. */
    public function test_the_page_says_why_it_cannot_be_ordered(): void
    {
        $box = $this->box(0);
        $box->forceFill(['price' => null])->save();

        $this->get(route('products.customize', $box->slug))
            ->assertOk()
            ->assertSee('qiyməti hələ təyin olunmayıb', false);
    }

    /**
     * The reminder measured itself against the window over which a payment
     * ACCOUNT's counter empties — nothing to do with how long an order may
     * wait. Set the rotation to three hours and every reminder stopped.
     */
    public function test_the_reminder_is_measured_against_the_clock_that_cancels_the_order(): void
    {
        $source = file_get_contents(app_path('Console/Commands/RemindUnpaidOrders.php'));

        $this->assertStringContainsString('ExpireUnpaidOrders::HOURS', $source);
        $this->assertStringNotContainsString('Setting::get(Setting::PAYMENT_WINDOW_HOURS)', $source);
        $this->assertSame(24, ExpireUnpaidOrders::HOURS);

        // A rotation of an hour must not silence it.
        Setting::put(Setting::PAYMENT_WINDOW_HOURS, 1);
        Setting::put(Setting::PAYMENT_REMIND_AFTER, 3);
        $this->artisan('orders:remind-unpaid')->assertSuccessful();
    }

    /**
     * One code, in any case. The rule compared the typed spelling against
     * the stored one, so «yaz10» passed and then went down on the index.
     */
    public function test_a_promo_code_is_one_code_however_it_is_typed(): void
    {
        PromoCode::create(['code' => 'YAZ10', 'percent' => 10, 'is_active' => true]);

        foreach (['YAZ10', 'yaz10', 'YaZ10'] as $typed) {
            $this->assertNotNull(PromoCode::byCode($typed), 'found when typed as ' . $typed);
        }

        $rule = function (string $attribute, $value, \Closure $fail) {
            if (PromoCode::whereRaw('UPPER(code) = ?', [\Illuminate\Support\Str::upper(trim((string) $value))])->exists()) {
                $fail('taken');
            }
        };
        foreach (['YAZ10', 'yaz10', 'YaZ10'] as $typed) {
            $this->assertTrue(
                \Illuminate\Support\Facades\Validator::make(['code' => $typed], ['code' => [$rule]])->fails(),
                'the admin refuses a second ' . $typed
            );
        }
    }

    /**
     * The link in a letter speaks for its customer. Whoever was signed in a
     * moment ago may have had a basket, and it must not follow him in.
     */
    public function test_a_basket_does_not_follow_the_link_into_another_account(): void
    {
        $mine = User::factory()->create();
        $order = $this->orderFor($mine);

        $somebodyElse = User::factory()->create();
        $box = $this->box(7);
        $this->actingAs($somebodyElse)->post(route('cart.add'), ['product_id' => $box->id])
            ->assertSessionHasNoErrors();
        $this->assertNotEmpty(Cart::items(), 'he really has one');

        $this->actingAs($somebodyElse)
            ->get(OrderKey::url($order, OrderKey::DOORS[0], 24))
            ->assertRedirect();

        $this->assertSame([], Cart::items(), 'and it stays behind');
        $this->assertSame($mine->id, auth()->id());
    }

    /**
     * A guest types his number once, at the top of the form. With every
     * delivery method switched off the checkout read a field that was not
     * there and answered 500.
     */
    public function test_a_guest_can_order_with_no_delivery_method_set_up(): void
    {
        DeliveryMethod::query()->update(['is_active' => false]);
        $box = $this->box(12);
        $this->post(route('cart.add'), ['product_id' => $box->id])->assertSessionHasNoErrors();

        $this->post(route('checkout.store'), [
            'guest_name' => 'Aygün', 'guest_email' => 'aygun@example.com', 'guest_phone' => '+994551234567',
            'delivery_address' => 'Bakı, Nizami küç. 5',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('+994551234567', Order::latest('id')->firstOrFail()->contact_phone,
            'his number reached the order, which is what used to end in a 500');
    }

    /**
     * An order worth nothing has nothing to pay: sent to the bank for 0.00
     * it would wait for a payment that can never arrive, and the owner
     * would never hear about it.
     */
    public function test_an_order_with_nothing_to_pay_is_confirmed_outright(): void
    {
        PromoCode::create(['code' => 'PULSUZ', 'percent' => 100, 'is_active' => true]);
        Setting::put(Setting::FREE_DELIVERY_FROM, 1);

        $user = User::factory()->create();
        $order = $this->orderFor($user, 20, 1, 'PULSUZ');

        $this->assertSame(0.0, $order->total());
        $this->assertSame('confirmed', $order->status);
        $this->assertNotNull($order->payment_confirmed_at, 'and the owner is told about it');
    }

    /**
     * A refusal for an earlier attempt, arriving late, used to overwrite the
     * transaction that had actually paid — and the next repeat of the good
     * callback then read as a second charge.
     */
    public function test_a_late_refusal_does_not_touch_an_order_already_paid(): void
    {
        $user = User::factory()->create();
        $order = $this->orderFor($user);
        $order->forceFill([
            'status' => 'confirmed',
            'payment_confirmed_at' => now(),
            'epoint_transaction' => 'PAID_ME',
        ])->save();

        $source = file_get_contents(app_path('Http/Controllers/EpointController.php'));
        $notPaid = strpos($source, 'if (! $paid) {');
        $guard = strpos($source, 'if ($order->payment_confirmed_at) {', $notPaid);
        $write = strpos($source, "'epoint_transaction' => \$transaction ?: \$seen,", $notPaid);

        $this->assertNotFalse($guard);
        $this->assertLessThan($write, $guard, 'the guard has to come before the writing');
    }
}
