<?php

namespace Tests\Feature;

use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Mail\OrderStatus;
use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Support\CustomerNotice;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The customer is not left guessing: every move of his order reaches him by
 * e-mail, and the same words are one tap away on WhatsApp.
 */
class CustomerNoticeTest extends TestCase
{
    use RefreshDatabase;

    private function order(array $fields = []): Order
    {
        $customer = User::factory()->create(['name' => 'Aysel Məmmədova', 'email' => 'aysel@example.com']);
        $box = Product::create(['name' => 'Love Story', 'slug' => 'love-story', 'is_active' => true, 'price' => 4.90,
            'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/bg.webp', 'x' => 0, 'y' => 0, 'width' => 969,
            'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);

        $door = DeliveryMethod::where('type', DeliveryMethod::DOOR)->firstOrFail();
        $door->update(['price' => 5, 'is_active' => true]);

        $this->actingAs($customer)->post(route('cart.add'), ['product_id' => $box->id]);
        $this->actingAs($customer)->post(route('checkout.store'), $fields + [
            'delivery_method_id' => $door->id,
            'contact_phone' => '050 123 45 67',
            'delivery_address' => 'Bakı, Nəsimi 1',
        ])->assertRedirect();

        return Order::firstOrFail();
    }

    public function test_every_change_of_status_writes_to_the_customer(): void
    {
        Mail::fake();
        $order = $this->order();

        $order->forceFill(['status' => 'confirmed'])->save();

        Mail::assertSent(OrderStatus::class, function (OrderStatus $mail) use ($order) {
            return $mail->hasTo('aysel@example.com')
                && $mail->envelope()->subject === 'Nefis.az, sifariş #' . $order->id . ': Təsdiqləndi';
        });

        // The letter says what happened and where to look at the order.
        $this->assertSame('Ödəniş təsdiqləndi, sifarişiniz hazırlanır.', CustomerNotice::line($order->fresh()));
        Mail::assertSent(OrderStatus::class, fn (OrderStatus $mail) => str_contains(
            $mail->render(), 'Ödəniş təsdiqləndi, sifarişiniz hazırlanır.'
        ));
    }

    public function test_a_russian_customer_is_written_to_in_russian(): void
    {
        Mail::fake();
        Setting::put(Setting::SITE_LANGUAGES, 'az,ru,en');
        $customer = User::factory()->create(['email' => 'aysel@example.com']);
        $box = Product::create(['name' => 'Love Story', 'slug' => 'love-story', 'is_active' => true, 'price' => 4.90,
            'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/bg.webp', 'x' => 0, 'y' => 0, 'width' => 969,
            'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);
        $door = DeliveryMethod::where('type', DeliveryMethod::DOOR)->firstOrFail();
        $door->update(['price' => 5, 'is_active' => true]);

        // The whole order is placed on the Russian pages.
        $this->actingAs($customer)->post('/ru/cart', ['product_id' => $box->id]);
        $this->actingAs($customer)->post('/ru/checkout', [
            'delivery_method_id' => $door->id, 'contact_phone' => '050 123 45 67',
            'delivery_address' => 'Bakı, Nəsimi 1',
        ])->assertRedirect();

        $order = Order::firstOrFail();
        $this->assertSame('ru', $order->locale);

        $order->forceFill(['status' => 'confirmed'])->save();

        Mail::assertSent(OrderStatus::class, function (OrderStatus $mail) {
            $body = $mail->render();

            return str_contains($body, 'Оплата подтверждена')
                && str_contains($body, 'Посмотреть заказ')
                && ! str_contains($body, 'Ödəniş təsdiqləndi');
        });

        // The message the owner would send by hand is Russian too, and reading
        // it leaves the site itself in whatever language it was showing.
        app()->setLocale('az');
        $this->assertStringContainsString('Оплата подтверждена', CustomerNotice::text($order->fresh()));
        $this->assertSame('az', app()->getLocale());
    }

    public function test_the_owner_can_switch_the_letters_off(): void
    {
        Mail::fake();
        Setting::put(Setting::NOTIFY_EMAIL, '0');
        $order = $this->order();

        $order->forceFill(['status' => 'completed'])->save();

        Mail::assertNothingSent();
        $this->assertFalse(CustomerNotice::$sent);
    }

    public function test_the_customers_number_becomes_a_ready_whatsapp_message(): void
    {
        $this->assertSame('994501234567', CustomerNotice::phone('050 123 45 67'));
        $this->assertSame('994501234567', CustomerNotice::phone('+994 50 123 45 67'));
        $this->assertSame('994501234567', CustomerNotice::phone('501234567'));
        $this->assertNull(CustomerNotice::phone('123'));
        $this->assertNull(CustomerNotice::phone(null));

        $order = $this->order();
        $link = CustomerNotice::whatsapp($order);

        $this->assertStringStartsWith('https://wa.me/994501234567?text=', $link);
        $text = rawurldecode(substr($link, strpos($link, 'text=') + 5));
        $this->assertStringContainsString('sifariş #' . $order->id, $text);
        $this->assertStringContainsString('Sifarişiniz qeydə alındı', $text);
        $this->assertStringContainsString(route('orders.index'), $text);
    }

    public function test_the_list_offers_the_chat_after_the_status_is_changed(): void
    {
        Mail::fake();
        $order = $this->order();
        $this->actingAs(User::factory()->create(['role' => User::ADMIN]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListOrders::class)
            ->callTableAction('status_quick', $order, ['status' => 'completed'])
            ->assertHasNoTableActionErrors()
            ->assertNotified('Status dəyişdi');

        $this->assertSame('completed', $order->fresh()->status);
        $this->assertTrue(CustomerNotice::$sent, 'the customer was written to');
        $this->assertStringContainsString('wa.me/994501234567', CustomerNotice::whatsapp($order->fresh()));
    }
}
