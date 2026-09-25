<?php

namespace App\Filament\Resources\LivePhotoResource\Pages;

use App\Filament\Resources\LivePhotoResource;
use App\Models\Setting;
use App\Support\LiveMaterials;
use App\Support\LivePage;
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
                ->fillForm(fn () => [
                    'enabled' => Setting::get(Setting::AR_ENABLED) === '1',
                    'price' => (float) Setting::get(Setting::AR_PRICE),
                    'video_mb' => LiveMaterials::videoMb(),
                ])
                ->form([
                    Forms\Components\Toggle::make('enabled')->label('Canlı şəkil satılsın')
                        ->helperText('Menyuda "Canlı şəkil" səhifəsi və qutu seçəndə "Canlı şəkil (AR)" seçimi. Müştəri şəkli və videonu özü yükləyir.'),
                    Forms\Components\TextInput::make('price')->label('Qiymət')->numeric()->minValue(0)->step(0.01)->suffix('₼')->required(),
                    // The server has the last word, so the field cannot ask for
                    // more than PHP accepts — that would only break uploads.
                    Forms\Components\TextInput::make('video_mb')
                        ->label('Videonun maksimum ölçüsü')
                        // Never below what is already in use, so a server that
                        // reports a smaller ceiling cannot block the form.
                        ->numeric()->minValue(1)->maxValue(fn () => max(LiveMaterials::hostMb(), LiveMaterials::videoMb()))
                        ->suffix('MB')
                        ->helperText(fn () => 'Hostinq hazırda ən çox ' . LiveMaterials::hostMb() . ' MB qəbul edir '
                            . '(PHP: upload_max_filesize ' . ini_get('upload_max_filesize') . ', post_max_size ' . ini_get('post_max_size') . '). '
                            . 'Daha böyük video lazımdırsa, cPanel → MultiPHP INI Editor-də həmin iki dəyəri artırın — sonra bu sahədə də artıq yazmaq olar.'),
                ])
                ->action(function (array $data) {
                    Setting::put(Setting::AR_ENABLED, (bool) $data['enabled']);
                    Setting::put(Setting::AR_PRICE, round((float) $data['price'], 2));
                    Setting::put(Setting::AR_VIDEO_MB, max(1, (int) ($data['video_mb'] ?? LiveMaterials::videoMb())));
                    Notification::make()->success()->title('Saxlanıldı')
                        ->body('Video limiti: ' . LiveMaterials::videoMb() . ' MB')->send();
                }),
            // The page's own sentences: the owner writes them the way he speaks.
            Actions\Action::make('page')
                ->label('Səhifənin mətnləri')
                ->icon('heroicon-o-pencil-square')
                ->color('gray')
                ->modalHeading('"Canlı şəkil" səhifəsinin mətnləri')
                ->modalDescription('Boş qoyduğunuz sahə öz ilkin mətnini saxlayır. Dəyişdiyiniz mətn rus və ingilis dillərində tərcüməsiz, olduğu kimi görünür.')
                ->modalWidth('3xl')
                ->fillForm(fn () => LivePage::page())
                ->form([
                    Forms\Components\TextInput::make('eyebrow')->label('Başlığın üstündəki kiçik yazı')->maxLength(60)
                        ->placeholder(LivePage::DEFAULTS['eyebrow']),
                    Forms\Components\TextInput::make('title')->label('Başlıq')->maxLength(60)
                        ->placeholder(LivePage::DEFAULTS['title']),
                    Forms\Components\Textarea::make('lede')->label('Başlığın altındakı mətn')->rows(2)->maxLength(300)
                        ->placeholder(LivePage::DEFAULTS['lede'])->columnSpanFull(),
                    Forms\Components\TextInput::make('photo_label')->label('1-ci addımın adı')->maxLength(60)
                        ->placeholder(LivePage::DEFAULTS['photo_label']),
                    Forms\Components\TextInput::make('photo_button')->label('Şəkil düyməsi')->maxLength(60)
                        ->placeholder(LivePage::DEFAULTS['photo_button']),
                    Forms\Components\Textarea::make('photo_hint')->label('Şəklin altındakı izah')->rows(2)->maxLength(300)
                        ->placeholder(LivePage::DEFAULTS['photo_hint'])->columnSpanFull(),
                    Forms\Components\TextInput::make('video_label')->label('2-ci addımın adı')->maxLength(60)
                        ->placeholder(LivePage::DEFAULTS['video_label']),
                    Forms\Components\TextInput::make('price_label')->label('Qiymətin yazısı')->maxLength(40)
                        ->placeholder(LivePage::DEFAULTS['price_label']),
                    Forms\Components\Textarea::make('video_hint')->label('Videonun altındakı izah')->rows(2)->maxLength(300)
                        ->placeholder(LivePage::DEFAULTS['video_hint'])->columnSpanFull(),
                    Forms\Components\TextInput::make('button')->label('Səbət düyməsi')->maxLength(40)
                        ->placeholder(LivePage::DEFAULTS['button']),
                    Forms\Components\TextInput::make('preview')->label('Boş telefonun içindəki yazı')->maxLength(120)
                        ->placeholder(LivePage::DEFAULTS['preview']),
                    Forms\Components\Textarea::make('caption')->label('Telefonun altındakı yazı')->rows(2)->maxLength(200)
                        ->placeholder(LivePage::DEFAULTS['caption'])->columnSpanFull(),
                    Forms\Components\Textarea::make('step1')->label('1-ci şəkilli addım')->rows(2)->maxLength(200)
                        ->placeholder(LivePage::DEFAULTS['step1'])->columnSpanFull(),
                    Forms\Components\Textarea::make('step2')->label('2-ci şəkilli addım')->rows(2)->maxLength(200)
                        ->placeholder(LivePage::DEFAULTS['step2'])->columnSpanFull(),
                    Forms\Components\Textarea::make('step3')->label('3-cü şəkilli addım')->rows(2)->maxLength(200)
                        ->placeholder(LivePage::DEFAULTS['step3'])->columnSpanFull(),
                ])
                ->action(function (array $data) {
                    $page = [];
                    foreach (array_keys(LivePage::DEFAULTS) as $key) {
                        $page[$key] = trim((string) ($data[$key] ?? ''));
                    }
                    Setting::put(Setting::LIVE_PAGE, json_encode($page, JSON_UNESCAPED_UNICODE));
                    Notification::make()->success()->title('Saxlanıldı')->send();
                }),
            Actions\CreateAction::make()->label('Canlı şəkil yarat'),
        ];
    }
}
