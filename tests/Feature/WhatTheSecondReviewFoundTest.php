<?php

namespace Tests\Feature;

use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\Product;
use App\Models\PromoCode;
use App\Models\SavedCart;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhatsAppChat;
use App\Models\WhatsAppMessage;
use App\Support\Cart;
use App\Support\OrderEditor;
use App\Support\OrderKey;
use App\Support\WhatsApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The second review, the day after the first — and most of what it found had
 * been put there by the first day's repairs. Each of these guards one of
 * those.
 */
class WhatTheSecondReviewFoundTest extends TestCase
{
    use RefreshDatabase;

    private function box(float $price = 20): Product
    {
        $box = Product::create(['name' => 'T', 'slug' => 't-' . uniqid(), 'is_active' => true,
            'price' => $price, 'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/' . $box->id . '/bg.webp', 'x' => 0, 'y' => 0,
            'width' => 969, 'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);

        return $box;
    }

    private function orderFor(User $user, float $price, int $qty, string $promo = ''): Order
    {
        PaymentAccount::firstOrCreate(['number' => '4169738111111111'], ['type' => PaymentAccount::CARD, 'label' => 'K']);
        $box = $this->box($price);
        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $box->id, 'quantity' => $qty])
            ->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('checkout.store'), [
            'delivery_method_id' => DeliveryMethod::where('type', 'door')->value('id'),
            'contact_phone' => '1', 'delivery_address' => 'Bakı, Nizami 5',
        ] + ($promo ? ['promo_code' => $promo] : []))->assertSessionHasNoErrors();

