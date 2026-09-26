<?php

namespace App\Support;

/**
 * The little scannable bar with the waveform that opens a song in Spotify.
 *
 * The customer pastes whatever Spotify handed him — the share link, the app's
 * own uri, sometimes with a country prefix and a ?si= tail — and we keep the
 * one canonical form. The picture itself is drawn by Spotify's own service:
 * that way the code stays right if they ever change how one looks, and we are
 * not the ones deciding what a scanner has to read.
 */
class SpotifyCode
{
    /** What a code may point at. Spotify draws all of these. */
    public const KINDS = ['track', 'album', 'playlist', 'artist', 'episode', 'show'];

    private const DRAWER = 'https://scannables.scdn.co/uri/plain';

    /** Spotify ids are always 22 characters of base62. */
    private const ID = '[A-Za-z0-9]{22}';

    /**
     * The canonical spotify:track:… for anything a customer might paste, or
     * null when it is not a Spotify address at all.
     */
    public static function uri(?string $input): ?string
    {
        $input = trim((string) $input);
        if ($input === '') {
            return null;
        }

        $kinds = implode('|', self::KINDS);

        // The app's own form, straight from "Copy Spotify URI".
        if (preg_match('~^spotify:(' . $kinds . '):(' . self::ID . ')$~', $input, $m)) {
            return 'spotify:' . $m[1] . ':' . $m[2];
        }

        // The share link, with or without an /intl-az/ prefix and a ?si= tail.
        if (preg_match('~^https?://(?:open|play)\.spotify\.com/(?:intl-[a-z-]+/)?(' . $kinds . ')/(' . self::ID . ')~', $input, $m)) {
            return 'spotify:' . $m[1] . ':' . $m[2];
        }

        return null;
    }

    public static function isValid(?string $input): bool
    {
        return self::uri($input) !== null;
    }

    /** track, album, playlist … — for saying in words what was ordered. */
    public static function kind(string $uri): string
    {
        return explode(':', $uri)[1] ?? 'track';
    }

    /** The page that opens when the printed code is scanned. */
    public static function link(string $uri): string
    {
        $parts = explode(':', $uri);

        return 'https://open.spotify.com/' . ($parts[1] ?? 'track') . '/' . ($parts[2] ?? '');
    }

    /**
     * The code as a picture.
     *
     * svg for printing — it is a vector, so it holds at any size on the box —
     * and png for the screen. The background is a hex without its '#', and
     * the bars are either 'black' or 'white'; Spotify draws nothing else.
     * Their png stops at 1024 across, which is why nothing here asks for more.
     */
    public static function image(string $uri, string $format = 'svg', string $background = 'ffffff',
        string $bars = 'black', int $width = 640): string
    {
        $format = in_array($format, ['svg', 'png', 'jpeg'], true) ? $format : 'svg';
        $bars = $bars === 'white' ? 'white' : 'black';
        $background = ltrim($background, '#');
        $background = preg_match('~^[0-9a-fA-F]{6}$~', $background) ? strtolower($background) : 'ffffff';
        $width = max(160, min($format === 'svg' ? 4096 : 1024, $width));

        return self::DRAWER . '/' . $format . '/' . $background . '/' . $bars . '/' . $width . '/' . $uri;
    }

    /** Azerbaijani for what the code opens, for the order line. */
    public static function kindLabel(string $uri): string
    {
        return match (self::kind($uri)) {
            'album' => 'Albom',
            'playlist' => 'Pleylist',
            'artist' => 'İfaçı',
            'episode' => 'Epizod',
            'show' => 'Podkast',
            default => 'Mahnı',
        };
    }
}
