<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use App\Models\Chocolate;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Wrapping;
use App\Support\Media;
use App\Support\OrderEditor;
use App\Support\Price;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Sifariş məhsulları';

    /**
     * Every price here is frozen on the line: `OrderItem::unitPrice()` adds up
     * the line's own five columns and never asks the product what it costs
     * today. So editing an order cannot quietly re-price what the customer
     * already bought — which is exactly why the owner may edit it at all.
     */
    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('product_id')
                ->label('Dizayn')
                ->options(fn () => Product::orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->helperText('Boş buraxsanız sətir yalnız məktub və ya canlı şəkil olur.')
                ->live()
                ->afterStateUpdated(function ($state, Forms\Set $set) {
                    $product = $state ? Product::find($state) : null;
                    $set('product_name', $product?->name);
                    $set('price', $product?->price);
                }),
            Forms\Components\TextInput::make('product_name')
                ->label('Adı (sifarişdə saxlanan)')
                ->maxLength(255),
            Forms\Components\TextInput::make('quantity')
                ->label('Say')->numeric()->minValue(1)->default(1)->required(),
            Forms\Components\TextInput::make('price')
                ->label('Qutunun qiyməti, ₼')->numeric()->step(0.01)->minValue(0),

            Forms\Components\Select::make('chocolate_id')
                ->label('Şokolad')
                ->options(fn () => Chocolate::orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->live()
                ->afterStateUpdated(function ($state, Forms\Set $set) {
                    $bar = $state ? Chocolate::find($state) : null;
                    $set('chocolate_name', $bar?->name);
                    $set('chocolate_price', $bar?->price());
                    $set('chocolate_cost', $bar?->costPrice());
                }),
            Forms\Components\TextInput::make('chocolate_name')->label('Şokoladın adı')->maxLength(255),
            Forms\Components\TextInput::make('chocolate_price')->label('Şokoladın qiyməti, ₼')->numeric()->step(0.01)->minValue(0),
            Forms\Components\TextInput::make('chocolate_cost')
                ->label('Şokoladın maya dəyəri, ₼')->numeric()->step(0.01)->minValue(0)
                ->helperText('Kitablar üçün — müştəri görmür.'),

            Forms\Components\Select::make('wrapping_id')
                ->label('Qablaşdırma')
                ->options(fn () => Wrapping::orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->live()
                ->afterStateUpdated(function ($state, Forms\Set $set) {
                    $wrap = $state ? Wrapping::find($state) : null;
                    $set('wrapping_name', $wrap?->name);
                    $set('wrapping_price', $wrap?->price);
                }),
            Forms\Components\TextInput::make('wrapping_name')->label('Qablaşdırmanın adı')->maxLength(255),
            Forms\Components\TextInput::make('wrapping_price')->label('Qablaşdırmanın qiyməti, ₼')->numeric()->step(0.01)->minValue(0),

            Forms\Components\TextInput::make('letter_price')
                ->label('Məktub, ₼')->numeric()->step(0.01)->minValue(0)
                ->helperText('Müştəri məktubdan imtina etdisə — 0 yazın.'),
            Forms\Components\TextInput::make('ar_price')
                ->label('Canlı şəkil, ₼')->numeric()->step(0.01)->minValue(0)
                ->helperText('Müştəri canlandırmadan imtina etdisə — 0 yazın.'),

            // Not a column: the words the customer reads on the payment page.
            Forms\Components\TextInput::make('reason')
                ->label('Dəyişikliyin səbəbi')
                ->helperText('Sifariş artıq ödənilibsə, fərq bu adla yazılır və müştəri bunu görür.')
                ->maxLength(255)
                ->visible(fn (RelationManager $livewire) => (bool) $livewire->getOwnerRecord()?->isPaidFor()),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        $admin = fn () => (bool) auth()->user()?->isAdmin();

        return $table
            ->recordTitleAttribute('id')
            ->columns([
                // On a phone only "what the customer sent" stays, with the rest summed up at its
                // top (see filament.order-item-fields): side by side it all ran off the screen.
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Məhsul')
                    ->visibleFrom('md')
                    // The name was kept on the order line for when the design is gone.
                    ->getStateUsing(fn ($record) => $record->product?->name ?? $record->product_name ?? 'Silinmiş məhsul'),
                Tables\Columns\ImageColumn::make('product.template_image')
                    ->label('Qutu dizaynı')
                    // A letter bought on its own has no box: its photo stands in.
                    ->getStateUsing(fn ($record) => $record->product ? Media::url($record->product->catalogImage()) : $record->letterPhotoUrl())
                    ->square()
                    ->size(80)
                    ->visibleFrom('md'),
                // The photos and captions under the names the customer filled
                // them in, as on the design's page.
                Tables\Columns\ViewColumn::make('fields')
                    ->label('Müştərinin göndərdiyi')
                    ->view('filament.order-item-fields'),
                Tables\Columns\TextColumn::make('chocolate_name')
                    ->label('Şokolad')
                    ->description(fn ($record) => $record->chocolate_price ? Price::format($record->chocolate_price) : null)
                    ->placeholder('—')
                    ->wrap()
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('wrapping_name')
                    ->label('Qablaşdırma')
                    ->description(fn ($record) => $record->wrapping_price ? Price::format($record->wrapping_price) : null)
                    ->placeholder('—')
                    ->wrap()
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Say')
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('price')
                    ->label('Qiymət')
                    ->visibleFrom('md')
                    // The box and the bar each, then the line's total.
                    ->getStateUsing(fn ($record) => $record->unitPrice() > 0 ? Price::format($record->unitPrice() * $record->quantity) : '—')
                    ->description(fn ($record) => ($record->chocolate_price || $record->wrapping_price)
                        ? 'qutu ' . ($record->price ? Price::format($record->price) : '—')
                            . ($record->chocolate_price ? ' + şokolad ' . Price::format($record->chocolate_price) : '')
                            . ($record->wrapping_price ? ' + qablaşdırma ' . Price::format($record->wrapping_price) : '')
                            . ($record->quantity > 1 ? ' × ' . $record->quantity : '')
                        : null),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Sətir əlavə et')
                    ->visible($admin)
                    ->modalHeading('Sifarişə əlavə')
                    ->using(function (array $data, RelationManager $livewire) {
                        $order = $livewire->getOwnerRecord();
                        $reason = self::pullReason($data, 'Sifarişə əlavə edildi');
                        $item = null;

                        $money = OrderEditor::change($order, $reason, function () use ($order, $data, &$item) {
                            // Labels are written now, from the design as it is
                            // today: left empty, OrderItem::fields() would read
                            // them off a design that may since have changed.
                            $product = $data['product_id'] ? Product::find($data['product_id']) : null;
                            $item = $order->items()->create($data + [
                                'customer_photos' => [],
                                'custom_texts' => [],
                                'photo_labels' => $product ? OrderItem::photoLabelsFor($product) : null,
                                'text_labels' => $product ? OrderItem::textLabelsFor($product) : null,
                            ]);
                        });

                        self::tell($money);

                        return $item;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible($admin)
                    ->modalHeading('Sətri dəyiş')
                    ->using(function (OrderItem $record, array $data, RelationManager $livewire) {
                        $order = $livewire->getOwnerRecord();
                        $reason = self::pullReason($data, 'Sifariş dəyişdirildi');

                        $money = OrderEditor::change($order, $reason, fn () => $record->update($data));
                        self::tell($money);

                        return $record;
                    }),
                Tables\Actions\DeleteAction::make()
                    ->visible($admin)
                    ->modalDescription('Bu sətrin materialları anbara qaytarılacaq və sifarişin məbləği dəyişəcək. Sifariş artıq ödənilibsə, fərq müştəriyə qaytarılmalı kimi yazılacaq.')
                    ->using(function (OrderItem $record, RelationManager $livewire) {
                        $order = $livewire->getOwnerRecord();
                        $money = OrderEditor::change($order, 'Sətir silindi', fn () => $record->delete());
                        self::tell($money);
                    }),
            ]);
    }

    /**
     * The owner's own words for the change, taken out of the form data: it is
     * a field on the form but not a column on the line, so it must not reach
     * the create or the update.
     */
    private static function pullReason(array &$data, string $fallback): string
    {
        $typed = $data['reason'] ?? null;
        unset($data['reason']);

        return filled($typed) ? (string) $typed : $fallback;
    }

    /** Says plainly what the change did to the money, if it did anything. */
    private static function tell(?\App\Models\OrderAdjustment $money): void
    {
        if (! $money) {
            return;
        }

        Notification::make()
            ->warning()
            ->title($money->isCharge()
                ? 'Müştəri ' . Price::format((float) $money->amount) . ' əlavə ödəməlidir'
                : 'Müştəriyə ' . Price::format((float) $money->amount) . ' qaytarılmalıdır')
            ->body($money->isCharge()
                ? 'Aşağıdakı "Sonradan edilən dəyişikliklər" cədvəlindən ödəniş linkini götürüb müştəriyə göndərin.'
                : 'Pulu ePoint kabinetindən geri göndərin, sonra həmin cədvəldə "Qaytarıldı" düyməsini basın.')
            ->persistent()
            ->send();
    }
}
