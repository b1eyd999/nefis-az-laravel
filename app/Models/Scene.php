<?php

namespace App\Models;

use App\Support\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

/**
 * A mockup the customer sees their box in, built by the owner in the scene
 * editor: a background, pictures stacked over it (the rendered empty box,
 * props), and one or more places where the flat design is corner-pinned.
 *
 * `elements` is the stack, bottom first. Each entry is either
 *   image:  {id, type, name, image, x, y, width, height, rotation, opacity,
 *            blend, flip_x, flip_y, tint, sheen, recolor, locked, hidden}
 *           tint dyes a white render, sheen (0-100) keeps its highlights, and
 *           recolor makes it take each product's own box colour instead.
 *   design: {id, type, name, corners: [[x,y] top-left, top-right,
 *            bottom-right, bottom-left], opacity, blend, shade, shade_from,
 *            locked, hidden}
 * in the scene's own pixels. `shade` (0-200 %) takes the light and paper
 * texture of the image `shade_from` names and lays it over the design.
 */
class Scene extends Model
{
    public const BLENDS = ['source-over', 'multiply', 'screen', 'overlay', 'soft-light', 'hard-light', 'darken', 'lighten'];

    protected $fillable = [
        'name', 'background', 'background_color', 'width', 'height',
        'elements', 'preview_image', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'elements' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::deleted(function (Scene $scene) {
            if ($scene->preview_image) {
                Storage::disk('public')->delete($scene->preview_image);
            }
        });
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    public function scopeShown(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    /** Every library picture this scene draws. */
    public function imagePaths(): array
    {
        return collect($this->elements ?? [])
            ->where('type', 'image')
            ->pluck('image')
            ->push($this->background)
            ->filter()
            ->values()
            ->all();
    }

    /** What the customer page draws; hidden elements are left out. */
    public function toCustomer(): array
    {
        $elements = collect($this->elements ?? [])
            ->reject(fn (array $el) => ! empty($el['hidden']))
            ->map(fn (array $el) => ($el['type'] ?? null) === 'image'
                ? $el + ['url' => Media::url($el['image'] ?? null)]
                : $el)
            ->values()
            ->all();

        return [
            'label' => $this->name,
            'w' => (int) $this->width,
            'h' => (int) $this->height,
            'bgColor' => $this->background_color,
            'bg' => Media::url($this->background),
            'elements' => $elements,
        ];
    }

    /**
     * One of the stand-in chocolate-bar scenes in config/boxes.php, in the
     * same shape, for as long as the owner has not built scenes of their own.
     */
    public static function fromConfig(array $scene, int $w, int $h): array
    {
        return [
            'label' => $scene['label'],
            'w' => (int) $scene['width'],
            'h' => (int) $scene['height'],
            'bgColor' => null,
            // Scenes are part of the site itself, shipped in public/.
            'bg' => asset($scene['background']),
            'elements' => [[
                'id' => 'design', 'type' => 'design', 'name' => 'Dizayn',
                'corners' => self::rectCorners($scene['cx'], $scene['cy'], $scene['scale'] * $w, $scene['scale'] * $h, $scene['rotation']),
                'opacity' => 100, 'blend' => 'source-over', 'shade' => 0, 'shade_from' => null,
            ]],
        ];
    }

    /** The corners of a w×h rectangle centred on cx,cy and turned by deg. */
    public static function rectCorners(float $cx, float $cy, float $w, float $h, float $deg): array
    {
        $r = deg2rad($deg);
        $cos = cos($r);
        $sin = sin($r);

        return array_map(fn ($p) => [
            round($cx + $p[0] * $cos - $p[1] * $sin, 2),
            round($cy + $p[0] * $sin + $p[1] * $cos, 2),
        ], [[-$w / 2, -$h / 2], [$w / 2, -$h / 2], [$w / 2, $h / 2], [-$w / 2, $h / 2]]);
    }
}
