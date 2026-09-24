<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Models\User;
use App\Support\Contact;
use App\Support\Telegram;
use App\Support\Seo;
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
 * Site-wide switches: closing the site for maintenance, payments, how the net
 * profit is shared out, and the codes that prove the site is the owner's to
 * Google, Yandex and Bing.
 */
class SiteSettings extends Page implements HasActions, HasForms
{
    use InteractsWithActions;
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
            'seo_analytics' => Setting::get(Setting::SEO_ANALYTICS),
            'contact_phone' => Setting::get(Setting::CONTACT_PHONE),
            'contact_hours' => Setting::get(Setting::CONTACT_HOURS),
            'delivery_lead_days' => (int) Setting::get(Setting::DELIVERY_LEAD_DAYS),
            'delivery_slots' => Setting::get(Setting::DELIVERY_SLOTS),
            'chocolate_min_g' => (int) Setting::get(Setting::CHOCOLATE_MIN_G),
            'chocolate_max_g' => (int) Setting::get(Setting::CHOCOLATE_MAX_G),
            'telegram_token' => Telegram::token(),
            'telegram_chat' => Telegram::chat(),
        ]);
    }

    /** The two buttons that set the bot up: find the chat, then try it. */
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('findChat')
                ->label('Telegram: chat-ı tap')
                ->icon('heroicon-o-magnifying-glass')
                ->color('gray')
                ->action(function () {
                    try {
                        $this->data['telegram_chat'] = Telegram::findChat($this->data['telegram_token'] ?? null);
                        Notification::make()->success()->title('Tapıldı — indi "Saxla" düyməsini basın')->send();
                    } catch (\RuntimeException $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();
                    }
                }),
            Actions\Action::make('testMessage')
                ->label('Telegram: test mesajı')
                ->icon('heroicon-o-paper-airplane')
                ->color('gray')
                ->action(fn () => Telegram::send('✅ Nefis: bildirişlər işləyir. Sifariş gələndə bura yazacağam.')
                    ? Notification::make()->success()->title('Göndərildi — Telegram-a baxın')->send()
                    : Notification::make()->danger()->title('Göndərilmədi. Əvvəlcə "Saxla", sonra token və Chat ID-ni yoxlayın.')->send()),
        ];
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
                Forms\Components\Section::make('Əlaqə')
                    ->description('Saytın aşağısında və mobil menyuda görünür. Nömrə həm zəng, həm də WhatsApp üçün işlədilir.')
                    ->schema([
                        Forms\Components\TextInput::make('contact_phone')
                            ->label('Telefon (WhatsApp)')
                            ->tel()
                            ->placeholder('+994 99 230 80 50')
                            ->helperText('Ölkə kodu ilə yazın. Boş qalsa, saytda yalnız Instagram göstərilir.')
                            ->maxLength(30),
                        Forms\Components\TextInput::make('contact_hours')
                            ->label('İş saatları')
                            ->placeholder('Hər gün 10:00 — 20:00')
                            ->maxLength(80),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Çatdırılma vaxtı')
                    ->description('Müştəri sifariş verəndə tarix və vaxt seçir. Qutular əl ilə hazırlandığına görə ən tez tarix bu gündən neçə gün sonra olacağını siz deyirsiniz.')
                    ->schema([
                        Forms\Components\TextInput::make('delivery_lead_days')
                            ->label('Sifariş neçə gündən sonra hazır olur')
                            ->numeric()->minValue(0)->maxValue(30)->required()
                            ->suffix('gün')
                            ->helperText('Müştəri bundan tez tarix seçə bilmir; səbətdə bu barədə yazı görünür.'),
                        Forms\Components\Textarea::make('delivery_slots')
                            ->label('Vaxt aralıqları')
                            ->rows(3)
                            ->helperText('Hər sətirdə bir aralıq, məs. "10:00 — 14:00".'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Şokolad plitkaları')
                    ->description('Marketlərdən yalnız qutuya sığan plitkalar götürülür.')
                    ->schema([
                        Forms\Components\TextInput::make('chocolate_min_g')
                            ->label('Ən az çəki')->numeric()->minValue(10)->maxValue(500)->required()->suffix('q'),
                        Forms\Components\TextInput::make('chocolate_max_g')
                            ->label('Ən çox çəki')->numeric()->minValue(10)->maxValue(500)->required()->suffix('q')
                            ->helperText('Dəyişdikdən sonra Şokoladlar səhifəsində "Yenilə" düyməsini basın.'),
                    ])
                    ->columns(2)
                    ->collapsible(),
                Forms\Components\Section::make('Telegram bildirişləri')
                    ->description('Sifariş gələn kimi Telegram-a mesaj gəlir. Bot sizindir: Telegram-da @BotFather-ə "/newbot" yazıb bot yaradın, verdiyi tokeni bura yapışdırın, sonra öz botunuza "/start" yazıb "Chat-ı tap" düyməsini basın.')
                    ->schema([
                        Forms\Components\TextInput::make('telegram_token')
                            ->label('Bot tokeni')
                            ->password()
                            ->revealable()
                            ->placeholder('123456789:AA...')
                            ->helperText('@BotFather verir. Saytda şifrələnmiş saxlanılır.')
                            ->maxLength(200),
                        Forms\Components\TextInput::make('telegram_chat')
                            ->label('Chat ID')
                            ->placeholder('123456789')
                            ->helperText('Mesajların gedəcəyi söhbət. Aşağıdakı düymə özü tapır.')
                            ->maxLength(40),
                    ])
                    ->columns(2)
                    ->collapsible(),
                Forms\Components\Section::make('Axtarış sistemləri (SEO)')
                    ->description('Google Search Console, Yandex Webmaster və Bing Webmaster-də saytı təsdiqləmək üçün. Oradan "HTML tag" üsulunu seçin və verilən kodu (və ya bütün <meta …> sətrini) buraya yapışdırın. Sayt xəritəsi: ' . url('/sitemap.xml'))
                    ->schema([
                        Forms\Components\TextInput::make('seo_google')->label('Google (google-site-verification)')->maxLength(300),
                        Forms\Components\TextInput::make('seo_yandex')->label('Yandex (yandex-verification)')->maxLength(300),
                        Forms\Components\TextInput::make('seo_bing')->label('Bing (msvalidate.01)')->maxLength(300),
                        Forms\Components\TextInput::make('seo_analytics')
                            ->label('Google Analytics (ölçmə ID-si)')
                            ->placeholder('G-XXXXXXXXXX')
                            ->helperText('Saytın ziyarətçilərini saymaq üçün. Analytics-dən "G-" ilə başlayan ID-ni (və ya bütün kodu) yapışdırın. Boş qalsa, sayğac işləmir.')
                            ->maxLength(500)
                            ->columnSpan(3),
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
        Setting::put(Setting::SEO_ANALYTICS, Seo::measurementId($data['seo_analytics'] ?? ''));
        Setting::put(Setting::CONTACT_PHONE, Contact::clean($data['contact_phone'] ?? ''));
        Setting::put(Setting::CONTACT_HOURS, trim((string) ($data['contact_hours'] ?? '')));
        Setting::put(Setting::DELIVERY_LEAD_DAYS, max(0, (int) ($data['delivery_lead_days'] ?? 2)));
        Setting::put(Setting::DELIVERY_SLOTS, trim((string) ($data['delivery_slots'] ?? '')));
        Setting::put(Setting::CHOCOLATE_MIN_G, max(1, (int) ($data['chocolate_min_g'] ?? 90)));
        Setting::put(Setting::CHOCOLATE_MAX_G, max((int) ($data['chocolate_min_g'] ?? 90), (int) ($data['chocolate_max_g'] ?? 105)));
        Telegram::saveToken($data['telegram_token'] ?? '');
        Setting::put(Setting::TELEGRAM_CHAT, preg_replace('/[^0-9-]/', '', (string) ($data['telegram_chat'] ?? '')));

        Notification::make()->success()
            ->title($data['maintenance'] ? 'Saxlanıldı — sayt müştərilər üçün bağlıdır' : 'Saxlanıldı')
            ->send();
    }
}
