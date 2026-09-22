<?php

namespace App\Filament\Resources\LivePhotoResource\Pages;

use App\Filament\Resources\LivePhotoResource;
use App\Models\Setting;
use App\Support\Price;
use App\Support\YandexDisk;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\HtmlString;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListLivePhotos extends ListRecords
{
    protected static string $resource = LivePhotoResource::class;

    public function getSubheading(): ?string
    {
        return 'Müştərilər şəkil və videonu özləri yükləyir: sifarişlə canlı şəkil özü yaranır, video Yandex Diskə köçür. Sizə yalnız QR kodu çap etmək qalır.';
    }

    protected function getHeaderActions(): array
    {
        return [
            // The owner's own Yandex Disk, where customers' videos go. He types the token himself.
            Actions\Action::make('yandex')
                ->label(fn () => YandexDisk::hasToken() ? 'Yandex Disk: qoşulub' : 'Yandex Diski qoşun')
                ->icon('heroicon-o-cloud-arrow-up')
                ->color(fn () => YandexDisk::hasToken() ? 'gray' : 'warning')
                ->modalHeading('Yandex Disk')
                ->modalDescription('Müştərilərin videoları buraya yüklənir və hostinqdən silinir.')
                ->fillForm(fn () => ['folder' => Setting::get(Setting::YANDEX_FOLDER)])
                ->form([
                    Forms\Components\TextInput::make('token')->label('OAuth token')->password()->revealable()
                        ->placeholder(fn () => YandexDisk::hasToken() ? 'Saxlanıb — dəyişmək üçün yenisini yazın' : 'y0_…')
                        ->helperText(new HtmlString('Tokeni almaq: Yandex hesabınıza girin, <a href="https://yandex.ru/dev/disk/poligon/" target="_blank" rel="noopener" style="text-decoration:underline;">yandex.ru/dev/disk/poligon</a> səhifəsində "Получить OAuth-токен" düyməsini basın və tokeni bura yapışdırın. Token təxminən 1 il işləyir.')),
                    Forms\Components\TextInput::make('folder')->label('Qovluq')->required()->maxLength(80)
                        ->helperText('Yandex Diskdə videoların qovluğu — özü yaradılır.'),
                    Forms\Components\Toggle::make('forget')->label('Tokeni sil (Yandex Diski ayır)')
                        ->visible(fn () => YandexDisk::hasToken()),
                ])
                ->action(function (array $data, Actions\Action $action) {
                    Setting::put(Setting::YANDEX_FOLDER, trim($data['folder']));
                    if (! empty($data['forget'])) {
                        YandexDisk::saveToken(null);
                        Notification::make()->success()->title('Yandex Disk ayrıldı')->send();

                        return;
                    }
                    if (filled($data['token'])) {
                        try {
                            $account = YandexDisk::account(trim($data['token']));
                        } catch (\RuntimeException $e) {
                            Notification::make()->danger()->title('Token işləmədi')->body($e->getMessage())->persistent()->send();
                            $action->halt();
                        }
                        YandexDisk::saveToken($data['token']);
                        Notification::make()->success()->title('Yandex Disk qoşuldu')
                            ->body($account['name'] . ' · boş yer: ' . $account['free_gb'] . ' GB')->send();
                    } else {
                        Notification::make()->success()->title('Saxlanıldı')->send();
                    }
                    // Videos that waited on the hosting go now, after the page has answered.
                    defer(fn () => Artisan::call('live:push'));
                }),
            // Customers order a live photo on its own or with a box, sending their picture and video.
            Actions\Action::make('sale')
                ->label(fn () => Setting::get(Setting::AR_ENABLED) === '1'
                    ? 'Müştərilər üçün: ' . Price::format((float) Setting::get(Setting::AR_PRICE))
                    : 'Müştərilər üçün: bağlı')
                ->icon('heroicon-o-shopping-bag')
                ->color('gray')
                ->fillForm(fn () => ['enabled' => Setting::get(Setting::AR_ENABLED) === '1', 'price' => (float) Setting::get(Setting::AR_PRICE)])
                ->form([
                    Forms\Components\Toggle::make('enabled')->label('Canlı şəkil satılsın')
                        ->helperText('Menyuda "Canlı şəkil" səhifəsi və qutu seçəndə "Canlı şəkil (AR)" seçimi. Müştəri şəkli və videonu özü yükləyir.'),
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
