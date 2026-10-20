<?php

namespace Tests\Feature;

use App\Filament\Pages\WhatsAppInbox;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhatsAppChat;
use App\Models\WhatsAppMessage;
use App\Support\WhatsApp;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * WhatsApp inside the panel, so that answering a customer is no longer
 * something only the owner's own telephone can do.
 *
 * What these guard, in order of what it would cost to get wrong: that
 * nobody but Meta can write into the shop's conversations; that a message
 * repeated by Meta does not appear twice; that an answer sent after the
 * day WhatsApp allows is refused here rather than swallowed there; and that
 * a customer cannot reach any of it.
 */
class WhatsAppInTheAdminTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'app-secret-for-the-test';

    protected function setUp(): void
    {
        parent::setUp();
        Setting::put(Setting::WHATSAPP_ENABLED, true);
        Setting::put(Setting::WHATSAPP_PHONE_ID, '1234567890');
        WhatsApp::saveToken('a-permanent-token');
        WhatsApp::saveVerifyWord('a-word-we-chose');
        WhatsApp::saveAppSecret(self::SECRET);
    }

    /** A notice the way Meta sends one, signed the way Meta signs it. */
    private function fromMeta(array $body): \Illuminate\Testing\TestResponse
    {
        $json = json_encode($body);

        return $this->call('POST', route('whatsapp.hook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=' . hash_hmac('sha256', $json, self::SECRET),
        ], $json);
    }

    private function aMessage(string $id = 'wamid.1', string $text = 'Salam, qutu nə qədərdir?'): array
    {
        return ['entry' => [['changes' => [['value' => [
            'contacts' => [['wa_id' => '994551112233', 'profile' => ['name' => 'Aygün']]],
            'messages' => [[
                'from' => '994551112233',
                'id' => $id,
                'timestamp' => (string) now()->timestamp,
                'type' => 'text',
                'text' => ['body' => $text],
            ]],
        ]]]]]];
    }

    public function test_the_handshake_answers_only_with_the_word_it_was_given(): void
    {
        $this->get(route('whatsapp.verify', [
            'hub_mode' => 'subscribe', 'hub_verify_token' => 'a-word-we-chose', 'hub_challenge' => '31415',
        ]))->assertOk()->assertSee('31415');

        $this->get(route('whatsapp.verify', [
            'hub_mode' => 'subscribe', 'hub_verify_token' => 'somebody-elses-guess', 'hub_challenge' => '31415',
        ]))->assertForbidden();
    }

    public function test_a_notice_nobody_signed_is_refused(): void
    {
        $this->postJson(route('whatsapp.hook'), $this->aMessage())->assertForbidden();
        $this->assertSame(0, WhatsAppMessage::count(), 'and nothing is written');
    }

    public function test_a_notice_signed_with_the_wrong_secret_is_refused(): void
    {
        $json = json_encode($this->aMessage());

        $this->call('POST', route('whatsapp.hook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=' . hash_hmac('sha256', $json, 'not-the-secret'),
        ], $json)->assertForbidden();

        $this->assertSame(0, WhatsAppMessage::count());
    }

    public function test_a_question_becomes_a_conversation(): void
    {
        $this->fromMeta($this->aMessage())->assertOk();

        $chat = WhatsAppChat::firstOrFail();
        $this->assertSame('994551112233', $chat->wa_id);
        $this->assertSame('Aygün', $chat->name);
        $this->assertSame(1, $chat->unread);
        $this->assertTrue($chat->windowOpen(), 'she has just written, so the shop may answer');

        $line = WhatsAppMessage::firstOrFail();
        $this->assertSame('in', $line->direction);
        $this->assertSame('Salam, qutu nə qədərdir?', $line->body);
    }

    /** Meta says it again whenever our answer is slow. */
    public function test_the_same_message_said_twice_is_written_once(): void
    {
        $this->fromMeta($this->aMessage())->assertOk();
        $this->fromMeta($this->aMessage())->assertOk();

        $this->assertSame(1, WhatsAppMessage::count());
        $this->assertSame(1, (int) WhatsAppChat::firstOrFail()->unread, 'and she is not counted twice');
    }

    public function test_the_shop_answers_and_the_answer_is_signed_with_a_name(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.out.1']]], 200)]);
        $this->fromMeta($this->aMessage())->assertOk();

        $staff = User::factory()->create(['is_admin' => true, 'name' => 'Rəşid']);
        $chat = WhatsAppChat::firstOrFail();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::actingAs($staff)->test(WhatsAppInbox::class)
            ->set('openChat', $chat->id)
            ->set('draft', '8.50 ₼-dan başlayır')
            ->call('send');

        $answer = WhatsAppMessage::where('direction', 'out')->firstOrFail();
        $this->assertSame('8.50 ₼-dan başlayır', $answer->body);
        $this->assertSame('sent', $answer->status);
        $this->assertSame('wamid.out.1', $answer->wam_id);
        $this->assertSame($staff->id, $answer->user_id, 'the panel remembers who wrote it');
    }

    /**
     * WhatsApp swallows anything sent more than a day after the customer
     * last wrote. Better to say so here than to let the shop believe it
     * answered.
     */
    public function test_an_answer_after_the_day_is_over_is_not_even_attempted(): void
    {
        Http::fake();
        $this->fromMeta($this->aMessage())->assertOk();

        $chat = WhatsAppChat::firstOrFail();
        $chat->forceFill(['last_incoming_at' => now()->subHours(25)])->save();
        $this->assertFalse($chat->fresh()->windowOpen());

        $staff = User::factory()->create(['is_admin' => true]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::actingAs($staff)->test(WhatsAppInbox::class)
            ->set('openChat', $chat->id)
            ->set('draft', 'gec qalmış cavab')
            ->call('send');

        $this->assertSame(0, WhatsAppMessage::where('direction', 'out')->count());
        Http::assertNothingSent();
    }

    public function test_what_became_of_an_answer_is_written_down(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.out.9']]], 200)]);
        $this->fromMeta($this->aMessage())->assertOk();

        $staff = User::factory()->create(['is_admin' => true]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::actingAs($staff)->test(WhatsAppInbox::class)
            ->set('openChat', WhatsAppChat::firstOrFail()->id)
            ->set('draft', 'bir dəqiqə')
            ->call('send');

        $this->fromMeta(['entry' => [['changes' => [['value' => [
            'statuses' => [['id' => 'wamid.out.9', 'status' => 'read']],
        ]]]]]])->assertOk();

        $this->assertSame('read', WhatsAppMessage::where('wam_id', 'wamid.out.9')->value('status'));
    }

    /** «read» must not fall back to «delivered» when a late notice arrives. */
    public function test_a_late_notice_does_not_walk_the_answer_backwards(): void
    {
        WhatsAppMessage::create([
            'wa_id' => '994551112233', 'direction' => 'out', 'wam_id' => 'wamid.out.7',
            'type' => 'text', 'body' => 'salam', 'status' => 'read',
        ]);

        $this->fromMeta(['entry' => [['changes' => [['value' => [
            'statuses' => [['id' => 'wamid.out.7', 'status' => 'delivered']],
        ]]]]]])->assertOk();

        $this->assertSame('read', WhatsAppMessage::where('wam_id', 'wamid.out.7')->value('status'));
    }

    public function test_a_customer_cannot_reach_the_conversations(): void
    {
        $customer = User::factory()->create(['is_admin' => false]);

        $this->actingAs($customer);
        $this->assertFalse(WhatsAppInbox::canAccess(), 'the page refuses him');
        $this->get('/admin/whatsapp')->assertForbidden();

        $this->post(route('whatsapp.hook'))->assertForbidden();   // nor the hook
    }

    public function test_the_page_is_not_offered_until_it_is_connected(): void
    {
        Setting::put(Setting::WHATSAPP_ENABLED, false);
        $this->assertFalse(WhatsApp::enabled());

        $staff = User::factory()->create(['is_admin' => true]);
        $this->actingAs($staff)->get('/admin/whatsapp')->assertOk()
            ->assertSee('hələ qoşulmayıb', false);
    }
}
