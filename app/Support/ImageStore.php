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

    /**
     * The «which way up» flag a camera writes beside the pixels, 1 if none.
     *
     * Read out of the bytes rather than with exif_read_data(), because the
     * exif extension is not switched on everywhere this runs — and a
     * photograph coming out sideways is not a thing to leave depending on a
     * hosting setting.
     */
    public static function orientation(string $path): int
    {
        $fh = @fopen($path, 'rb');
        if (! $fh) {
            return 1;
        }
        $head = (string) fread($fh, 256 * 1024);
        fclose($fh);

        if (strlen($head) < 4 || substr($head, 0, 2) !== "\xFF\xD8") {
            return 1;                                   // not a JPEG
        }

        // Walk the markers to APP1, which is where Exif lives.
        $i = 2;
        $n = strlen($head);
        while ($i + 4 <= $n) {
            if ($head[$i] !== "\xFF") {
                $i++;
                continue;
            }
            $marker = ord($head[$i + 1]);
            if ($marker === 0xD8 || $marker === 0x01 || ($marker >= 0xD0 && $marker <= 0xD7)) {
                $i += 2;
                continue;
            }
            if ($marker === 0xDA || $marker === 0xD9) {
                return 1;                               // the pixels start here
            }
            $len = (ord($head[$i + 2]) << 8) | ord($head[$i + 3]);
            if ($len < 2) {
                return 1;
            }
            if ($marker === 0xE1 && substr($head, $i + 4, 6) === "Exif\0\0") {
                return self::inExif(substr($head, $i + 10, max(0, $len - 8)));
            }
            $i += 2 + $len;
        }

        return 1;
    }

    /** Tag 0x0112 of the first directory of a TIFF header. */
    private static function inExif(string $tiff): int
    {
        if (strlen($tiff) < 14) {
            return 1;
        }
        $byteOrder = substr($tiff, 0, 2);
        if ($byteOrder !== 'II' && $byteOrder !== 'MM') {
            return 1;
        }
        $big = $byteOrder === 'MM';
        $short = fn (int $at) => strlen($tiff) >= $at + 2
            ? (int) unpack($big ? 'n' : 'v', substr($tiff, $at, 2))[1] : 0;
        $long = fn (int $at) => strlen($tiff) >= $at + 4
            ? (int) unpack($big ? 'N' : 'V', substr($tiff, $at, 4))[1] : 0;

        $ifd = $long(4);
        if ($ifd < 8 || $ifd + 2 > strlen($tiff)) {
            return 1;
        }
        $count = $short($ifd);
        for ($e = 0; $e < $count; $e++) {
            $at = $ifd + 2 + $e * 12;
            if ($at + 12 > strlen($tiff)) {
                break;
            }
            if ($short($at) === 0x0112) {
                $value = $short($at + 8);

                return $value >= 1 && $value <= 8 ? $value : 1;
            }
        }

        return 1;
    }

    /**
     * The same picture, the right way up.
     *
     * imagerotate turns counter-clockwise, so a flag that says «turn this a
     * quarter clockwise» is asked for as 270.
     *
     * @param  \GdImage  $image
     * @return \GdImage
     */
    private static function turn($image, int $orientation)
    {
        if ($orientation <= 1 || $orientation > 8 || ! function_exists('imagerotate')) {
            return $image;
        }

        $angle = match ($orientation) {
            3, 4 => 180,
            5, 6 => 270,
            7, 8 => 90,
            default => 0,
        };

        $out = $angle === 0 ? $image : @imagerotate($image, $angle, 0);
        if (! $out) {
            return $image;
        }
        if ($angle !== 0) {
            imagealphablending($out, false);
            imagesavealpha($out, true);
        }

        // 2, 4, 5 and 7 are the mirrored ones.
        if (in_array($orientation, [2, 4, 5, 7], true) && function_exists('imageflip')) {
            imageflip($out, IMG_FLIP_HORIZONTAL);
        }

        return $out;
    }

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

            /* A telephone does not turn the picture it takes: it writes the
               pixels sideways and a flag beside them saying which way up it
               goes. Every browser obeys that flag, GD does not — so the
               customer placed his face upright on the screen and the shop
               stored, and printed, the same face lying on its side.
               Turned here, once, and the flag is then of no further use:
               what is written out has no EXIF at all. */
            $turned = self::turn($source, self::orientation($file->getRealPath()));
            if ($turned !== $source) {
                imagedestroy($source);
                $source = $turned;
                $width = imagesx($source);
                $height = imagesy($source);
            }

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
            $written = Storage::disk('public')->put($path, ob_get_clean());
            imagedestroy($source);

            /* A full disk or a quota reached makes put() answer false, and
               the path was handed back all the same: the order carried a
               receipt that was not there, and the caller then deleted the
               one before it. */
            if ($written === false) {
                throw new \RuntimeException('Could not write ' . $path);
            }

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

        /* A gift wrap arrived as a print master twelve thousand pixels
           square. GD wants four bytes a pixel before it will look at it —
           six hundred megabytes — and running out of memory is not an
           exception on the way to a catch block, it is the end of the
           process. Every wrapping paper, poster and card queued behind that
           one was never made, and the deploy still said it had succeeded,
           so the shop went on serving the three-megabyte master to every
           customer who opened a design.

           So the size is read from the file's header first, which costs
           nothing, and anything too big for GD is handed to Imagick, which
           can decode a JPEG straight to the size we asked for. */
        if (self::tooBigForGd($disk->path($path))) {
            return self::smallerWithImagick($disk, $path, $small, $side);
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

    /**
     * Would opening this with GD cost more memory than we are allowed?
     *
     * Four bytes a pixel for the picture, and the scaled copy wants its own
     * on top, so the whole is asked for with room to spare. A file whose
     * header cannot be read is left to GD, which will simply refuse it.
     */
    private static function tooBigForGd(string $full): bool
    {
        $size = @getimagesize($full);
        if (! $size || empty($size[0]) || empty($size[1])) {
            return false;
        }

        $limit = self::memoryLimit();
        if ($limit <= 0) {
            return false;                       // no limit set: GD may try
        }

        $wants = $size[0] * $size[1] * 4 * 1.25;

        return $wants > ($limit - memory_get_usage(true));
    }

    /** memory_limit in bytes, or 0 when it is unlimited. */
    private static function memoryLimit(): int
    {
        $raw = trim((string) ini_get('memory_limit'));
        if ($raw === '' || $raw === '-1') {
            return 0;
        }

        $n = (int) $raw;

        return match (strtolower(substr($raw, -1))) {
            'g' => $n * 1024 * 1024 * 1024,
            'm' => $n * 1024 * 1024,
            'k' => $n * 1024,
            default => $n,
        };
    }

    /**
     * The same copy, for a picture GD cannot lift.
     *
     * Imagick is told the size we want before it reads: for a JPEG that lets
     * it throw away seven eighths of the file during decoding, so a master
     * GD needs six hundred megabytes for costs a few. Where Imagick is not
     * installed there is nothing more to try, and null leaves the page
     * serving the original — heavy, but there.
     */
    private static function smallerWithImagick($disk, string $path, string $small, int $side): ?string
    {
        if (! class_exists(\Imagick::class)) {
            return null;
        }

        $im = null;
        try {
            $im = new \Imagick();
            // Only JPEG reads this, and on anything else it is ignored.
            $im->setOption('jpeg:size', $side . 'x' . $side);
            $im->readImage($disk->path($path));
            $im->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT);

            if (max($im->getImageWidth(), $im->getImageHeight()) <= $side) {
                return null;                    // already small enough as it is
            }

            $im->thumbnailImage($side, $side, true);
            $im->setImageFormat('webp');
            $im->setImageCompressionQuality(82);

            return $disk->put($small, $im->getImageBlob()) ? $small : null;
        } catch (\Throwable) {
            return null;
        } finally {
            if ($im instanceof \Imagick) {
                $im->clear();
            }
        }
    }
}
