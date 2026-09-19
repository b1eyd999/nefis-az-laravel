<?php

namespace App\Support;

/**
 * Reads the colour a box should be from its design.
 *
 * A box's sides carry the colour its front design runs out to at the edges,
 * so this looks at a thin band around the border and takes the most common
 * colour there. Counting clusters instead of averaging means a photo that
 * touches one edge (the Spotify box's top half) does not muddy the colour of
 * the background that runs along the rest.
 */
class ImageColor
{
    public static function edgeColor(string $file): ?string
    {
        if (! is_file($file) || ! function_exists('imagecreatefromstring')) {
            return null;
        }
        $im = @imagecreatefromstring((string) file_get_contents($file));
        if (! $im) {
            return null;
        }

        // A small copy is plenty to find a dominant colour, and cheap.
        $w = imagesx($im);
        $h = imagesy($im);
        $k = min(1, 200 / max($w, $h));
        $sw = max(1, (int) round($w * $k));
        $sh = max(1, (int) round($h * $k));
        $small = imagecreatetruecolor($sw, $sh);
        imagealphablending($small, false);
        imagesavealpha($small, true);
        imagecopyresampled($small, $im, 0, 0, 0, 0, $sw, $sh, $w, $h);
        imagedestroy($im);

        $band = max(1, (int) round(min($sw, $sh) * 0.04));
        $bins = [];
        for ($y = 0; $y < $sh; $y++) {
            for ($x = 0; $x < $sw; $x++) {
                if ($x >= $band && $x < $sw - $band && $y >= $band && $y < $sh - $band) {
                    continue;
                }
                $c = imagecolorat($small, $x, $y);
                if ((($c >> 24) & 0x7F) > 64) {
                    continue; // mostly transparent
                }
                $r = ($c >> 16) & 0xFF;
                $g = ($c >> 8) & 0xFF;
                $b = $c & 0xFF;
                $key = ($r >> 4) << 8 | ($g >> 4) << 4 | ($b >> 4);
                $bins[$key] ??= [0, 0, 0, 0];
                $bins[$key][0]++;
                $bins[$key][1] += $r;
                $bins[$key][2] += $g;
                $bins[$key][3] += $b;
            }
        }
        imagedestroy($small);

        if (! $bins) {
            return null;
        }
        usort($bins, fn ($a, $b) => $b[0] <=> $a[0]);
        [$n, $r, $g, $b] = $bins[0];

        return sprintf('#%02x%02x%02x', (int) round($r / $n), (int) round($g / $n), (int) round($b / $n));
    }
}
