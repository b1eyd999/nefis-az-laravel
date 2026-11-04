<?php

namespace Tests\Feature;

use App\Filament\Resources\CartHandoffResource;
use App\Filament\Resources\CartHandoffResource\Pages\EditCartHandoff;
use App\Models\CartHandoff;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A ready basket can be opened, changed and added to.
 *
 * It could only be looked at from the outside before: the list said how many
 * pieces and what it came to, and the one way to learn what was actually in
 * it was to open the customer's own link. The owner asked to get inside.
 *
 * The part worth guarding is what a line carries. A basket's line holds far
 * more than it shows — the frames a photograph sits in, a star map's date
 * and place, a song, a wrapping — and the form knows only the quantity. Save
 * it carelessly and the rest is gone, with nothing to say so.
 */
class TheReadyBasketCanBeOpenedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function design(string $name = 'Lokasiya', bool $withSlot = false): Product
    {
        $box = Product::create(['name' => $name, 'slug' => \Illuminate\Support\Str::slug($name) . '-' . uniqid(),
            'is_active' => true, 'price' => 20, 'template_width' => 3508, 'template_height' => 2480]);

        if ($withSlot) {
            $box->photoSlots()->create(['label' => 'Üz şəkli', 'x' => 700, 'y' => 700, 'width' => 400,
                'height' => 400, 'rotation' => 0, 'shape' => 'rectangle', 'cutout' => false, 'sort_order' => 0]);
            $box->textSlots()->create(['label' => 'Ad', 'x' => 700, 'y' => 1200, 'width' => 400, 'height' => 80,
                'rotation' => 0, 'size' => 40, 'color' => '#000000', 'align' => 'center', 'sort_order' => 0]);
        }

        return $box;
    }

    /** A line with everything on it that a real one carries. */
    private function basket(Product $box): CartHandoff
    {
        return CartHandoff::create([
            'token' => \Illuminate\Support\Str::random(40),
            'note' => 'Bayram bəy',
            'items' => [[
                'id' => 'line-1',
                'product_id' => $box->id,
                'photo_paths' => ['cart-photos/one.webp'],
                'custom_texts' => ['Ad günün mübarək'],
                'quantity' => 2,
                'photo_labels' => ['1. Üz şəkli'],
                /* Shaped the way the design page writes it, not as a list of
                   names. A fixture that said `['Ad']` is why this screen went
                   live able to open only the baskets a test had made. */
                'text_labels' => [['label' => 'Ad', 'fixed' => false, 'repeat' => false]],
                'photo_frames' => ['circle'],
                'star' => ['date' => '2020-08-14', 'place' => 'Bakı'],
                'spot' => null,
                'chocolate' => ['name' => 'Milka'],
                'wrapping' => null,
                'letter' => null,
                'ar' => null,
                'spotify' => 'spotify:track:1',
                'bundle' => null,
            ]],
            'rush' => false,
            'expires_at' => now()->addDays(21),
        ]);
    }

    public function test_the_owner_opens_a_basket_and_sees_what_is_in_it(): void
    {
        $box = $this->design('Ulduz xəritəsi');
        $basket = $this->basket($box);

        $this->get(CartHandoffResource::getUrl('edit', ['record' => $basket]))
            ->assertOk()
            ->assertSee('Ulduz xəritəsi')
            ->assertSee('Ad günün mübarək')
            ->assertSee('Bayram bəy');
    }

    /**
     * A basket the shop itself made opens, not only one a test wrote.
     *
     * The names of the lines of writing are kept as
     * `['label' => …, 'fixed' => …, 'repeat' => …]` — that is what the design
     * page writes and what the order screen reads. Printed straight, the
     * page answered «500 Server Error» for every real basket in the shop,
     * and the test suite said nothing because its own fixture held a list of
     * plain names that the shop has never written.
     */
    public function test_a_basket_built_the_way_the_shop_builds_them_opens(): void
    {
        $box = $this->design('Ulduz xəritəsi', withSlot: true);
        $box->textSlots()->create(['label' => 'Tarix', 'x' => 700, 'y' => 1400, 'width' => 400,
            'height' => 80, 'rotation' => 0, 'size' => 40, 'color' => '#000000',
            'align' => 'center', 'sort_order' => 1]);

        $basket = CartHandoff::create([
            'token' => \Illuminate\Support\Str::random(40),
            'items' => [[
                'id' => 'line-1',
                'product_id' => $box->id,
                'photo_paths' => [],
                'custom_texts' => ['Bakı', '14 avqust 2020'],
                'quantity' => 1,
                'photo_labels' => \App\Models\OrderItem::photoLabelsFor($box),
                'text_labels' => \App\Models\OrderItem::textLabelsFor($box),
                'photo_frames' => [],
                'star' => null, 'spot' => null, 'chocolate' => null, 'wrapping' => null,
                'letter' => null, 'ar' => null, 'spotify' => null, 'bundle' => null,
            ]],
            'rush' => false,
        ]);

        $this->get(CartHandoffResource::getUrl('edit', ['record' => $basket]))
            ->assertOk()
            ->assertSee('Bakı');

        /* And each caption is under its own name. Read on the page itself
           this proves nothing: «Ad» is a part of half the words on an
           Azerbaijani admin screen, so a line that had quietly fallen back
           to «Yazı» would still have passed. */
        $html = (string) CartHandoffResource::lineDetail('line-1', $basket->items);

        $this->assertStringContainsString('<b>Ad:</b> Bakı', $html);
        $this->assertStringContainsString('<b>Tarix:</b> 14 avqust 2020', $html);
        $this->assertStringNotContainsString('Yazı', $html);
    }

    public function test_the_list_offers_a_way_in(): void
    {
        $basket = $this->basket($this->design());

        $this->get(CartHandoffResource::getUrl('index'))
            ->assertOk()
            ->assertSee(CartHandoffResource::getUrl('edit', ['record' => $basket]));
    }

    /**
     * Saving the quantity keeps everything else on the line.
     *
     * This is the whole reason the save is written by hand: the form holds
     * two fields, the line holds fifteen, and replacing one with the other
     * would quietly throw away the star map, the song and the chocolate.
     */
    public function test_changing_the_number_keeps_the_rest_of_the_line(): void
    {
        $basket = $this->basket($this->design());

        $page = Livewire::test(EditCartHandoff::class, ['record' => $basket->getKey()]);

        /* A repeater keys its rows by a uuid of its own, not by 0, 1, 2 — so
           the row is found rather than guessed at. */
        $key = array_key_first($page->get('data.items'));

        $page->set('data.items.' . $key . '.quantity', 5)
            ->call('save')
            ->assertHasNoErrors();

        $line = $basket->refresh()->items[0];

        $this->assertSame(5, $line['quantity']);
        $this->assertSame(['cart-photos/one.webp'], $line['photo_paths']);
        $this->assertSame(['Ad günün mübarək'], $line['custom_texts']);
        $this->assertSame(['circle'], $line['photo_frames']);
        $this->assertSame('Bakı', $line['star']['place']);
        $this->assertSame('Milka', $line['chocolate']['name']);
        $this->assertSame('spotify:track:1', $line['spotify']);
    }

    public function test_a_line_taken_out_is_gone_and_the_others_stay(): void
    {
        $box = $this->design();
        $basket = $this->basket($box);
        $basket->addItem($box->id, 3);
        $this->assertCount(2, $basket->refresh()->items);

        $second = $basket->items[1]['id'];

        Livewire::test(EditCartHandoff::class, ['record' => $basket->getKey()])
            ->set('data.items', [['id' => $second, 'quantity' => 3]])
            ->call('save')
            ->assertHasNoErrors();

        $items = $basket->refresh()->items;

        $this->assertCount(1, $items);
        $this->assertSame($second, $items[0]['id']);
    }

    /**
     * The line is rebuilt from the record, not from what was submitted.
     *
     * The repeater happens to carry every key of a line in its own state
     * today, so taking the form's word for it would look right — until a
     * row reaches `save` with only the two fields the form actually edits,
     * which is what a hand-made request sends and what a future repeater
     * may well send. Then the star map, the song and the chocolate are
     * gone, and nothing anywhere says so. Hence the merge, and hence this.
     */
    public function test_a_bare_row_still_saves_the_whole_line(): void
    {
        $basket = $this->basket($this->design());

        Livewire::test(EditCartHandoff::class, ['record' => $basket->getKey()])
            // Exactly the two fields the form owns, and nothing else.
            ->set('data.items', [['id' => 'line-1', 'quantity' => 7]])
            ->call('save')
            ->assertHasNoErrors();

        $line = $basket->refresh()->items[0];

        $this->assertSame(7, $line['quantity']);
        $this->assertSame(['cart-photos/one.webp'], $line['photo_paths']);
        $this->assertSame(['circle'], $line['photo_frames']);
        $this->assertSame('Bakı', $line['star']['place']);
        $this->assertSame('Milka', $line['chocolate']['name']);
        $this->assertSame('spotify:track:1', $line['spotify']);
        $this->assertSame(['Ad günün mübarək'], $line['custom_texts']);
    }

    // --------------------------------------------------------- adding one

    /**
     * Choosing a design puts its photograph fields on the form.
     *
     * Left to the repeater's own `default()` this looked right and did
     * nothing: a default is read once, when the form is first filled, and at
     * that moment no design has been chosen. The owner picked a design that
     * needs a picture, saw no field to put one in, and the guard below then
     * refused the line — the design simply could not be added.
     */
    public function test_choosing_a_design_puts_its_photograph_fields_there(): void
    {
        $basket = $this->basket($this->design());
        $box = $this->design('İki pəncərə', withSlot: true);
        $box->photoSlots()->create(['label' => 'Arxa şəkli', 'x' => 1400, 'y' => 700, 'width' => 400,
            'height' => 400, 'rotation' => 0, 'shape' => 'rectangle', 'cutout' => false, 'sort_order' => 1]);

        $page = Livewire::test(EditCartHandoff::class, ['record' => $basket->getKey()])
            ->mountAction('add');

        // Set through Livewire, the way the browser does, so the design's
        // own `afterStateUpdated` runs.
        $page->set('mountedActionsData.0.product_id', $box->id);

        $data = $page->get('mountedActionsData')[0];

        $this->assertSame(['1. Üz şəkli', '2. Arxa şəkli'], array_column($data['photos'], 'label'));
        $this->assertSame(['Ad'], array_column($data['texts'], 'label'));

        /* And they are still named after the next thing he touches. The name
           of a slot is not a field the owner fills in, so unless the form
           carries it as one of its own it is dropped the next time the page
           talks to the server — and both upload boxes end up called «Şəkil». */
        $page->set('mountedActionsData.0.quantity', 3);

        $this->assertSame(
            ['1. Üz şəkli', '2. Arxa şəkli'],
            array_column($page->get('mountedActionsData')[0]['photos'], 'label')
        );
    }

    /**
     * A window the night sky fills is not a window to upload into.
     *
     * It asks the customer for nothing, so it is not on the shop's list of
     * photographs either. Counted here it would both demand a picture that
     * does not exist and push every name one place along the line.
     */
    public function test_a_window_filled_by_the_sky_is_not_asked_for(): void
    {
        $basket = $this->basket($this->design());
        $box = $this->design('Göy üzü', withSlot: true);
        $box->photoSlots()->create(['label' => 'Ulduzlar', 'x' => 1400, 'y' => 700, 'width' => 400,
            'height' => 400, 'rotation' => 0, 'shape' => 'rectangle', 'cutout' => false,
            'sort_order' => 1, 'fill' => 'sky']);

        $page = Livewire::test(EditCartHandoff::class, ['record' => $basket->getKey()])
            ->mountAction('add');
        $page->set('mountedActionsData.0.product_id', $box->id);

        $this->assertSame(
            ['1. Üz şəkli'],
            array_column($page->get('mountedActionsData')[0]['photos'], 'label')
        );
    }

    /**
     * The line's own names are written the way the rest of the shop reads
     * them.
     *
     * The basket is handed to a customer, becomes an order, and is read from
     * then on by the checkout and the order screen — both of which take
     * `text_labels` apart as `['label' => …, 'fixed' => …, 'repeat' => …]`.
     * A list of plain names there is a fault that only shows up later, in a
     * place far from this screen.
     */
    public function test_the_added_line_names_its_fields_the_way_the_shop_does(): void
    {
        $basket = $this->basket($this->design());
        $box = $this->design('Adlı qutu', withSlot: true);

        Livewire::test(EditCartHandoff::class, ['record' => $basket->getKey()])
            ->callAction('add', [
                'product_id' => $box->id,
                'quantity' => 1,
                'photos' => [['label' => '1. Üz şəkli', 'file' => ['k' => 'cart-photos/x.webp']]],
                'texts' => [['label' => 'Ad', 'fixed' => false, 'value' => 'Leyla']],
            ]);

        $line = $basket->refresh()->items[1];

        $this->assertSame(\App\Models\OrderItem::textLabelsFor($box), $line['text_labels']);
        $this->assertSame(\App\Models\OrderItem::photoLabelsFor($box), $line['photo_labels']);
        $this->assertSame(['Leyla'], $line['custom_texts']);
    }

    /**
     * A caption left empty keeps its place in the line.
     *
     * The words and the names beside them are two lists read by position.
     * Drop the blank one and every caption after it is printed under the
     * name of the one before.
     */
    public function test_an_empty_caption_keeps_its_place(): void
    {
        $basket = $this->basket($this->design());
        $box = $this->design('İki yazı', withSlot: true);
        $box->textSlots()->create(['label' => 'Tarix', 'x' => 700, 'y' => 1400, 'width' => 400,
            'height' => 80, 'rotation' => 0, 'size' => 40, 'color' => '#000000',
            'align' => 'center', 'sort_order' => 1]);

        Livewire::test(EditCartHandoff::class, ['record' => $basket->getKey()])
            ->callAction('add', [
                'product_id' => $box->id,
                'quantity' => 1,
                'photos' => [['label' => '1. Üz şəkli', 'file' => ['k' => 'cart-photos/x.webp']]],
                'texts' => [
                    ['label' => 'Ad', 'fixed' => false, 'value' => ''],
                    ['label' => 'Tarix', 'fixed' => false, 'value' => '14.08.2020'],
                ],
            ]);

        $this->assertSame(['', '14.08.2020'], $basket->refresh()->items[1]['custom_texts']);
    }

    public function test_a_design_is_added_to_the_basket(): void
    {
        $basket = $this->basket($this->design());
        $extra = $this->design('Pampers');

        Livewire::test(EditCartHandoff::class, ['record' => $basket->getKey()])
            ->callAction('add', ['product_id' => $extra->id, 'quantity' => 4])
            ->assertHasNoActionErrors();

        $items = $basket->refresh()->items;

        $this->assertCount(2, $items);
        $this->assertSame($extra->id, $items[1]['product_id']);
        $this->assertSame(4, $items[1]['quantity']);
    }

    /**
     * A design that needs a photograph cannot be put in without one.
     *
     * There is nothing downstream to mend it with: the customer's own basket
     * page lists his lines and lets him change how many, and offers no way
     * to open one and add a picture. A box put in blank here would be
     * printed blank.
     */
    public function test_a_design_that_wants_a_photograph_will_not_go_in_without_one(): void
    {
        $basket = $this->basket($this->design());
        $needsPhoto = $this->design('Lokasiya HOME', withSlot: true);

        Livewire::test(EditCartHandoff::class, ['record' => $basket->getKey()])
            ->callAction('add', ['product_id' => $needsPhoto->id, 'quantity' => 1])
            ->assertHasActionErrors(['photos.0.file']);

        $this->assertCount(1, $basket->refresh()->items);
    }

    /**
     * And not even when the upload field was never on the form.
     *
     * The field only exists once a design has been chosen, so a submission
     * that reached the action without it — a hand-made request, or a form
     * whose rows were set rather than typed — would otherwise walk past the
     * required field and put a blank box in. The same check stands in the
     * action itself.
     */
    public function test_the_guard_also_stands_where_the_field_never_appeared(): void
    {
        $basket = $this->basket($this->design());
        $needsPhoto = $this->design('Lokasiya EV', withSlot: true);

        Livewire::test(EditCartHandoff::class, ['record' => $basket->getKey()])
            ->mountAction('add')
            // Set, not chosen: the design's own hook never runs, so no
            // upload row is ever put on the form.
            ->setActionData(['product_id' => $needsPhoto->id, 'quantity' => 1, 'photos' => [], 'texts' => []])
            ->callMountedAction();

        \Filament\Notifications\Notification::assertNotified('Şəkil lazımdır');
        $this->assertCount(1, $basket->refresh()->items);
    }

    /**
     * A line put in here is shaped exactly as the cart shapes its own.
     *
     * The basket is handed to a customer and from that moment the shop's
     * ordinary cart reads it. A line missing a key the cart expects would
     * break at the checkout, a long way from this screen.
     */
    public function test_the_added_line_carries_every_key_the_cart_expects(): void
    {
        $basket = $this->basket($this->design());
        $basket->addItem($this->design('Netflix')->id, 1);

        foreach (['id', 'product_id', 'photo_paths', 'custom_texts', 'quantity', 'photo_labels',
            'text_labels', 'photo_frames', 'star', 'spot', 'chocolate', 'wrapping', 'letter',
            'ar', 'spotify', 'bundle'] as $key) {
            $this->assertArrayHasKey($key, $basket->refresh()->items[1], $key . ' is missing from a new line');
        }
    }

    public function test_the_total_and_the_count_follow_what_was_added(): void
    {
        $box = $this->design();
        $basket = $this->basket($box);
        $was = $basket->pieces();

        $basket->addItem($box->id, 3);

        $this->assertSame($was + 3, $basket->refresh()->pieces());
        $this->assertGreaterThan(0, $basket->total());
    }

    /** A design taken off the shelf since is shown, not hidden. */
    public function test_a_design_that_is_gone_is_still_listed(): void
    {
        $box = $this->design();
        $basket = $this->basket($box);
        $box->delete();

        $lines = $basket->refresh()->lines();

        $this->assertCount(1, $lines);
        $this->assertTrue($lines[0]['gone']);
        $this->assertSame(0.0, $lines[0]['sum']);
        $this->assertSame('Silinmiş dizayn × 2', CartHandoffResource::lineTitle($basket->items[0]));
    }

    public function test_a_manager_does_not_get_into_the_baskets(): void
    {
        $basket = $this->basket($this->design());
        $this->actingAs(User::factory()->create(['is_admin' => false, 'role' => User::MANAGER]));

        $this->get(CartHandoffResource::getUrl('edit', ['record' => $basket]))->assertForbidden();
    }
}
