<?php

namespace App\Filament\Resources\GiftPageResource\Pages;

use App\Filament\Resources\GiftPageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGiftPages extends ListRecords
{
    protected static string $resource = GiftPageResource::class;

    public function getSubheading(): ?string
    {
        return 'Hər səhifə bir axtarış üçündür: "ad günü hədiyyəsi", "sevgiliyə hədiyyə"… Google onları nefis.az/hediyye/ ünvanında tapır. Sırasını dartaraq dəyişin.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('site')->label('Saytda bax')->icon('heroicon-o-eye')->color('gray')
                ->url(fn () => route('gifts.index'))->openUrlInNewTab(),
            Actions\CreateAction::make()->label('Yeni səhifə'),
        ];
    }
}
