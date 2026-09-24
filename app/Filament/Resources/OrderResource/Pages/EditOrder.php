<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Support\CustomerNotice;
use Filament\Actions;
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
            Actions\DeleteAction::make(),
        ];
    }
}
