<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Keeps the admin's uploads small: artwork is re-encoded as WebP, which holds
 * the same transparency at a fraction of a PNG's size. The hosting carries
 * every upload, so this is what keeps it light.
 */
class ImageStore
{
    /**
     * @return array{0: string, 1: int, 2: int} path on the public disk, width, height
     */
    /**
     * What may be written out untouched when there is no converter for it.
     * Deliberately short, and deliberately without svg: an SVG is a script
     * that happens to draw, and these files are served from the shop's own
     * address.
     */
    private const KEPT_AS_IS = ['gif', 'pdf', 'png', 'jpg', 'jpeg', 'webp', 'heic', 'heif'];

    public static function store(UploadedFile $file, string $dir, string $prefix, int $quality = 90, ?int $maxSide = null): array
    {
        [$width, $height] = getimagesize($file->getRealPath()) ?: [0, 0];
        $name = $prefix . '-' . Str::lower(Str::random(10));

        /* What the file IS, read from its own bytes — never what it is
         * called. A real PNG named "cek.html" used to fall past the converter
         * below and be written as .html into storage/app/public, which is
         * symlinked into the document root: the shop then served the
         * customer's own script from nefis.az, and the owner ran it in his
         * admin session the moment he opened the receipt to check it.
         */
        $kind = strtolower((string) $file->guessExtension());

        $source = match ($kind) {
            'png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($file->getRealPath()) : false,
            'jpg', 'jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($file->getRealPath()) : false,
            'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file->getRealPath()) : false,
            default => false,
        };

        if ($source && function_exists('imagewebp')) {
            imagepalettetotruecolor($source);

            // The editor already shrinks big pictures before sending them;
            // this only catches one that reached the server another way.
            if ($maxSide && max($width, $height) > $maxSide) {
                $k = $maxSide / max($width, $height);
                $width = (int) round($width * $k);
                $height = (int) round($height * $k);
                $scaled = imagecreatetruecolor($width, $height);
                imagealphablending($scaled, false);
                imagesavealpha($scaled, true);
                imagecopyresampled($scaled, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));
                imagedestroy($source);
                $source = $scaled;
            }

            imagealphablending($source, false);
            imagesavealpha($source, true);
            $path = "{$dir}/{$name}.webp";
            ob_start();
            imagewebp($source, null, $quality);
            Storage::disk('public')->put($path, ob_get_clean());
            imagedestroy($source);

            return [$path, (int) $width, (int) $height];
        }

        /* Nothing could be re-encoded — an animated GIF, a PDF, or a build
           without GD. It is kept as it came, but only if what it actually is
           is something this shop stores, and under that name. */
        abort_unless(in_array($kind, self::KEPT_AS_IS, true), 422, 'Bu fayl növü qəbul edilmir.');

        $path = $file->storeAs($dir, $name . '.' . $kind, 'public');

        return [$path, (int) $width, (int) $height];
    }

    /**
     * A smaller copy of a picture already kept here, for the catalogue cards.
     *
     * A card is about 160 points wide on a phone and the posters are 1080
     * across, so without this every visitor downloads roughly twelve times
     * the pixels the screen paints. The copy is written once, beside the
     * original as `name@400.webp`, and this is safe to call again: it returns
     * the copy it already made.
     *
     * @return string|null the path on the public disk, or null when there is
     *                     nothing to shrink and the original must be used
     */
    public static function smaller(?string $path, int $side = 400, bool $make = false): ?string
    {
        if (blank($path)) {
            return null;
        }

        $disk = Storage::disk('public');
        // Always a WebP, whatever the original is: it is the smallest thing
        // every phone in use can read.
        $small = Str::beforeLast($path, '.') . '@' . $side . '.webp';

        if ($disk->exists($small)) {
            return $small;
        }

        // A page only ever serves a copy that is already there; making them is
        // the deploy's job, so no visitor waits on twenty-seven resizes.
        if (! $make || ! $disk->exists($path) || ! function_exists('imagewebp')) {
            return null;
        }

        $source = match (Str::lower(Str::afterLast($path, '.'))) {
            'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($disk->path($path)) : false,
            'png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($disk->path($path)) : false,
            'jpg', 'jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($disk->path($path)) : false,
            default => false,
        };

        if (! $source) {
            return null;
        }

        try {
            $w = imagesx($source);
            $h = imagesy($source);

            if (max($w, $h) <= $side) {
                return null;            // already small enough to serve as it is
            }

            $k = $side / max($w, $h);
            $scaled = imagecreatetruecolor((int) round($w * $k), (int) round($h * $k));
            imagepalettetotruecolor($source);
            imagealphablending($scaled, false);
            imagesavealpha($scaled, true);
            imagecopyresampled($scaled, $source, 0, 0, 0, 0, imagesx($scaled), imagesy($scaled), $w, $h);

            ob_start();
            imagewebp($scaled, null, 82);
            $disk->put($small, ob_get_clean());
            imagedestroy($scaled);

            return $small;
        } catch (\Throwable) {
            return null;
        } finally {
            imagedestroy($source);
        }
    }
}
