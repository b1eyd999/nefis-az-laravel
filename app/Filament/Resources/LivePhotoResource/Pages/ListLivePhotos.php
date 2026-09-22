<?php

namespace App\Filament\Resources\LivePhotoResource\Pages;

use App\Filament\Resources\LivePhotoResource;
use App\Models\Setting;
use App\Support\Price;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListLivePhotos extends ListRecords
{
    protected static string $resource = LivePhotoResource::class;

    public function getSubheading(): ?string
    {
        return 'Qutudakı şəkil + Yandex Diskdəki video + QR kod. Müştəri QR kodu oxudub telefonu şəklə tutanda video şəklin üstündə oynayır — tətbiq yükləmədən.';
    }

    protected function getHeaderActions(): array
    {
        return [
            // Customers may order a live photo with a box, sending their video with it.
            Actions\Action::make('sale')
                ->label(fn () => Setting::get(Setting::AR_ENABLED) === '1'
                    ? 'Müştərilər üçün: ' . Price::format((float) Setting::get(Setting::AR_PRICE))
                    : 'Müştərilər üçün: bağlı')
                ->icon('heroicon-o-shopping-bag')
                ->color('gray')
                ->fillForm(fn () => ['enabled' => Setting::get(Setting::AR_ENABLED) === '1', 'price' => (float) Setting::get(Setting::AR_PRICE)])
                ->form([
                    Forms\Components\Toggle::make('enabled')->label('Qutu seçəndə "Canlı video" təklif olunsun')
                        ->helperText('Müştəri videosunu sifarişlə göndərir; siz onu Yandex Diskə qoyub buradan canlı şəkil yaradırsınız.'),
                    Forms\Components\TextInput::make('price')->label('Qiymət')->numeric()->minValue(0)->step(0.01)->suffix('₼')->required(),
                ])
                ->action(function (array $data) {
                    Setting::put(Setting::AR_ENABLED, (bool) $data['enabled']);
                    Setting::put(Setting::AR_PRICE, round((float) $data['price'], 2));
                    Notification::make()->success()->title('Saxlanıldı')->send();
                }),
            Actions\CreateAction::make()->label('Canlı şəkil yarat'),
        ];
    }
}
