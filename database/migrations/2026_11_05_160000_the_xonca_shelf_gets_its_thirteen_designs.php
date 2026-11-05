<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The xonça shelf, which has been live and empty, gets its thirteen designs.
 *
 * `/xonca` has existed for weeks: it is in the menu, its wording is the
 * owner's, and it fills itself from the «Xonça» category — which held no
 * designs at all, so the page showed its «coming soon» line and nothing
 * could be bought. A friend of the owner then drew thirteen box designs and
 * a whole ordering flow as a separate static site, where the «add to
 * basket» button was a 700 ms pause and a reassuring message.
 *
 * This puts his thirteen into the shop proper. They are ordinary designs:
 * a printed face under `public/images/xonca/`, text slots the customer
 * fills, one photograph slot on the design that asks for a photograph. So
 * they go through the real basket, the real card payment, the real Telegram
 * card and the real admin, and the owner edits them where he edits
 * everything else.
 *
 * What the face carries and what it asks for:
 *   printed   the border, the ribbon, the flowers, the wreath, the heart,
 *             the ampersand between the names, the little rule beneath them
 *   asked for the occasion (NİŞAN, TOY …), the names or the initials, the
 *             date, and on one design the couple's photograph
 *
 * The artwork is the friend's, laid out again on the shop's own face rather
 * than stretched onto it. The owner replaces any of it in the admin without
 * touching the slots: it is only a picture.
 *
 * Prices are what he confirmed: 1.50 ₼ a box, never fewer than twenty.
 */
