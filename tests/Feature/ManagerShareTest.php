<?php

namespace Tests\Feature;

use App\Filament\Resources\FontResource\Pages\CreateFont;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\Chocolate;
use App\Models\DeliveryMethod;
use App\Models\Font;
use App\Models\Product;
use App\Models\User;
use App\Support\Accounting;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ManagerShareTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['name' => 'Cəmil', 'role' => User::ADMIN]);
        $this->actingAs($this->owner);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function sale(): void
    {
        $box = Product::create(['name' => 'Test', 'slug' => 'test-' . uniqid(), 'is_active' => true, 'price' => 20,
            'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/' . $box->id . '/bg.webp', 'x' => 0, 'y' => 0,
            'width' => 969, 'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);
        $bar = Chocolate::create(['name' => 'Milka 90 q', 'base_price' => 3]);
        $customer = User::factory()->create();
        $this->actingAs($customer)->post(route('cart.add'), ['product_id' => $box->id, 'chocolate_id' => $bar->id]);
        $this->actingAs($customer)->post(route('checkout.store'), [
            'delivery_method_id' => DeliveryMethod::where('type', 'door')->value('id'),
            'contact_phone' => '1', 'delivery_address' => 'Bakı, Nizami küç. 5',
        ]);
        $this->actingAs($this->owner);
    }

    public function test_the_admin_gives_a_share_with_the_role_and_it_cannot_pass_100(): void
    {
        $vuqar = User::factory()->create(['name' => 'Vüqar']);
        $idris = User::factory()->create(['name' => 'İdris']);

        Livewire::test(ListUsers::class)
            ->callTableAction('role', $vuqar, ['role' => User::MANAGER, 'profit_percent' => 30])
            ->assertHasNoTableActionErrors();
        $this->assertSame([User::MANAGER, 30.0], [$vuqar->fresh()->role, $vuqar->fresh()->profit_percent]);

        // 30 % is taken; 80 % more would make 110.
        Livewire::test(ListUsers::class)
            ->callTableAction('role', $idris, ['role' => User::MANAGER, 'profit_percent' => 80])
            ->assertHasTableActionErrors(['profit_percent']);
        $this->assertSame(0.0, $idris->fresh()->profit_percent);

        // Back to customer: the share goes with the role.
        Livewire::test(ListUsers::class)
            ->callTableAction('role', $vuqar, ['role' => User::CUSTOMER]);
        $this->assertSame(0.0, $vuqar->fresh()->profit_percent);
    }

    public function test_the_books_split_the_profit_by_the_shares_on_the_accounts(): void
    {
        $this->owner->forceFill(['profit_percent' => 60])->save();
        $vuqar = User::factory()->create(['name' => 'Vüqar', 'role' => User::MANAGER]);
        $vuqar->forceFill(['profit_percent' => 40])->save();
        $this->sale();

        $r = Accounting::report();
        $shares = collect($r['shares'])->keyBy('name');

        $this->assertSame([$this->owner->id, $vuqar->id], [$shares['Cəmil']['user_id'], $shares['Vüqar']['user_id']]);
        $this->assertEqualsWithDelta($r['net'] * 0.6, $shares['Cəmil']['amount'], 0.01);
        $this->assertEqualsWithDelta($r['net'] * 0.4, $shares['Vüqar']['amount'], 0.01);
    }

    public function test_a_manager_sees_their_own_balance_and_nothing_else(): void
    {
        $vuqar = User::factory()->create(['name' => 'Vüqar', 'role' => User::MANAGER]);
        $vuqar->forceFill(['profit_percent' => 25])->save();
        $this->sale();
        $mine = collect(Accounting::report(now()->startOfMonth(), now()->endOfMonth())['shares'])->firstWhere('user_id', $vuqar->id);

        $this->actingAs($vuqar)->get('/admin/balance')
            ->assertOk()
            ->assertSee('Balansım')
            ->assertSee('Sizin payınız · 25%')
            ->assertSee(\App\Support\Price::format($mine['amount']))
            ->assertDontSee('Kassa')
            ->assertDontSee('Maya dəyəri')
            ->assertDontSee('Cəmil');

        // A manager without a share is told who hands them out.
        $this->actingAs(User::factory()->create(['role' => User::MANAGER]))->get('/admin/balance')
            ->assertOk()->assertSee('Sizə hələ mənfəətdən pay verilməyib');
    }

    public function test_the_admin_sees_and_adds_fonts(): void
    {
        Storage::fake('public');
        $this->get('/admin/fonts')->assertOk()->assertSee('Şriftlər');

        // A WOFF2 file starts with "wOF2".
        $file = UploadedFile::fake()->createWithContent('brand.woff2', 'wOF2' . str_repeat("\0", 60));
        Livewire::test(CreateFont::class)
            ->fillForm(['name' => 'Brend Bold', 'weight' => 700, 'file' => $file])
            ->call('create')
            ->assertHasNoFormErrors();

        $font = Font::where('name', 'Brend Bold')->firstOrFail();
        $this->assertSame(['NF Brend Bold', 700], [$font->family, (int) $font->weight]);
        Storage::disk('public')->assertExists($font->file);
        $this->get('/admin/fonts')->assertSee('Brend Bold');

        $this->actingAs(User::factory()->create(['role' => User::MANAGER]))->get('/admin/fonts')->assertForbidden();
    }
}
