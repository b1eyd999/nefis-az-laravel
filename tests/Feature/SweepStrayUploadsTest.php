<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SavedCart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The photographs of boxes nobody ordered.
 *
 * A customer opens a design, uploads his picture, changes his mind and
 * leaves. Nothing ever pointed at that file again, and nothing ever removed
 * it. Deleting is not undoable, so a file goes only when it is both old and
 * mentioned nowhere.
 */
class SweepStrayUploadsTest extends TestCase
{
    use RefreshDatabase;

    private function photo(string $name, int $daysOld = 0): string
    {
        Storage::disk('public')->put('cart-photos/' . $name, 'not really a picture');

        if ($daysOld > 0) {
            touch(
                Storage::disk('public')->path('cart-photos/' . $name),
                now()->subDays($daysOld)->getTimestamp()
            );
        }

        return 'cart-photos/' . $name;
    }

    public function test_an_old_photograph_nothing_points_at_is_swept(): void
    {
        Storage::fake('public');

        $stray = $this->photo('stray.webp', 90);
        $fresh = $this->photo('fresh.webp');

        $this->artisan('uploads:sweep')->assertSuccessful();

        Storage::disk('public')->assertMissing($stray);
        // Still being worked on in a basket this cannot see: a session is
        // not a table, so recent files are left alone whatever happens.
        Storage::disk('public')->assertExists($fresh);
    }

    public function test_a_photograph_an_order_carries_is_never_swept(): void
    {
        Storage::fake('public');

        $ordered = $this->photo('ordered.webp', 200);
        $box = Product::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true, 'price' => 20,
            'template_width' => 969, 'template_height' => 1895]);
        $order = Order::create(['user_id' => User::factory()->create()->id, 'status' => 'completed',
            'contact_phone' => '1', 'delivery_address' => 'Bakı']);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $box->id, 'product_name' => 'Test',
            'customer_photos' => [$ordered], 'quantity' => 1, 'price' => 20]);

        $this->artisan('uploads:sweep')->assertSuccessful();

        Storage::disk('public')->assertExists($ordered);
    }

    public function test_a_photograph_in_a_basket_kept_for_somebody_is_never_swept(): void
    {
        Storage::fake('public');

        $waiting = $this->photo('waiting.webp', 200);
        SavedCart::create([
            'user_id' => User::factory()->create()->id,
            'items' => [['id' => 'x', 'product_id' => 1, 'photo_paths' => [$waiting], 'quantity' => 1]],
            'rush' => false,
        ]);

        $this->artisan('uploads:sweep')->assertSuccessful();

        Storage::disk('public')->assertExists($waiting);
    }

    public function test_nothing_is_removed_on_a_dry_run(): void
    {
        Storage::fake('public');

        $stray = $this->photo('stray.webp', 90);

        $this->artisan('uploads:sweep --dry-run')->assertSuccessful();

        Storage::disk('public')->assertExists($stray);
    }
}
