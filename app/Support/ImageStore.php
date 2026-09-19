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
    public static function store(UploadedFile $file, string $dir, string $prefix, int $quality = 90, ?int $maxSide = null): array
    {
        [$width, $height] = getimagesize($file->getRealPath()) ?: [0, 0];
        $name = $prefix . '-' . Str::lower(Str::random(10));

        $source = match (strtolower($file->getClientOriginalExtension())) {
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

        // No converter available: keep the file as it came.
        $path = $file->storeAs($dir, $name . '.' . strtolower($file->getClientOriginalExtension()), 'public');

        return [$path, (int) $width, (int) $height];
    }
}
