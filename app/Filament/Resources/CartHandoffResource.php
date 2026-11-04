<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AdminOnly;
use App\Filament\Resources\CartHandoffResource\Pages;
use App\Models\CartHandoff;
use App\Models\User;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

/**
 * The baskets the shop has filled for customers who could not fill them.
 *
 * A basket is still built on the site, in the same design page a customer
 * would use, and turned into a link from the basket page — that is the only
 * way a box with a photograph and words on it can be made. What this screen
 * adds is everything afterwards: opening a basket to see what is in it,
 * changing how many of a line, taking a line out, and putting in a design
 * that needs no photograph.
 */
class CartHandoffResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = CartHandoff::class;

    protected static ?string $navigationIcon = 'heroicon-o-link';

    protected static ?string $navigationLabel = 'Hazır səbətlər';

    protected static ?string $navigationGroup = 'Mağaza';

    protected static ?string $modelLabel = 'hazır səbət';

    protected static ?string $pluralModelLabel = 'hazır səbətlər';

    protected static ?int $navigationSort = 3;

    /** Sent and not yet ordered: the ones still worth a message. */
    public static function getNavigationBadge(): ?string
    {
        $waiting = static::getModel()::whereNull('order_id')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Section::make('Səbətin içi')
                ->description('Sətri silə və sayını dəyişə bilərsiniz. Yeni sətir yuxarıdakı '
                    . '«Məhsul əlavə et» düyməsi ilə qoyulur.')
                ->schema([
                    Forms\Components\Repeater::make('items')
                        ->label('')
                        ->addable(false)
                        ->reorderable(false)
                        ->deletable()
                        ->deleteAction(fn (Forms\Components\Actions\Action $action) => $action->label('Sətri sil'))
                        ->itemLabel(fn (array $state) => self::lineTitle($state))
                        ->schema([
                            Forms\Components\Hidden::make('id'),
                            Forms\Components\Placeholder::make('what')
                                ->label('')
                                ->content(fn (Forms\Get $get) => self::lineDetail((string) $get('id'), $get('../../items'))),
                            Forms\Components\TextInput::make('quantity')
                                ->label('Ədəd')
                                ->numeric()->minValue(1)->maxValue(999)->required(),
                        ])
                        ->columns(1),
                ]),

            Forms\Components\Section::make('Səbət haqqında')
                ->schema([
                    Forms\Components\TextInput::make('note')
                        ->label('Kimin üçün')
                        ->helperText('Yalnız sizin üçün — müştəri bunu görmür.')
                        ->maxLength(120),
                    Forms\Components\Toggle::make('rush')
                        ->label('Təcili hazırlansın')
                        ->helperText('Müştəri səbəti açanda bu seçim onda da qalır.'),
                    Forms\Components\DateTimePicker::make('expires_at')
                        ->label('Nə vaxta qədər açıqdır')
                        ->seconds(false)
                        ->helperText('Bu tarixdən sonra link açılmır.'),
                ])->columns(2),
        ]);
    }

    /** The one line of a basket, as the repeater's closed row says it. */
    public static function lineTitle(array $item): string
    {
        $name = isset($item['product_id']) ? \App\Models\Product::find($item['product_id'])?->name : null;

        if ($name === null) {
            $name = match (true) {
                \App\Support\Cart::isLetter($item) => 'Polaroid məktub',
                \App\Support\Cart::isLive($item) => 'Canlı şəkil',
                default => 'Silinmiş dizayn',
            };
        }

        return $name . ' × ' . max(1, (int) ($item['quantity'] ?? 1));
    }

    /**
     * What is actually on that line: the pictures the customer's box carries
     * and the words printed on it. Read from the record rather than from the
     * row's own state, because the state holds only the two fields the form
     * edits — everything else would be lost to the placeholder.
     */
    public static function lineDetail(string $id, mixed $items): HtmlString
    {
        $item = collect(is_array($items) ? $items : [])->firstWhere('id', $id) ?: [];

        $bits = [];

        foreach (array_filter((array) ($item['custom_texts'] ?? [])) as $i => $text) {
            $bits[] = '<div><b>' . e(self::textLabel($item, $i)) . ':</b> '
                . e(is_scalar($text) ? (string) $text : '') . '</div>';
        }

        $shots = '';
        foreach (array_filter((array) ($item['photo_paths'] ?? [])) as $path) {
            if (! is_string($path)) {
                continue;
            }

            $url = \App\Support\Media::url($path);
            if ($url) {
                $shots .= '<img src="' . e($url) . '" alt="" style="height:88px;border-radius:.5rem;'
                    . 'object-fit:cover;border:1px solid rgba(0,0,0,.1)">';
            }
        }
        if ($shots !== '') {
            $bits[] = '<div style="display:flex;gap:.4rem;flex-wrap:wrap;margin-top:.4rem">' . $shots . '</div>';
        }

        foreach (['chocolate' => 'Şokolad', 'wrapping' => 'Qablaşdırma', 'letter' => 'Məktub',
            'ar' => 'Canlı şəkil', 'spotify' => 'Mahnı'] as $key => $label) {
            if (filled($item[$key] ?? null)) {
                $bits[] = '<div><b>' . $label . ':</b> ' . __('var') . '</div>';
            }
        }

        if ($bits === []) {
            $bits[] = '<div style="opacity:.6">' . __('Şəkil və yazı yoxdur.') . '</div>';
        }

        return new HtmlString('<div style="font-size:.85rem;line-height:1.6">' . implode('', $bits) . '</div>');
    }

    /**
     * What the shop calls the n-th line of writing on a box.
     *
     * A line's `text_labels` is not a list of names. It is a list of
     * `['label' => …, 'fixed' => …, 'repeat' => …]`, which is what the design
     * page writes and what the order screen reads — and printing one of them
     * straight is what turned this whole page into a «500 Server Error» the
     * first time the owner opened a real basket.
     *
     * A plain string is still accepted: baskets written before the shop kept
     * the names at all, and the shorter shape this screen itself wrote for a
     * day, both have one.
     */
    private static function textLabel(array $item, int $i): string
    {
        $label = ((array) ($item['text_labels'] ?? []))[$i] ?? null;

        if (is_array($label)) {
            $label = $label['label'] ?? null;
        }

        return is_scalar($label) && (string) $label !== '' ? (string) $label : 'Yazı';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Hazırlanıb')
                    ->dateTime('d.m.Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('note')->label('Kimin üçün')
                    ->placeholder('—')->searchable()->wrap()->weight('bold')
                    ->state(fn (CartHandoff $row) => $row->forWhom())
                    // A basket already sitting in somebody's account says so,
                    // so the owner does not send him a link as well.
                    ->description(fn (CartHandoff $row) => $row->user_id
                        ? 'Səbətinə atılıb' . ($row->given_at ? ' · ' . $row->given_at->format('d.m H:i') : '')
                        : null),
                Tables\Columns\TextColumn::make('pieces')->label('Ədəd')
                    ->state(fn (CartHandoff $row) => $row->pieces()),
                Tables\Columns\TextColumn::make('total')->label('Məbləğ')
                    ->state(fn (CartHandoff $row) => $row->totalLabel()),
                Tables\Columns\TextColumn::make('state')->label('Vəziyyət')
                    ->badge()
                    ->state(fn (CartHandoff $row) => $row->stateLabel())
                    ->color(fn (CartHandoff $row) => match ($row->state()) {
                        'ordered' => 'success',
                        'expired' => 'gray',
                        'opened' => 'warning',
                        default => 'info',
                    }),
                Tables\Columns\TextColumn::make('order_id')->label('Sifariş')
                    ->placeholder('—')
                    ->url(fn (CartHandoff $row) => $row->order_id
                        ? OrderResource::getUrl('edit', ['record' => $row->order_id])
                        : null)
                    ->formatStateUsing(fn ($state) => $state ? '#' . $state : '—'),
                Tables\Columns\TextColumn::make('maker.name')->label('Kim hazırlayıb')
                    ->placeholder('—')->visibleFrom('lg'),
            ])
            ->actions([
                /* Straight into a customer's own basket.
                   The link below is for somebody the shop only knows from
                   Instagram; this is for somebody who already has an account.
                   He is not written to and nothing is reserved — the next
                   time he opens the shop the box is in his basket, on
                   whichever telephone he opens it. */
                Tables\Actions\Action::make('give')
                    ->label('Səbətinə at')
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->visible(fn (CartHandoff $row) => $row->isOpen())
                    ->modalHeading('Səbəti müştəriyə at')
                    ->modalDescription('Seçdiyiniz müştəri saytı açanda bu qutu onun səbətində olacaq. Onun öz səbətindəki məhsullar silinmir.')
                    ->modalSubmitActionLabel('At')
                    ->form([
                        Forms\Components\Select::make('user_id')
                            ->label('Müştəri')
                            ->placeholder('Ad, telefon və ya e-poçt yazın')
                            ->required()
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search) => User::query()
                                ->where(fn ($q) => $q
                                    ->where('name', 'like', '%' . $search . '%')
                                    ->orWhere('phone', 'like', '%' . $search . '%')
                                    ->orWhere('email', 'like', '%' . $search . '%'))
                                ->orderBy('name')->limit(25)
                                ->get()
                                ->mapWithKeys(fn (User $u) => [$u->id => trim($u->name . ' — ' . ($u->phone ?: $u->email))])
                                ->all())
                            ->getOptionLabelUsing(fn ($value) => ($u = User::find($value))
                                ? trim($u->name . ' — ' . ($u->phone ?: $u->email))
                                : null)
                            ->helperText('Yalnız qeydiyyatdan keçmiş müştərilər. Hesabı yoxdursa, aşağıdakı linki göndərin.'),
                    ])
                    ->action(function (CartHandoff $row, array $data) {
                        $customer = User::find($data['user_id']);
                        if (! $customer) {
                            Notification::make()->danger()->title('Müştəri tapılmadı')->send();

                            return;
                        }

                        $added = $row->giveTo($customer);

                        Notification::make()
                            ->success()
                            ->title($customer->name . ' — səbətinə atıldı')
                            ->body($added . ' sətir əlavə olundu. O, sayta girəndə görəcək.')
                            ->send();
                    }),
                /* The link itself, to copy out of the panel and paste into
                   whichever conversation it belongs to. */
                Tables\Actions\EditAction::make()->label('Bax')->icon('heroicon-o-eye'),
                Tables\Actions\Action::make('link')
                    ->label('Link')
                    ->icon('heroicon-o-clipboard')
                    ->modalHeading('Səbətin linki')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Bağla')
                    ->modalContent(fn (CartHandoff $row) => new HtmlString(
                        '<p style="word-break:break-all; font-family:ui-monospace,monospace; font-size:.85rem; '
                        . 'padding:.7rem .8rem; border-radius:.6rem; background:rgba(128,128,128,.12)">'
                        . e($row->url()) . '</p>'
                        . '<p style="font-size:.85rem; opacity:.8">'
                        . ($row->isOpen()
                            ? 'Müştəri bu linki açanda səbət onun qarşısına çıxır.'
                            : 'Bu link artıq işləmir.')
                        . '</p>'
                    )),
                Tables\Actions\DeleteAction::make()->label('Sil'),
            ])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()])
            ->emptyStateHeading('Hələ hazır səbət yoxdur')
            ->emptyStateDescription('Saytda qutunu özünüz yığın, səbətdə «Linki yarat» düyməsinə basın — link burada görünəcək.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCartHandoffs::route('/'),
            'edit' => Pages\EditCartHandoff::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        // A basket is built on the site, not typed into a form.
        return false;
    }
}
