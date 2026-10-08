<?php

namespace App\Filament\Pages;

use App\Support\Menu;
use App\Support\Xonca;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * The shop's "Məhsullar" menu, in one place.
 *
 * Each line used to appear because its own feature was switched on somewhere
 * else — a wrapping existed, the letters were on sale — and there was no way
 * to see the menu whole, to hold a page back while it is being written, or
 * to say "new" beside one. All of that is here now: show or hide, a small
 * badge, and drag the order.
 *
 * A line whose page has nothing on it stays out whatever is set here, and
 * the row says why, so a switch that looks ignored never is.
 */
class MenuSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-bars-3';

    protected static ?string $navigationLabel = 'Menyu';

    protected static ?string $title = 'Saytın menyusu';

    protected static ?string $slug = 'menu';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.menu-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public function mount(): void
    {
        $saved = Menu::settings();
        uasort($saved, fn ($a, $b) => $a['order'] <=> $b['order']);

        $rows = [];
        foreach ($saved as $key => $row) {
            $rows[] = ['key' => $key, 'on' => $row['on'], 'badge' => $row['badge']];
        }

        $pictures = [];
        foreach ([1, 2, 3] as $n) {
            $pictures['block' . $n . '_image'] = Xonca::raw('block' . $n . '_image');
        }

        $this->form->fill(['rows' => $rows] + $pictures + Xonca::page());
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Section::make('Məhsullar menyusu')
                    ->description('Sətri tutub sırasını dəyişin. «Göstər» söndürülsə, həmin səhifə menyuda görünmür — səhifənin özü isə ünvanı ilə açıq qalır.')
                    ->schema([
                        Forms\Components\Repeater::make('rows')
                            ->label('Menyu sətirləri')
                            ->hiddenLabel()
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable()
                            ->reorderableWithDragAndDrop()
                            ->itemLabel(fn (array $state) => (Menu::ENTRIES[$state['key']]['icon'] ?? '') . ' '
                                . (Menu::ENTRIES[$state['key']]['label'] ?? $state['key']))
                            ->schema([
                                Forms\Components\Hidden::make('key'),
                                Forms\Components\Toggle::make('on')
                                    ->label('Göstər')
                                    ->helperText(fn (Forms\Get $get) => Menu::why((string) $get('key'))
                                        ?? 'Səhifə hazırdır, menyuda görünə bilər.'),
                                Forms\Components\TextInput::make('badge')
                                    ->label('Nişan (istəyə bağlı)')
                                    ->maxLength(20)
                                    ->datalist(Menu::BADGES)
                                    ->placeholder('Məs. Yeni, Tezliklə')
                                    ->helperText('Menyuda adın yanında kiçik yazı. Boş qoysanız, nişan olmur.'),
                            ])
                            ->columns(2),
                    ]),

                Forms\Components\Section::make('Xonça səhifəsi')
                    ->description('nefis.az/xonca — nişan, hinayaxdı və toy xonçası üçün kiçik şokoladlar. '
                        . 'Dizaynları «Xonça» kateqoriyasına yazın, onlar bu səhifədə özləri görünəcək.')
                    ->collapsed()
                    ->schema(self::xoncaFields())
                    ->columns(2),
            ]);
    }

    /** @return array<int, Forms\Components\Component> */
    private static function xoncaFields(): array
    {
        $long = ['lede', 'note', 'empty', 'block1_text', 'block2_text', 'block3_text'];
        $bands = [1 => 'Birinci', 2 => 'İkinci', 3 => 'Üçüncü'];
        $fields = [];
        $labels = [
            'eyebrow' => 'Üst yazı', 'title' => 'Başlıq', 'lede' => 'Giriş mətni',
            'size_label' => 'Ölçü başlığı', 'size' => 'Ölçü',
            'how_title' => 'Addımların başlığı', 'how_1' => '1-ci addım', 'how_2' => '2-ci addım',
            'how_3' => '3-cü addım', 'how_4' => '4-cü addım',
            'note_title' => 'Vaxt başlığı', 'note' => 'Vaxt haqqında',
            'empty' => 'Dizayn yoxdursa, nə yazılsın',
        ];

        foreach ($labels as $key => $label) {
            $fields[] = in_array($key, $long, true)
                ? Forms\Components\Textarea::make($key)->label($label)->rows(3)
                    ->placeholder(Xonca::DEFAULTS[$key])->columnSpanFull()
                : Forms\Components\TextInput::make($key)->label($label)->maxLength(160)
                    ->placeholder(Xonca::DEFAULTS[$key]);
        }

        /* The three wide bands, one fieldset each: a picture on one half and
           these words on the other. Leave a band empty and it does not show. */
        foreach ($bands as $n => $word) {
            $fields[] = Forms\Components\Fieldset::make($word . ' blok')
                ->schema([
                    Forms\Components\FileUpload::make('block' . $n . '_image')
                        ->label('Şəkil')
                        ->helperText('Blokun yarısını tutur. Eni hündürlüyündən böyük şəkillər yaxşı oturur.')
                        ->image()->disk('public')->directory('xonca')->maxSize(8192)
                        ->imageEditor()->columnSpanFull(),
                    Forms\Components\TextInput::make('block' . $n . '_eyebrow')
                        ->label('Üst yazı')->maxLength(60)
                        ->placeholder(Xonca::DEFAULTS['block' . $n . '_eyebrow']),
                    Forms\Components\TextInput::make('block' . $n . '_title')
                        ->label('Başlıq')->maxLength(120)
                        ->placeholder(Xonca::DEFAULTS['block' . $n . '_title']),
                    Forms\Components\Textarea::make('block' . $n . '_text')
                        ->label('Mətn')->rows(4)
                        ->placeholder(Xonca::DEFAULTS['block' . $n . '_text'])->columnSpanFull(),
                    Forms\Components\TextInput::make('block' . $n . '_button')
                        ->label('Düymənin adı (istəyə bağlı)')->maxLength(60)
                        ->placeholder(Xonca::DEFAULTS['block' . $n . '_button'] ?: 'Məs. Dizaynlara bax'),
                    Forms\Components\TextInput::make('block' . $n . '_url')
                        ->label('Düymənin ünvanı')
                        ->helperText('Saytın öz səhifəsi (/dizaynlar) və ya tam ünvan (https://…).')
                        ->maxLength(255)
                        ->placeholder('/dizaynlar'),
                    Forms\Components\Select::make('block' . $n . '_size')
                        ->label('Hündürlük')
                        ->helperText('Blokun ekranda nə qədər yer tutduğu. Telefonda hamısı özünü yığır.')
                        ->options(Xonca::SIZES)
                        ->default('orta')
                        ->selectablePlaceholder(false),
                ])
                ->columns(2);
        }

        return $fields;
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Menu::save($data['rows'] ?? []);
        Xonca::save($data);

        Notification::make()->success()->title('Yadda saxlanıldı')->send();
    }
}
