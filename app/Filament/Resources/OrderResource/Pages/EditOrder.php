<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Support\CustomerNotice;
use Filament\Actions;
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
                ->modalHeading(fn (Order $record) => 'Müştəriyə məktub — ' . $record->user?->email)
                ->modalDescription('Yazdığınız mətn olduğu kimi gedir. Sifarişin dilində göndərilir.')
                ->modalSubmitActionLabel('Göndər')
                ->fillForm(fn (Order $record) => [
                    'subject' => 'Nefis.az, sifariş #' . $record->id,
                    'body' => CustomerNotice::text($record),
                ])
                ->form([
                    \Filament\Forms\Components\TextInput::make('subject')
                        ->label('Mövzu')->required()->maxLength(150),
                    \Filament\Forms\Components\Textarea::make('body')
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
            Actions\DeleteAction::make()
                ->visible(fn () => (bool) auth()->user()?->isAdmin())
                ->modalDescription('Sifariş kitablardan çıxır, materialları anbara qayıdır. Adətən silmək yox, ləğv etmək lazımdır.'),
        ];
    }
}
