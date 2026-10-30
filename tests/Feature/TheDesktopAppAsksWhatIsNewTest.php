<?php

namespace Tests\Feature;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The one question the desktop app asks the shop.
 *
 * It wraps the admin panel, and the panel's sidebar badge is drawn once when
 * the page loads — so the app cannot learn from the page it is showing that
 * an order has just arrived. It asks here instead, with the owner's own
 * session and no key of its own.
 *
 * Two things matter and are pinned below. The numbers must be the panel's
 * own, not a second set of rules that drifts away from it; and nobody
 * without a place in the shop may ask at all.
 */
class TheDesktopAppAsksWhatIsNewTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/admin-masaustu';

    private function order(string $status, float $price = 20): Order
    {
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'status' => $status,
            'contact_phone' => '0501112233',
            'delivery_address' => 'Bakı',
            'delivery_price' => 0,
        ]);

        $design = Product::create([
            'name' => 'Qutu '.$order->id, 'slug' => 'qutu-'.$order->id,
            'is_active' => true, 'price' => $price,
        ]);

        $order->items()->create([
            'product_id' => $design->id,
            'product_name' => $design->name,
            'price' => $price,
            'quantity' => 1,
        ]);

        return $order->refresh();
    }

    private function asStaff(): User
    {
        $owner = User::factory()->create(['role' => User::ADMIN]);
        $this->actingAs($owner);

        return $owner;
    }

    public function test_a_stranger_is_not_told_anything(): void
    {
        $this->order('pending');

        $this->get(self::URL)->assertRedirect();
    }

    public function test_a_customer_is_refused(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::CUSTOMER]));

        $this->get(self::URL)->assertForbidden();
    }

    public function test_the_shop_is_told_what_is_waiting(): void
    {
        $this->order('pending');
        $this->order('awaiting_payment');
        $this->order('completed');

        $this->asStaff();

        $this->get(self::URL)
            ->assertOk()
            ->assertJsonPath('orders', 2)
            ->assertJsonPath('messages', 0)
            ->assertJsonPath('corporate', 0)
            ->assertJsonPath('reviews', 0);
    }

    /**
     * The number is the sidebar's own.
     *
     * An app announcing three orders beside a panel showing two is worse
     * than an app that says nothing, so both read the same rule.
     */
    public function test_the_count_is_the_one_the_sidebar_shows(): void
    {
        foreach (Order::WAITING as $status) {
            $this->order($status);
        }
        $this->order('ready');

        $this->asStaff();

        $this->get(self::URL)
            ->assertOk()
            ->assertJsonPath('orders', (int) OrderResource::getNavigationBadge());
    }

    /**
     * The newest waiting order is named, with a total that is really its own.
     *
     * An order has no «total» column — it is worked out from the lines — so
     * asking the table for one hands back nothing and the app would announce
     * every order as costing zero.
     */
    public function test_the_newest_order_comes_with_its_real_total(): void
    {
        $this->order('pending', 10);
        $newest = $this->order('pending', 35.50);

        $this->asStaff();

        $this->get(self::URL)
            ->assertOk()
            ->assertJsonPath('newest_order', $newest->id)
            ->assertJsonPath('newest_total', 35.50);
    }

    /** With nothing waiting, the app is told so plainly rather than guessing. */
    public function test_an_empty_shop_answers_with_noughts(): void
    {
        $this->order('completed');
        $this->asStaff();

        $this->get(self::URL)
            ->assertOk()
            ->assertJsonPath('orders', 0)
            ->assertJsonPath('newest_order', 0)
            ->assertJsonPath('newest_total', 0);
    }

    /** An order dealt with and a new one arriving must not look like no change. */
    public function test_the_newest_number_moves_even_when_the_count_does_not(): void
    {
        $first = $this->order('pending');
        $this->asStaff();

        $this->get(self::URL)->assertJsonPath('newest_order', $first->id);

        $first->update(['status' => 'confirmed']);
        $second = $this->order('pending');

        $this->get(self::URL)
            ->assertJsonPath('orders', 1)
            ->assertJsonPath('newest_order', $second->id);
    }

    /** No customer's name, phone or address travels to a desktop. */
    public function test_nothing_about_the_customer_is_handed_over(): void
    {
        $order = $this->order('pending');
        $order->update(['recipient_name' => 'Rəşid Rəşidli', 'contact_phone' => '0509998877']);

        $this->asStaff();

        $answer = $this->get(self::URL)->assertOk();

        $this->assertStringNotContainsString('Rəşid', $answer->getContent());
        $this->assertStringNotContainsString('0509998877', $answer->getContent());
        $this->assertStringNotContainsString('Bakı', $answer->getContent());
    }

    /** The answer is never kept by a cache that another person could read. */
    public function test_the_answer_is_not_stored_anywhere(): void
    {
        $this->asStaff();

        $kept = (string) $this->get(self::URL)->headers->get('Cache-Control');

        $this->assertStringContainsString('no-store', $kept);
        $this->assertStringNotContainsString('public', $kept);
    }
}
