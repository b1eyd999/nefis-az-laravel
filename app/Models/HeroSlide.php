<?php

namespace App\Models;

use App\Support\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** One slide of the home page's opening banner. */
class HeroSlide extends Model
{
    use \App\Models\Concerns\Translatable;

    protected $fillable = [
        'i18n',
        'eyebrow', 'title', 'text', 'button1_label', 'button1_url', 'button2_label', 'button2_url',
        'badges', 'image', 'image_fit', 'ribbon', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'i18n' => 'array',
            'badges' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::deleted(function (HeroSlide $slide) {
            if ($slide->image) {
                Storage::disk('public')->delete($slide->image);
            }
        });
    }

    public function scopeShown(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    public function imageUrl(): ?string
    {
        return Media::url($this->image);
    }

    /**
     * Where a button goes: a page of the site ("/dizaynlar"), a place on the
     * home page ("#collections") or another site. Anything else is dropped.
     */
    public static function href(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }
        if (str_starts_with($url, '#') || str_starts_with($url, '/')) {
            return $url;
        }

        return preg_match('#^https?://#i', $url) ? $url : null;
    }

}
