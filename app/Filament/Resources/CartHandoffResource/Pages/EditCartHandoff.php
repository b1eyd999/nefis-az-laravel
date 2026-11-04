<?php

namespace App\Filament\Resources\CartHandoffResource\Pages;

use App\Filament\Resources\CartHandoffResource;
use App\Models\Product;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditCartHandoff extends EditRecord
{
    protected static string $resource = CartHandoffResource::class;

    public function getTitle(): string
    {
        return $this->record->forWhom();
    }

    public function getSubheading(): ?string
    {
        return $this->record->pieces() . ' ədəd · ' . $this->record->totalLabel()
            . ' · ' . $this->record->stateLabel();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('add')
                ->label('Məhsul əlavə et')
                ->icon('heroicon-o-plus')
                ->modalHeading('Səbətə məhsul əlavə et')
                ->modalSubmitActionLabel('Əlavə et')
                ->form(fn () => $this->addForm())
                ->action(function (array $data, Actions\Action $action) {
                    $product = Product::find($data['product_id']);

                    if (! $product) {
                        return;
                    }

                    [$paths, $labels] = $this->storePhotos($product, $data);

                    /* Checked here and not only on the upload field: that
                       field only exists once a design has been chosen, so a
                       submission that never rendered it would otherwise put
                       a blank box in the basket. Nothing downstream can mend
                       one — the customer's basket page offers no way to open
                       a line and add a picture. */
                    if ($paths === [] && $product->photoSlots()->exists()) {
                        Notification::make()->danger()
                            ->title('Şəkil lazımdır')
                            ->body('«' . $product->name . '» şəkilsiz hazırlanmır. Şəkli yükləyin.')
                            ->send();

                        $action->halt();
                    }

                    $this->record->addItem(
                        productId: $product->id,
                        quantity: (int) ($data['quantity'] ?? 1),
                        photoPaths: $paths,
                        customTexts: array_values(array_filter((array) ($data['texts'] ?? []), 'strlen')),
                        photoLabels: $labels,
                        textLabels: $this->textLabels($product),
                    );

                    Notification::make()->success()->title('Səbətə əlavə olundu')->send();

                    // The form is filled from the record, and the record has
                    // just changed under it: without this the new line only
                    // appears after a reload, and saving would drop it.
                    $this->fillForm();
                }),

            Actions\Action::make('open')
                ->label('Səbəti aç')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn () => $this->record->url())
                ->openUrlInNewTab(),

            Actions\DeleteAction::make()->label('Sil'),
        ];
    }

    /**
     * What has to be asked before a design can be put in a basket.
     *
     * A box with a photograph on it is still built in the design page — that
     * is where a customer zooms and turns his picture, and none of that can
     * be asked for in a modal. What can be done here is the rest: a design
     * that needs no photograph at all goes in with one tap, and one that
     * does gets its pictures and its words asked for, slot by slot, so a
     * blank box can never reach the checkout.
     */
    private function addForm(): array
    {
        return [
            Forms\Components\Select::make('product_id')
                ->label('Dizayn')
                ->options(fn () => Product::where('is_active', true)
                    ->orderBy('sort_order')->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->required()
                ->live()
                /* The rows are put there the moment a design is chosen, and
                   not left to the repeaters' own defaults: a default is read
                   once, when the form is first filled, and at that moment no
                   design has been picked — so the fields stayed empty and the
                   box could not be given its photograph at all. */
                ->afterStateUpdated(function (mixed $state, Forms\Set $set): void {
                    $set('photos', array_map(
                        fn (array $row) => ['label' => $row['label'], 'file' => null],
                        $this->photoRows($state)
                    ));
                    $set('texts', array_fill(0, $this->textCount($state), ''));
                }),

            Forms\Components\TextInput::make('quantity')
                ->label('Ədəd')->numeric()->default(1)->minValue(1)->maxValue(999)->required(),

            Forms\Components\Placeholder::make('hint')
                ->label('')
                ->visible(fn (Forms\Get $get) => $this->slotCount($get('product_id')) > 0)
                ->content('Bu dizayn şəkil istəyir. Şəkli burada yükləyin — boş qutu səbətə düşməsin.'),

            // One upload and one line of text per slot the design really has.
            Forms\Components\Repeater::make('photos')
                ->label('Şəkillər')
                ->visible(fn (Forms\Get $get) => $this->slotCount($get('product_id')) > 0)
                ->schema([
                    /* The slot's own name — «Üz şəkli», «Arxa şəkli» — rides
                       along as a field of the row rather than as a loose key
                       beside it, so that the row's head, the upload's own
                       label and the line written into the basket all read it
                       from something the form actually declares. */
                    Forms\Components\Hidden::make('label'),
                    Forms\Components\FileUpload::make('file')
                        ->label(fn (Forms\Get $get) => $get('label') ?: 'Şəkil')
                        ->image()->disk('public')->directory('cart-photos')->maxSize(8192)
                        /* Required, because nothing downstream can mend it:
                           the customer's basket page offers no way to open a
                           line and put a picture in, so a box added blank
                           here reaches the checkout blank. */
                        ->required(),
                ])
                ->addable(false)->deletable(false)->reorderable(false)
                ->itemLabel(fn (array $state) => $state['label'] ?? null),

            Forms\Components\Repeater::make('texts')
                ->label('Yazılar')
                ->visible(fn (Forms\Get $get) => $this->textCount($get('product_id')) > 0)
                ->simple(Forms\Components\TextInput::make('text')->label('')->maxLength(200))
                ->addable(false)->deletable(false)->reorderable(false),
        ];
    }

    private function slotCount(mixed $productId): int
    {
        return $productId ? (int) Product::find($productId)?->photoSlots()->count() : 0;
    }

    private function textCount(mixed $productId): int
    {
        return $productId ? (int) Product::find($productId)?->textSlots()->count() : 0;
    }

    /** @return array<int, array{label: string}> */
    private function photoRows(mixed $productId): array
    {
        if (! $productId) {
            return [];
        }

        return Product::find($productId)?->photoSlots()->orderBy('sort_order')->get()
            ->map(fn ($slot) => ['label' => $slot->label ?: 'Şəkil'])->all() ?? [];
    }

    /** @return array<int, string> */
    private function textLabels(Product $product): array
    {
        return $product->textSlots()->orderBy('sort_order')->get()
            ->map(fn ($slot) => (string) ($slot->label ?: 'Yazı'))->all();
    }

    /**
     * The uploaded pictures, kept where the cart keeps its own.
     *
     * Beside the customers' own uploads on purpose: the basket is handed to
     * a customer and the checkout reads these paths exactly as it reads his,
     * and the sweeper that clears stray photographs already knows the folder.
     *
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function storePhotos(Product $product, array $data): array
    {
        $paths = [];
        $labels = [];

        $named = $this->photoRows($product->id);

        foreach (array_values((array) ($data['photos'] ?? [])) as $i => $row) {
            $file = $row['file'] ?? null;

            if (blank($file)) {
                continue;
            }

            /* Filament has already put the file on the disk and hands back
               its path; nothing is moved, only recorded. */
            $paths[] = is_array($file) ? (string) reset($file) : (string) $file;
            $labels[] = $row['label'] ?? ($named[$i]['label'] ?? 'Şəkil');
        }

        return [$paths, $labels];
    }

    /**
     * Only the two things this form may change are written back.
     *
     * A line carries far more than it shows — the frames a photograph sits
     * in, a star map's date and place, a song, a wrapping. The repeater only
     * knows the quantity, so the quantities are merged into the lines the
     * record already has rather than the record being replaced by what the
     * form happens to hold.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $kept = [];

        foreach ((array) ($data['items'] ?? []) as $row) {
            $kept[(string) ($row['id'] ?? '')] = max(1, (int) ($row['quantity'] ?? 1));
        }

        $items = [];

        foreach ($this->record->items ?? [] as $item) {
            $id = (string) ($item['id'] ?? '');

            // A line the owner removed in the form is simply not carried over.
            if (! array_key_exists($id, $kept)) {
                continue;
            }

            $item['quantity'] = $kept[$id];
            $items[] = $item;
        }

        $data['items'] = $items;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
