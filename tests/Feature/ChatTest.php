<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\Setting;
use App\Support\ChatBot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The chat in the corner of the site: a visitor writes, the message goes to
 * the shop's Telegram, the shop answers by replying to it there, and the
 * answer comes back into that visitor's own window. A screenshot may travel
 * either way.
 */
class ChatTest extends TestCase
{
    use RefreshDatabase;

    private function openTheChat(): void
    {
        ChatBot::saveToken('123456:test-token');
        Setting::put(Setting::CHAT_CHAT, '-100999');
        Setting::put(Setting::CHAT_ENABLED, '1');
    }

    public function test_the_corner_is_empty_until_the_shop_opens_the_chat(): void
    {
        $this->get(route('home'))->assertOk()->assertDontSee('chat-open', false);

        $this->openTheChat();

        $this->get(route('home'))->assertOk()->assertSee('chat-open', false);
    }

    public function test_a_visitor_writes_and_the_shop_gets_it_on_telegram(): void
    {
        $this->openTheChat();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 77]])]);

        $this->postJson(route('chat.send'), ['body' => 'Salam, qutu neçəyədir?', 'page' => '/dizaynlar'])
            ->assertOk()->assertJson(['ok' => true]);

        $written = ChatMessage::sole();
        $this->assertSame(ChatMessage::VISITOR, $written->side);
        $this->assertSame('Salam, qutu neçəyədir?', $written->body);
        // The Telegram message is remembered, or an answer could never find its way back.
        $this->assertSame(77, (int) $written->tg_message_id);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && str_contains((string) ($request['text'] ?? ''), 'qutu neçəyədir'));
    }

    public function test_a_screenshot_goes_as_a_picture(): void
    {
        $this->openTheChat();
        Storage::fake('public');
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 5]])]);

        $this->post(route('chat.send'), [
            'body' => 'Belə olsun',
            'image' => UploadedFile::fake()->image('ekran.png', 400, 300),
        ])->assertOk();

        $written = ChatMessage::sole();
        $this->assertNotNull($written->image);
        Storage::disk('public')->assertExists($written->image);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendPhoto'));
    }

    public function test_the_shops_answer_comes_back_into_the_same_window(): void
    {
        $this->openTheChat();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 12]])]);

        $this->postJson(route('chat.send'), ['body' => 'Sabaha hazır olar?'])->assertOk();
        $asked = ChatMessage::sole();

        // Telegram knocks with what the shop replied.
        $this->postJson(route('telegram.chat', ChatBot::hookSecret()), [
            'message' => [
                'text' => 'Bəli, sabah axşama hazırdır.',
                'reply_to_message' => ['message_id' => 12],
            ],
        ], ['X-Telegram-Bot-Api-Secret-Token' => ChatBot::hookSecret()])->assertOk();

        $answer = ChatMessage::where('side', ChatMessage::SHOP)->sole();
        $this->assertSame($asked->thread, $answer->thread);

        $this->getJson(route('chat.poll') . '?after=' . $asked->id)
            ->assertOk()
            ->assertJsonPath('messages.0.body', 'Bəli, sabah axşama hazırdır.')
            ->assertJsonPath('messages.0.side', ChatMessage::SHOP);
    }

    public function test_a_stranger_cannot_knock_at_the_hook(): void
    {
        $this->openTheChat();

        $this->postJson(route('telegram.chat', str_repeat('a', 32)), ['message' => []])->assertNotFound();

        // The right address, but not Telegram's own word for it.
        $this->postJson(route('telegram.chat', ChatBot::hookSecret()), ['message' => []],
            ['X-Telegram-Bot-Api-Secret-Token' => 'nope'])->assertNotFound();
    }

    public function test_one_visitor_never_reads_another_ones_thread(): void
    {
        $this->openTheChat();
        ChatMessage::create(['thread' => 'someone-else', 'side' => ChatMessage::SHOP, 'body' => 'Özgə söhbəti']);

        $this->getJson(route('chat.poll'))->assertOk()->assertJsonCount(0, 'messages');
    }

    public function test_nothing_is_taken_while_the_chat_is_closed(): void
    {
        $this->postJson(route('chat.send'), ['body' => 'Salam'])->assertNotFound();
        $this->assertSame(0, ChatMessage::count());
    }
}
