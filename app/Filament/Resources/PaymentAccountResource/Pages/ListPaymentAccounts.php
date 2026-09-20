<?php

namespace App\Filament\Resources\PaymentAccountResource\Pages;

use App\Filament\Resources\PaymentAccountResource;
use App\Models\PaymentAccount;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPaymentAccounts extends ListRecords
{
    protected static string $resource = PaymentAccountResource::class;

    public function getSubheading(): ?string
    {
        return 'Bir hesab ' . PaymentAccount::windowHours() . ' saat ərzində ' . PaymentAccount::limit()
            . ' sifariş qəbul edir, sonra növbəti hesaba keçilir. Limit "Tənzimləmələr"də dəyişilir.';
    }

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Hesab əlavə et')];
    }
}
