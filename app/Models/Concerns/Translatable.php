<?php

namespace App\Models\Concerns;

use App\Support\Locale;

/**
 * The owner writes everything in Azerbaijani, and may write the same field in
 * Russian or English next to it. The site then shows whichever the reader's
 * language has; where nothing was written, the Azerbaijani stands — a design
 * without a Russian name is better than a design with no name at all.
 *
 * The translations live in one JSON column, `i18n`, shaped
 * {"ru": {"name": "…"}, "en": {"name": "…"}}.
 */
trait Translatable
{
    /** What the reader should see in this field. */
    public function tr(string $field): mixed
    {
        $locale = Locale::current();
        if ($locale === Locale::DEFAULT) {
            return $this->{$field};
        }

        $written = data_get($this->i18n, $locale . '.' . $field);

        return filled($written) ? $written : $this->{$field};
    }

    /** What the admin's form is filled with, and what it saves back. */
    public function translationsFor(string $locale): array
    {
        return (array) data_get($this->i18n, $locale, []);
    }

    public function setTranslations(string $locale, array $values): void
    {
        $all = (array) $this->i18n;
        $all[$locale] = array_filter($values, fn ($v) => filled($v));

        if ($all[$locale] === []) {
            unset($all[$locale]);
        }

        $this->i18n = $all ?: null;
    }
}
