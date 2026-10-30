<?php

namespace App\Filament\Pages;

use App\Models\Product;
use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Which designs lead the home page.
 *
 * The catalogue opens straight under the banner, and its order came from
 * `sort_order` alone — a number set by dragging rows in a table of fifty-odd
 * designs. Dragging one from the bottom of that table to the top is not
 * something anyone does twice, so in practice the front of the shop never
 * changed. Here the owner adds the designs he wants first, in the order he
 * wants them, and everything else keeps the order it already had.
 *
 * Only designs that can actually appear are offered — a hidden one, or one
 * drawn for a single company, is not on the public shelf. One already picked
 * and since hidden stays in the list rather than vanishing from it, labelled
 * so the reason it is not on the page is visible instead of mysterious.
 */
class HomeOrder extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Ana səhifə';

    protected static ?string $title = 'Ana səhifədə dizaynların sırası';

    protected static ?string $slug = 'home-order';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.home-order';

    public ?array $data = [];

    /** Remembered for the length of one render, never longer. */
    private ?array $designs = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public function mount(): void
    {
        $this->form->fill([
            'rows' => array_map(
                fn (int $id) => ['product_id' => $id],
                array_values(array_filter(
                    Setting::homeFirst(),
                    fn (int $id) => array_key_exists($id, $this->designs()),
                )),
            ),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Section::make('Öndə duracaq dizaynlar')
                    ->description('Burada yazdığınız dizaynlar ana səhifədə ən başda, bu sıra ilə görünür. '
                        .'Qalanları həmişəki sırasında arxadan gəlir. Kompüterdə bir sətirdə dörd dizayn, '
                        .'telefonda iki dizayn durur — yəni ilk sətir üçün dörd ədəd seçin.')
                    ->schema([
                        Forms\Components\Repeater::make('rows')
                            ->label('Dizaynlar')
                            ->hiddenLabel()
                            ->addActionLabel('Dizayn əlavə et')
                            ->reorderable()
                            ->reorderableWithDragAndDrop()
                            ->itemLabel(fn (array $state) => $this->designs()[$state['product_id'] ?? 0] ?? 'Dizayn seçin')
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('Dizayn')
                                    ->hiddenLabel()
                                    ->options(fn () => $this->designs())
                                    ->searchable()
                                    ->required()
                                    /* The same design twice would take two places in
                                       the front row and push a different one out. */
                                    ->distinct()
                                    ->columnSpanFull(),
                            ])
                            ->columns(1),
                    ]),
            ]);
    }

    public function save(): void
    {
        $picked = array_map(
            fn (array $row) => (int) ($row['product_id'] ?? 0),
            array_values($this->form->getState()['rows'] ?? []),
        );

        Setting::put(Setting::HOME_FIRST, json_encode($picked));

        /* Read back through the one place that cleans this list, so what the
           message says is what the home page will do — rather than a second
           copy of the same rules, kept in step by hope. */
        $shown = Setting::homeFirst();

        Notification::make()
            ->title($shown === []
                ? 'Sıra götürüldü — ana səhifə həmişəki sırasına qayıtdı.'
                : 'Yadda saxlanıldı. Ana səhifədə indi bu '.count($shown).' dizayn öndədir.')
            ->success()
            ->send();
    }

    /**
     * Every design that may be picked, by id.
     *
     * The public shelf, plus anything already picked that has since left it —
     * dropping those silently would lose the owner's choice the moment he
     * hides a design for an afternoon.
     *
     * @return array<int, string>
     */
    private function designs(): array
    {
        if ($this->designs !== null) {
            return $this->designs;
        }

        $shelf = Product::onTheShelf()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        $missing = array_diff(Setting::homeFirst(), array_keys($shelf));

        foreach (Product::whereIn('id', $missing)->pluck('name', 'id') as $id => $name) {
            $shelf[$id] = $name.' (saytda görünmür)';
        }

        return $this->designs = $shelf;
    }
}
