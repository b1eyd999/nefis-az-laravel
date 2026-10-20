<?php

namespace App\Filament\Pages;

use App\Models\WhatsAppChat;
use App\Models\WhatsAppMessage;
use App\Support\WhatsApp;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * The shop's WhatsApp, inside the panel.
 *
 * It exists so that answering a customer is no longer something only the
 * owner's own telephone can do: anybody he trusts with the panel can read
 * and answer here, and every answer is signed with the name of whoever
 * wrote it.
 *
 * Refreshed by Livewire on a quiet poll rather than by hand — a question
 * nobody sees is a lost order. The window on the right says how long is
 * left to answer in, because WhatsApp closes a conversation a day after the
 * customer last wrote and anything sent after that is simply swallowed.
 */
class WhatsAppInbox extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'Mağaza';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'WhatsApp';

    protected static ?string $navigationLabel = 'WhatsApp';

    protected static string $view = 'filament.pages.whatsapp-inbox';

    protected static ?string $slug = 'whatsapp';

    public ?int $openChat = null;

    public string $draft = '';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isStaff();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return WhatsApp::enabled() && (bool) auth()->user()?->isStaff();
    }

    /** The little number beside the name in the menu. */
    public static function getNavigationBadge(): ?string
    {
        if (! WhatsApp::enabled()) {
            return null;
        }
        $waiting = (int) WhatsAppChat::where('unread', '>', 0)->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public function mount(): void
    {
        $this->openChat = WhatsAppChat::newestFirst()->value('id');
        $this->seen();
    }

    /** @return Collection<int, WhatsAppChat> */
    public function chats(): Collection
    {
        return WhatsAppChat::newestFirst()->limit(200)->get();
    }

    public function chat(): ?WhatsAppChat
    {
        return $this->openChat ? WhatsAppChat::find($this->openChat) : null;
    }

    /** @return Collection<int, WhatsAppMessage> */
    public function thread(): Collection
    {
        $chat = $this->chat();
        if (! $chat) {
            return collect();
        }

        return WhatsAppMessage::with('author')
            ->where('wa_id', $chat->wa_id)
            ->orderBy('id')
            ->limit(400)
            ->get();
    }

    public function open(int $id): void
    {
        $this->openChat = $id;
        $this->draft = '';
        $this->seen();
    }

    /** Whoever opens it has read it. */
    private function seen(): void
    {
        $chat = $this->chat();
        if ($chat && $chat->unread > 0) {
            $chat->forceFill(['unread' => 0])->save();
        }
    }

    public function send(): void
    {
        $chat = $this->chat();
        $text = trim($this->draft);

        if (! $chat || $text === '') {
            return;
        }

        if (! WhatsApp::enabled()) {
            Notification::make()->danger()
                ->title('WhatsApp qoşulmayıb')
                ->body('Tənzimləmələr → WhatsApp bölməsində nömrəni və açarı yazın.')
                ->send();

            return;
        }

        /* Said before the message is written rather than after it fails:
           outside the day WhatsApp allows, nothing but a message Meta has
           approved in advance will go, and the customer would be left
           waiting for an answer that was never sent. */
        if (! $chat->windowOpen()) {
            Notification::make()->warning()
                ->title('24 saat keçib')
                ->body('WhatsApp bu söhbətə sərbəst cavab verməyə icazə vermir. '
                    . 'Müştəri yenidən yazanda pəncərə açılır — ona qədər zəng edin.')
                ->send();

            return;
        }

        $line = WhatsApp::reply($chat, $text, auth()->id());
        $this->draft = '';

        if ($line && $line->status === 'failed') {
            Notification::make()->danger()
                ->title('Getmədi')
                ->body($line->error ?: 'WhatsApp cavab vermədi.')
                ->send();
        }
    }
}
