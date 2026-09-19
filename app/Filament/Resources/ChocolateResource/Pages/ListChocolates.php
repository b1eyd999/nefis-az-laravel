<?php

namespace App\Filament\Resources\ChocolateResource\Pages;

use App\Filament\Resources\ChocolateResource;
use App\Models\Chocolate;
use App\Models\Market;
use App\Models\Setting;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;

class ListChocolates extends ListRecords
{
    protected static string $resource = ChocolateResource::class;

    /** One tab per shop, so each shop's bars are a category of their own. */
    public function getTabs(): array
    {
        $tabs = ['all' => Tab::make('Hamısı')->badge(Chocolate::count())];

        foreach (Market::orderBy('sort_order')->orderBy('name')->withCount('chocolates')->get() as $market) {
            $tabs['market-' . $market->id] = Tab::make($market->name)
                ->badge($market->chocolates_count)
                ->modifyQueryUsing(fn ($query) => $query->where('market_id', $market->id));
        }

        if ($loose = Chocolate::whereNull('market_id')->count()) {
            $tabs['none'] = Tab::make('Marketsiz')
                ->badge($loose)
                ->modifyQueryUsing(fn ($query) => $query->whereNull('market_id'));
        }

        return $tabs;
    }

    /** Reads a shop's bars and prices off its website, and says how it went. */
    public static function runSync(Market $market): void
    {
        try {
            $r = $market->sync();
            Notification::make()->success()
                ->title($market->name . ' yeniləndi')
                ->body("{$r['found']} şokolad tapıldı: {$r['created']} yeni, {$r['updated']} yeniləndi" . ($r['missing'] ? ", {$r['missing']} artıq saytda yoxdur" : '') . '.')
                ->send();
        } catch (\Throwable $e) {
            Notification::make()->danger()->title($market->name . ' yenilənmədi')->body($e->getMessage())->send();
        }
    }

    protected function getHeaderActions(): array
    {
        $syncs = Market::whereIn('importer', array_keys(Market::IMPORTERS))->orderBy('sort_order')->get()
            ->map(fn (Market $market) => Actions\Action::make('sync-' . $market->id)
                ->label('Yenilə: ' . $market->name)
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->modalHeading('Yenilə: ' . $market->name)
                ->modalDescription('Şokoladlar və onların qiymətləri (endirimlər daxil) ' . $market->name . ' saytından yenidən oxunur. Yeni şokoladlar əlavə olunur; sizin ad, şəkil və əlavə faiziniz dəyişmir.')
                ->action(fn () => self::runSync($market)))
            ->all();

        return [
            Actions\ActionGroup::make($syncs)
                ->label('Qiymətləri yenilə')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->button()
                ->visible((bool) $syncs),
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
                        ->helperText('Saytdakı qiymət = marketin qiyməti + bu faiz. Məs. 3 ₼ və 30% → 3.90 ₼. Öz faizi olan şokoladlara təsir etmir.')
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
            Actions\Action::make('top-brands')
                ->label('Top markalar')
                ->icon('heroicon-o-fire')
                ->color('warning')
                ->fillForm(fn () => ['brands' => Setting::topBrands()])
                ->form([
                    Forms\Components\TagsInput::make('brands')
                        ->label('Top markalar')
                        ->placeholder('Marka yazın, məs. Milka')
                        ->suggestions(fn () => Chocolate::whereNotNull('brand')->distinct()->orderBy('brand')->pluck('brand')->all())
                        ->reorderable()
                        ->helperText('Müştəri şokolad seçəndə bu markalar birinci gəlir və narıncı yanır; ilki açıq olur. Sıra buradakı kimidir.'),
                ])
                ->action(function (array $data) {
                    $brands = array_values(array_unique(array_filter(array_map('trim', $data['brands'] ?? []))));
                    Setting::put(Setting::TOP_BRANDS, json_encode($brands, JSON_UNESCAPED_UNICODE));
                    Notification::make()->success()->title('Top markalar: ' . ($brands ? implode(', ', $brands) : 'yoxdur'))->send();
                }),
            Actions\CreateAction::make()->label('Şokolad əlavə et'),
        ];
    }
}
