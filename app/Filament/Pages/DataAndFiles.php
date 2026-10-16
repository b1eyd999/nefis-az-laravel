<?php

namespace App\Filament\Pages;

use App\Support\DataMap;
use Filament\Actions;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Everything the shop keeps about a person, on one screen.
 *
 * Four questions, in the order they get asked. What does the site put in a
 * visitor's browser. Which of the details in the database are unreadable
 * even to somebody holding the file. What is on the disk, and how much of it
 * is photographs nobody ordered. And is anything happening right now.
 *
 * It is a reading screen. The one button that changes anything re-counts the
 * disk; deleting stray photographs stays in `uploads:sweep`, where it is a
 * deliberate act and not a mis-click.
 */
class DataAndFiles extends Page implements HasActions
{
    use InteractsWithActions;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Kuki, şifrə və fayllar';

    protected static ?string $title = 'Məlumat, kuki və fayllar';

    protected static ?string $slug = 'data-and-files';

    protected static ?int $navigationSort = 91;

    protected static string $view = 'filament.pages.data-and-files';

    /** Set when the owner asks for the disk to be walked again. */
    public bool $fresh = false;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('recount')
                ->label('Faylları yenidən say')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function () {
                    $this->fresh = true;
                    Notification::make()->success()->title('Yenidən sayıldı')->send();
                }),

            /* The owner has no terminal on that host, so `uploads:sweep` is
               out of his reach and this button is the only way the strays
               ever go. Deleting is not undoable, so it asks first and the
               question carries the real number — not «are you sure». */
            Actions\Action::make('sweep')
                ->label('Sahibsiz şəkilləri sil')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->visible(fn () => DataMap::strays()['files'] > 0)
                ->requiresConfirmation()
                ->modalHeading('Sahibsiz şəkillər silinsin?')
                ->modalDescription(function () {
                    $strays = DataMap::strays();

                    return number_format($strays['files']) . ' fayl, '
                        . DataMap::size($strays['bytes']) . '. Bunlara heç bir sifariş, səbət '
                        . 'və ya link baxmır və ' . $strays['days'] . ' gündən çoxdur toxunulmayıb. '
                        . 'Silinmə geri alınmır.';
                })
                ->modalSubmitActionLabel('Bəli, sil')
                ->action(function () {
                    $gone = DataMap::sweep();
                    $this->fresh = true;

                    Notification::make()->success()
                        ->title('Silindi')
                        ->body(number_format($gone['files']) . ' fayl, '
                            . DataMap::size($gone['bytes']) . ' boşaldı.')
                        ->send();
                }),
        ];
    }

    public function inTheBrowser(): array
    {
        return DataMap::inTheBrowser();
    }

    public function noticeState(): array
    {
        return DataMap::noticeState();
    }

    public function locked(): array
    {
        return DataMap::locked();
    }

    public function readable(): array
    {
        return DataMap::readable();
    }

    public function keyOpens(): ?bool
    {
        return DataMap::keyOpens();
    }

    public function files(): array
    {
        return DataMap::files($this->fresh);
    }

    public function strays(): array
    {
        return DataMap::strays(30, $this->fresh);
    }

    public function happening(): array
    {
        return DataMap::happening();
    }
}
