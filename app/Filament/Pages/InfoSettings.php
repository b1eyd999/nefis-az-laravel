<?php

namespace App\Filament\Pages;

use App\Support\Info;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * The three pages that answer a visitor before he buys, in the owner's own
 * hands: how the shop works, what people ask, and how to reach a person.
 *
 * The questions used to be written into the front page itself, and one of
 * them went on telling visitors an order needed signing up long after the
 * checkout had stopped asking. One list now, edited here, shown on both the
 * front page and the page of its own.
 */
class InfoSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static ?string $navigationLabel = 'Suallar və əlaqə';

    protected static ?string $title = 'Suallar, «necə işləyir» və əlaqə';

    protected static ?string $slug = 'info-pages';

    protected static ?int $navigationSort = 7;

    protected static string $view = 'filament.pages.info-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public function mount(): void
    {
        $this->form->fill(Info::all());
    }

    public function form(Form $form): Form
    {
        $text = fn (string $key, string $label, int $max = 160) => Forms\Components\TextInput::make($key)
            ->label($label)->maxLength($max)->placeholder(Info::DEFAULTS[$key] ?? null);

        $area = fn (string $key, string $label, int $rows = 2, int $max = 600) => Forms\Components\Textarea::make($key)
            ->label($label)->rows($rows)->maxLength($max)->placeholder(Info::DEFAULTS[$key] ?? null);

        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Tabs::make('pages')->columnSpanFull()->tabs([

                    Forms\Components\Tabs\Tab::make('Tez-tez soruşulan suallar')
                        ->icon('heroicon-o-question-mark-circle')
                        ->schema([
                            $text('faq_eyebrow', 'Yuxarıdakı kiçik yazı', 60),
                            $text('faq_title', 'Başlıq', 80),
                            $area('faq_lede', 'Başlığın altındaki izah'),
                            Forms\Components\Repeater::make('faq')
                                ->label('Suallar')
                                ->helperText('Sürüşdürərək sıralayın. Saytın ana səhifəsində ilk 5-i görünür, '
                                    . 'hamısı isə /suallar səhifəsində. Boş sual silinir. '
                                    . 'Siyahını tamam boşaltsanız, bölmə saytda görünmür.')
                                ->schema([
                                    Forms\Components\TextInput::make('q')->label('Sual')->maxLength(200)->columnSpanFull(),
                                    Forms\Components\Textarea::make('a')->label('Cavab')->rows(3)->maxLength(1200)->columnSpanFull(),
                                ])
                                ->itemLabel(fn (array $state) => $state['q'] ?? null)
                                ->collapsed()
                                ->collapsible()
                                ->reorderableWithButtons()
                                ->defaultItems(0)
                                ->columnSpanFull(),
                        ])->columns(2),

                    Forms\Components\Tabs\Tab::make('Necə işləyir')
                        ->icon('heroicon-o-list-bullet')
                        ->schema([
                            $text('how_eyebrow', 'Yuxarıdakı kiçik yazı', 60),
                            $text('how_title', 'Başlıq', 80),
                            $area('how_lede', 'Başlığın altındaki izah'),
                            $area('how_outro', 'Səhifənin sonundaki yazı'),
                            Forms\Components\Repeater::make('steps')
                                ->label('Addımlar')
                                ->helperText('Nömrələri səhifə özü qoyur. Ana səhifədə də elə bu addımlar görünür. '
                                    . 'Çatdırılma, ödəniş və hazırlanma müddəti haqqında yazı bu addımların altında '
                                    . 'öz-özünə yazılır — orada nə yazıldığı Tənzimləmələr və Çatdırılma Üsullarından gəlir.')
                                ->schema([
                                    Forms\Components\TextInput::make('title')->label('Addımın adı')->maxLength(80),
                                    Forms\Components\Textarea::make('text')->label('İzahı')->rows(2)->maxLength(400),
                                ])
                                ->itemLabel(fn (array $state) => $state['title'] ?? null)
                                ->collapsed()
                                ->collapsible()
                                ->reorderableWithButtons()
                                ->defaultItems(0)
                                ->columnSpanFull(),
                        ])->columns(2),

                    Forms\Components\Tabs\Tab::make('Əlaqə')
                        ->icon('heroicon-o-phone')
                        ->schema([
                            Forms\Components\Placeholder::make('whence')
                                ->label('Nömrə, iş saatları və şirkət məlumatları')
                                ->content('Bunlar burada yazılmır — Tənzimləmələr səhifəsindən gəlir (telefon, '
                                    . 'iş saatları, hüquqi ad, VÖEN, ünvan). Burada yalnız səhifənin sözləri var.')
                                ->columnSpanFull(),
                            $text('contact_eyebrow', 'Yuxarıdakı kiçik yazı', 60),
                            $text('contact_title', 'Başlıq', 80),
                            $area('contact_lede', 'Başlığın altındaki izah', 3),
                            $text('contact_where_title', '«Bizi haradan tapmaq olar» başlığı', 80),
                            $area('contact_where_note', 'Mağaza və çatdırılma barədə izah', 3),
                            $text('contact_form_title', 'Mesaj formasının başlığı', 80),
                            $text('contact_form_button', 'Düymənin yazısı', 40),
                            $area('contact_form_note', 'Formanın izahı'),
                            $area('contact_form_thanks', 'Göndərdikdən sonra görünən yazı'),
                        ])->columns(2),
                ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $page = [];
        foreach (array_keys(Info::DEFAULTS) as $key) {
            $value = $data[$key] ?? null;
            $page[$key] = is_string($value) ? trim($value) : (string) $value;
        }

        // The two lists as the owner arranged them, empty rows dropped so a
        // half-filled one cannot leave a blank card on the page.
        foreach (['faq' => ['q', 'a'], 'steps' => ['title']] as $list => $required) {
            $rows = array_values((array) ($data[$list] ?? []));
            $page[$list] = array_values(array_filter($rows, function ($row) use ($required) {
                foreach ($required as $field) {
                    if (trim((string) ($row[$field] ?? '')) === '') {
                        return false;
                    }
                }

                return true;
            }));
        }

        Info::save($page);

        Notification::make()->success()->title('Saxlanıldı')
            ->body('Səhifələr yeniləndi: /suallar, /nece-isleyir, /elaqe')
            ->send();
    }
}
