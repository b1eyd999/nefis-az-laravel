<?php

namespace App\Filament\Forms;

use App\Support\Locale;
use Filament\Forms;
use Filament\Forms\Components\Section;

/**
 * The same fields again, once per language the shop speaks. Folded away by
 * default: the owner writes in Azerbaijani and only opens this when he wants
 * the Russian or English wording too. Left empty, the Azerbaijani is shown.
 */
class Translations
{
    /**
     * @param  array<string, string>  $fields  field name => its label
     * @param  array<int, string>  $long  the fields that need a textarea
     */
    public static function section(array $fields, array $long = []): Section
    {
        $tabs = [];
        foreach (array_diff(Locale::all(), [Locale::DEFAULT]) as $locale) {
            $tabs[] = Forms\Components\Tabs\Tab::make(Locale::NAMES[$locale])
                ->schema(self::fields($locale, $fields, $long));
        }

        return Section::make('Tərcümələr')
            ->description('Rusca və ingiliscə saytda göstəriləcək mətnlər. Boş qalan sahə üçün azərbaycancası işlədilir.')
            ->collapsed()
            ->schema([Forms\Components\Tabs::make('i18n')->tabs($tabs)]);
    }

    /** @return array<int, Forms\Components\Field> */
    private static function fields(string $locale, array $fields, array $long): array
    {
        $made = [];
        foreach ($fields as $name => $label) {
            $made[] = in_array($name, $long, true)
                ? Forms\Components\Textarea::make('i18n.' . $locale . '.' . $name)->label($label)->rows(3)
                : Forms\Components\TextInput::make('i18n.' . $locale . '.' . $name)->label($label);
        }

        return $made;
    }
}
