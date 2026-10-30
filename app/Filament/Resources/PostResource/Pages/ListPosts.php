<?php

namespace App\Filament\Resources\PostResource\Pages;

use App\Filament\Resources\PostResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPosts extends ListRecords
{
    protected static string $resource = PostResource::class;

    public function getSubheading(): ?string
    {
        return 'Saytın bloqu: nefis.az/bloq. Hər yazı bir dilə aiddir — rusca və ingiliscə variantları '
            . 'ayrıca yazı kimi əlavə edin və «Azərbaycanca variantı» sahəsində əslini seçin. '
            . 'Yazılar tarixə görə, ən yenisi əvvəldə sıralanır.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('site')->label('Saytda bax')->icon('heroicon-o-eye')->color('gray')
                ->url(fn () => route('blog.index'))->openUrlInNewTab(),
            Actions\CreateAction::make()->label('Yeni yazı'),
        ];
    }
}
