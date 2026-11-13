<?php

namespace Tests\Feature;

use App\Filament\Resources\OrderResource\Pages\CreateOrder;
use App\Models\Order;
use App\Models\PhotoSlot;
use App\Models\Product;
use App\Models\TextSlot;
use App\Models\User;
use App\Support\PhoneOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A customer writes on WhatsApp and has no time for the site: the owner puts
 * the name, the number, the photograph and the words into the panel himself.
 */
class AnOrderFromWhatsAppTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['role' => User::ADMIN, 'is_admin' => true]);
    }

    private function box(): Product
    {
        $box = Product::create(['name' => 'Alyonka', 'slug' => 'alyonka-'.uniqid(), 'is_active' => true, 'price' => 12]);
        $box->photoSlots()->create(['label' => 'Üz', 'fill' => PhotoSlot::PHOTO]);
        $box->textSlots()->create(['label' => 'Ad']);

        return $box->fresh();
    }

    public function test_the_page_opens_for_the_owner(): void
    {
        $this->actingAs($this->owner())->get('/admin/orders/create')->assertOk()->assertSee('Müştəri');
    }

    public function test_the_order_is_written_down_with_the_photo_and_the_words(): void
    {
        Storage::fake('public');
        $box = $this->box();

        Livewire::actingAs($this->owner())
            ->test(CreateOrder::class)
            ->fillForm(['name' => 'Aydan', 'phone' => '055 123 45 67', 'product_id' => $box->id, 'quantity' => 2])
            ->set('data.photo_0', [UploadedFile::fake()->image('face.jpg')])
            ->fillForm(['text_0' => 'Aydan'])
            ->call('create')
            ->assertHasNoFormErrors();

        $order = Order::with('items', 'user')->firstOrFail();
        $line = $order->items->first();

        $this->assertSame('Aydan', $order->user->name);
        $this->assertSame('+994 55 123 45 67', $order->contact_phone);
        $this->assertSame('confirmed', $order->status);
        $this->assertNotNull($order->payment_confirmed_at);
        $this->assertSame(2, $line->quantity);
        $this->assertSame(['Aydan'], $line->custom_texts);
        $this->assertCount(1, $line->customer_photos);
        Storage::disk('public')->assertExists($line->customer_photos[0]);
        $this->assertSame(24.0, $order->total());
    }

    public function test_the_same_number_finds_the_same_customer(): void
    {
        $first = PhoneOrder::customer('Aydan', '+994 55 123 45 67');
        $again = PhoneOrder::customer('Başqa ad', '0551234567');

        $this->assertTrue($first->is($again));
        $this->assertTrue(PhoneOrder::isPlaceholder($first->email));
        $this->assertSame(1, User::where('phone', '+994 55 123 45 67')->count());
    }

    public function test_a_number_that_is_no_number_is_refused(): void
    {
        $box = $this->box();

        Livewire::actingAs($this->owner())
            ->test(CreateOrder::class)
            ->fillForm(['name' => 'Aydan', 'phone' => '123', 'product_id' => $box->id])
            ->call('create')
            ->assertHasFormErrors(['phone']);

        $this->assertSame(0, Order::count());
    }
}
