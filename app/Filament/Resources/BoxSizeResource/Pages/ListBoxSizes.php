<?php

namespace App\Filament\Resources\BoxSizeResource\Pages;

use App\Filament\Resources\BoxSizeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBoxSizes extends ListRecords
{
    protected static string $resource = BoxSizeResource::class;

    public function getSubheading(): ?string
    {
        return 'Şirkətlər üçün səhifəsində müştəri bu ölçülərdən birini seçir: qutunun şəkli, ölçüləri və '
            . 'qiyməti ona görə dəyişir. Minimum sifariş və hazırlanma müddəti bütün ölçülər üçün eynidir — '
            . 'onlar «Şirkətlər üçün» səhifəsindədir. Bir dənə də ölçü əlavə etməyincə sayt həmişəki '
            . 'tək ölçünü göstərir.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('site')->label('Saytda bax')->icon('heroicon-o-eye')->color('gray')
                ->url(fn () => route('corporate.index'))->openUrlInNewTab(),
            Actions\CreateAction::make()->label('Yeni ölçü'),
        ];
    }
}
