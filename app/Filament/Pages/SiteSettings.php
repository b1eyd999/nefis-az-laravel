<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Site-wide switches: closing the site for maintenance, and how the net
 * profit is shared out between the owner and the managers.
 */
class SiteSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Tənzimləmələr';

    protected static ?string $title = 'Tənzimləmələr';

    protected static ?string $slug = 'settings';

    protected static ?int $navigationSort = 90;

    protected static string $view = 'filament.pages.site-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public function mount(): void
    {
        $this->form->fill([
            'maintenance' => Setting::get(Setting::MAINTENANCE) === '1',
            'maintenance_message' => Setting::get(Setting::MAINTENANCE_MESSAGE),
            'shares' => Setting::profitShares(),
            'payment_limit' => (int) Setting::get(Setting::PAYMENT_LIMIT),
            'payment_window_hours' => (int) Setting::get(Setting::PAYMENT_WINDOW_HOURS),
            'payment_note' => Setting::get(Setting::PAYMENT_NOTE),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Section::make('Texniki işlər')
                    ->description('Açıq olanda müştərilər saytın yerinə "texniki işlər" səhifəsini görür. Siz və menecerlər saytdan və admin paneldən adi qaydada istifadə edirsiniz.')
                    ->schema([
                        Forms\Components\Toggle::make('maintenance')->label('Saytı texniki işlərə bağla')->onColor('danger'),
                        Forms\Components\Textarea::make('maintenance_message')->label('Müştərilərə yazı')->rows(3)->required()->maxLength(500),
                    ]),
                Forms\Components\Section::make('Ödəniş')
                    ->description('Müştəri "Ödəniş hesabları"ndakı hesaba köçürür və çeki yükləyir. Bir hesab limitini doldurduqda növbəti hesaba keçilir.')
                    ->schema([
                        Forms\Components\TextInput::make('payment_limit')
                            ->label('Bir hesaba neçə sifariş')->numeric()->minValue(1)->maxValue(500)->required(),
                        Forms\Components\TextInput::make('payment_window_hours')
                            ->label('Neçə saat ərzində')->numeric()->minValue(1)->maxValue(720)->suffix('saat')->required()
                            ->helperText('Bu müddət keçdikcə sayğac özü boşalır.'),
                        Forms\Components\Textarea::make('payment_note')
                            ->label('Ödəniş səhifəsindəki yazı')->rows(2)->maxLength(300)->columnSpanFull(),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Mənfəətin bölgüsü')
                    ->description('Xalis mənfəət bu paylarla bölünür ("Balans" səhifəsində görünür). Faizlərin cəmi 100 olmalıdır.')
                    ->schema([
                        Forms\Components\Repeater::make('shares')
                            ->label('')
                            ->schema([
                                Forms\Components\TextInput::make('name')->label('Kim')->required()->maxLength(60),
                                Forms\Components\TextInput::make('percent')->label('Pay')->numeric()->minValue(0)->maxValue(100)->suffix('%')->required(),
                            ])
                            ->columns(2)
                            ->defaultItems(3)
                            ->addActionLabel('Pay əlavə et')
                            ->reorderable(false),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $shares = array_values(array_map(fn ($s) => ['name' => trim($s['name']), 'percent' => round((float) $s['percent'], 2)], $data['shares'] ?? []));
        $sum = array_sum(array_column($shares, 'percent'));
        if ($shares && abs($sum - 100) > 0.05) {
            Notification::make()->danger()->title('Payların cəmi 100% olmalıdır')->body('İndi: ' . rtrim(rtrim(number_format($sum, 2, '.', ''), '0'), '.') . '%')->send();

            return;
        }

        Setting::put(Setting::PAYMENT_LIMIT, (int) $data['payment_limit']);
        Setting::put(Setting::PAYMENT_WINDOW_HOURS, (int) $data['payment_window_hours']);
        Setting::put(Setting::PAYMENT_NOTE, $data['payment_note'] ?? '');
        Setting::put(Setting::MAINTENANCE, (bool) $data['maintenance']);
        Setting::put(Setting::MAINTENANCE_MESSAGE, $data['maintenance_message']);
        Setting::put(Setting::PROFIT_SHARES, json_encode($shares, JSON_UNESCAPED_UNICODE));

        Notification::make()->success()
            ->title($data['maintenance'] ? 'Saxlanıldı — sayt müştərilər üçün bağlıdır' : 'Saxlanıldı')
            ->send();
    }
}
