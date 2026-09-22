<?php

namespace App\Models;

use App\Support\Media;
use App\Support\YandexDisk;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A printed picture that comes alive: scanned by its QR code and seen
 * through the phone's camera, it plays its video over itself (MindAR).
 */
class LivePhoto extends Model
{
    protected $fillable = ['title', 'target_image', 'target_mind', 'video_url', 'video_path', 'order_item_id', 'is_active'];

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

        // A video on Yandex Disk stays there; one still waiting on the hosting goes.
        static::deleted(fn (LivePhoto $live) => Storage::disk('public')->delete(array_filter([$live->target_image, $live->target_mind, $live->video_path])));
    }

    /**
     * The live photo a customer ordered, made the moment the order is placed:
     * their picture, the tracking data their browser made from it and their
     * video, which is then handed to Yandex Disk.
     *
     * @param  array{video: string, image: ?string, mind: ?string}  $ar
     */
    public static function makeFor(OrderItem $item, array $ar): self
    {
        $disk = Storage::disk('public');
        // Without a picture of its own (an old browser), the box's first photo stands in.
        $image = $ar['image'] ?? null;
        $borrowed = $image === null ? (($item->customer_photos ?? [])[0] ?? null) : null;

        $live = static::create([
            'title' => 'Sifariş #' . $item->order_id . ' — ' . ($item->product_name ?? 'Canlı şəkil'),
            'target_image' => '',
            'video_path' => $ar['video'],
            'order_item_id' => $item->id,
            'is_active' => true,
        ]);

        $dir = 'live/' . $live->id . '/';
        $moves = [];
        foreach (['target_image' => $image, 'target_mind' => $ar['mind'] ?? null, 'video_path' => $ar['video']] as $column => $path) {
            if ($path && $disk->exists($path)) {
                $to = $dir . basename($path);
                $disk->move($path, $to);
                $moves[$column] = $to;
            }
        }
        if ($borrowed && $disk->exists($borrowed)) {
            $moves['target_image'] = $dir . 'photo-' . basename($borrowed);
            $disk->copy($borrowed, $moves['target_image']);
        }
        $live->forceFill($moves)->saveQuietly();

        return $live;
    }

    /**
     * Hands a video still on the hosting to the owner's Yandex Disk and
     * deletes it here. Leaves it be (to try again later) when that fails.
     */
    /** Why the last move to Yandex Disk did not happen, for the owner. */
    public ?string $pushError = null;

    public function pushVideo(): bool
    {
        $this->pushError = null;
        $disk = Storage::disk('public');
        if ($this->video_url || ! $this->video_path || ! YandexDisk::hasToken() || ! $disk->exists($this->video_path)) {
            return false;
        }

        $name = ($this->orderItem ? 'sifaris-' . $this->orderItem->order_id . '-' : '') . $this->code
            . '.' . (pathinfo($this->video_path, PATHINFO_EXTENSION) ?: 'mp4');
        try {
            $link = YandexDisk::upload($disk->path($this->video_path), $name);
        } catch (\RuntimeException $e) {
            // At error level: the hosting's log keeps nothing quieter.
            Log::error('Live photo video not moved to Yandex Disk', ['live_photo' => $this->id, 'error' => $e->getMessage()]);
            $this->pushError = $e->getMessage();

            return false;
        }

        $local = $this->video_path;
        $this->forceFill(['video_url' => $link, 'video_path' => null])->saveQuietly();
        $disk->delete($local);

        return true;
    }

    /** Where the video is now: 'yandex', 'hosting' (waiting to be moved) or null. */
    public function videoPlace(): ?string
    {
        return match (true) {
            filled($this->video_url) => 'yandex',
            filled($this->video_path) => 'hosting',
            default => null,
        };
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function isReady(): bool
    {
        return $this->is_active && filled($this->target_mind) && $this->videoPlace() !== null;
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

    /** The video goes through this site only as a redirect (to Yandex Disk, once it is there). */
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
