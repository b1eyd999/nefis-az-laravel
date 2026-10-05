<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Models\User;
use App\Support\Courier;
use App\Support\CustomerNotice;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Writing to the customer without copying his number out by hand.
            Actions\Action::make('whatsapp')
                ->label('WhatsApp-a yaz')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('success')
                ->url(fn (Order $record) => CustomerNotice::whatsapp($record), shouldOpenInNewTab: true)
                ->visible(fn (Order $record) => filled(CustomerNotice::whatsapp($record))),
            /* And by e-mail, for everything WhatsApp is not: a long answer,
               something the customer should be able to find again. */
            Actions\Action::make('mail')
                ->label('Mail göndər')
                ->icon('heroicon-o-envelope')
                ->color('info')
                ->visible(fn (Order $record) => filled($record->user?->email))
                ->modalHeading(fn (Order $record) => 'Müştəriyə məktub — '.$record->user?->email)
                ->modalDescription('Yazdığınız mətn olduğu kimi gedir. Sifarişin dilində göndərilir.')
                ->modalSubmitActionLabel('Göndər')
                ->fillForm(fn (Order $record) => [
                    'subject' => 'Nefis.az, sifariş #'.$record->id,
                    'body' => CustomerNotice::text($record),
                ])
                ->form([
                    TextInput::make('subject')
                        ->label('Mövzu')->required()->maxLength(150),
                    Textarea::make('body')
                        ->label('Mətn')->required()->rows(10)->maxLength(4000)
                        ->helperText('Sətir keçidləri saxlanılır.'),
                ])
                ->action(function (Order $record, array $data) {
                    $failed = CustomerNotice::write($record, $data['subject'], $data['body']);

                    if ($failed) {
                        Notification::make()->danger()
                            ->title('Məktub getmədi')
                            ->body($failed)
                            ->persistent()
                            ->send();

                        return;
                    }

                    Notification::make()->success()
                        ->title('Məktub göndərildi')
                        ->body($record->user?->email)
                        ->send();
                }),
            /* Whose delivery this is. The courier sees it on his own phone
               the moment it is his, and nowhere else in the shop. */
            Actions\Action::make('courier')
                ->label(fn (Order $record) => $record->courier_id ? 'Kuryeri dəyiş' : 'Kuryerə ver')
                ->icon('heroicon-o-truck')
                ->color('info')
                ->modalHeading(fn (Order $record) => 'Sifariş #'.$record->id.' — kuryer')
                ->modalSubmitActionLabel('Yadda saxla')
                ->modalWidth('sm')
                ->fillForm(fn (Order $record) => ['courier_id' => $record->courier_id])
                ->form([
                    Select::make('courier_id')
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
                        ->body($courier ? $courier->name : 'Sifariş #'.$record->id)
                        ->send();
                }),
            /* And the one line the customer waits for. The owner's own tap:
               he is the one who knows the man has driven off. */
            Actions\Action::make('on_the_way')
                ->label('Kuryer yoldadır')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Müştəriyə bildirilsin?')
                ->modalDescription(fn (Order $record) => CustomerNotice::onTheWayText($record))
                ->modalSubmitActionLabel('Göndər')
                ->visible(fn (Order $record) => filled($record->courierLabel())
                    && ! in_array($record->status, ['cancelled', 'refunded', 'completed'], true))
                ->action(function (Order $record) {
                    $failed = Courier::tellCustomer($record);
                    $whatsapp = CustomerNotice::onTheWayWhatsapp($record);

                    Notification::make()
                        ->status($failed ? 'warning' : 'success')
                        ->title($failed ? 'Məktub getmədi' : 'Müştəriyə bildirildi')
                        ->body($failed ?: 'Sifariş #'.$record->id)
                        ->actions(array_filter([
                            $whatsapp ? Action::make('whatsapp')
                                ->label('WhatsApp-a yaz')
                                ->url($whatsapp, shouldOpenInNewTab: true)
                                ->button() : null,
                        ]))
                        ->persistent()
                        ->send();
                }),
            Actions\DeleteAction::make()
                ->visible(fn () => (bool) auth()->user()?->isAdmin())
                ->modalDescription('Sifariş kitablardan çıxır, materialları anbara qayıdır. Adətən silmək yox, ləğv etmək lazımdır.'),
        ];
    }
}
