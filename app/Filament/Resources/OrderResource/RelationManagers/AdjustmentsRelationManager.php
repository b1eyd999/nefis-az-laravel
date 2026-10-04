<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use App\Models\OrderAdjustment;
use App\Support\OrderEditor;
use App\Support\Price;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Money owed either way, because the order was changed after it was paid for.
 *
 * Each row is settled on its own: a charge by the link the customer is sent,
 * or by the owner's own word once a transfer or cash has arrived; money owed
 * back is given back in the ePoint cabinet — the gateway has no refund call —
 * and ticked off here.
 */
class AdjustmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'adjustments';

    protected static ?string $title = 'Sonradan edilən dəyişikliklər';

    public static function canViewForUser(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('kind')
                ->label('Növ')
                ->options([
                    OrderAdjustment::CHARGE => 'Müştəri əlavə ödəyir',
                    OrderAdjustment::REFUND => 'Müştəriyə qaytarılır',
                ])
                ->required()
                ->default(OrderAdjustment::CHARGE),
            Forms\Components\TextInput::make('amount')
                ->label('Məbləğ, ₼')
                ->numeric()->minValue(0.01)->step(0.01)->required(),
            Forms\Components\TextInput::make('reason')
                ->label('Nəyə görə')
                ->helperText('Müştəri bunu ödəniş səhifəsində görür — sadə dillə yazın.')
                ->maxLength(255)
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reason')
            ->emptyStateHeading('Dəyişiklik yoxdur')
            ->emptyStateDescription('Sifariş ödənildikdən sonra nə isə dəyişsə, fərq burada görünəcək.')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Tarix')->dateTime('d.m.Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('kind')
                    ->label('Növ')
                    ->badge()
                    ->color(fn ($state) => $state === OrderAdjustment::CHARGE ? 'warning' : 'info')
                    ->formatStateUsing(fn ($state) => $state === OrderAdjustment::CHARGE ? 'Əlavə ödəniş' : 'Qaytarılır'),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Məbləğ')
                    ->formatStateUsing(fn ($state, $record) => ($record->isCharge() ? '+' : '−') . Price::format((float) $state)),
                Tables\Columns\TextColumn::make('reason')->label('Nəyə görə')->wrap(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Vəziyyət')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        OrderAdjustment::PAID => 'success',
                        OrderAdjustment::CANCELLED => 'gray',
                        OrderAdjustment::CHECK => 'warning',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn ($state) => OrderAdjustment::STATUSES[$state] ?? $state),
                Tables\Columns\TextColumn::make('payment_method')->label('Üsul')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('author.name')->label('Kim')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Əl ilə əlavə et')
                    ->modalHeading('Fərq yaz')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['status'] = OrderAdjustment::WAITING;
                        $data['created_by'] = auth()->id();

                        return $data;
                    }),
            ])
            ->actions([
                // The link the customer opens. It carries his own language, so
                // a Russian customer does not land on the Azerbaijani page.
                Tables\Actions\Action::make('link')
                    ->label('Ödəniş linki')
                    ->icon('heroicon-m-link')
                    ->color('gray')
                    ->visible(fn (OrderAdjustment $record) => $record->isCharge() && $record->isOpen())
                    ->modalHeading('Müştəriyə göndəriləcək link')
                    ->modalDescription(fn (OrderAdjustment $record) => self::linkFor($record))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Bağla'),
                Tables\Actions\Action::make('settle')
                    ->label(fn (OrderAdjustment $record) => $record->isCharge() ? 'Ödənildi' : 'Qaytarıldı')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->visible(fn (OrderAdjustment $record) => $record->isOpen())
                    ->requiresConfirmation()
                    ->modalHeading(fn (OrderAdjustment $record) => $record->isCharge()
                        ? 'Pul alındı?'
                        : 'Pul müştəriyə qaytarıldı?')
                    ->modalDescription(fn (OrderAdjustment $record) => $record->isCharge()
                        ? 'Köçürmə və ya nağd ' . Price::format((float) $record->amount) . ' aldığınızı təsdiqləyirsiniz.'
                        : Price::format((float) $record->amount) . ' məbləği ePoint kabinetindən geri göndərdiyinizi təsdiqləyirsiniz — sayt pulu özü qaytara bilmir.')
                    ->action(function (OrderAdjustment $record) {
                        OrderEditor::settle($record);
                        Notification::make()->success()->title('Yazıldı')->send();
                    }),
                Tables\Actions\Action::make('cancel')
                    ->label('Ləğv et')
                    ->icon('heroicon-m-x-circle')
                    ->color('gray')
                    // Only a charge: money the shop owes is settled by giving
                    // it back, otherwise "already paid" stops being true.
                    ->visible(fn (OrderAdjustment $record) => $record->isCharge() && $record->isOpen())
                    ->requiresConfirmation()
                    ->modalDescription('Müştəri bunu ödəməyəcək. Sifarişin məbləği dəyişmir — əlavə etdiyiniz sətri də geri götürün.')
                    ->action(function (OrderAdjustment $record) {
                        OrderEditor::cancel($record);
                        Notification::make()->success()->title('Ləğv edildi')->send();
                    }),
                Tables\Actions\ViewAction::make()
                    ->label('Çek')
                    ->icon('heroicon-m-document-text')
                    ->visible(fn (OrderAdjustment $record) => (bool) $record->payment_receipt)
                    ->modalHeading('Göndərilən çek')
                    ->modalContent(fn (OrderAdjustment $record) => str(
                        '<a href="' . e($record->receiptUrl()) . '" target="_blank" rel="noopener">'
                        . '<img src="' . e($record->receiptUrl()) . '" alt="" style="max-width:100%;border-radius:.5rem"></a>'
                    )->toHtmlString())
                    ->modalSubmitAction(false),
            ]);
    }

    /** The address to send, in the language the customer ordered in. */
    public static function linkFor(OrderAdjustment $adjustment): string
    {
        $locale = $adjustment->order->locale ?: \App\Support\Locale::DEFAULT;
        $name = $locale === \App\Support\Locale::DEFAULT ? 'orders.extra.show' : $locale . '.orders.extra.show';

        return route($name, ['order' => $adjustment->order_id, 'adjustment' => $adjustment->id]);
    }
}
