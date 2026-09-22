<?php

namespace App\Filament\Resources\HeroSlideResource\Pages;

use App\Filament\Resources\HeroSlideResource;
use App\Models\Setting;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListHeroSlides extends ListRecords
{
    protected static string $resource = HeroSlideResource::class;

    public function getSubheading(): ?string
    {
        return 'Ana səhifənin ən yuxarısındakı hissə. Bir neçə slayd saytda göstərilsə, növbə ilə dəyişir. Sırasını dartaraq dəyişin.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('slider')
                ->label(fn () => Setting::get(Setting::HERO_AUTOPLAY) === '1'
                    ? 'Slayder: hər ' . Setting::get(Setting::HERO_INTERVAL) . ' san.'
                    : 'Slayder: əl ilə')
                ->icon('heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->fillForm(fn () => [
                    'autoplay' => Setting::get(Setting::HERO_AUTOPLAY) === '1',
                    'interval' => (int) Setting::get(Setting::HERO_INTERVAL),
                ])
                ->form([
                    Forms\Components\Toggle::make('autoplay')
                        ->label('Slaydlar özü dəyişsin')
                        ->helperText('Söndürülübsə, müştəri oxlarla və nöqtələrlə keçir.')
                        ->live(),
                    Forms\Components\TextInput::make('interval')
                        ->label('Neçə saniyədən bir')
                        ->numeric()->minValue(2)->maxValue(60)->suffix('san.')->required()
                        ->visible(fn (Forms\Get $get) => (bool) $get('autoplay')),
                ])
                ->action(function (array $data) {
                    Setting::put(Setting::HERO_AUTOPLAY, (bool) $data['autoplay']);
                    if (isset($data['interval'])) {
                        Setting::put(Setting::HERO_INTERVAL, (int) $data['interval']);
                    }
                    Notification::make()->success()->title('Slayder ayarları saxlanıldı')->send();
                }),
            Actions\CreateAction::make()->label('Slayd əlavə et'),
        ];
    }
}
