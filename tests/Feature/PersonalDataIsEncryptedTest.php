<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\ContactMessage;
use App\Models\CorporateRequest;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * What the database file holds where a person's details used to be.
 *
 * Two halves have to stay true at once: the shop must still read every one
 * of these, and the file must not. The second half is what a test is for —
 * a cast quietly dropped from a model breaks nothing visible, and nobody
 * finds out until a copy of the database is somewhere it should not be.
 *
 * The other half matters just as much: the columns the shop looks things up
 * by must stay readable, or signing in stops working and the owner can no
 * longer find an order by a customer's name.
 */
class PersonalDataIsEncryptedTest extends TestCase
{
    use RefreshDatabase;

    /** The value as it really sits in the file, cast and model bypassed. */
    private function raw(string $table, int $id, string $column): ?string
    {
        $value = DB::table($table)->where('id', $id)->value($column);

        return $value === null ? null : (string) $value;
    }

    private function unreadable(string $table, int $id, string $column, string $plain): void
    {
        $stored = $this->raw($table, $id, $column);

        $this->assertNotNull($stored, "$table.$column was not written at all");
        $this->assertStringNotContainsString(
            $plain,
            $stored,
            "$table.$column is still readable in the database"
        );
        // Laravel's envelope: base64 of a JSON object carrying iv, value, mac.
        $this->assertNotNull(
            json_decode((string) base64_decode($stored, true), true),
            "$table.$column does not look like ciphertext"
        );
    }

    public function test_the_order_says_nothing_about_where_it_went(): void
    {
        $user = User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id,
            'status' => 'completed',
            'locale' => 'az',
            'contact_phone' => '+994555555555',
            'recipient_name' => 'Aysel Məmmədova',
            'delivery_address' => 'Nizami küç. 203, mənzil 14',
            'postal_index' => 'AZ1000',
            'metro_station' => 'Cəfər Cabbarlı',
            'note' => 'Zəng etməyin, qapını döyün',
            'courier_name' => 'Elvin',
        ]);

        foreach ([
            'contact_phone' => '+994555555555',
            'recipient_name' => 'Aysel Məmmədova',
            'delivery_address' => 'Nizami küç. 203, mənzil 14',
            'postal_index' => 'AZ1000',
            'metro_station' => 'Cəfər Cabbarlı',
            'note' => 'Zəng etməyin, qapını döyün',
            'courier_name' => 'Elvin',
        ] as $column => $plain) {
            $this->unreadable('orders', $order->id, $column, $plain);
        }

        // And the shop reads every one of them back unchanged.
        $fresh = Order::find($order->id);
        $this->assertSame('+994555555555', $fresh->contact_phone);
        $this->assertSame('Nizami küç. 203, mənzil 14', $fresh->delivery_address);
        $this->assertSame('Zəng etməyin, qapını döyün', $fresh->note);
        $this->assertSame('Cəfər Cabbarlı', $fresh->metro_station);
    }

    public function test_the_money_on_the_order_stays_plain_so_the_books_can_add_it_up(): void
    {
        $user = User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id, 'status' => 'completed', 'locale' => 'az',
            'delivery_price' => 5, 'rush_fee' => 3, 'materials_cost' => 1.25,
        ]);

        // Encrypting a number would put the accounting screens out of action:
        // they add these up in SQL.
        $this->assertSame('5', (string) $this->raw('orders', $order->id, 'delivery_price'));
        $this->assertSame('3', (string) $this->raw('orders', $order->id, 'rush_fee'));
    }

    public function test_the_chat_does_not_keep_who_wrote_in_the_clear(): void
    {
        $line = ChatMessage::create([
            'thread' => 'abc123', 'side' => ChatMessage::VISITOR,
            'body' => 'Salam', 'name' => 'Könül', 'phone' => '+994555555555',
        ]);

        $this->unreadable('chat_messages', $line->id, 'name', 'Könül');
        $this->unreadable('chat_messages', $line->id, 'phone', '+994555555555');

        $this->assertSame('Könül', ChatMessage::find($line->id)->name);
    }

    public function test_what_is_still_readable_is_readable_on_purpose(): void
    {
        /* The shop signs people in by e-mail and by number, and the owner
           finds an order by typing a customer's name. SQL cannot match what
           it cannot read, so these three stay as they are — deliberately,
           and this test is the record of that decision. */
        $user = User::factory()->create([
            'name' => 'Könül Məmmədova',
            'email' => 'konul@example.com',
            'phone' => '+994555555555',
        ]);

        $this->assertSame('Könül Məmmədova', $this->raw('users', $user->id, 'name'));
        $this->assertSame('konul@example.com', $this->raw('users', $user->id, 'email'));
        $this->assertSame('+994555555555', $this->raw('users', $user->id, 'phone'));

        // And the lookups they exist for still work.
        $this->assertTrue(User::samePhone('+994555555555')->exists());
        $this->assertTrue(User::where('email', 'konul@example.com')->exists());
    }

    public function test_a_corporate_request_hides_the_person_and_keeps_the_company(): void
    {
        $request = CorporateRequest::create([
            'company' => 'Kapital Bank', 'person' => 'Elvin Əliyev',
            'phone' => '+994555555555', 'quantity' => 300,
            'note' => '300 qutu, yeni il üçün',
        ]);

        $this->unreadable('corporate_requests', $request->id, 'person', 'Elvin Əliyev');
        $this->unreadable('corporate_requests', $request->id, 'note', '300 qutu');

        // Searched in the admin, so left alone.
        $this->assertSame('Kapital Bank', $this->raw('corporate_requests', $request->id, 'company'));
    }

    public function test_a_review_hides_the_name_and_keeps_the_words(): void
    {
        $user = User::factory()->create();
        $order = Order::create(['user_id' => $user->id, 'status' => 'completed', 'locale' => 'az']);
        $review = Review::create([
            'order_id' => $order->id, 'user_id' => $user->id, 'stars' => 5,
            'shown_name' => 'Könül M.', 'body' => 'Qutu çox gözəl çıxdı',
        ]);

        $this->unreadable('reviews', $review->id, 'shown_name', 'Könül M.');
        // The words are searched in the admin.
        $this->assertSame('Qutu çox gözəl çıxdı', $this->raw('reviews', $review->id, 'body'));
        $this->assertSame('Könül M.', Review::find($review->id)->shown_name);
    }

    public function test_the_note_the_shop_writes_to_itself_is_hidden(): void
    {
        $letter = ContactMessage::create([
            'name' => 'Könül', 'phone' => '+994555555555',
            'message' => 'Sifarişim haradadır?', 'note' => 'Zəng etdim, həll olundu',
        ]);

        $this->unreadable('contact_messages', $letter->id, 'note', 'Zəng etdim');
        $this->assertSame('Zəng etdim, həll olundu', ContactMessage::find($letter->id)->note);
    }
}
