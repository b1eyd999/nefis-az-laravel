<?php

namespace App\Models;

use App\Support\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * A picture on the box editor's shelf: a frame, a pattern, a sticker. Uploaded
 * once and put on any design.
 *
 * The design never points here. Putting one on a box copies the file into that
 * box's own folder, so deleting a picture from the shelf cannot empty a design
 * that is already on sale.
 */
class LibraryAsset extends Model
{
    public const DIRECTORY = 'library';

    /** What each kind is called in the admin and in the editor's picker. */
    public const CATEGORIES = [
        'frame' => 'Çərçivə',
        'pattern' => 'Naxış',
        'sticker' => 'Naklyeka',
        'other' => 'Digər',
    ];

    protected $fillable = ['name', 'category', 'image', 'width', 'height', 'is_active', 'sort_order'];

    protected $casts = ['width' => 'integer', 'height' => 'integer', 'is_active' => 'boolean', 'sort_order' => 'integer'];

    protected static function booted(): void
    {
        // The measurements are read off the file itself, whichever way it
        // arrived: the admin's form, or the editor's own upload.
        static::saving(function (LibraryAsset $asset) {
            if ($asset->isDirty('image') || ! $asset->width || ! $asset->height) {
                [$w, $h] = $asset->measure();
                $asset->width = $w;
                $asset->height = $h;
            }
        });

        static::deleted(fn (LibraryAsset $asset) => Storage::disk('public')->delete($asset->image));
    }

    public function scopeOffered(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }

    public function categoryName(): string
    {
        return self::CATEGORIES[$this->category] ?? self::CATEGORIES['other'];
    }

    /** @return array{0: int, 1: int} */
    public function measure(): array
    {
        $disk = Storage::disk('public');

        if (! $this->image || ! $disk->exists($this->image)) {
            return [(int) $this->width, (int) $this->height];
        }

        $size = @getimagesize($disk->path($this->image));

        return $size ? [(int) $size[0], (int) $size[1]] : [(int) $this->width, (int) $this->height];
    }

    /** What the editor's picker needs to show it. */
    public function toEditor(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'url' => Media::url($this->image),
            'width' => (int) $this->width,
            'height' => (int) $this->height,
        ];
    }
}
