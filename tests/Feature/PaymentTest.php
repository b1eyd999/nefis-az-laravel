<?php

namespace Tests\Feature;

use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function box(): Product
    {
        $box = Product::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true, 'price' => 20,
            'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/' . $box->id . '/bg.webp', 'x' => 0, 'y' => 0,
            'width' => 969, 'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);

        return $box;
    }

    /** A card the owner would add in the panel; the digits are a test number. */
    private function card(string $label = 'Kapital Bank / Elxan', string $number = '4169738111111111', int $sort = 0): PaymentAccount
    {
        return PaymentAccount::create(['type' => PaymentAccount::CARD, 'label' => $label, 'number' => $number, 'sort_order' => $sort]);
    }

    private function order(Product $box, ?User $user = null): Order
    {
        $user ??= User::factory()->create();
        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $box->id])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('checkout.store'), [
            'delivery_method_id' => DeliveryMethod::where('type', 'door')->value('id'),
            'contact_phone' => '1', 'delivery_address' => 'Bakı, Nizami küç. 5',
        ]);

        return Order::latest('id')->firstOrFail();
    }

    public function test_the_customer_is_sent_to_pay_and_sees_the_card(): void
    {
        $card = $this->card();
        $box = $this->box();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $box->id])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('checkout.store'), [
            'delivery_method_id' => DeliveryMethod::where('type', 'door')->value('id'),
            'contact_phone' => '1', 'delivery_address' => 'Bakı, Nizami küç. 5',
        ])->assertRedirect(route('orders.pay', Order::latest('id')->first()));

        $order = Order::firstOrFail();
        $this->assertSame(['awaiting_payment', $card->id], [$order->status, $order->payment_account_id]);

        $this->actingAs($user)->get(route('orders.pay', $order))
            ->assertOk()
            ->assertSee('4169 7381 1111 1111')            // read in fours
            ->assertSee('Kapital Bank / Elxan')
            ->assertSee('Kartdan-karta');

        // Someone else's order is none of their business.
        $this->actingAs(User::factory()->create())->get(route('orders.pay', $order))->assertForbidden();
    }

    public function test_the_card_changes_after_its_share_of_orders_and_frees_up_a_day_later(): void
    {
        $first = $this->card('Birinci', '4169738111111111', 0);
        $second = $this->card('İkinci', '4169738122222222', 1);
        $box = $this->box();

        $cards = [];
        for ($i = 0; $i < 6; $i++) {
            $cards[] = $this->order($box)->payment_account_id;
        }

        // Five to the first card, then the next one takes over.
        $this->assertSame(array_merge(array_fill(0, 5, $first->id), [$second->id]), $cards);

        // A day later the first card's own count has run out, so it is offered again.
        Order::where('payment_account_id', $first->id)->update(['created_at' => now()->subHours(25)]);
        $this->assertSame($first->id, $this->order($box)->payment_account_id);

        // Cancelled orders do not take up a card's share.
        Order::where('payment_account_id', $second->id)->update(['status' => 'cancelled']);
        $this->assertSame(0, $second->fresh()->used());

        // The owner can change how many orders a card takes.
        Setting::put(Setting::PAYMENT_LIMIT, 1);
        $this->assertTrue($first->fresh()->isFull());
    }

    public function test_the_customer_picks_m10_uploads_the_receipt_and_the_owner_confirms(): void
    {
        $card = $this->card();
        $m10 = PaymentAccount::create(['type' => PaymentAccount::M10, 'label' => 'M10 / Elxan', 'number' => '+994501112233', 'sort_order' => 1]);
        $order = $this->order($this->box());
        $customer = $order->user;

        $this->actingAs($customer)->get(route('orders.pay', $order))->assertSee('M10');
        $this->assertSame($card->id, $order->payment_account_id);

        $this->actingAs($customer)->post(route('orders.pay.method', $order), ['type' => PaymentAccount::M10])
            ->assertRedirect(route('orders.pay', $order));
        $this->assertSame($m10->id, $order->fresh()->payment_account_id);

        $this->actingAs($customer)->post(route('orders.pay.receipt', $order), [
            'receipt' => UploadedFile::fake()->image('cek.jpg', 600, 800),
        ])->assertRedirect(route('orders.index'));

        $order->refresh();
        $this->assertSame('payment_check', $order->status);
        $this->assertNotNull($order->receipt_at);
        Storage::disk('public')->assertExists($order->payment_receipt);

        // The customer's own page shows where to go back to.
        $this->actingAs($customer)->get(route('orders.index'))->assertSee('Çek yoxlanılır')->assertSee(route('orders.pay', $order), false);

        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ListOrders::class)
            ->callTableAction('confirm_payment', $order)
            ->assertHasNoTableActionErrors();

        $order->refresh();
        $this->assertSame('confirmed', $order->status);
        $this->assertNotNull($order->payment_confirmed_at);

        // Paid for: the payment page is behind it now.
        $this->actingAs($customer)->get(route('orders.pay', $order))->assertRedirect(route('orders.index'));
    }

    public function test_the_status_is_changed_from_the_list_itself(): void
    {
        $order = $this->order($this->box());
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // Tapping the status badge asks for the new one.
        Livewire::test(ListOrders::class)
            ->callTableAction('status_quick', $order, ['status' => 'completed'])
            ->assertHasNoTableActionErrors();
        $this->assertSame('completed', $order->fresh()->status);

        // The same from the ⋮ menu, and cancelling still goes through the
        // model, so the materials come back to the store room.
        Livewire::test(ListOrders::class)
            ->callTableAction('status_change', $order, ['status' => 'cancelled'])
            ->assertHasNoTableActionErrors();
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('Ləğv edildi', $order->fresh()->statusLabel());
    }

    public function test_without_any_account_an_order_goes_through_as_before(): void
    {
        $box = $this->box();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $box->id]);
        $this->actingAs($user)->post(route('checkout.store'), [
            'delivery_method_id' => DeliveryMethod::where('type', 'door')->value('id'),
            'contact_phone' => '1', 'delivery_address' => 'Bakı, Nizami küç. 5',
        ])->assertRedirect(route('orders.index'));

        $this->assertSame('pending', Order::firstOrFail()->status);
    }
}
