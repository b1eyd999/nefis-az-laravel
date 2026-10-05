<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The panel the workshop works from. Making the box means taking the
 * customer's words and his photograph out of it, so both have to come out in
 * one click: the browser shows a .jpg rather than saving it, and a caption
 * dragged over with a mouse is a caption half-copied.
 */
class OrderItemPanelTest extends TestCase
{
    use RefreshDatabase;

    private function line(): OrderItem
    {
        $box = Product::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true, 'price' => 20,
            'template_width' => 969, 'template_height' => 1895]);
        $order = Order::create(['user_id' => User::factory()->create()->id, 'status' => 'confirmed']);

        return $order->items()->create([
            'product_id' => $box->id, 'product_name' => 'Test', 'quantity' => 1, 'price' => 20,
            'customer_photos' => ['orders/sekil.jpg'],
            'photo_labels' => ['1. Şəkil'],
            'custom_texts' => ['Sevda Mələyi', 'Ikinci söz'],
            'text_labels' => [
                ['label' => 'Mətn 1', 'fixed' => false, 'repeat' => false],
                ['label' => 'Mətn 2', 'fixed' => false, 'repeat' => false],
            ],
            'letter_text' => "Sən mənə güvəndin,\nmən etdim xəta.",
            'letter_photo' => 'orders/mektub.jpg',
            'letter_price' => 3,
        ]);
    }

    private function panel(OrderItem $line): string
    {
        return view('filament.order-item-fields', ['getRecord' => fn () => $line])->render();
    }

    public function test_every_caption_can_be_copied_in_one_click(): void
    {
        $html = $this->panel($this->line());

        $this->assertStringContainsString('data-oi-text="Sevda Mələyi"', $html);
        $this->assertStringContainsString('Bütün mətnləri kopyala', $html, 'and all of them together');
        // The letter is the longest thing anyone retypes; it copies too.
        $this->assertStringContainsString('Məktubu kopyala', $html);
    }

    public function test_a_photo_is_saved_rather_than_opened_and_says_which_order_it_is(): void
    {
        $line = $this->line();
        $html = $this->panel($line);

        // Fetched as a blob and handed to the browser, so the server's content
        // type cannot turn a download into a preview.
        $this->assertStringContainsString('data-oi-file=', $html);
        $this->assertStringContainsString('sifaris-' . $line->order_id . '-' . $line->id . '-1.jpg', $html);
        $this->assertStringContainsString('sifaris-' . $line->order_id . '-' . $line->id . '-mektub.jpg', $html);
        $this->assertStringNotContainsString('<a href="' . \App\Support\Media::url('orders/sekil.jpg') . '" download', $html);
    }

    public function test_a_caption_the_design_holds_fixed_is_not_offered_for_copying(): void
    {
        $box = Product::create(['name' => 'Sabit', 'slug' => 'sabit', 'is_active' => true, 'price' => 20,
            'template_width' => 969, 'template_height' => 1895]);
        $box->textSlots()->create(['label' => 'Mətn 1', 'fixed' => true, 'default_value' => 'SPECIAL EDITION FOR',
            'x' => 10, 'y' => 10, 'sort_order' => 0]);
        $order = Order::create(['user_id' => User::factory()->create()->id, 'status' => 'confirmed']);
        $line = $order->items()->create([
            'product_id' => $box->id, 'product_name' => 'Sabit', 'quantity' => 1, 'price' => 20,
            'customer_photos' => [], 'custom_texts' => [''],
        ]);

        $html = $this->panel($line);

        $this->assertStringContainsString('dizaynda sabit', $html);
        $this->assertStringNotContainsString('Bütün mətnləri kopyala', $html,
            'nothing of the customer\'s own to copy here');
    }
}
