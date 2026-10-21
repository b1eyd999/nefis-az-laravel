<?php

namespace App\Filament\Pages;

use App\Support\SamiraKey;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Samira — the assistant on the owner's computer — and the key she reads
 * the shop with.
 *
 * The owner makes the key here and sees it once; he pastes it into the
 * «Подключить сайты к Самире» window on his computer himself. Nobody else
 * ever handles it: the site keeps only its hash, so the page cannot show it
 * again, and losing it means making a new one — which also finishes the old.
 *
 * What she can read is the feed in App\Support\SamiraFeed: paid orders,
 * first names, what was in the boxes, today's figures. Never a telephone,
 * an address or an e-mail, and nothing she could change.
 */
class Samira extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationLabel = 'Samira';

    protected static ?string $title = 'Samira — kompüterdəki köməkçi';

    protected static ?string $slug = 'samira';

    protected static ?int $navigationSort = 95;

    protected static string $view = 'filament.pages.samira';

    /**
     * The key just made, for this one view of the page. A public property so
     * the page can draw it; it is not kept anywhere else, and it is gone as
     * soon as the page is left.
     */
    public ?string $freshKey = null;

    public static function canAccess(): bool
    {
        // The owner alone: whoever has this key reads the whole day's sales.
        return (bool) auth()->user()?->isAdmin();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('make')
                ->label('Açar yarat')
                ->icon('heroicon-o-key')
                ->visible(fn () => ! SamiraKey::exists())
                ->action(fn () => $this->issue('Açar yaradıldı')),

            Actions\Action::make('renew')
                ->label('Yeni açar yarat')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn () => SamiraKey::exists())
                ->requiresConfirmation()
                ->modalHeading('Yeni açar yaradılsın?')
                ->modalDescription('Köhnə açar dərhal işləməyəcək. Yenisini kompüterdəki pəncərəyə '
                    . 'yapışdırana qədər Samira saytı oxumayacaq.')
                ->modalSubmitActionLabel('Bəli, yenisini yarat')
                ->action(fn () => $this->issue('Yeni açar yaradıldı, köhnəsi artıq işləmir')),

            Actions\Action::make('off')
                ->label('Söndür')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->visible(fn () => SamiraKey::exists())
                ->requiresConfirmation()
                ->modalHeading('Samira söndürülsün?')
                ->modalDescription('Açar silinəcək və Samira saytı oxuya bilməyəcək. '
                    . 'Yenidən qoşmaq üçün yeni açar yaratmaq lazım olacaq.')
                ->modalSubmitActionLabel('Bəli, söndür')
                ->action(function () {
                    SamiraKey::off();
                    $this->freshKey = null;
                    Notification::make()->success()->title('Samira söndürüldü')->send();
                }),
        ];
    }

    private function issue(string $said): void
    {
        $this->freshKey = SamiraKey::make();

        Notification::make()->success()->title($said)
            ->body('Açarı indi kopyalayın — bu səhifədən çıxandan sonra bir daha göstərilməyəcək.')
            ->send();
    }

    /** @return array<string, mixed> */
    public function state(): array
    {
        return [
            'on' => SamiraKey::exists(),
            'made' => SamiraKey::madeAt(),
            'seen' => SamiraKey::seenAt(),
            'from' => SamiraKey::seenFrom(),
            'feed' => url('/api/samira/feed'),
        ];
    }
}
