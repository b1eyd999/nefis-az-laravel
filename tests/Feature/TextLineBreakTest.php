<?php

namespace Tests\Feature;

use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A caption the owner or the customer breaks by hand keeps its lines all the
 * way to the order. The drawing itself is box-render.js's job.
 */
class TextLineBreakTest extends TestCase
{
    use RefreshDatabase;

    private function box(int $maxLines, string $default): Product
    {
        $box = Product::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true, 'price' => 4,
            'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/' . $box->id . '/bg.webp', 'x' => 0, 'y' => 0,
            'width' => 969, 'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);
        $box->textSlots()->create(['label' => 'Mesaj', 'default_value' => $default, 'x' => 10, 'y' => 10,
            'max_width' => 820, 'font_size' => 46, 'color' => '#ffffff', 'align' => 'left', 'rotation' => 0,
            'max_lines' => $maxLines, 'max_length' => 120, 'kind' => 'text', 'fixed' => false, 'sort_order' => 0]);

        return $box;
    }

    public function test_a_caption_with_a_break_is_offered_as_a_textarea_and_ordered_as_typed(): void
    {
        Storage::fake('public');
        $box = $this->box(2, "Samalyotdur, amma ancaq\nyanacaqdoldurma məntəqələrində eniş edir.");

        // A text input would silently drop the line break, so multi-line slots get a textarea.
        $this->get(route('products.customize', $box->slug))
            ->assertOk()
            ->assertSee('<textarea class="text-input"', false)
            ->assertSee("Samalyotdur, amma ancaq\nyanacaqdoldurma məntəqələrində eniş edir.", false)
            ->assertSee('Yeni sətir üçün Enter basın.');

        $user = User::factory()->create();
        $this->actingAs($user)->post(route('cart.add'), [
            'product_id' => $box->id,
            'custom_texts' => ["Birinci sətir\r\nİkinci sətir "],   // a textarea sends CRLF
        ])->assertSessionHasNoErrors();

        $this->actingAs($user)->post(route('checkout.store'), [
            'delivery_method_id' => DeliveryMethod::where('type', 'door')->value('id'),
            'contact_phone' => '1', 'delivery_address' => 'Bakı, Nizami küç. 5',
        ])->assertRedirect(route('orders.index'));

        $item = Order::firstOrFail()->items()->firstOrFail();
        $this->assertSame("Birinci sətir\nİkinci sətir", $item->custom_texts[0]);
    }

    public function test_the_designs_own_wording_is_never_too_long_for_its_slot(): void
    {
        // The owner's sentence is 65 characters; the slot was left at 60.
        $wording = 'Samalyotdur, amma ancaq yanacaqdoldurma məntəqələrində eniş edir.';
        $box = $this->box(2, $wording);
        $box->textSlots()->first()->update(['max_length' => 60]);
        $user = User::factory()->create();

        $this->get(route('products.customize', $box->slug))->assertSee('maxlength="65"', false);

        // Left as it is, it goes into the cart…
        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $box->id, 'custom_texts' => [$wording]])
            ->assertSessionHasNoErrors();

        // …a line break counts once even though the browser sends two characters…
        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $box->id,
            'custom_texts' => [str_replace('ancaq ', "ancaq\r\n", $wording)]])->assertSessionHasNoErrors();

        // …and anything longer is refused in Azerbaijani.
        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $box->id, 'custom_texts' => [$wording . ' Bəli!']])
            ->assertSessionHasErrors(['custom_texts.0' => 'Mesaj 65 simvoldan uzun ola bilməz.']);
    }

    public function test_captions_have_no_60_character_limit_any_more(): void
    {
        $box = $this->box(3, 'Salam');
        $slot = $box->textSlots()->create(['label' => 'Uzun', 'default_value' => 'Test', 'x' => 10, 'y' => 200,
            'max_width' => 820, 'font_size' => 40, 'color' => '#ffffff', 'align' => 'left', 'rotation' => 0,
            'max_lines' => 3, 'kind' => 'text', 'fixed' => false, 'sort_order' => 1]);
        $this->assertSame(255, (int) $slot->fresh()->max_length, 'a new caption takes up to 255 characters');

        $long = str_repeat('Çox sevirəm səni, ', 10);   // 180 characters
        $this->actingAs(User::factory()->create())->post(route('cart.add'), [
            'product_id' => $box->id, 'custom_texts' => ['Salam', $long],
        ])->assertSessionHasNoErrors();
    }

    public function test_a_one_line_slot_stays_a_plain_field(): void
    {
        $box = $this->box(1, 'Leaving');

        $this->get(route('products.customize', $box->slug))
            ->assertOk()
            ->assertSee('<input type="text" class="text-input"', false)
            ->assertDontSee('<textarea class="text-input"', false);
    }
}
