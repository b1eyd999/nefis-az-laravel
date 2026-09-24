<?php

namespace App\Support;

/**
 * The shop speaks three languages. Azerbaijani is the site itself — it keeps
 * the addresses it was found at — while Russian and English live one step in:
 * /ru/… and /en/…. Every route is registered once per language, its name
 * carrying the language in front ("ru.cart.index"), so a page always links on
 * in the language the customer is reading.
 */
class Locale
{
    public const DEFAULT = 'az';

    /** What the switcher shows, in each language's own words. */
    public const NAMES = [
        'az' => 'Azərbaycanca',
        'ru' => 'Русский',
        'en' => 'English',
    ];

    public const SHORT = ['az' => 'AZ', 'ru' => 'RU', 'en' => 'EN'];

    /** @return array<int, string> */
    public static function all(): array
    {
        return array_keys(self::NAMES);
    }

    /**
     * The languages a customer is offered. A language whose pages are still
     * half-written is reachable by address — so it can be looked at — but it
     * is not in the switcher, not in hreflang and not in the sitemap, and it
     * tells search engines not to keep it.
     *
     * @return array<int, string>
     */
    public static function published(): array
    {
        $set = preg_split('/[^a-z]+/', strtolower((string) \App\Models\Setting::get(\App\Models\Setting::SITE_LANGUAGES))) ?: [];
        $set = array_values(array_intersect(self::all(), $set));

        return $set === [] ? [self::DEFAULT] : array_unique(array_merge([self::DEFAULT], $set));
    }

    public static function isPublished(?string $locale = null): bool
    {
        return in_array($locale ?: self::current(), self::published(), true);
    }

    public static function current(): string
    {
        $locale = app()->getLocale();

        return isset(self::NAMES[$locale]) ? $locale : self::DEFAULT;
    }

    public static function is(string $locale): bool
    {
        return self::current() === $locale;
    }

    /** The name a route goes by in this language: "ru.cart.index". */
    public static function route(string $name, ?string $locale = null): string
    {
        $locale = $locale ?: self::current();

        return $locale === self::DEFAULT ? $name : $locale . '.' . $name;
    }

    /** The address of the page being looked at, in another language. */
    public static function switchUrl(string $locale): string
    {
        $name = (string) request()->route()?->getName();
        $bare = preg_replace('/^(?:' . implode('|', array_diff(self::all(), [self::DEFAULT])) . ')\./', '', $name);
        $wanted = self::route((string) $bare, $locale);

        if ($name === '' || ! app('router')->has($wanted)) {
            return self::home($locale);
        }

        try {
            return route($wanted, request()->route()->parameters() + request()->query());
        } catch (\Throwable) {
            return self::home($locale);
        }
    }

    public static function home(string $locale): string
    {
        return url($locale === self::DEFAULT ? '/' : '/' . $locale);
    }

    /**
     * What the <html lang> attribute says, and what search engines are told
     * in hreflang: "az", "ru", "en".
     */
    public static function tag(?string $locale = null): string
    {
        return $locale ?: self::current();
    }
}
