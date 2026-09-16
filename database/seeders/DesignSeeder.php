<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class DesignSeeder extends Seeder
{
    /**
     * Instagram renders are catalog previews only — they already contain a
     * customer photo, so they are never used as `template_image`.
     */
    private const DESIGNS = [
        ['azerbaijan-style-ornament', 'Azerbaijan Style — Ornament', 'sokolad'],
        ['milka-cutluk', 'Milka — Cütlük', 'sokolad'],
        ['kinder-special-8li', 'Kinder Special — 8-li Qutu', 'sokolad'],
        ['mezeli-senden-adam-olmuyub', 'Məzəli — Səndən Adam Olmuyub', 'sokolad'],
        ['mezeli-kartof', 'Məzəli — Kartof', 'sokolad'],
        ['alpen-gold-oreo-aile', 'Alpen Gold Oreo — Ailə', 'sokolad'],
        ['toy-ag-klassik', 'Toy — Ağ Klassik', 'sokolad'],
        ['toy-nisan', 'Toy — Nişan', 'sokolad'],
        ['toy-gelin-bey', 'Toy — Gəlin və Bəy', 'sokolad'],
        ['netflix-memory-of-love', 'Netflix Stili — Memory of Love', 'sokolad'],
        ['google-my-love', 'Google Stili — My Love', 'sokolad'],
        ['kinder-special-foto-kollaj', 'Kinder Special — Foto Kollaj', 'sokolad'],
        ['romantik-special-edition', 'Romantik — Special Edition', 'sokolad'],
        ['eti-ikirem-ferdi', 'ETİ İkirem — Fərdi', 'sokolad'],
        ['urekler-aile', 'Ürəklər — Ailə', 'sokolad'],
        ['kinder-chocolate-ferdi', 'Kinder Chocolate — Fərdi', 'sokolad'],
        ['barbie-cercive', 'Barbie Çərçivə', 'poster'],
        ['vintage-cercive-benovseyi', 'Vintage Çərçivə — Bənövşəyi', 'poster'],
        ['bantli-cercive-usaq', 'Bantlı Çərçivə — Uşaq', 'poster'],
        ['qizili-vintage-cercive', 'Qızılı Vintage Çərçivə', 'poster'],
        ['i-love-you-poster', 'I Love You — Poster', 'poster'],
        ['hendesi-cercive-aile', 'Həndəsi Çərçivə — Ailə', 'poster'],
        ['damci-dizayn-cutluk', 'Damcı Dizayn — Cütlük', 'poster'],
        ['avtomobil-poster-bmw', 'Avtomobil Posteri — BMW', 'poster'],
        ['love-is-mavi', 'Love is... — Mavi', 'love-is'],
        ['love-is-narinci', 'Love is... — Narıncı', 'love-is'],
        ['love-is-qirmizi', 'Love is... — Qırmızı', 'love-is'],
        ['xerite-nerimanov', 'Xəritə — Nərimanov', 'xerite'],
        ['xerite-28-mall', 'Xəritə — 28 Mall', 'xerite'],
        ['xerite-electra-hall', 'Xəritə — Electra Hall', 'xerite'],
        ['spotify-kod-1', 'Spotify Kod — Poster 1', 'spotify'],
        ['spotify-kod-2', 'Spotify Kod — Poster 2', 'spotify'],
        ['spotify-kod-3', 'Spotify Kod — Poster 3', 'spotify'],
    ];

    public function run(): void
    {
        Product::where('slug', 'alyonka-azerbaijan-style')->update([
            'category' => 'sokolad',
            'preview_image' => 'designs/azerbaijan-style-kelagayi.jpg',
        ]);

        Product::where('slug', 'klassik-qutu')->update(['category' => 'sokolad']);

        foreach (self::DESIGNS as $i => [$slug, $name, $category]) {
            Product::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'category' => $category,
                    'preview_image' => "designs/{$slug}.jpg",
                    'is_active' => true,
                    'sort_order' => 10 + $i,
                ]
            );
        }
    }
}
