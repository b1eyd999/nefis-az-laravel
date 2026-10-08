<?php

namespace App\Support;

use App\Models\GiftPage;
use App\Models\Setting;
use App\Models\Wrapping;

/**
 * What stands in the shop's "Məhsullar" menu, and in what order.
 *
 * Every entry was switched on by its own feature — a wrap existed, the
 * letters were on sale, the corporate page was enabled — and there was
 * nowhere to see the menu as a whole or to say "this one is not ready yet".
 * The owner now has one screen for it: show or hide each line, hang a small
 * "Yeni" or "Tezliklə" on it, and drag the order.
 *
 * The feature's own switch still has the last word: a wrapping page with no
 * wrappings on it helps nobody, so an entry whose page has nothing to show
 * stays out however the menu is set. The admin says so beside each line.
 */
class Menu
{
    /**
     * The lines that may appear. Fixed in code — each one needs a route that
     * exists and a page behind it, so this is not something to type into a
     * form; what the owner arranges is which of them show and how.
     */
    public const ENTRIES = [
        'designs' => [
            'route' => 'designs.index',
            'icon' => '🍫',
            'label' => 'Dizaynlar',
            'note' => 'Fərdi şokolad qutuları',
        ],
        'gifts' => [
            'route' => 'gifts.index',
            'icon' => '🎉',
            'label' => 'Hədiyyə fikirləri',
            'note' => 'Ad günü, Sevgiliyə, 14 Fevral…',
        ],
        'xonca' => [
            'route' => 'xonca.index',
            'icon' => '💍',
            'label' => 'Xonça və nişan',
            'note' => 'Xonçaya kiçik şokoladlar',
        ],
        'wrappings' => [
            'route' => 'wrappings.index',
            'icon' => '🎁',
            'label' => 'Qablaşdırma',
            'note' => 'Hədiyyə kağızı və lent',
        ],
        'letters' => [
            'route' => 'letters.create',
            'icon' => '💌',
            'label' => 'Polaroid məktub',
            'note' => 'Şəkil və sözlərlə polaroid',
        ],
        'live' => [
            'route' => 'live.create',
            'icon' => '🎬',
            'label' => 'Canlı şəkil',
            'note' => 'Telefonda canlanan şəkil (AR)',
        ],
        'corporate' => [
            'route' => 'corporate.index',
            'icon' => '🏢',
            'label' => 'Şirkətlər üçün',
            'note' => 'Loqonuzla mini şokolad',
        ],
    ];

    /** The badges offered, beyond leaving it empty. */
    public const BADGES = ['Yeni', 'Tezliklə'];

    /**
     * Where a line stands.
     *
     * Everything for sale lives under one word so the bar stays short however
     * many pages there are — but a line the shop is pushing is worth a word of
     * its own up there, where nobody has to open anything to find it.
     */
    public const PLACES = [
        'drop' => 'Məhsullar siyahısında',
        'top' => 'Yuxarı sətirdə',
    ];

    /** How the menu stands until the owner arranges it. */
    public static function defaults(): array
    {
        $out = [];
        $at = 0;
        foreach (array_keys(self::ENTRIES) as $key) {
            $out[$key] = [
                'on' => true,
                // Until the designs are drawn the line says so itself.
                'badge' => $key === 'xonca' ? 'Tezliklə' : '',
                /* Xonça stands on the bar. A tray is ordered for a date that
                   is already fixed, and the family buying one is not browsing
                   a list of products — they are looking for that word. */
                'place' => $key === 'xonca' ? 'top' : 'drop',
                /* And it is a word, not a door: the designs are not drawn,
                   so the line says «Tezliklə» and goes nowhere. The owner
                   opens it in the admin when there is something behind it. */
                'link' => $key !== 'xonca',
                'order' => $at++,
            ];
        }

        return $out;
    }

    public static function settings(): array
    {
        $saved = json_decode((string) Setting::get(Setting::MENU), true) ?: [];

        $out = self::defaults();
        foreach ($out as $key => $row) {
            if (! isset($saved[$key]) || ! is_array($saved[$key])) {
                continue;
            }
            $out[$key] = [
                'on' => (bool) ($saved[$key]['on'] ?? $row['on']),
                'badge' => trim((string) ($saved[$key]['badge'] ?? $row['badge'])),
                'place' => self::place($saved[$key]['place'] ?? $row['place']),
                'link' => (bool) ($saved[$key]['link'] ?? $row['link']),
                'order' => (int) ($saved[$key]['order'] ?? $row['order']),
            ];
        }

        return $out;
    }

