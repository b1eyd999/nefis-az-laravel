<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The one key that lets Samira — the assistant on the owner's own computer —
 * read the shop.
 *
 * She reads and never writes: what was paid for, what is waiting, today's
 * figures. No status changes, no prices, nothing that moves money.
 *
 * The site makes the key itself, on the owner's request, from the operating
 * system's own randomness, and shows it to him once. Only its SHA-256 is
 * kept — a database copy that leaks gives nobody the key — and a fresh key
 * replaces the old one outright, so a key he suspects has been seen is
 * finished the moment he presses the button again.
 *
 * SHA-256 rather than bcrypt on purpose: the key is 256 random bits, not a
 * word a person chose, so there is no dictionary to slow down, and she asks
 * every minute — a deliberately slow hash would only slow down the shop.
 */
final class SamiraKey
{
    /** Said at the front so the setup window on the computer knows which site a pasted key is for. */
    public const PREFIX = 'nefis_';

    /** Longer than this is not a key of ours, whatever it is. */
    private const LONGEST = 200;

    /** How often «last read» is written down — not on every call, it is a database write. */
    private const SEEN_EVERY = 60;

    /**
     * A new key, written down as its hash, and the old one is gone.
     *
     * Returned once, for the page to show; nothing else ever has it.
     */
    public static function make(): string
    {
        $key = self::PREFIX . bin2hex(random_bytes(32));

        Setting::put(Setting::SAMIRA_KEY_HASH, self::hash($key));
        Setting::put(Setting::SAMIRA_KEY_MADE, (string) now()->getTimestamp());
        // A new key has not been read with yet: the old key's reads say
        // nothing about whether the computer has the new one.
        Setting::put(Setting::SAMIRA_SEEN, '');
        Setting::put(Setting::SAMIRA_SEEN_IP, '');

        return $key;
    }

    /** No key at all: every call is refused until a new one is made. */
    public static function off(): void
    {
        Setting::put(Setting::SAMIRA_KEY_HASH, '');
        Setting::put(Setting::SAMIRA_KEY_MADE, '');
        Setting::put(Setting::SAMIRA_SEEN, '');
        Setting::put(Setting::SAMIRA_SEEN_IP, '');
    }

    public static function exists(): bool
    {
        return filled(Setting::get(Setting::SAMIRA_KEY_HASH));
    }

    /**
     * Whether what was presented is the key.
     *
     * Compared as hashes with hash_equals, so how long the comparison takes
     * says nothing about how much of a guess was right.
     */
    public static function matches(?string $presented): bool
    {
        $stored = (string) Setting::get(Setting::SAMIRA_KEY_HASH);

        if ($stored === '' || $presented === null || $presented === '' || strlen($presented) > self::LONGEST) {
            return false;
        }

        return hash_equals($stored, self::hash($presented));
    }

    public static function hash(string $key): string
    {
        return hash('sha256', $key);
    }

    /** She has just read the shop: written down at most once a minute. */
    public static function seen(Request $request): void
    {
        $last = (int) Setting::get(Setting::SAMIRA_SEEN);

        if ($last > 0 && now()->getTimestamp() - $last < self::SEEN_EVERY) {
            return;
        }

        Setting::put(Setting::SAMIRA_SEEN, (string) now()->getTimestamp());
        Setting::put(Setting::SAMIRA_SEEN_IP, (string) $request->ip());
    }

    public static function madeAt(): ?Carbon
    {
        return self::moment(Setting::get(Setting::SAMIRA_KEY_MADE));
    }

    public static function seenAt(): ?Carbon
    {
        return self::moment(Setting::get(Setting::SAMIRA_SEEN));
    }

    public static function seenFrom(): ?string
    {
        return filled($ip = Setting::get(Setting::SAMIRA_SEEN_IP)) ? $ip : null;
    }

    private static function moment(?string $unix): ?Carbon
    {
        return ctype_digit((string) $unix) && (int) $unix > 0
            ? Carbon::createFromTimestamp((int) $unix, config('app.timezone'))
            : null;
    }
}
