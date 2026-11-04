<?php

namespace App\Models;

use App\Models\Concerns\Translatable;
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
        'face_left', 'face_top', 'face_right', 'face_bottom',
        'ladder', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'i18n' => 'array',
            'ladder' => 'array',
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
        return Media::url($this->front_image);
    }

    public function backUrl(): ?string
    {
        return Media::url($this->back_image);
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
            'ladder' => $this->ladderRows(),
        ];
    }
}