return new class extends Migration
{
    /** The shop's printed face. */
    private const W = 969;

    private const H = 1895;

    /** Where the friend's card sat inside his own drawing. */
    private const CARD_X = 115;

    private const CARD_Y = 130;

    private const CARD_W = 170;

    private const CARD_H = 270;

    public function up(): void
    {
        foreach ($this->designs() as $i => $d) {
            if (DB::table('products')->where('slug', $d['slug'])->exists()) {
                continue;               // his own, already here: left alone
            }

            $id = DB::table('products')->insertGetId([
                'name' => $d['name'],
                'slug' => $d['slug'],
                'description' => $d['desc'],
                'i18n' => json_encode($d['i18n'], JSON_UNESCAPED_UNICODE),
                'price' => 1.5,
                // A xonça is a trayful, not a box. Twenty is where it starts.
                'min_quantity' => 20,
                'max_quantity' => 300,
                'category' => 'xonca',
                'template_image' => 'images/xonca/' . $d['id'] . '.png',
                'template_width' => self::W,
                'template_height' => self::H,
                'box_color' => $d['box'],
                'allow_text' => true,
                'is_active' => true,
                'sort_order' => $i + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($this->textSlots($d) as $n => $slot) {
                DB::table('text_slots')->insert($slot + [
                    'slotable_type' => 'App\\Models\\Product',
                    'slotable_id' => $id,
                    'align' => 'center',
                    'sort_order' => $n,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if (! empty($d['photo'])) {
                DB::table('photo_slots')->insert([
                    'slotable_type' => 'App\\Models\\Product',
                    'slotable_id' => $id,
                    'label' => 'Cütlüyün şəkli',
                    'i18n' => json_encode([
                        'ru' => ['label' => 'Фото пары'],
                        'en' => ['label' => 'Your photo'],
                    ], JSON_UNESCAPED_UNICODE),
                    // The gold ring is printed; the photograph fills it.
                    'x' => $this->fx(154),
                    'y' => $this->fy(180),
                    'width' => $this->len(92),
                    'height' => $this->len(92),
                    'rotation' => 0,
                    'shape' => 'circle',
                    'sort_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        /* The shelf itself was switched off while it stood empty. It has
           thirteen designs on it now, so it belongs in the catalogue's own
           list of shelves like every other. */
        DB::table('product_categories')->where('slug', 'xonca')->update(['is_active' => true]);
    }

    public function down(): void
    {
        $slugs = array_column($this->designs(), 'slug');
        $ids = DB::table('products')->whereIn('slug', $slugs)->pluck('id');

        DB::table('text_slots')->where('slotable_type', 'App\\Models\\Product')->whereIn('slotable_id', $ids)->delete();
        DB::table('photo_slots')->where('slotable_type', 'App\\Models\\Product')->whereIn('slotable_id', $ids)->delete();
        DB::table('products')->whereIn('id', $ids)->delete();
        DB::table('product_categories')->where('slug', 'xonca')->update(['is_active' => false]);
    }

    // ------------------------------------------------------------ geometry

    /** His x, on the shop's face. */
    private function fx(float $x): int
    {
        return (int) round(($x - self::CARD_X) / self::CARD_W * self::W);
    }

    /** His y, at the same fraction of the card, on the shop's face. */
    private function fy(float $y): int
    {
        return (int) round(($y - self::CARD_Y) / self::CARD_H * self::H);
    }

    /** One of his units, in printed pixels - the same both ways. */
    private function len(float $n): int
    {
        return (int) round($n * self::W / self::CARD_W);
    }

    /**
     * Where the rows sit. A ribbon or a spray of flowers at the top pushes
     * everything below it down; that is the friend's own arrangement.
     */
    private function rows(bool $top): array
    {
        return $top
            ? ['label' => 218, 'n1' => 254, 'n2' => 306, 'mono' => 276, 'monoFs' => 42, 'line' => 304, 'date' => 362]
            : ['label' => 174, 'n1' => 236, 'n2' => 296, 'mono' => 262, 'monoFs' => 50, 'line' => 298, 'date' => 360];
    }

    // --------------------------------------------------------------- slots

    private function text(string $label, array $ru, float $y, float $size, string $color, string $font, array $extra = []): array
    {
        return array_merge([
            'label' => $label,
            'i18n' => json_encode($ru, JSON_UNESCAPED_UNICODE),
            'x' => (int) round(self::W / 2),
            'y' => $this->fy($y),
            'max_width' => $this->len(140),
            'font_size' => $this->len($size),
            'color' => $color,
            'font_family' => $font,
            'max_length' => 20,
            'max_lines' => 1,
            'kind' => 'text',
        ], $extra);
    }

    /** @return array<int, array<string, mixed>> */
    private function textSlots(array $d): array
    {
        $Y = $this->rows($d['top']);
        $dark = ! empty($d['dark']);
        $ink = $d['ink'];
        $label = $dark ? '#E2BE6E' : '#9C6C2A';
        $date = $dark ? '#E9D9B4' : '#6B4630';
        $gold = '#C9A04A';

        $slots = [];

        /* The occasion, printed in spaced capitals however it is typed - so
           «nişan» and «Nişan» both come out NİŞAN. */
        $slots[] = $this->text('Mərasim', ['ru' => ['label' => 'Торжество'], 'en' => ['label' => 'Occasion']],
            $Y['label'], 10.5, $label, 'Cinzel', [
                'max_length' => 16,
                'text_case' => 'upper',
                'tracking' => $this->len(3.5),
                'placeholder' => 'Nişan',
                'default_value' => 'Nişan',
                'max_width' => $this->len(136),
            ]);

        if ($d['kind'] === 'names') {
            $slots[] = $this->text('Birinci ad', ['ru' => ['label' => 'Первое имя'], 'en' => ['label' => 'First name']],
                $Y['n1'], 28, $ink, 'Great Vibes', ['placeholder' => 'Aynur']);
            $slots[] = $this->text('İkinci ad', ['ru' => ['label' => 'Второе имя'], 'en' => ['label' => 'Second name']],
                $Y['n2'], 28, $ink, 'Great Vibes', ['placeholder' => 'Elvin']);
        } elseif ($d['kind'] === 'photo') {
            $slots[] = $this->text('Adlar', ['ru' => ['label' => 'Имена'], 'en' => ['label' => 'Names']],
                306, 26, $ink, 'Great Vibes', ['placeholder' => 'Aynur & Elvin', 'max_length' => 30]);
        } elseif ($d['kind'] === 'olive') {
            // The branch runs down the middle, so the initials stand aside.
            $slots[] = array_merge($this->text('Birinci baş hərf', ['ru' => ['label' => 'Первая буква'], 'en' => ['label' => 'First initial']],
                270, 46, $ink, 'Cinzel', ['max_length' => 2, 'text_case' => 'upper']),
                ['x' => $this->fx(168), 'max_width' => $this->len(40)]);
            $slots[] = array_merge($this->text('İkinci baş hərf', ['ru' => ['label' => 'Вторая буква'], 'en' => ['label' => 'Second initial']],
                270, 46, $ink, 'Cinzel', ['max_length' => 2, 'text_case' => 'upper']),
                ['x' => $this->fx(232), 'max_width' => $this->len(40)]);
            $slots[] = $this->text('Adlar', ['ru' => ['label' => 'Имена'], 'en' => ['label' => 'Names']],
                306, 11, $ink, 'Cinzel', ['text_case' => 'upper', 'tracking' => $this->len(2),
                    'placeholder' => 'Aynur & Elvin', 'max_length' => 30, 'max_width' => $this->len(136)]);
        } else {
            // mono, wreath, heart: the initials in the middle, names beneath.
            $middle = ['mono' => [$Y['mono'], $Y['monoFs']], 'wreath' => [254, 26], 'heart' => [262, 26]][$d['kind']];
            $names = ['mono' => $Y['line'], 'wreath' => 310, 'heart' => 316][$d['kind']];

            $slots[] = $this->text('Baş hərflər', ['ru' => ['label' => 'Инициалы'], 'en' => ['label' => 'Initials']],
                $middle[0], $middle[1], $d['kind'] === 'mono' ? $gold : $ink, 'Cinzel', [
                    'max_length' => 5,
                    'text_case' => 'upper',
                    'tracking' => $this->len(4),
                    'placeholder' => 'A | E',
                    'max_width' => $this->len(120),
                ]);
            $slots[] = $this->text('Adlar', ['ru' => ['label' => 'Имена'], 'en' => ['label' => 'Names']],
                $names, 11, $ink, 'Cinzel', ['text_case' => 'upper', 'tracking' => $this->len(2),
                    'placeholder' => 'Aynur & Elvin', 'max_length' => 30, 'max_width' => $this->len(136)]);
        }

        $slots[] = $this->text('Tarix', ['ru' => ['label' => 'Дата'], 'en' => ['label' => 'Date']],
            $d['kind'] === 'heart' ? 364 : $Y['date'], 13, $date, 'Cinzel', [
                'max_length' => 12,
                'tracking' => $this->len(2),
                'placeholder' => '12.12.2026',
                'max_width' => $this->len(136),
            ]);

        return $slots;
    }

    // ------------------------------------------------------------ the list

    /** @return array<int, array<string, mixed>> */
    private function designs(): array
    {
        $ink = '#3A2617';
        $gold = '#B8912F';
        $onGold = '#E6C676';

        $rows = [
            ['id' => 'kalliqrafiya', 'name' => 'Kalliqrafiya', 'kind' => 'names', 'top' => false, 'ink' => $ink, 'box' => '#FFFDF9',
                'desc' => 'Xonça üçün ən çox seçilən şokolad qutusu. Ağ qutunun üstündə mərasimin adı, kalliqrafiya ilə adlarınız və tarix.',
                'ru' => ['Каллиграфия', 'Самая популярная коробочка для хончи: название торжества, ваши имена каллиграфией и дата.'],
                'en' => ['Calligraphy', 'The most chosen xonça box: the occasion, your names in calligraphy and the date.']],

            ['id' => 'qizili-cerceve', 'name' => 'Qızılı çərçivə', 'kind' => 'names', 'top' => false, 'ink' => $gold, 'box' => '#FFFDF9',
                'desc' => 'Ağ qutunun kənarında qızılı ikiqat çərçivə, adlar və tarix qızılı rəngdə. Qızılı xonçalarla dəst kimi görünür.',
                'ru' => ['Золотая рамка', 'Двойная золотая рамка по краю, имена и дата золотом. Смотрится в комплекте с золотой хончой.'],
                'en' => ['Gold frame', 'A double gold border, names and date in gold. A set with a gold xonça.']],

            ['id' => 'qizili-monoqram', 'name' => 'Qızılı monoqram', 'kind' => 'mono', 'top' => false, 'ink' => $ink, 'box' => '#FFFDF9',
                'desc' => 'Qutunun ortasında iri qızılı baş hərfləriniz, altında adlar və tarix.',
                'ru' => ['Золотая монограмма', 'Крупные золотые инициалы в центре, под ними имена и дата.'],
                'en' => ['Gold monogram', 'Large gold initials in the middle, names and date beneath.']],

            ['id' => 'zeytun', 'name' => 'Zeytun budağı', 'kind' => 'olive', 'top' => false, 'ink' => $ink, 'box' => '#FFFDF9',
                'desc' => 'Baş hərflərin arasında yaşıl zeytun budağı, altında adlar və tarix.',
                'ru' => ['Оливковая ветвь', 'Зелёная оливковая ветвь между инициалами, ниже имена и дата.'],
                'en' => ['Olive branch', 'A green olive branch between the initials, names and date below.']],

            ['id' => 'celeng', 'name' => 'Qızılı çələng', 'kind' => 'wreath', 'top' => false, 'ink' => $ink, 'box' => '#FFFDF9',
                'desc' => 'Baş hərfləriniz qızılı yarpaqlardan çələngin içində. Təntənəli, amma sakit görünür.',
                'ru' => ['Золотой венок', 'Инициалы внутри венка из золотых листьев. Торжественно и спокойно.'],
                'en' => ['Gold wreath', 'Your initials inside a wreath of gold leaves: formal, but quiet.']],

            ['id' => 'ag-cicek', 'name' => 'Ağ çiçək', 'kind' => 'mono', 'top' => true, 'ink' => $ink, 'box' => '#FFFDF9',
                'desc' => 'Yuxarıda akvarel üslubunda ağ çiçəklər, ortada baş hərfləriniz, altında adlar.',
                'ru' => ['Белый цветок', 'Акварельные белые цветы сверху, в центре инициалы, ниже имена.'],
                'en' => ['White flower', 'Watercolour white flowers above, initials in the middle, names below.']],

            ['id' => 'lavanda', 'name' => 'Lavanda', 'kind' => 'names', 'top' => false, 'ink' => $ink, 'box' => '#FFFDF9',
                'desc' => 'Qutunun iki tərəfində bənövşəyi lavanda budaqları, ortada adlarınız və tarix.',
                'ru' => ['Лаванда', 'Веточки лаванды по краям, в центре ваши имена и дата.'],
                'en' => ['Lavender', 'Lavender sprigs down both sides, your names and the date in the middle.']],

            ['id' => 'evkalipt', 'name' => 'Evkalipt', 'kind' => 'names', 'top' => false, 'ink' => $ink, 'box' => '#FFFDF9',
                'desc' => 'Yuxarıda və aşağıda yaşıl evkalipt yarpaqları, ortada adlar və tarix.',
                'ru' => ['Эвкалипт', 'Зелёные листья эвкалипта сверху и снизу, в центре имена и дата.'],
                'en' => ['Eucalyptus', 'Green eucalyptus above and below, names and date in the middle.']],

            ['id' => 'goy-fiyonk', 'name' => 'Göy fiyonk', 'kind' => 'mono', 'top' => true, 'ink' => $ink, 'box' => '#FFFDF9',
                'desc' => 'Qutunun başında tünd göy fiyonk, altında baş hərflər, adlar və tarix.',
                'ru' => ['Синий бант', 'Тёмно-синий бант сверху, под ним инициалы, имена и дата.'],
                'en' => ['Navy bow', 'A navy ribbon at the top, initials, names and date beneath.']],

            ['id' => 'krem-fiyonk', 'name' => 'Krem fiyonk', 'kind' => 'names', 'top' => true, 'ink' => $ink, 'box' => '#FFFDF9',
                'desc' => 'Qutunun başında krem atlas fiyonk, altında kalliqrafiya ilə adlarınız və tarix.',
                'ru' => ['Кремовый бант', 'Кремовый атласный бант сверху, ниже имена каллиграфией и дата.'],
                'en' => ['Cream bow', 'A cream satin ribbon above, your names in calligraphy and the date.']],

            ['id' => 'bordo-urek', 'name' => 'Bordo ürək', 'kind' => 'heart', 'top' => false, 'ink' => $onGold, 'dark' => true, 'box' => '#6E1F2B',
                'desc' => 'Bordo qutu, qızılı ürəyin içində baş hərfləriniz, altında adlar və tarix qızılı rəngdə.',
                'ru' => ['Бордовое сердце', 'Бордовая коробочка, инициалы в золотом сердце, имена и дата золотом.'],
                'en' => ['Burgundy heart', 'A burgundy box, initials in a gold heart, names and date in gold.']],

            ['id' => 'gece-mavisi', 'name' => 'Gecə mavisi', 'kind' => 'mono', 'top' => false, 'ink' => $onGold, 'dark' => true, 'box' => '#1F2A44',
                'desc' => 'Tünd göy qutu, qızılı baş hərflər, adlar və tarix. Ağ xonçaların yanında seçilir.',
                'ru' => ['Ночная синь', 'Тёмно-синяя коробочка, золотые инициалы, имена и дата. Выделяется на белой хонче.'],
                'en' => ['Midnight blue', 'A deep blue box with gold initials, names and date.']],

            ['id' => 'cutluk-sekli', 'name' => 'Cütlük şəkli', 'kind' => 'photo', 'top' => false, 'ink' => $ink, 'photo' => true, 'box' => '#FFFDF9',
                'desc' => 'Qutunun üstündə sizin şəkliniz, altında adlarınız və tarix. Şəkli sifariş zamanı yükləyirsiniz.',
                'ru' => ['Фото пары', 'Ваше фото на коробочке, под ним имена и дата. Фото загружаете при заказе.'],
                'en' => ['Your photo', 'Your photograph on the box, names and date beneath.']],
        ];

        return array_map(function (array $r) {
            $r['slug'] = 'xonca-' . $r['id'];
            $r['i18n'] = [
                'ru' => ['name' => $r['ru'][0], 'description' => $r['ru'][1]],
                'en' => ['name' => $r['en'][0], 'description' => $r['en'][1]],
            ];

            return $r;
        }, $rows);
    }
};
