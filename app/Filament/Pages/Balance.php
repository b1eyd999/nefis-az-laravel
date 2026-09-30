<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Models\User;
use App\Support\Accounting;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * The books at a glance: what came in, what it cost, the real profit and
 * everyone's share of it, for this month, last month, this year or all time.
 */
class Balance extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationGroup = 'Mühasibatlıq';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.balance';

    public const PERIODS = [
        'month' => 'Bu ay',
        'last_month' => 'Keçən ay',
        'year' => 'Bu il',
        'all' => 'Hamısı',
    ];

    public string $period = 'month';

    /** The admin sees the books; a manager sees their own share of them. */
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isStaff();
    }

    public static function getNavigationLabel(): string
    {
        return auth()->user()?->isAdmin() ? 'Balans' : 'Balansım';
    }

    public function getTitle(): string
    {
        return static::getNavigationLabel();
    }

    /**
     * The split itself, changed here rather than user by user: this is the
     * page where the shares are looked at, so it is the page where the owner
     * reaches for them.
     */
    protected function getHeaderActions(): array
    {
        if (! auth()->user()?->isAdmin()) {
            return [];
        }

        return [
            /* Zeroing the books does not delete anything: the orders, the
               expenses and the money taken out all stay where they are, and
               the page simply starts counting from the moment he draws the
               line — the way a new ledger starts on a new page. */
            Actions\Action::make('zero')
                ->label(Accounting::booksFrom() ? 'Hesabın başlanğıcını dəyiş' : 'Hesabları sıfırla')
                ->icon('heroicon-o-flag')
                ->color('gray')
                ->modalHeading('Hesabları sıfırla')
                ->modalDescription('Seçilmiş andan əvvəlki sifarişlər, xərclər və çıxarışlar bu səhifədə sayılmayacaq. Heç nə silinmir — istədiyiniz vaxt geri qaytara bilərsiniz.')
                ->modalSubmitActionLabel('Sıfırla')
                ->fillForm(fn () => ['at' => (Accounting::booksFrom() ?? now())->format('Y-m-d\TH:i')])
                ->form([
                    Forms\Components\DateTimePicker::make('at')
                        ->label('Bu andan sonrası sayılsın')
                        ->seconds(false)
                        ->required(),
                ])
                ->action(function (array $data) {
                    Setting::put(Setting::BOOKS_FROM, \Illuminate\Support\Carbon::parse($data['at'])->toDateTimeString());
                    Notification::make()->success()
                        ->title('Hesablar sıfırlandı')
                        ->body('Bu səhifə artıq yalnız ' . \Illuminate\Support\Carbon::parse($data['at'])->format('d.m.Y H:i') . '-dən sonrakını sayır.')
                        ->send();
                }),
            Actions\Action::make('unzero')
                ->label('Bütün tarixə qayıt')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->visible(fn () => (bool) Accounting::booksFrom())
                ->requiresConfirmation()
                ->modalHeading('Bütün tarixə qayıt')
                ->modalDescription('Səhifə yenidən ilk gündən bu günə qədər hər şeyi sayacaq.')
                ->action(function () {
                    Setting::put(Setting::BOOKS_FROM, '');
                    Notification::make()->success()->title('Hesablar bütün tarixə qaytarıldı')->send();
                }),
            Actions\Action::make('shares')
                ->label('Payları dəyiş')
                ->icon('heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->modalHeading('Mənfəətin bölgüsü')
                ->modalDescription('Hər kəsin xalis mənfəətdən payı. Yığımı 100%-dən az olsa, qalan biznesə qalır.')
                ->modalSubmitActionLabel('Saxla')
                ->fillForm(fn () => self::staff()->mapWithKeys(fn (User $u) => ['u' . $u->id => (float) $u->profit_percent])->all())
                ->form(fn () => self::staff()->map(fn (User $u) => Forms\Components\TextInput::make('u' . $u->id)
                    ->label($u->name . ' · ' . $u->roleLabel())
                    ->numeric()->minValue(0)->maxValue(100)->step('0.01')->suffix('%')
                    ->default(0))->all())
                ->action(function (array $data) {
                    $staff = self::staff();
                    $total = 0.0;
                    foreach ($staff as $user) {
                        $total += (float) ($data['u' . $user->id] ?? 0);
                    }

                    if ($total > 100.0001) {
                        Notification::make()->danger()
                            ->title('Payların cəmi 100%-i keçir')
                            ->body('İndiki cəmi: ' . self::percent($total) . '. Əvvəlcə kiminsə payını azaldın.')
                            ->send();

                        return;
                    }

                    foreach ($staff as $user) {
                        $user->forceFill(['profit_percent' => round((float) ($data['u' . $user->id] ?? 0), 2)])->save();
                    }

                    Notification::make()->success()
                        ->title('Paylar yeniləndi')
                        ->body('Biznesə qalan: ' . self::percent(round(100 - $total, 2)))
                        ->send();
                }),
        ];
    }

    /** Everyone who can hold a share: the shop's own people. */
    private static function staff()
    {
        return User::query()
            ->where(fn ($q) => $q->whereIn('role', [User::ADMIN, User::MANAGER])->orWhere('is_admin', true))
            ->orderByDesc('profit_percent')->orderBy('name')->get();
    }

    /** 22.5% rather than 22.50%, and 22% rather than 22.00%. */
    public static function percent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') . '%';
    }

    /** The signed-in manager's line of the profit split, if they have one. */
    public function mine(): ?array
    {
        return collect($this->report['shares'])->firstWhere('user_id', auth()->id());
    }

    /** @return array{0: ?Carbon, 1: ?Carbon} */
    public function range(): array
    {
        return match ($this->period) {
            'last_month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            'year' => [now()->startOfYear(), now()->endOfYear()],
            'all' => [null, null],
            default => [now()->startOfMonth(), now()->endOfMonth()],
        };
    }

    public function getReportProperty(): array
    {
        [$from, $to] = $this->range();

        return Accounting::report($from, $to);
    }
}
