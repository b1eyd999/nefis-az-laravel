<?php

namespace App\Models;

use App\Models\Concerns\Translatable;
use App\Support\Assets;
use App\Support\CorporatePage;
use App\Support\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One size of the mini chocolate a company puts its logo on.
 *
 * The company picks a size on /sirketler-ucun and everything on the page
 * follows it: the proportions of the box drawn in the preview, the picture
 * behind it where the owner has rendered one, the chip that says the
 * measurements, and the price ladder.
 *
 * The minimum order and the making time are not here. They are the same
 * whatever the size, so they stay where the owner already edits them, on the
 * page's own settings.
 */
class BoxSize extends Model
{
    use Translatable;

    protected $fillable = [
        'name', 'i18n', 'dimensions', 'width_mm', 'height_mm',
        'front_image', 'back_image',
        'face_left', 'face_top', 'face_right', 'face_bottom', 'face_quad',
        'ladder', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'i18n' => 'array',
            'ladder' => 'array',
            'face_quad' => 'array',
            'width_mm' => 'integer',
            'height_mm' => 'integer',
            'face_left' => 'float',
            'face_top' => 'float',
            'face_right' => 'float',
            'face_bottom' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function scopeOffered(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    /** The name in the reader's language, falling back to the owner's own. */
    public function title(): string
    {
        return (string) $this->tr('name');
    }

    public function dimensionsText(): string
    {
        return (string) $this->tr('dimensions');
    }

    public function frontUrl(): ?string
    {
        return self::pictureUrl($this->front_image);
    }

    public function backUrl(): ?string
    {
        return self::pictureUrl($this->back_image);
    }

    /**
     * Where a render is served from.
     *
     * Two kinds of file reach these columns. One is uploaded through the
     * admin and lands on the hosting's own disk, which is what Media
     * answers for. The other travels with the site, under public/images,
     * the way the three photographs of the counter already do — there is no
     * SSH on this host, so a render prepared from this side reaches the
     * server by being in the repository, and the deploy copies public/ into
     * the web root. Those are asked of Assets, which stamps the address, so
     * a re-render at the same name is picked up at once instead of after
     * the week of cache these pictures are served with.
     */
    private static function pictureUrl(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        return str_starts_with($path, 'images/') ? Assets::url($path) : Media::url($path);
    }

    /**
     * Where the printed face sits inside the render, as fractions.
     *
     * Read defensively: these are four numbers typed into a form, and a
     * right edge to the left of the left one would have the drawing paint
     * backwards rather than fail. Anything that does not describe a
     * rectangle falls back to the whole picture.
     *
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    public function faceRect(): array
    {
        $left = max(0.0, min(100.0, $this->face_left));
        $top = max(0.0, min(100.0, $this->face_top));
        $right = max(0.0, min(100.0, $this->face_right));
        $bottom = max(0.0, min(100.0, $this->face_bottom));

        if ($right <= $left || $bottom <= $top) {
            return [0.0, 0.0, 1.0, 1.0];
        }

        return [$left / 100, $top / 100, $right / 100, $bottom / 100];
    }

    /**
     * The four corners of the printed face inside the render, as fractions
     * of the picture, clockwise from the face's own top left.
     *
     * A box is rarely photographed dead square on, and on a turned or
     * tilted render the face is not a rectangle at all. Four points say
     * where it really is; the page warps the logo onto them the same way it
     * lays one onto the photograph of a café counter.
     *
     * Read defensively, because these are four points dragged over a
     * picture and saved as JSON. Anything that is not four sane, spread
     * out, uncrossed points falls back to the rectangle — which is also
     * what every size has until the owner drags them.
     *
     * @return array<int, array{0: float, 1: float}>
     */
    public function faceCorners(): array
    {
        $points = self::cleanQuad($this->face_quad);

        if ($points !== null) {
            return $points;
        }

        [$left, $top, $right, $bottom] = $this->faceRect();

        return [[$left, $top], [$right, $top], [$right, $bottom], [$left, $bottom]];
    }

    /** @return array<int, array{0: float, 1: float}>|null */
    private static function cleanQuad(mixed $saved): ?array
    {
        if (! is_array($saved) || count($saved) !== 4) {
            return null;
        }

        $points = [];
        foreach ($saved as $point) {
            $point = is_array($point) ? array_values($point) : [];
            if (count($point) < 2 || ! is_numeric($point[0]) || ! is_numeric($point[1])) {
                return null;
            }
            $points[] = [
                max(0.0, min(1.0, (float) $point[0])),
                max(0.0, min(1.0, (float) $point[1])),
            ];
        }

        /* Four points on top of each other are not a face. Two per cent of
           the picture each way is the smallest one the admin lets him make,
           and the warper refuses anything smaller anyway. */
        $xs = array_column($points, 0);
        $ys = array_column($points, 1);
        if (max($xs) - min($xs) < 0.02 || max($ys) - min($ys) < 0.02) {
            return null;
        }

        return self::convex($points) ? $points : null;
    }

    /**
     * Whether the four points go round without crossing.
     *
     * A corner dragged past its neighbours makes a bow tie, and the warper
     * would paint the logo folded over itself rather than fail. Every turn
     * has to go the same way round.
     *
     * @param  array<int, array{0: float, 1: float}>  $points
     */
    private static function convex(array $points): bool
    {
        $sign = 0;

        for ($i = 0; $i < 4; $i++) {
            $a = $points[$i];
            $b = $points[($i + 1) % 4];
            $c = $points[($i + 2) % 4];
            $cross = ($b[0] - $a[0]) * ($c[1] - $b[1]) - ($b[1] - $a[1]) * ($c[0] - $b[0]);

            if (abs($cross) < 1e-9) {
                continue;               // three of them in a line: harmless
            }

            $now = $cross > 0 ? 1 : -1;
            if ($sign === 0) {
                $sign = $now;
            } elseif ($sign !== $now) {
                return false;
            }
        }

        return $sign !== 0;
    }

    /**
     * This size's price ladder, or the page's own where it has none.
     *
     * A size added before its prices are known shows the shop's existing
     * ladder rather than no price at all — which is what a company reading
     * the page is there for.
     *
     * @return array<int, array{from: int, price: float}>
     */
    public function ladderRows(): array
    {
        $own = CorporatePage::ladderRows($this->ladder);

        return $own !== [] ? $own : CorporatePage::sharedLadder();
    }

    /** What the page and the script need to know about this size. */
    public function forPage(): array
    {
        [$left, $top, $right, $bottom] = $this->faceRect();

        return [
            'id' => $this->id,
            'title' => $this->title(),
            'dimensions' => $this->dimensionsText(),
            'w' => $this->width_mm,
            'h' => $this->height_mm,
            'front' => $this->frontUrl(),
            'back' => $this->backUrl(),
            'face' => ['l' => $left, 't' => $top, 'r' => $right, 'b' => $bottom],
            // Where the face really sits. `face` above is the same thing
            // squared off, for a browser that cannot warp.
            'quad' => $this->faceCorners(),
            'ladder' => $this->ladderRows(),
        ];
    }
}
