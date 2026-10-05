<?php

namespace Tests\Feature;

use App\Mail\OrderMessage;
use App\Models\Order;
use App\Models\User;
use App\Support\CustomerNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Writing to the customer by e-mail from the order's own page — the
 * counterpart of the WhatsApp button beside it, for everything WhatsApp is
 * not: a long answer, something he can find again.
 */
class OrderMailTest extends TestCase
{
    use RefreshDatabase;

    private function order(array $user = []): Order
    {
        $customer = User::factory()->create($user + ['email' => 'musteri@nefis.az']);

        return Order::create(['user_id' => $customer->id, 'status' => 'confirmed', 'locale' => 'ru']);
    }

    public function test_the_owner_writes_and_it_goes_to_the_customer_in_his_language(): void
    {
        Mail::fake();
        $order = $this->order();

        $failed = CustomerNotice::write($order, 'Sifarişiniz hazırdır', "Salam!\n\nQutunuz hazırdır.");

        $this->assertNull($failed);
        Mail::assertSent(OrderMessage::class, function (OrderMessage $mail) use ($order) {
            return $mail->hasTo('musteri@nefis.az')
                && $mail->subjectLine === 'Sifarişiniz hazırdır'
                && str_contains($mail->body, 'Qutunuz hazırdır')
                && $mail->order->is($order)
                && $mail->locale === 'ru';
        });
    }

    public function test_a_customer_with_no_address_is_said_so_rather_than_failing_quietly(): void
    {
        Mail::fake();
        $order = $this->order(['email' => '']);

        $this->assertNotNull(CustomerNotice::write($order, 'Salam', 'Mətn'));
        Mail::assertNothingSent();
    }

    public function test_the_owners_own_line_breaks_survive_and_his_words_are_not_read_as_markup(): void
    {
        $order = $this->order();
        $body = "Birinci sətir\nİkinci sətir <b>qalın deyil</b>";

        $html = view('mail.order-message', [
            'order' => $order, 'body' => $body, 'link' => CustomerNotice::link($order),
        ])->render();

        $this->assertStringContainsString('white-space:pre-wrap', $html, 'his line breaks are kept');
        $this->assertStringContainsString('&lt;b&gt;', $html, 'and his angle brackets stay text');
        $this->assertStringNotContainsString('<b>qalın', $html);
    }

    public function test_the_button_is_on_the_order_page_only_when_there_is_somewhere_to_write(): void
    {
        $admin = User::factory()->create(['role' => User::ADMIN]);

        $with = $this->order();
        $this->actingAs($admin)->get('/admin/orders/' . $with->id . '/edit')
            ->assertOk()->assertSee('Mail göndər');

        $without = $this->order(['email' => '']);
        $this->actingAs($admin)->get('/admin/orders/' . $without->id . '/edit')
            ->assertOk()->assertDontSee('Mail göndər');
    }
}
