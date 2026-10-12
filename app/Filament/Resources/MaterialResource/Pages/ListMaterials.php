<?php

namespace App\Filament\Resources\MaterialResource\Pages;

use App\Filament\Resources\MaterialResource;
use App\Models\Material;
use App\Support\Price;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMaterials extends ListRecords
{
    protected static string $resource = MaterialResource::class;

    public function getSubheading(): ?string
    {
        // The wrap is said separately, because most boxes are not wrapped: one
        // figure that quietly added it to every box was the thing that made
        // the whole page's maya dəyəri wrong.
        $wrap = Material::costOfOneWrap();
        $ribbon = Material::costOfRibbon();

        return 'Bir hazır qutunun materialı: ' . Price::format(round(Material::costOfOneBox(), 3))
            . ' (şokoladsız)'
            . ($wrap > 0 ? '. Bükülürsə üstünə ' . Price::format(round($wrap, 3)) : '')
            . ($ribbon ? ' + lent ' . self::money($ribbon) : '')
            . '. Hər sifarişdə material anbardan özü çıxılır.';
    }

    /** «0.04 ₼», or «0.04–0.06 ₼» once the rolls stop costing the same. */
    private static function money(array $range): string
    {
        [$from, $to] = $range;

        return $from === $to
            ? Price::format($from)
            : rtrim(rtrim(number_format($from, 3, '.', ''), '0'), '.') . '–' . Price::format($to);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Material əlavə et'),
        ];
    }
}
