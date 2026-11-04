<?php

namespace App\Filament\Resources\CartHandoffResource\Pages;

use App\Filament\Resources\CartHandoffResource;
use App\Models\OrderItem;
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

                    $paths = $this->storePhotos($data);

                    /* Checked here and not only on the upload field: that
                       field only exists once a design has been chosen, so a
                       submission that never rendered it would otherwise put
                       a blank box in the basket. Nothing downstream can mend
                       one — the customer's basket page offers no way to open
                       a line and add a picture. */
                    if ($paths === [] && $this->photoRows($product->id) !== []) {
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
                        /* Every slot keeps its place, empty ones included.
                           The names beside them are a list of their own, read
                           by position — drop the blanks and the second
                           caption is printed under the first one's name. */
                        customTexts: array_map(
                            fn ($row) => (string) ($row['value'] ?? ''),
                            array_values((array) ($data['texts'] ?? []))
                        ),
                        /* The shop's own two lists, not names made up here:
                           the basket is handed to a customer and read from
                           then on by the ordinary cart, the checkout and the
                           order screen, each of which expects these exactly
                           as the design page writes them. */
                        photoLabels: OrderItem::photoLabelsFor($product),
                        textLabels: OrderItem::textLabelsFor($product),
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
                    $set('photos', $this->photoRows($state));
                    $set('texts', $this->textRows($state));
                }),

            Forms\Components\TextInput::make('quantity')
                ->label('Ədəd')->numeric()->default(1)->minValue(1)->maxValue(999)->required(),

            Forms\Components\Placeholder::make('hint')
                ->label('')
                ->visible(fn (Forms\Get $get) => $this->photoRows($get('product_id')) !== [])
                ->content('Bu dizayn şəkil istəyir. Şəkli burada yükləyin — boş qutu səbətə düşməsin.'),

            // One upload and one line of text per slot the design really has.
            Forms\Components\Repeater::make('photos')
                ->label('Şəkillər')
                ->visible(fn (Forms\Get $get) => $this->photoRows($get('product_id')) !== [])
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
                ->visible(fn (Forms\Get $get) => $this->textRows($get('product_id')) !== [])
                ->schema([
                    Forms\Components\Hidden::make('label'),
                    Forms\Components\Hidden::make('fixed'),
                    Forms\Components\TextInput::make('value')
                        ->label(fn (Forms\Get $get) => $get('label') ?: 'Yazı')
                        ->maxLength(200)
                        /* A line the design writes itself, or one the night
                           sky fills in, is shown but not typed over — and
                           read-only rather than disabled, because a disabled
                           field comes back empty and the slot beneath it
                           would be printed blank. */
                        ->readOnly(fn (Forms\Get $get) => (bool) $get('fixed'))
                        ->helperText(fn (Forms\Get $get) => $get('fixed')
                            ? 'Dizaynın öz yazısı — dəyişmir.'
                            : null),
                ])
                ->addable(false)->deletable(false)->reorderable(false)
                ->itemLabel(fn (array $state) => $state['label'] ?? null),
        ];
    }

    /**
     * One upload row per window the customer would be asked to fill.
     *
     * Named by the shop's own list rather than by the slots' labels: a window
     * the night sky or a street map fills asks for nothing, and counting it
     * here would both demand a photograph that does not exist and push every
     * name one place along the line written into the basket.
     *
     * @return array<int, array{label: string, file: null}>
     */
    private function photoRows(mixed $productId): array
    {
        $product = $productId ? Product::find($productId) : null;

        if (! $product) {
            return [];
        }

        return array_map(
            fn (string $label) => ['label' => $label, 'file' => null],
            OrderItem::photoLabelsFor($product)
        );
    }

    /**
     * One row per line of writing on the box, in the design's own order.
     *
     * Every slot gets a row, the design's own wording included, because the
     * values are read back by position: leave one out and the next caption
     * is printed in its place.
     *
     * @return array<int, array{label: string, fixed: bool, value: string}>
     */
    private function textRows(mixed $productId): array
    {
        $product = $productId ? Product::find($productId) : null;

        if (! $product) {
            return [];
        }

        return $product->textSlots->values()->map(fn ($slot) => [
            'label' => (string) ($slot->label ?: 'Mətn'),
            'fixed' => $slot->isGiven(),
            /* The design's own wording is carried over; a caption the sky
               writes is left empty, because there is no sky on a line put in
               from here — no date and no place were ever asked for. */
            'value' => $slot->fixed ? (string) $slot->default_value : '',
        ])->all();
    }

    /**
     * The uploaded pictures, kept where the cart keeps its own.
     *
     * Beside the customers' own uploads on purpose: the basket is handed to
     * a customer and the checkout reads these paths exactly as it reads his,
     * and the sweeper that clears stray photographs already knows the folder.
     *
     * @return array<int, string>
     */
    private function storePhotos(array $data): array
    {
        $paths = [];

        foreach (array_values((array) ($data['photos'] ?? [])) as $row) {
            $file = $row['file'] ?? null;

            if (blank($file)) {
                continue;
            }

            /* Filament has already put the file on the disk and hands back
               its path; nothing is moved, only recorded. */
            $paths[] = is_array($file) ? (string) reset($file) : (string) $file;
        }

        return $paths;
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
