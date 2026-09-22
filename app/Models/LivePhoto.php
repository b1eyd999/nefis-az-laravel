<?php

namespace App\Models;

use App\Support\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A printed picture that comes alive: scanned by its QR code and seen
 * through the phone's camera, it plays its video over itself (MindAR).
 */
class LivePhoto extends Model
{
    protected $fillable = ['title', 'target_image', 'target_mind', 'video_url', 'order_item_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (LivePhoto $live) {
            do {
                $code = Str::lower(Str::random(8));
            } while (static::where('code', $code)->exists());
            $live->code ??= $code;
        });

        // A new picture needs its tracking data made again.
        static::updating(function (LivePhoto $live) {
            if ($live->isDirty('target_image') && ! $live->isDirty('target_mind')) {
                $old = $live->getOriginal('target_mind');
                $live->target_mind = null;
                if ($old) {
                    Storage::disk('public')->delete($old);
                }
            }
        });

        // The video stays where it is, on Yandex Disk.
        static::deleted(fn (LivePhoto $live) => Storage::disk('public')->delete(array_filter([$live->target_image, $live->target_mind])));
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function isReady(): bool
    {
        return $this->is_active && filled($this->target_mind);
    }

    /** Where the QR code leads. */
    public function url(): string
    {
        return route('live.show', $this->code);
    }

    public function imageUrl(): ?string
    {
        return Media::url($this->target_image);
    }

    /** The video goes through this site only as a redirect to Yandex Disk. */
    public function videoUrl(): string
    {
        return route('live.video', $this->code);
    }

    public function mindUrl(): ?string
    {
        return Media::url($this->target_mind);
    }

    /** The picture's height over its width, so the video covers it exactly. */
    public function aspect(): float
    {
        $path = Storage::disk('public')->path((string) $this->target_image);
        $size = is_file($path) ? @getimagesize($path) : false;

        return $size && $size[0] > 0 ? round($size[1] / $size[0], 4) : 1.0;
    }
}
