<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers;
use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Support\CustomerNotice;
use App\Support\Price;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
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
                Forms\Components\Section::make('Ödəniş')
                    ->schema([
                        Forms\Components\Placeholder::make('payment_account')
                            ->label('Hesab')
                            ->content(fn (?Order $record) => $record?->paymentAccount
                                ? $record->paymentAccount->typeLabel() . ' · ' . $record->paymentAccount->label . ' · ' . $record->paymentAccount->formatted()
                                : 'Təyin olunmayıb'),
                        Forms\Components\Placeholder::make('receipt_at')
                            ->label('Çek göndərilib')
                            ->content(fn (?Order $record) => $record?->receipt_at?->format('d.m.Y H:i')
                                ?? ($record?->payment_confirmed_at ? 'Çeksiz təsdiqlənib' : 'Hələ yox')),
                        Forms\Components\Placeholder::make('receipt')
                            ->label('Çek')
                            ->content(function (?Order $record) {
                                $url = $record?->receiptUrl();
                                if (! $url) {
                                    return 'Yoxdur';
                                }
                                $isPdf = str_ends_with(strtolower((string) $record->payment_receipt), '.pdf');

                                return new HtmlString($isPdf
                                    ? '<a href="' . e($url) . '" target="_blank" rel="noopener" style="text-decoration:underline;">PDF çeki aç</a>'
                                    : '<a href="' . e($url) . '" target="_blank" rel="noopener"><img src="' . e($url) . '" alt="" style="max-height:320px;border-radius:.6rem"></a>');
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->visible(fn (?Order $record) => $record?->payment_account_id || $record?->payment_receipt),
                // How it goes out, as the customer chose it at checkout.
                Forms\Components\Section::make('Çatdırılma')
                    ->schema([
                        // The day the customer is waiting for: the one thing the
                        // shop works to, so it stands at the top of the section.
                        Forms\Components\Placeholder::make('delivery_when')
                            ->label('Nə vaxta')
                            ->content(fn (?Order $record) => $record?->delivery_date
                                ? \App\Support\DeliveryTime::day($record->delivery_date)
                                    . ($record->delivery_slot ? ', ' . $record->delivery_slot : '')
                                : 'Seçilməyib')
                            ->columnSpanFull(),
                        Forms\Components\Placeholder::make('delivery_method')
                            ->label('Üsul')
                            ->content(fn (?Order $record) => $record?->delivery_name
                                ? $record->delivery_name . ' — ' . ($record->delivery_price > 0 ? Price::format($record->delivery_price) : 'pulsuz')
                                : 'Seçilməyib (köhnə sifariş)'),
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
                            ->content(fn (?Order $record) => new \Illuminate\Support\HtmlString(
                                '<a href="' . e($record->mapUrl()) . '" target="_blank" rel="noopener" style="color:#d97706;font-weight:600;text-decoration:underline">Xəritədə aç ↗</a>'
                                . '<span style="opacity:.6;margin-left:.6rem">' . e(number_format($record->delivery_lat, 5) . ', ' . number_format($record->delivery_lng, 5)) . '</span>'))
                            ->visible(fn (?Order $record) => (bool) $record?->mapUrl()),
                        Forms\Components\Placeholder::make('totals')
                            ->label('Məbləğ')
                            ->content(fn (?Order $record) => $record
                                ? 'Məhsullar ' . Price::format($record->itemsTotal())
                                    . ' + çatdırılma ' . Price::format($record->delivery_price ?? 0)
                                    . ' = ' . Price::format($record->total())
                                : '—')
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
            ->modalHeading(fn (Order $record) => 'Sifariş #' . $record->id . ' — status')
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
                    ->body('Sifariş #' . $record->id . ' — ' . self::STATUSES[$data['status']]
                        . (CustomerNotice::$sent ? '. Müştəriyə e-poçt göndərildi.' : ''))
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
                    ->description(fn (Order $r) => '#' . $r->id)
                    ->wrap(),   // two lines on a phone rather than pushing the status off the screen
                // On a phone: customer, amount with the day, status — the rest from a tablet up.
                Tables\Columns\TextColumn::make('contact_phone')
                    ->label('Telefon')
                    ->visibleFrom('md'),
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
                        ? new HtmlString(e(\Illuminate\Support\Carbon::parse($r->delivery_date)->format('d.m.Y'))
                            . ($r->delivery_slot ? '<br>' . e(str_replace(' — ', '–', $r->delivery_slot)) : ''))
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
                        ->modalDescription(fn (Order $record) => 'Sifariş #' . $record->id . ' — ' . Price::format($record->total())
                            . ($record->paymentAccount ? ' · ' . $record->paymentAccount->label : ''))
                        ->visible(fn (Order $record) => in_array($record->status, ['awaiting_payment', 'payment_check'], true))
                        ->action(function (Order $record) {
                            $record->forceFill(['status' => 'confirmed', 'payment_confirmed_at' => now()])->save();
                            Notification::make()->success()->title('Ödəniş təsdiqləndi')->send();
                        }),
                    self::statusAction('status_change'),
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
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ItemsRelationManager::class,
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
        return static::getModel()::where('status', 'pending')->count() ?: null;
    }
}