    public static function save(array $rows): void
    {
        // What a row does not say about its place is what it says now, not
        // 'drop': the form always sends the field, but anything else calling
        // this would otherwise pull a line off the bar without meaning to.
        $standing = self::settings();

        $keep = [];
        $at = 0;
        foreach ($rows as $row) {
            $key = $row['key'] ?? null;
            if (! isset(self::ENTRIES[$key])) {
                continue;
            }
            $keep[$key] = [
                'on' => (bool) ($row['on'] ?? false),
                'badge' => mb_substr(trim((string) ($row['badge'] ?? '')), 0, 20),
                'place' => array_key_exists('place', $row)
                    ? self::place($row['place'])
                    : ($standing[$key]['place'] ?? 'drop'),
                'link' => array_key_exists('link', $row)
                    ? (bool) $row['link']
                    : ($standing[$key]['link'] ?? true),
                'order' => $at++,
            ];
        }

        Setting::put(Setting::MENU, json_encode($keep, JSON_UNESCAPED_UNICODE));
    }

    /** A place the menu knows about; anything else goes back in the list. */
    private static function place(mixed $value): string
    {
        // A hand-edited setting can hold anything at all, an array included.
        $value = is_string($value) ? $value : '';

        return isset(self::PLACES[$value]) ? $value : 'drop';
    }

    /**
     * Whether the page behind an entry has anything on it. The owner can hide
     * a line that is ready; he cannot show one that is not.
     */
    public static function ready(string $key): bool
    {
        return match ($key) {
            'designs' => true,
            'gifts' => GiftPage::shown()->inLocale(Locale::current())->exists(),
            /* On from the start, even before a design is filed under it:
               the page says in the owner's own words that they are being
               drawn, and the «Tezliklə» beside the line says so in the menu.
               A promise is worth more here than an absence. */
            'xonca' => true,
            'wrappings' => Wrapping::where('is_active', true)->exists(),
            'letters' => Letter::enabled(),
            'live' => LiveMaterials::enabled(),
            'corporate' => CorporatePage::enabled(),
            default => false,
        };
    }

    /** Why a line cannot be shown, in the owner's language, or null. */
    public static function why(string $key): ?string
    {
        if (self::ready($key)) {
            return null;
        }

        return match ($key) {
            'gifts' => 'Göstəriləcək hədiyyə səhifəsi yoxdur.',
            'wrappings' => 'Aktiv qablaşdırma yoxdur.',
            'letters' => 'Polaroid məktub satışı bağlıdır.',
            'live' => 'Canlı şəkil satışı bağlıdır.',
            'corporate' => 'Şirkətlər səhifəsi bağlıdır.',
            default => 'Hazır deyil.',
        };
    }

    /**
     * The menu as the visitor sees it: in the owner's order, only the lines
     * he has switched on and whose page has something on it.
     */
    public static function shown(): array
    {
        $rows = self::settings();
        uasort($rows, fn ($a, $b) => $a['order'] <=> $b['order']);

        $out = [];
        foreach ($rows as $key => $row) {
            if (! $row['on'] || ! self::ready($key)) {
                continue;
            }
            $out[] = self::ENTRIES[$key] + [
                'key' => $key,
                'badge' => $row['badge'],
                'place' => $row['place'],
                'link' => $row['link'],
            ];
        }

        return $out;
    }

    /**
     * The shown lines split into the two places, in the owner's order.
     *
     * Takes the list rather than fetching it, because working out what is
     * shown asks the database whether each page has anything on it — the
     * header needs both halves and must not pay for that twice.
     *
     * @return array{drop: array, top: array}
     */
    public static function split(?array $shown = null): array
    {
        $out = ['drop' => [], 'top' => []];
        foreach ($shown ?? self::shown() as $item) {
            $out[$item['place']][] = $item;
        }

        return $out;
    }

    /**
     * The lines standing in one place: 'drop' inside «Məhsullar», 'top' on
     * the bar beside it.
     */
    public static function shownIn(string $place): array
    {
        return self::split()[$place] ?? [];
    }

    /**
     * The words on a line. Two of them are the owner's own and are kept
     * where he already edits them: the letter's menu name on its settings
     * page, and the gift menu's note, which lists whichever occasions are
     * live rather than a sentence that would go stale.
     */
    public static function title(array $item): string
    {
        return $item['key'] === 'letters' ? Letter::text('menu') : __($item['label']);
    }

    public static function note(array $item, $gifts = null): string
    {
        if ($item['key'] === 'gifts' && $gifts !== null && $gifts->isNotEmpty()) {
            return $gifts->take(3)->pluck('menu_label')->implode(', ') . '…';
        }

        return __($item['note']);
    }

    /** Is this one line of the menu on screen? Used where a page links itself. */
    public static function has(string $key): bool
    {
        foreach (self::shown() as $entry) {
            if ($entry['key'] === $key) {
                return true;
            }
        }

        return false;
    }
}
