<?php

namespace App\Filament\Pages;

use App\Models\OrderItem;
use App\Models\Setting;
use App\Support\Letter;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * Everything about the Polaroid letters in one place: on sale or not and the
 * price, the page's wording, how the Polaroid looks, and the letters ordered
 * so far, ready to print.
 */
class LetterSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-envelope-open';

    protected static ?string $navigationLabel = 'Polaroid məktub';

    protected static ?string $title = 'Polaroid məktub';

    protected static ?string $slug = 'letters';

    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.pages.letter-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public function mount(): void
    {
        $this->form->fill(Letter::page() + [
            'enabled' => Letter::enabled(),
            'price' => Letter::price(),
            'max' => Letter::maxLength(),
        ]);
    }

    public function form(Form $form): Form
    {
        $text = fn (string $key, string $label, int $max = 120) => Forms\Components\TextInput::make($key)->label($label)->maxLength($max)
            ->placeholder(Letter::PAGE_DEFAULTS[$key]);

        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Section::make('Satış')
                    ->schema([
                        Forms\Components\Toggle::make('enabled')->label('Satışda olsun')
                            ->helperText('Söndürülsə, səhifə, menyu linki və qutudakı seçim gizlənir.')->columnSpanFull(),
                        Forms\Components\TextInput::make('price')->label('Qiymət')->numeric()->minValue(0)->step(0.01)->suffix('₼')->required(),
                        Forms\Components\TextInput::make('max')->label('Mətn ən çox')->numeric()->minValue(20)->maxValue(1000)->suffix('simvol')->required(),
                    ])->columns(2),
                Forms\Components\Section::make('Polaroidin görünüşü')
                    ->description('Saytdakı bütün polaroidlərə aiddir: səhifədə, qutunun seçimində və səbətdə.')
                    ->schema([
                        Forms\Components\ColorPicker::make('frame')->label('Çərçivənin rəngi')->regex('/^#[0-9a-fA-F]{6}$/')->live(),
                        Forms\Components\ColorPicker::make('ink')->label('Yazının rəngi')->regex('/^#[0-9a-fA-F]{6}$/')->live(),
                        Forms\Components\Select::make('font')->label('Əl yazısı')->options(Letter::FONTS)->required()->live(),
                        Forms\Components\Select::make('filter')->label('Şəklin effekti')->options(Letter::FILTERS)->required()->live(),
                        Forms\Components\TextInput::make('tilt')->label('Əyilmə')->numeric()->minValue(-10)->maxValue(10)->step(0.5)
                            ->suffix('°')->helperText('0 — düz; mənfi — sola, müsbət — sağa.')->live(onBlur: true),
                        $text('placeholder', 'Boş polaroiddəki yazı', 40)->live(onBlur: true),
                    ])->columns(2),
                Forms\Components\Section::make('Səhifənin mətnləri')
                    ->description('"/mektub" səhifəsi və menyudakı ad.')
                    ->schema([
                        $text('menu', 'Menyuda', 30),
                        $text('eyebrow', 'Yuxarıdakı kiçik yazı', 60),
                        $text('title', 'Başlıq', 60),
                        Forms\Components\Textarea::make('lede')->label('Başlığın altındakı mətn')->rows(2)->maxLength(300)
                            ->placeholder(Letter::PAGE_DEFAULTS['lede'])->columnSpanFull(),
                        $text('photo_label', 'Şəkil düyməsi', 40),
                        $text('text_placeholder', 'Mətn sahəsindəki nümunə', 120),
                        $text('hint', 'Mətnin altındakı izah', 160),
                        $text('button', 'Səbət düyməsi', 40),
                        $text('note', 'Səhifənin altındakı qeyd', 200),
                        $text('box_label', 'Qutunun seçimindəki yazı', 80),
                    ])->columns(2),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Setting::put(Setting::LETTER_ENABLED, (bool) $data['enabled']);
        Setting::put(Setting::LETTER_PRICE, round((float) $data['price'], 2));
        Setting::put(Setting::LETTER_MAX, (int) $data['max']);

        $page = [];
        foreach (array_keys(Letter::PAGE_DEFAULTS) as $key) {
            $value = $data[$key] ?? null;
            $page[$key] = is_string($value) ? trim($value) : $value;
        }
        Setting::put(Setting::LETTER_PAGE, json_encode($page, JSON_UNESCAPED_UNICODE));

        Notification::make()->success()->title('Saxlanıldı')->send();
    }

    /** The letters ordered, newest first, to print. */
    public function getLettersProperty(): Collection
    {
        return OrderItem::query()
            ->with('order')
            ->where(fn ($q) => $q->whereNotNull('letter_price')->orWhereNotNull('letter_text')->orWhereNotNull('letter_photo'))
            ->whereHas('order', fn ($q) => $q->where('status', '!=', 'cancelled'))
            ->latest('id')
            ->limit(50)
            ->get();
    }
}
