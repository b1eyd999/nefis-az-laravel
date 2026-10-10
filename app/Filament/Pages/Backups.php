<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\Backup;
use Filament\Actions;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * The copies of the database, and the button that makes one now.
 *
 * Everything the shop knows — the orders, the designs, the accounts, the
 * figures materials are reordered from — is in one database on a shared
 * host. A copy that only exists on that same host is half a copy, so the
 * page is above all a download button: the one that matters is the one on
 * the owner's own computer.
 */
class Backups extends Page implements HasForms, HasActions
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationLabel = 'Baza kopyaları';

    protected static ?string $title = 'Verilənlər bazasının kopyaları';

    protected static ?string $slug = 'backups';

    protected static ?int $navigationSort = 90;

    protected static string $view = 'filament.pages.backups';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public function mount(): void
    {
        $this->form->fill([
            'backup' => Backup::enabled(),
            'backup_keep' => Backup::keep(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Section::make('Qayda')
                    ->schema([
                        Forms\Components\Toggle::make('backup')
                            ->label('Hər gecə kopya alınsın')
                            ->helperText('Gecə saat 03:40-da. Bunun işləməsi üçün «Avtomatik işlər» (cron) '
                                . 'qurulmalıdır — Tənzimləmələr səhifəsində yazılıb.'),
                        Forms\Components\TextInput::make('backup_keep')
                            ->label('Neçə kopya saxlanılsın')
                            ->numeric()->minValue(1)->maxValue(90)->suffix('ədəd')
                            ->helperText('Köhnələri özü silir. 14 kopya iki həftəlik tarix deməkdir.'),
                    ])->columns(2),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('now')
                ->label('İndi kopya al')
                ->icon('heroicon-o-arrow-down-on-square')
                ->action(function () {
                    try {
                        $path = Backup::make();
                        Backup::sweep();
                        Notification::make()->success()
                            ->title('Kopya alındı')
                            ->body(basename($path) . ' — ' . self::size((int) filesize($path)))
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()->danger()
                            ->title('Kopya alınmadı')->body($e->getMessage())->send();
                    }
                }),
        ];
    }

    /** The copies there are, for the page's own table. */
    public function rows(): array
    {
        return array_map(fn (array $row) => $row + ['pretty' => self::size($row['size'])], Backup::all());
    }

    /**
     * Hand one over.
     *
     * Streamed from storage rather than linked to: storage/app is outside
     * the web root on purpose, and the whole point of this page is that the
     * copy ends up somewhere other than this server.
     */
    public function download(string $name)
    {
        $path = Backup::find($name);

        if (! $path) {
            Notification::make()->danger()->title('Belə kopya yoxdur')->send();

            return null;
        }

        return response()->download($path);
    }

    public function forget(string $name): void
    {
        $path = Backup::find($name);

        if ($path && @unlink($path)) {
            Notification::make()->success()->title('Silindi')->body(basename($path))->send();

            return;
        }

        Notification::make()->danger()->title('Silinmədi')->send();
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Setting::put(Setting::BACKUP, ! empty($data['backup']));
        Setting::put(Setting::BACKUP_KEEP, max(1, min(90, (int) ($data['backup_keep'] ?? Backup::KEEP))));

        Notification::make()->success()->title('Saxlanıldı')->send();
    }

    public static function size(int $bytes): string
    {
        return $bytes >= 1048576
            ? number_format($bytes / 1048576, 1) . ' MB'
            : number_format($bytes / 1024, 1) . ' KB';
    }
}
