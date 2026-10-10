<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\CorporatePage;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * The company page, in the owner's hands: every word on it, who it says it
 * is for, why it is worth it, which colours the box comes in and the
 * photographs of it standing on somebody's counter.
 *
 * Nothing here is code the owner has to wait for — he rewrites the page and
 * saves it.
 */
class CorporateSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Şirkətlər üçün';

    protected static ?string $title = 'Şirkətlər üçün səhifə';

    protected static ?string $slug = 'corporate';

    protected static ?int $navigationSort = 6;

    protected static string $view = 'filament.pages.corporate-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public function mount(): void
    {
        $this->form->fill(CorporatePage::all() + ['enabled' => CorporatePage::enabled()]);
    }

    public function form(Form $form): Form
    {
        $text = fn (string $key, string $label, int $max = 160) => Forms\Components\TextInput::make($key)
            ->label($label)->maxLength($max)->placeholder(CorporatePage::DEFAULTS[$key] ?? null);

        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Section::make('Satış')
                    ->schema([
                        Forms\Components\Toggle::make('enabled')
                            ->label('Bu xidmət işləsin')
                            ->helperText('Söndürsəniz, menyudakı «Şirkətlər üçün» yazısı və səhifənin özü yox olur — '
                                . 'köhnə link də açılmır. Gələn müraciətlər yerində qalır.')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Başlıq')
                    ->schema([
                        $text('eyebrow', 'Yuxarıdakı kiçik yazı', 60),
                        $text('title', 'Başlıq', 80),
                        Forms\Components\Textarea::make('lede')->label('Başlığın altındakı mətn')->rows(3)->maxLength(400)
                            ->placeholder(CorporatePage::DEFAULTS['lede'])->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Şərtlər')
                    ->description('Səhifənin yuxarısındakı üç rəqəm və onların altındakı izah.')
                    ->schema([
                        $text('size_label', 'Ölçünün adı', 40),
                        $text('size', 'Ölçü', 80),
                        $text('min_qty_label', 'Minimumun adı', 40),
                        Forms\Components\TextInput::make('min_qty')->label('Minimum sifariş, ədəd')
                            ->numeric()->minValue(1)->maxValue(100000)->required()
                            ->helperText('Müraciət formasında bundan az yazmaq olmur.'),
                        $text('lead_label', 'Müddətin adı', 40),
                        $text('lead', 'Hazırlanma müddəti', 60),
                        Forms\Components\Textarea::make('min_qty_note')->label('Minimumun altındakı izah')->rows(2)
                            ->maxLength(300)->placeholder(CorporatePage::DEFAULTS['min_qty_note'])->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Qiymət — sayına görə')
                    ->description('Səhifə indiyə qədər yalnız «say artdıqca bir ədədin qiyməti aşağı düşür» yazırdı '
                        . 'və heç bir rəqəm göstərmirdi — ona görə hər müraciət «neçəyədir?» sualı ilə başlayırdı. '
                        . 'Pillələri siz yazırsınız. Siyahı boş qalsa, səhifədə bu bölmə görünmür və hər şey '
                        . 'əvvəlki kimi işləyir.')
                    ->schema([
                        $text('ladder_title', 'Bölmənin başlığı', 80),
                        $text('ladder_per_label', '«Bir ədəd» yazısı', 40),
                        Forms\Components\Textarea::make('ladder_note')->label('Bölmənin altındakı izah')->rows(2)
                            ->maxLength(300)->placeholder(CorporatePage::DEFAULTS['ladder_note'])->columnSpanFull(),
                        Forms\Components\Repeater::make('ladder')
                            ->label('Pillələr')
                            ->helperText('Hər pillə: bu saydan başlayaraq bir ədədin qiyməti. Məs. 100 ədəddən 1.80 ₼, '
                                . '300 ədəddən 1.55 ₼. 250 ədəd sifariş edən 100-lük pillə ilə hesablanır — '
                                . 'çatmadığı endirim verilmir. Sıralamağa ehtiyac yoxdur, özü düzür.')
                            ->schema([
                                Forms\Components\TextInput::make('from')->label('Bu saydan')
                                    ->numeric()->minValue(1)->maxValue(1000000)->required()->suffix('ədəd'),
                                Forms\Components\TextInput::make('price')->label('Bir ədədin qiyməti')
                                    ->numeric()->minValue(0.01)->step(0.01)->required()->suffix('₼'),
                            ])
                            ->columns(2)
                            ->reorderable(false)
                            ->itemLabel(fn (array $state) => filled($state['from'] ?? null)
                                ? $state['from'] . '+ ədəd — ' . ($state['price'] ?? '?') . ' ₼'
                                : 'Yeni pillə')
                            ->addActionLabel('Pillə əlavə et')
                            ->defaultItems(0)
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Qutunun iki tərəfi')
                    ->schema([
                        $text('faces_title', 'Bölmənin başlığı', 80),
                        Forms\Components\Placeholder::make('faces_hint')->hiddenLabel()
                            ->content('Ön tərəfdə loqo, şüar və nömrə; arxada QR kod. Səhifədə ikisi də canlı çəkilir.'),
                        $text('front_title', 'Ön tərəfin adı', 40),
                        $text('back_title', 'Arxa tərəfin adı', 40),
                        Forms\Components\Textarea::make('front_text')->label('Ön tərəf haqqında')->rows(2)->maxLength(300)
                            ->placeholder(CorporatePage::DEFAULTS['front_text']),
                        Forms\Components\Textarea::make('back_text')->label('Arxa tərəf haqqında')->rows(2)
                            ->maxLength(300)->placeholder(CorporatePage::DEFAULTS['back_text']),
                        Forms\Components\Textarea::make('back_shot')->label('Arxa şəklin altındakı yazı')->rows(2)
                            ->maxLength(300)->placeholder(CorporatePage::DEFAULTS['back_shot'])->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Öz dizayneri olan şirkətlər')
                    ->description('Formada açılan bölmə: şablonu yükləyirlər, hazır faylı bizə göndərirlər.')
                    ->schema([
                        $text('designer_check', 'Açan sətir', 120),
                        $text('designer_template', 'Şablon düyməsinin yazısı', 60),
                        $text('designer_template_note', 'Düymənin altındakı yazı', 80),
                        $text('designer_field', 'Fayl sahəsinin adı', 60),
                        Forms\Components\Textarea::make('designer_note')->label('İzah')->rows(2)
                            ->maxLength(400)->placeholder(CorporatePage::DEFAULTS['designer_note'])->columnSpanFull(),
                        $text('designer_formats', 'Qəbul edilən fayllar', 120),
                        Forms\Components\Placeholder::make('designer_file')->label('Şablon faylı')
                            ->content(new \Illuminate\Support\HtmlString(
                                '<a href="/' . CorporatePage::TEMPLATE . '" target="_blank" rel="noopener" '
                                . 'style="text-decoration:underline">dizayn-sablonu.jpg</a> — ölçülər, kəsim xətti və nümunə.'
                            )),
                    ])->columns(2),

                Forms\Components\Section::make('Kimlər sifariş edir')
                    ->schema([
                        $text('whom_title', 'Bölmənin başlığı', 80),
                        Forms\Components\Repeater::make('whom')
                            ->hiddenLabel()
                            ->schema([
                                Forms\Components\TextInput::make('icon')->label('İşarə')->maxLength(8)
                                    ->helperText('Emoji, məs. ☕️')->columnSpan(1),
                                Forms\Components\TextInput::make('title')->label('Kim')->maxLength(60)->required()->columnSpan(2),
                                Forms\Components\TextInput::make('text')->label('Bir cümlə')->maxLength(140)->columnSpan(3),
                            ])
                            ->columns(3)
                            ->reorderableWithDragAndDrop()
                            ->collapsed()
                            ->itemLabel(fn (array $state) => $state['title'] ?? 'Yeni')
                            ->addActionLabel('Əlavə et')
                            ->columnSpanFull(),
                    ])->columns(1),

                Forms\Components\Section::make('Nəyə görə işləyir')
                    ->schema([
                        $text('perks_title', 'Bölmənin başlığı', 80),
                        Forms\Components\Repeater::make('perks')
                            ->hiddenLabel()
                            ->schema([
                                Forms\Components\TextInput::make('title')->label('Üstünlük')->maxLength(80)->required(),
                                Forms\Components\Textarea::make('text')->label('İzah')->rows(2)->maxLength(240),
                            ])
                            ->columns(2)
                            ->reorderableWithDragAndDrop()
                            ->collapsed()
                            ->itemLabel(fn (array $state) => $state['title'] ?? 'Yeni')
                            ->addActionLabel('Əlavə et')
                            ->columnSpanFull(),
                    ])->columns(1),

                Forms\Components\Section::make('Qutunun rəngləri')
                    ->description('Müştəri səhifədə bu rənglərdən seçir. Seçdiyi rəng müraciətlə birlikdə sizə gəlir.')
                    ->schema([
                        Forms\Components\Repeater::make('colors')
                            ->hiddenLabel()
                            ->schema([
                                Forms\Components\TextInput::make('name')->label('Ad')->maxLength(40)->required(),
                                Forms\Components\ColorPicker::make('hex')->label('Rəng')
                                    ->regex('/^#[0-9a-fA-F]{6}$/')->required(),
                            ])
                            ->columns(2)
                            ->reorderableWithDragAndDrop()
                            ->itemLabel(fn (array $state) => $state['name'] ?? 'Rəng')
                            ->addActionLabel('Rəng əlavə et')
                            ->columnSpanFull(),
                    ])->columns(1),

                Forms\Components\Section::make('Şəkillər')
                    ->description('Qutunun kafedə, salonda, ofisdə necə göründüyü. Şəkil yoxdursa, bölmə səhifədə görünmür.')
                    ->schema([
                        $text('gallery_title', 'Bölmənin başlığı', 80),
                        Forms\Components\Repeater::make('gallery')
                            ->hiddenLabel()
                            ->schema([
                                Forms\Components\FileUpload::make('image')->label('Şəkil')
                                    ->image()->disk('public')->directory('corporate')
                                    ->imageEditor()->maxSize(8192)->required(),
                                Forms\Components\TextInput::make('caption')->label('Altyazı')->maxLength(140),
                            ])
                            ->columns(2)
                            ->reorderableWithDragAndDrop()
                            ->collapsed()
                            ->itemLabel(fn (array $state) => $state['caption'] ?? 'Şəkil')
                            ->addActionLabel('Şəkil əlavə et')
                            ->columnSpanFull(),
                    ])->columns(1),

                Forms\Components\Section::make('Müraciət forması')
                    ->schema([
                        $text('try_title', 'Yoxlama bölməsinin başlığı', 80),
                        Forms\Components\Textarea::make('try_note')->label('Yoxlama bölməsinin izahı')->rows(2)
                            ->maxLength(300)->placeholder(CorporatePage::DEFAULTS['try_note']),
                        $text('form_title', 'Formanın başlığı', 80),
                        $text('form_button', 'Düymənin yazısı', 40),
                        Forms\Components\Textarea::make('form_note')->label('Formanın izahı')->rows(2)->maxLength(300)
                            ->placeholder(CorporatePage::DEFAULTS['form_note']),
                        Forms\Components\Textarea::make('form_thanks')->label('Göndərdikdən sonra görünən yazı')->rows(2)
                            ->maxLength(300)->placeholder(CorporatePage::DEFAULTS['form_thanks']),
                    ])->columns(2),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $page = [];
        foreach (array_keys(CorporatePage::DEFAULTS) as $key) {
            $value = $data[$key] ?? null;
            $page[$key] = is_string($value) ? trim($value) : (string) $value;
        }

        // The lists as the owner arranged them, empty rows dropped so a half
        // -filled one cannot leave a blank card on the page.
        // The ladder has rules of its own — whole numbers, real prices, in
        // order — so it is cleaned where those rules live.
        $page['ladder'] = CorporatePage::ladderRows($data['ladder'] ?? null);

        foreach (['whom' => ['title'], 'perks' => ['title'], 'colors' => ['name', 'hex'], 'gallery' => ['image']] as $list => $required) {
            $rows = array_values((array) ($data[$list] ?? []));
            $page[$list] = array_values(array_filter($rows, function ($row) use ($required) {
                foreach ($required as $field) {
                    if (! isset($row[$field]) || trim((string) $row[$field]) === '') {
                        return false;
                    }
                }

                return true;
            }));
        }

        Setting::put(Setting::CORPORATE, json_encode($page, JSON_UNESCAPED_UNICODE));

        $on = (bool) ($data['enabled'] ?? false);
        Setting::put(Setting::CORPORATE_ENABLED, $on ? '1' : '0');

        Notification::make()->success()->title('Saxlanıldı')
            ->body($on ? 'Səhifə yeniləndi: /sirketler-ucun' : 'Bu xidmət söndürüldü — səhifə saytda görünmür.')
            ->send();
    }
}
