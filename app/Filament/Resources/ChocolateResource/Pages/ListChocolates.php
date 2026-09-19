<?php

namespace App\Filament\Resources\ChocolateResource\Pages;

use App\Filament\Resources\ChocolateResource;
use App\Models\Setting;
use App\Support\ArazMarket;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListChocolates extends ListRecords
{
    protected static string $resource = ChocolateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('sync')
                ->label('Araz Market-dən yenilə')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('90–105 q plitka şokoladlar və onların qiymətləri (endirimlər daxil) Araz Market saytından yenidən oxunur. Yeni şokoladlar əlavə olunur; sizin ad, şəkil və əlavə faiziniz dəyişmir.')
                ->action(function () {
                    try {
                        $r = ArazMarket::sync();
                        Notification::make()->success()
                            ->title('Yeniləndi')
                            ->body("{$r['found']} şokolad tapıldı: {$r['created']} yeni, {$r['updated']} yeniləndi" . ($r['missing'] ? ", {$r['missing']} artıq saytda yoxdur" : '') . '.')
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()->danger()->title('Yenilənmədi')->body($e->getMessage())->send();
                    }
                }),
            Actions\Action::make('markup')
                ->label(fn () => 'Qiymət əlavəsi: ' . Setting::get(Setting::CHOCOLATE_MARKUP) . '%')
                ->icon('heroicon-o-receipt-percent')
                ->color('gray')
                ->fillForm(fn () => [
                    'markup' => (int) Setting::get(Setting::CHOCOLATE_MARKUP),
                    'from_sale' => Setting::get(Setting::CHOCOLATE_FROM_SALE) === '1',
                ])
                ->form([
                    Forms\Components\TextInput::make('markup')
                        ->label('Bütün şokoladlara əlavə (%)')
                        ->helperText('Saytdakı qiymət = Araz Market qiyməti + bu faiz. Məs. 3 ₼ və 30% → 3.90 ₼. Öz faizi olan şokoladlara təsir etmir.')
                        ->numeric()->minValue(0)->maxValue(500)->suffix('%')->required(),
                    Forms\Components\Toggle::make('from_sale')
                        ->label('Endirim varsa, endirimli qiymətdən hesabla')
                        ->helperText('Söndürülübsə, həmişə orijinal (endirimsiz) qiymət əsas götürülür.'),
                ])
                ->action(function (array $data) {
                    Setting::put(Setting::CHOCOLATE_MARKUP, (int) $data['markup']);
                    Setting::put(Setting::CHOCOLATE_FROM_SALE, (bool) $data['from_sale']);
                    Notification::make()->success()->title('Qiymətlər yeniləndi')->send();
                }),
            Actions\CreateAction::make()->label('Şokolad əlavə et'),
        ];
    }
}
