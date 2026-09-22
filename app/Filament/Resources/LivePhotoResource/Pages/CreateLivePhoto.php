<?php

namespace App\Filament\Resources\LivePhotoResource\Pages;

use App\Filament\Resources\LivePhotoResource;
use App\Models\OrderItem;
use Filament\Resources\Pages\CreateRecord;

class CreateLivePhoto extends CreateRecord
{
    use ChecksVideoLink;

    protected static string $resource = LivePhotoResource::class;

    /** Opened from an order line ("AR yarat"): named after the order and tied to it. */
    protected function afterFill(): void
    {
        $item = OrderItem::find(request()->integer('order_item'));
        if ($item) {
            $this->form->fill(['title' => 'Sifariş #' . $item->order_id . ' — ' . ($item->product_name ?? 'qutu'), 'order_item_id' => $item->id, 'is_active' => true]);
        }
    }

    protected function beforeCreate(): void
    {
        $this->checkVideoLink();
    }

    /** Straight on to preparing it and taking the QR code. */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
