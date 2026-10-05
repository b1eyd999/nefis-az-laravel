<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers;
use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\User;
use App\Support\Courier;
use App\Support\CustomerNotice;
use App\Support\DeliveryTime;
use App\Support\OrderEditor;
use App\Support\Price;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationLabel = 'Sifarişlər';

    protected static ?string $modelLabel = 'sifariş';

    protected static ?string $pluralModelLabel = 'sifarişlər';

    public const STATUSES = Order::STATUSES;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Sifariş məlumatı')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(self::STATUSES)
                            ->required(),
                        Forms\Components\TextInput::make('contact_phone')
                            ->label('Telefon')
                            ->tel(),
                        Forms\Components\Textarea::make('note')
                            ->label('Müştərinin qeydi')
                            ->columnSpanFull(),
                    ])->columns(2),
                // Paid by transfer: which account, and the receipt the customer sent.
                // Paid by card: the gateway's own answer, and nothing to check.
                Forms\Components\Section::make('Ödəniş')
                    ->schema([
                        Forms\Components\Placeholder::make('paid_by_card')
                            ->label('Kartla ödənilib')
                            ->content(fn (?Order $record) => 'ePoint'
                                .($record?->epoint_transaction ? ' · '.$record->epoint_transaction : '')
                                .($record?->payment_confirmed_at ? ' · '.$record->payment_confirmed_at->format('d.m.Y H:i') : ' · təsdiq gözlənilir'))
                            ->visible(fn (?Order $record) => $record?->payment_method === 'card')
                            ->columnSpanFull(),
                        Forms\Components\Placeholder::make('payment_account')
                            ->label('Hesab')
                            ->content(fn (?Order $record) => $record?->paymentAccount
                                ? $record->paymentAccount->typeLabel().' · '.$record->paymentAccount->label.' · '.$record->paymentAccount->formatted()
                                : 'Təyin olunmayıb')
                            ->visible(fn (?Order $record) => $record?->payment_method !== 'card'),
                        Forms\Components\Placeholder::make('receipt_at')
                            ->label('Çek göndərilib')
                            ->content(fn (?Order $record) => $record?->receipt_at?->format('d.m.Y H:i')
                                ?? ($record?->payment_confirmed_at ? 'Çeksiz təsdiqlənib' : 'Hələ yox'))
                            ->visible(fn (?Order $record) => $record?->payment_method !== 'card'),
                        Forms\Components\Placeholder::make('receipt')
                            ->label('Çek')
                            ->content(function (?Order $record) {
                                $url = $record?->receiptUrl();
                                if (! $url) {
                                    return 'Yoxdur';
                                }
                                $isPdf = str_ends_with(strtolower((string) $record->payment_receipt), '.pdf');

                                return new HtmlString($isPdf
                                    ? '<a href="'.e($url).'" target="_blank" rel="noopener" style="text-decoration:underline;">PDF çeki aç</a>'
                                    : '<a href="'.e($url).'" target="_blank" rel="noopener"><img src="'.e($url).'" alt="" style="max-height:320px;border-radius:.6rem"></a>');
                            })
                            ->columnSpanFull()
                            ->visible(fn (?Order $record) => $record?->payment_method !== 'card'),
                    ])
                    ->columns(2)
                    ->visible(fn (?Order $record) => $record?->payment_account_id || $record?->payment_receipt
                        || $record?->payment_method === 'card'),
                // How it goes out, as the customer chose it at checkout.
                Forms\Components\Section::make('Çatdırılma')
                    ->schema([
                        // The day the customer is waiting for: the one thing the
                        // shop works to, so it stands at the top of the section.
                        Forms\Components\Placeholder::make('delivery_when')
                            ->label('Nə vaxta')
                            ->content(fn (?Order $record) => $record?->delivery_date
                                ? DeliveryTime::day($record->delivery_date)
                                    .($record->delivery_slot ? ', '.$record->delivery_slot : '')
                                : 'Seçilməyib')
                            ->columnSpanFull(),
                        /* Who is carrying it: the courier the owner handed it
                           to, or the Telegram name of whoever tapped it in the
                           group first. Shown for both, and it says whether he
                           has set off. */
                        Forms\Components\Placeholder::make('courier')
                            ->label('Kuryer')
                            ->content(function (?Order $record) {
                                if (! $record?->courierLabel()) {
                                    return 'Hələ kimsə götürməyib';
                                }
                                $line = $record->courierLabel();
                                if ($record->courier?->phone) {
                                    $line .= ' · '.$record->courier->phone;
                                }
                                if ($record->courier_taken_at) {
                                    $line .= ' · '.$record->courier_taken_at->format('d.m.Y H:i');
                                }
                                if ($record->isOnTheWay()) {
                                    $line .= ' · yolda '.$record->on_the_way_at->format('H:i');
                                }

                                return $line;
                            })
                            ->visible(fn (?Order $record) => (bool) ($record?->courierLabel() || $record?->courier_chat_id)),
                        Forms\Components\Placeholder::make('delivery_method')
                            ->label('Üsul')
                            ->content(fn (?Order $record) => $record?->delivery_name
                                ? $record->delivery_name.' — '.($record->free_delivery
                                    ? 'pulsuz (siz bağışladınız'.($record->delivery_price > 0 ? ', '.Price::format($record->delivery_price) : '').')'
                                    : ($record->delivery_price > 0 ? Price::format($record->delivery_price) : 'pulsuz'))
                                : 'Seçilməyib (köhnə sifariş)'),
                        /* The owner waives the delivery. It is his decision
                           after the order exists — the checkout never offers
                           it — and on an order already paid for it is money
                           owed back, so it goes through OrderEditor like any
                           other change and leaves its own line behind. */
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('free_delivery')
                                ->label(fn (?Order $record) => $record?->free_delivery
                                    ? 'Çatdırılmanı yenidən ödənişli et'
                                    : 'Çatdırılmanı pulsuz et')
                                ->icon('heroicon-o-truck')
                                ->color(fn (?Order $record) => $record?->free_delivery ? 'gray' : 'warning')
                                ->visible(fn (?Order $record) => $record !== null
                                    && (bool) auth()->user()?->isAdmin()
                                    && ((float) ($record->delivery_price ?? 0) > 0 || $record->free_delivery))
                                ->requiresConfirmation()
                                ->modalHeading(fn (?Order $record) => $record?->free_delivery
                                    ? 'Çatdırılma yenidən ödənişli olsun?'
                                    : 'Çatdırılma pulsuz olsun?')
                                ->modalDescription(fn (?Order $record) => $record?->free_delivery
                                    ? 'Çatdırılmanın qiyməti sifarişə qayıdır: '.Price::format($record->delivery_price)
                                    : 'Sifarişin məbləğindən '.Price::format($record?->delivery_price ?? 0)
                                        .' düşəcək.'.($record?->isPaidFor()
                                            ? ' Sifariş artıq ödənilib, ona görə bu məbləğ müştəriyə qaytarılmalı kimi yazılacaq.'
                                            : ''))
                                ->action(function (Order $record, $livewire) {
                                    $free = ! $record->free_delivery;

                                    $money = OrderEditor::change(
                                        $record,
                                        $free ? 'Çatdırılma pulsuz edildi' : 'Çatdırılma yenidən ödənişli edildi',
                                        fn () => $record->forceFill(['free_delivery' => $free])->save(),
                                    );

                                    $note = Notification::make()
                                        ->success()
                                        ->title($free ? 'Çatdırılma pulsuzdur' : 'Çatdırılma yenidən ödənişlidir');

                                    if ($money) {
                                        $note->warning()
                                            ->title($money->isCharge()
                                                ? 'Müştəri '.Price::format((float) $money->amount).' əlavə ödəməlidir'
                                                : 'Müştəriyə '.Price::format((float) $money->amount).' qaytarılmalıdır')
                                            ->body('Aşağıdakı «Sonradan edilən dəyişikliklər» cədvəlinə baxın.')
                                            ->persistent();
                                    }

                                    $note->send();
                                    $livewire->redirect(OrderResource::getUrl('edit', ['record' => $record]));
                                }),
                        ])->columnSpanFull(),
                        Forms\Components\TextInput::make('recipient_name')
                            ->label('Ad və soyad')
                            ->visible(fn (?Order $record) => $record?->delivery_type === DeliveryMethod::POST),
                        Forms\Components\TextInput::make('postal_index')
                            ->label('Poçt şöbəsinin indeksi')
                            ->visible(fn (?Order $record) => $record?->delivery_type === DeliveryMethod::POST),
                        Forms\Components\TextInput::make('metro_station')
                            ->label('Metro stansiyası')
                            ->visible(fn (?Order $record) => $record?->delivery_type === DeliveryMethod::METRO),
                        Forms\Components\TextInput::make('delivery_address')
                            ->label('Ünvan')
                            ->visible(fn (?Order $record) => ! in_array($record?->delivery_type, [DeliveryMethod::POST, DeliveryMethod::METRO], true)),
                        Forms\Components\Placeholder::make('delivery_point')
                            ->label('Xəritədə')
                            ->content(fn (?Order $record) => new HtmlString(
                                '<a href="'.e($record->mapUrl()).'" target="_blank" rel="noopener" style="color:#d97706;font-weight:600;text-decoration:underline">Xəritədə aç ↗</a>'
                                .'<span style="opacity:.6;margin-left:.6rem">'.e(number_format($record->delivery_lat, 5).', '.number_format($record->delivery_lng, 5)).'</span>'))
                            ->visible(fn (?Order $record) => (bool) $record?->mapUrl()),
                        Forms\Components\Placeholder::make('rush')
                            ->label('Təcili')
                            ->content(fn (?Order $record) => 'Bəli, növbədənkənar, '.Price::format($record?->rush_fee ?? 0))
                            ->visible(fn (?Order $record) => (bool) $record?->isRush()),
                        Forms\Components\Placeholder::make('totals')
                            ->label('Məbləğ')
                            ->content(fn (?Order $record) => $record
                                ? 'Məhsullar '.Price::format($record->itemsTotal())
                                    .($record->hasDiscount()
                                        ? ' − endirim '.Price::format($record->discount)
                                            .' ('.$record->promo_code.', '.rtrim(rtrim(number_format((float) $record->promo_percent, 2, '.', ''), '0'), '.').'%)'
                                        : '')
                                    .' + çatdırılma '.Price::format($record->delivery_price ?? 0)
                                    .($record->isRush() ? ' + təcili '.Price::format($record->rush_fee) : '')
                                    .' = '.Price::format($record->total())
                                : '—')
                            ->columnSpanFull(),
                        // Once the order has been changed after payment, the
                        // total above is no longer what the customer handed
                        // over. This says who owes whom.
                        Forms\Components\Placeholder::make('settlement')
                            ->label('Ödəniş vəziyyəti')
                            ->content(function (?Order $record) {
                                if (! $record) {
                                    return '—';
                                }
                                $lines = ['Artıq ödənilib: '.Price::format($record->paidSoFar())];
                                if ($record->outstanding() > 0) {
                                    $lines[] = 'Müştəri əlavə ödəməlidir: '.Price::format($record->outstanding());
                                }
                                if ($record->owedBack() > 0) {
                                    $lines[] = 'Müştəriyə qaytarılmalıdır: '.Price::format($record->owedBack());
                                }

                                return new HtmlString(implode('<br>', array_map('e', $lines)));
                            })
                            ->visible(fn (?Order $record) => (bool) $record?->hasOpenAdjustments())
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    /**
     * Changing where an order stands without opening it: the status in the
     * list is tapped, the new one is picked, and that is it. Saved through
     * the model, so cancelling still puts the materials back in stock.
     */
    private static function statusAction(string $name): Tables\Actions\Action
    {
        return Tables\Actions\Action::make($name)
            ->label('Statusu dəyiş')
            ->icon('heroicon-o-arrow-path')
            ->modalHeading(fn (Order $record) => 'Sifariş #'.$record->id.' — status')
            ->modalSubmitActionLabel('Yadda saxla')
            ->modalWidth('sm')
            ->fillForm(fn (Order $record) => ['status' => $record->status])
            ->form([
                Forms\Components\Radio::make('status')
                    ->hiddenLabel()
                    ->options(self::STATUSES)
                    ->required(),
            ])
            ->action(function (Order $record, array $data) {
                if ($data['status'] === $record->status) {
                    return;
                }
                CustomerNotice::$sent = null;
                $record->forceFill(['status' => $data['status']])->save();

                $whatsapp = CustomerNotice::whatsapp($record);
                Notification::make()->success()
                    ->title('Status dəyişdi')
                    ->body('Sifariş #'.$record->id.' — '.self::STATUSES[$data['status']]
                        .(CustomerNotice::$sent ? '. Müştəriyə e-poçt göndərildi.' : ''))
                    ->actions(array_filter([
                        $whatsapp ? NotificationAction::make('whatsapp')
                            ->label('WhatsApp-a yaz')
                            ->url($whatsapp, shouldOpenInNewTab: true)
                            ->button() : null,
                    ]))
                    ->persistent()
                    ->send();
            });
    }

    /**
     * Handing the delivery to one of the shop's couriers. Until now a courier
     * took an order himself by tapping the Telegram group, which left the
     * owner no say in who carried what; this is the say.
     */
    private static function courierAction(string $name): Tables\Actions\Action
    {
        return Tables\Actions\Action::make($name)
            ->label('Kuryerə ver')
            ->icon('heroicon-o-truck')
            ->color('info')
            ->modalHeading(fn (Order $record) => 'Sifariş #'.$record->id.' — kuryer')
            ->modalSubmitActionLabel('Yadda saxla')
            ->modalWidth('sm')
            ->visible(fn () => (bool) auth()->user()?->isStaff())
            ->fillForm(fn (Order $record) => ['courier_id' => $record->courier_id])
            ->form([
                Forms\Components\Select::make('courier_id')
                    ->label('Kuryer')
                    ->options(fn () => User::couriers()->pluck('name', 'id')->all())
                    ->placeholder('Kimsə seçilməyib')
                    ->helperText(fn () => User::couriers()->isEmpty()
                        ? 'Hələ kuryer yoxdur: adam saytda qeydiyyatdan keçsin, sonra «İstifadəçilər»də rolunu «Kuryer» edin.'
                        : 'Sifariş yalnız onun telefonunda görünəcək.')
                    ->native(false),
            ])
            ->action(function (Order $record, array $data) {
                $courier = filled($data['courier_id'] ?? null)
                    ? User::find((int) $data['courier_id'])
                    : null;

                Courier::assign($record, $courier);

                Notification::make()->success()
                    ->title($courier ? 'Kuryerə verildi' : 'Kuryer silindi')
                    ->body($courier ? $courier->name.' — sifariş #'.$record->id : 'Sifariş #'.$record->id)
                    ->send();
            });
    }

    /**
     * "Your courier is on the way" — the owner's own tap, because he is the
     * one who knows the man has actually driven off. The letter goes in the
     * customer's language and the same words sit on a WhatsApp button beside
     * it, for the customers who read that sooner.
     */
    private static function onTheWayAction(string $name): Tables\Actions\Action
    {
        return Tables\Actions\Action::make($name)
            ->label('Kuryer yoldadır')
            ->icon('heroicon-o-paper-airplane')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Müştəriyə bildirilsin?')
            ->modalDescription(fn (Order $record) => CustomerNotice::onTheWayText($record))
            ->modalSubmitActionLabel('Göndər')
            ->visible(fn (Order $record) => (bool) auth()->user()?->isStaff()
                && filled($record->courierLabel())
                && ! in_array($record->status, ['cancelled', 'refunded', 'completed'], true))
            ->action(function (Order $record) {
                $failed = Courier::tellCustomer($record);
                $whatsapp = CustomerNotice::onTheWayWhatsapp($record);

                Notification::make()
                    ->status($failed ? 'warning' : 'success')
                    ->title($failed ? 'Məktub getmədi' : 'Müştəriyə bildirildi')
                    ->body($failed ?: 'Sifariş #'.$record->id)
                    ->actions(array_filter([
                        $whatsapp ? NotificationAction::make('whatsapp')
                            ->label('WhatsApp-a yaz')
                            ->url($whatsapp, shouldOpenInNewTab: true)
                            ->button() : null,
                    ]))
                    ->persistent()
                    ->send();
            });
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('№')
                    ->sortable()
                    ->searchable()
                    ->visibleFrom('md'),   // on a phone the number is under the name
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Müştəri')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (Order $r) => '#'.$r->id)
                    ->wrap(),   // two lines on a phone rather than pushing the status off the screen
                // On a phone: customer, amount with the day, status — the rest from a tablet up.
                Tables\Columns\TextColumn::make('contact_phone')
                    ->label('Telefon')
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('courier_name')
                    ->label('Kuryer')
                    ->placeholder('—')
                    ->description(fn (Order $r) => $r->courier_taken_at?->format('d.m H:i'))
                    ->visibleFrom('lg'),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Məhsul sayı')
                    ->counts('items')
                    ->visibleFrom('lg'),
                Tables\Columns\TextColumn::make('delivery_name')
                    ->label('Çatdırılma')
                    ->visibleFrom('lg')
                    ->placeholder('—')
                    ->description(fn (Order $r) => $r->delivery_type ? $r->deliverySummary() : null)
                    ->wrap(),
                // The day the box has to be ready travels under the money, so
                // a phone shows both without a column of its own.
                Tables\Columns\TextColumn::make('total')
                    ->label('Məbləğ')
                    ->getStateUsing(fn (Order $r) => $r->total() > 0 ? Price::format($r->total()) : '—')
                    ->description(fn (Order $r) => $r->delivery_date
                        ? new HtmlString(e(Carbon::parse($r->delivery_date)->format('d.m.Y'))
                            .($r->delivery_slot ? '<br>'.e(str_replace(' — ', '–', $r->delivery_slot)) : ''))
                        : null)
                    ->wrap(),
                // Tapped in the list, it asks for the new status straight away.
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->action(self::statusAction('status_quick'))
                    ->tooltip('Dəyişmək üçün toxunun')
                    ->colors([
                        'gray' => 'awaiting_payment',
                        'warning' => fn ($state) => in_array($state, ['payment_check', 'pending'], true),
                        'info' => 'confirmed',
                        'primary' => 'ready',
                        'success' => 'completed',
                        'danger' => 'cancelled',
                    ])
                    ->formatStateUsing(fn (string $state): string => self::STATUSES[$state] ?? $state)
                    ->wrap(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tarix')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->visibleFrom('md'),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with('items'))
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(self::STATUSES),
            ])
            ->actions([
                // Behind one ⋮ button: two buttons side by side pushed the
                // status off a phone screen, and more will be added in time.
                Tables\Actions\ActionGroup::make([
                    // The receipt is checked, the money is in: the order is on.
                    Tables\Actions\Action::make('confirm_payment')
                        ->label('Ödənişi təsdiqlə')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalDescription(fn (Order $record) => 'Sifariş #'.$record->id.' — '.Price::format($record->total())
                            .($record->paymentAccount ? ' · '.$record->paymentAccount->label : ''))
                        ->visible(fn (Order $record) => in_array($record->status, ['awaiting_payment', 'payment_check'], true))
                        ->action(function (Order $record) {
                            $record->forceFill(['status' => 'confirmed', 'payment_confirmed_at' => now()])->save();
                            Notification::make()->success()->title('Ödəniş təsdiqləndi')->send();
                        }),
                    self::statusAction('status_change'),
                    self::courierAction('courier_assign'),
                    self::onTheWayAction('courier_on_the_way'),
                    // The same words, but by hand, for a customer who reads
                    // WhatsApp sooner than his mail.
                    Tables\Actions\Action::make('whatsapp')
                        ->label('WhatsApp-a yaz')
                        ->icon('heroicon-o-chat-bubble-left-right')
                        ->color('success')
                        ->url(fn (Order $record) => CustomerNotice::whatsapp($record), shouldOpenInNewTab: true)
                        ->visible(fn (Order $record) => filled(CustomerNotice::whatsapp($record))),
                    Tables\Actions\EditAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => (bool) auth()->user()?->isAdmin())
                        ->modalDescription('Silinən sifarişlərin materialları anbara qayıdır, kitablardan çıxır. Adətən silmək yox, ləğv etmək lazımdır.'),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ItemsRelationManager::class,
            RelationManagers\AdjustmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        // 'pending' is what an order gets only when there is no way to pay at
        // all; with the card on, a new order waits for money instead, and the
        // badge had been dark ever since.
        return static::getModel()::whereIn('status', ['pending', 'awaiting_payment', 'payment_check'])->count() ?: null;
    }
}
