<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Material;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The shop in a pocket: the owner's own small pages at /admin-phone, for the
 * things he does standing up — what is due today, moving an order on, the
 * till, the shelves, the customers' videos.
 */
class PhoneAdminTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['role' => User::ADMIN, 'is_admin' => true]);
    }

    private function manager(): User
    {
        return User::factory()->create(['role' => User::MANAGER, 'is_admin' => false]);
    }

    private function order(string $status = 'pending', array $extra = []): Order
    {
        $box = Product::create(['name' => 'Qutu', 'slug' => 'qutu-' . uniqid(), 'is_active' => true, 'price' => 10]);
        $order = Order::create($extra + [
            'user_id' => User::factory()->create(['name' => 'Aydan'])->id,
            'status' => $status,
            'contact_phone' => '+994 55 123 45 67',
            'delivery_name' => 'Kuryer',
            'delivery_price' => 5,
            'delivery_date' => now()->addDay()->toDateString(),
        ]);
        $order->items()->create(['product_id' => $box->id, 'product_name' => $box->name,
            'customer_photos' => [], 'custom_texts' => [], 'quantity' => 2, 'price' => 10]);

        return $order;
    }

    public function test_a_stranger_is_sent_to_the_login_page(): void
    {
        $this->get('/admin-phone')->assertRedirect(route('login'));
    }

    public function test_a_customer_cannot_open_it_at_all(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::CUSTOMER, 'is_admin' => false]))
            ->get('/admin-phone')->assertForbidden();
    }

    public function test_a_manager_gets_the_orders_and_nothing_of_the_owners(): void
    {
        $this->actingAs($this->manager());

        $this->get('/admin-phone')->assertOk();
        // The books, the shelves and the videos are the owner's own — and they
        // are refused by the server, not merely hidden from the tab bar.
        $this->get('/admin-phone/kassa')->assertForbidden();
        $this->get('/admin-phone/anbar')->assertForbidden();
        $this->get('/admin-phone/canli')->assertForbidden();

        $this->get('/admin-phone')->assertDontSee(route('phone.money.index'), false);
    }

    public function test_the_owner_gets_all_four_tabs(): void
    {
        $this->actingAs($this->owner());

        foreach (['/admin-phone', '/admin-phone/kassa', '/admin-phone/anbar', '/admin-phone/canli'] as $page) {
            $this->get($page)->assertOk();
        }

        $this->get('/admin-phone')
            ->assertSee(route('phone.money.index'), false)
            ->assertSee(route('phone.stock.index'), false)
            ->assertSee(route('phone.live.index'), false);
    }

    public function test_the_list_shows_what_is_still_being_worked_on(): void
    {
        $this->actingAs($this->owner());
        $working = $this->order('pending');
        $done = $this->order('completed');

        // Each card is a link to its order, which is a surer mark than "#1" —
        // that alone also matches the theme colour in the head.
        $link = fn (Order $o) => route('phone.orders.show', $o);

        // The default list is the queue: a finished order is not today's work.
        $this->get('/admin-phone')
            ->assertSee($link($working), false)
            ->assertDontSee($link($done), false);

        $this->get('/admin-phone?status=completed')
            ->assertSee($link($done), false)
            ->assertDontSee($link($working), false);

        $this->get('/admin-phone?status=all')
            ->assertSee($link($working), false)
            ->assertSee($link($done), false);
    }

    public function test_an_order_shows_who_what_and_how_much(): void
    {
        $this->actingAs($this->owner());
        $order = $this->order('confirmed');

        $this->get(route('phone.orders.show', $order))->assertOk()
            ->assertSee('Aydan')
            ->assertSee('+994 55 123 45 67')
            // 2 boxes at 10 plus 5 delivery
            ->assertSee('25')
            ->assertSee('Hazırdır');
    }

    public function test_confirming_the_payment_moves_the_order_and_stamps_the_hour(): void
    {
        $this->actingAs($this->owner());
        $order = $this->order('awaiting_payment');

        $this->post(route('phone.orders.pay', $order))->assertRedirect();

        $order->refresh();
        $this->assertSame('confirmed', $order->status);
        $this->assertNotNull($order->payment_confirmed_at);
    }

    public function test_the_status_goes_through_the_model_so_everything_else_still_happens(): void
    {
        $this->actingAs($this->owner());
        $order = $this->order('confirmed');

        $this->post(route('phone.orders.status', $order), ['status' => 'ready'])->assertRedirect();
        $this->assertSame('ready', $order->fresh()->status);

        // Nothing invented: only the seven the shop actually uses.
        $this->post(route('phone.orders.status', $order), ['status' => 'teleported'])->assertStatus(422);
        $this->assertSame('ready', $order->fresh()->status);
    }

    public function test_an_expense_is_written_in_the_name_of_whoever_wrote_it(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);

        $this->post(route('phone.money.expense'), [
            'spent_on' => now()->toDateString(), 'amount' => '12.50', 'category' => 'Kuryer', 'note' => 'Taksi',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $expense = Expense::sole();
        $this->assertSame(12.5, $expense->amount);
        $this->assertSame($owner->id, $expense->user_id);

        // An empty amount is refused rather than written as zero.
        $this->post(route('phone.money.expense'), ['spent_on' => now()->toDateString(), 'category' => 'Kuryer'])
            ->assertSessionHasErrors('amount');
        $this->assertSame(1, Expense::count());
    }

    public function test_buying_stock_goes_through_the_books(): void
    {
        $this->actingAs($this->owner());
        $paper = Material::create(['name' => 'Karton', 'unit' => 'ədəd', 'pack_price' => 10, 'pack_size' => 50,
            'per_box' => 1, 'stock' => 4, 'low_stock' => 10, 'is_active' => true, 'sort_order' => 0]);

        $this->post(route('phone.stock.purchase', $paper), ['packs' => 2, 'pack_price' => 12])
            ->assertRedirect()->assertSessionHasNoErrors();

        $paper->refresh();
        $this->assertSame(104.0, (float) $paper->stock);
        // A purchase is an expense too, which is why it may not touch the
        // stock column directly.
        $this->assertSame(24.0, (float) StockMovement::where('type', StockMovement::PURCHASE)->sum('amount'));
    }

    public function test_a_stocktake_that_matches_writes_nothing_and_says_so(): void
    {
        $this->actingAs($this->owner());
        $paper = Material::create(['name' => 'Lent', 'unit' => 'metr', 'pack_price' => 8, 'pack_size' => 20,
            'per_box' => 1, 'stock' => 20, 'low_stock' => 5, 'is_active' => true, 'sort_order' => 0]);

        $this->post(route('phone.stock.adjust', $paper), ['counted' => 20])->assertRedirect();
        $this->assertSame(0, StockMovement::count());
        $this->assertSame('Dəyişiklik yoxdur', session('phone.flash')['title']);

        $this->post(route('phone.stock.adjust', $paper), ['counted' => 17])->assertRedirect();
        $this->assertSame(17.0, (float) $paper->fresh()->stock);
        $this->assertSame(1, StockMovement::count());
    }

    public function test_the_home_screen_app_has_its_manifest_and_the_address_still_reaches_laravel(): void
    {
        // A folder called admin-phone under public/ would shadow the route:
        // Apache only hands a request to Laravel when no such folder exists.
        $this->assertDirectoryDoesNotExist(public_path('admin-phone'));
        $this->assertFileExists(public_path('admin-phone.webmanifest'));

        $manifest = json_decode(file_get_contents(public_path('admin-phone.webmanifest')), true);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/admin-phone', $manifest['start_url']);
        // With a trailing slash the scope would exclude the start URL itself,
        // and .htaccess strips the slash anyway.
        $this->assertSame('/admin-phone', $manifest['scope']);
        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
        }
    }

    public function test_the_phone_pages_keep_search_engines_out(): void
    {
        $this->actingAs($this->owner());

        $this->get('/admin-phone')->assertSee('noindex', false);
        $this->assertStringContainsString('Disallow: /admin', file_get_contents(public_path('robots.txt')));
    }
}