        return Order::latest('id')->firstOrFail();
    }

    /**
     * Recomputing the percentage fixed the shrinking order and broke the
     * growing one: a single-use code went on applying to a box added a week
     * later, so the surcharge was half what the box costs.
     */
    public function test_a_code_never_comes_off_goods_it_was_not_accepted_for(): void
    {
        PromoCode::create(['code' => 'YARI', 'percent' => 50, 'is_active' => true, 'max_uses' => 1]);
        $order = $this->orderFor(User::factory()->create(), 20, 1, 'YARI');

        $this->assertSame(10.0, $order->discountOff(), 'half of the one box he bought');

        // A second box, added after the code was already spent.
        $order->items()->first()->update(['quantity' => 2]);
        $order->refresh()->load('items');

        $this->assertSame(10.0, $order->discountOff(),
            'the discount stays what the code was worth on the day');
        // He paid 10 for the first box; the goods now come to 30, so the
        // second box cost him its own 20 and not a qəpik less.
        $this->assertSame(30.0, $order->itemsTotal() - $order->discountOff());
    }

    /** And it still shrinks with the order, which is what it was changed for. */
    public function test_a_code_still_shrinks_with_the_order(): void
    {
        PromoCode::create(['code' => 'YARI2', 'percent' => 50, 'is_active' => true]);
        $order = $this->orderFor(User::factory()->create(), 20, 2, 'YARI2');
        $this->assertSame(20.0, $order->discountOff());

        $order->items()->first()->update(['quantity' => 1]);
        $order->refresh()->load('items');

        $this->assertSame(10.0, $order->discountOff(), 'half of the one box left');
    }

    /** Every screen prints the figure that is actually taken off. */
    public function test_the_discount_shown_is_the_discount_taken(): void
    {
        PromoCode::create(['code' => 'YARI3', 'percent' => 50, 'is_active' => true]);
        $order = $this->orderFor(User::factory()->create(), 20, 2, 'YARI3');
        $order->items()->first()->update(['quantity' => 1]);
        $order->refresh()->load('items');

        foreach ([
            app_path('Filament/Resources/OrderResource.php'),
            resource_path('views/orders/pay.blade.php'),
            resource_path('views/orders/index.blade.php'),
        ] as $file) {
            $this->assertStringNotContainsString('discount)', file_get_contents($file),
                basename($file) . ' must not print the frozen figure');
        }

        $this->assertTrue($order->hasDiscount());
        $order->items()->delete();
        $order->refresh()->load('items');
        $this->assertFalse($order->fresh()->load('items')->hasDiscount(),
            'nothing left to take it off, so no discount line');
    }

    /**
     * A wallet sheet left open while the owner edits the order used to pay
     * the old amount and be accepted, because the figure it was opened for
     * was still frozen on the order.
     */
    public function test_an_edit_cancels_the_amount_the_customer_was_asked_for(): void
    {
        $order = $this->orderFor(User::factory()->create(), 20, 1);
        $order->forceFill(['payment_asked_for' => 25.00])->save();

        OrderEditor::change($order, 'say sayı dəyişdi',
            fn () => $order->items()->first()->update(['quantity' => 2]));

        $this->assertNull($order->fresh()->payment_asked_for,
            'the old figure goes with the order it belonged to');
    }

    /** The link in a letter must not take the last visitor's basket with it. */
    public function test_the_link_does_not_delete_the_basket_of_whoever_was_here(): void
    {
        $mine = User::factory()->create();
        $order = $this->orderFor($mine, 20, 1);

        $owner = User::factory()->create(['is_admin' => true]);
        $box = $this->box(7);
        $this->actingAs($owner)->post(route('cart.add'), ['product_id' => $box->id])->assertSessionHasNoErrors();
        $kept = SavedCart::where('user_id', $owner->id)->firstOrFail();
        $this->assertNotEmpty($kept->items, 'the owner really has one kept');

        $this->actingAs($owner)->get(OrderKey::url($order, OrderKey::DOORS[0], 24))->assertRedirect();

        $this->assertSame([], Cart::items(), 'the basket does not follow him in');
        $this->assertNotEmpty(SavedCart::where('user_id', $owner->id)->first()?->items,
            'and the owner still has his own when he comes back');
    }

    /** One address, however it is typed — the rule that could not work before. */
    public function test_a_second_account_cannot_take_the_same_address(): void
    {
        User::factory()->create(['email' => 'aygun@mail.ru']);

        foreach (['aygun@mail.ru', 'Aygun@Mail.ru', 'AYGUN@MAIL.RU'] as $typed) {
            $this->post(route('register'), [
                'name' => 'Z', 'email' => $typed, 'phone' => '+994557778899',
                'password' => 'parol12345', 'password_confirmation' => 'parol12345',
            ])->assertSessionHasErrors('email');
        }

        $this->assertSame(1, User::whereRaw('LOWER(email) = ?', ['aygun@mail.ru'])->count());
    }

    /** The shop may close; the customers' questions may not be thrown away. */
    public function test_the_whatsapp_hook_is_open_while_the_shop_is_closed(): void
    {
        $open = (new \ReflectionClass(\App\Http\Middleware\MaintenanceMode::class))
            ->getConstant('OPEN');

        $this->assertContains('whatsapp/hook', $open);
    }

    /** A long conversation opens where it actually is. */
    public function test_a_long_conversation_shows_its_newest_messages(): void
    {
        Setting::put(Setting::WHATSAPP_ENABLED, true);
        Setting::put(Setting::WHATSAPP_PHONE_ID, '1');
        WhatsApp::saveToken('t');

        $chat = WhatsAppChat::create(['wa_id' => '994551112233', 'last_incoming_at' => now(), 'last_message_at' => now()]);
        for ($i = 1; $i <= 420; $i++) {
            WhatsAppMessage::create(['wa_id' => $chat->wa_id, 'direction' => 'in',
                'wam_id' => 'w.' . $i, 'type' => 'text', 'body' => 'line ' . $i, 'sent_at' => now()]);
        }

        $staff = User::factory()->create(['is_admin' => true]);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
        $page = \Livewire\Livewire::actingAs($staff)->test(\App\Filament\Pages\WhatsAppInbox::class)
            ->set('openChat', $chat->id);

        $thread = $page->instance()->thread();
        $this->assertSame('line 420', $thread->last()->body, 'the newest message is there');
        $this->assertSame('line 21', $thread->first()->body, 'and the oldest shown is the 400th from the end');
    }

    /** A query value that is not a word must not become a 500. */
    public function test_the_handshake_refuses_nonsense_without_falling_over(): void
    {
        WhatsApp::saveVerifyWord('a-word');

        $this->get('/whatsapp/hook?hub_mode=subscribe&' . http_build_query(['hub_verify_token' => ['x']]))
            ->assertForbidden();
        $this->get('/whatsapp/hook?hub_mode=subscribe&hub_verify_token=a-word&'
            . http_build_query(['hub_challenge' => ['x']]))->assertForbidden();
        $this->get('/whatsapp/hook?hub_mode=subscribe&hub_verify_token=a-word&hub_challenge=42')
            ->assertOk()->assertSee('42');
    }

    /** A hidden Filament field is dropped unless it is told otherwise. */
    public function test_the_two_hidden_fields_are_still_saved(): void
    {
        foreach ([
            app_path('Filament/Resources/WrappingResource.php'),
            app_path('Filament/Resources/GiftPageResource.php'),
        ] as $file) {
            $src = file_get_contents($file);
            $at = strpos($src, 'dehydrateStateUsing');
            $this->assertNotFalse($at, basename($file));
            $this->assertStringContainsString('dehydratedWhenHidden', substr($src, max(0, $at - 400), 400),
                basename($file) . ': without this the line below it never runs');
        }
    }
}
