<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Models\User;
use App\Support\Seo;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Site-wide switches: closing the site for maintenance, payments, how the net
 * profit is shared out, and the codes that prove the site is the owner's to
 * Google, Yandex and Bing.
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
            'payment_limit' => (int) Setting::get(Setting::PAYMENT_LIMIT),
            'payment_window_hours' => (int) Setting::get(Setting::PAYMENT_WINDOW_HOURS),
            'payment_note' => Setting::get(Setting::PAYMENT_NOTE),
            'seo_google' => Setting::get(Setting::SEO_GOOGLE),
            'seo_yandex' => Setting::get(Setting::SEO_YANDEX),
            'seo_bing' => Setting::get(Setting::SEO_BING),
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
                Forms\Components\Section::make('Axtarış sistemləri (SEO)')
                    ->description('Google Search Console, Yandex Webmaster və Bing Webmaster-də saytı təsdiqləmək üçün. Oradan "HTML tag" üsulunu seçin və verilən kodu (və ya bütün <meta …> sətrini) buraya yapışdırın. Sayt xəritəsi: ' . url('/sitemap.xml'))
                    ->schema([
                        Forms\Components\TextInput::make('seo_google')->label('Google (google-site-verification)')->maxLength(300),
                        Forms\Components\TextInput::make('seo_yandex')->label('Yandex (yandex-verification)')->maxLength(300),
                        Forms\Components\TextInput::make('seo_bing')->label('Bing (msvalidate.01)')->maxLength(300),
                    ])
                    ->columns(3)
                    ->collapsible(),
                // Shares are given with the role now, one per staff member.
                Forms\Components\Section::make('Mənfəətin bölgüsü')
                    ->description('Pay hər menecerə "İstifadəçilər" bölməsində, rol verəndə təyin olunur. Menecer öz payını "Balansım" səhifəsində görür.')
                    ->schema([
                        Forms\Components\Placeholder::make('shares_now')
                            ->label('Hazırkı paylar')
                            ->content(function () {
                                $holders = User::shareholders();
                                if ($holders->isEmpty()) {
                                    return 'Hələ heç kimə pay verilməyib.';
                                }
                                $pct = fn (float $v) => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') . '%';

                                return $holders->map(fn (User $u) => $u->name . ' — ' . $pct($u->profit_percent))->implode(', ')
                                    . ' · cəmi ' . $pct((float) $holders->sum('profit_percent'));
                            }),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Setting::put(Setting::PAYMENT_LIMIT, (int) $data['payment_limit']);
        Setting::put(Setting::PAYMENT_WINDOW_HOURS, (int) $data['payment_window_hours']);
        Setting::put(Setting::PAYMENT_NOTE, $data['payment_note'] ?? '');
        Setting::put(Setting::MAINTENANCE, (bool) $data['maintenance']);
        Setting::put(Setting::MAINTENANCE_MESSAGE, $data['maintenance_message']);
        Setting::put(Setting::SEO_GOOGLE, Seo::cleanCode($data['seo_google'] ?? ''));
        Setting::put(Setting::SEO_YANDEX, Seo::cleanCode($data['seo_yandex'] ?? ''));
        Setting::put(Setting::SEO_BING, Seo::cleanCode($data['seo_bing'] ?? ''));

        Notification::make()->success()
            ->title($data['maintenance'] ? 'Saxlanıldı — sayt müştərilər üçün bağlıdır' : 'Saxlanıldı')
            ->send();
    }
}
