<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One thing to do, or one thing to remember.
 *
 * A task moves todo → doing → done and may carry a day it is due; a note has
 * neither and simply stays written down.
 */
class Task extends Model
{
    public const TASK = 'task';

    public const NOTE = 'note';

    public const STATUSES = [
        'todo' => 'Ediləcək',
        'doing' => 'İşdədir',
        'done' => 'Bitdi',
    ];

    /** Where the pictures of a task are kept on the public disk. */
    public const DIRECTORY = 'tasks';

    protected $fillable = ['kind', 'title', 'body', 'photos', 'status', 'due_at', 'user_id', 'created_by', 'done_at', 'sort_order'];

    protected $casts = ['photos' => 'array', 'due_at' => 'datetime', 'done_at' => 'datetime'];

    protected static function booted(): void
    {
        // The day it was finished is not typed in by anyone: it is the moment
        // the switch was moved.
        // A task taken off the board takes its pictures with it.
        static::deleted(function (Task $task) {
            foreach ($task->photos ?? [] as $path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($path);
            }
        });

        static::saving(function (Task $task) {
            if ($task->isDirty('status')) {
                $task->done_at = $task->status === 'done' ? ($task->done_at ?: now()) : null;
            }
        });
    }

    public function scopeTasks(Builder $q): Builder
    {
        return $q->where('kind', self::TASK);
    }

    public function scopeNotes(Builder $q): Builder
    {
        return $q->where('kind', self::NOTE);
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->where('status', '!=', 'done');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return array<int, array{path: string, url: string}> */
    public function pictures(): array
    {
        return collect($this->photos ?? [])
            ->map(fn (string $path) => ['path' => $path, 'url' => \App\Support\Media::url($path)])
            ->all();
    }

    public function isDone(): bool
    {
        return $this->status === 'done';
    }

    public function isOverdue(): bool
    {
        return $this->due_at !== null && ! $this->isDone() && $this->due_at->isPast();
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** The next state the one button moves it to. */
    public function nextStatus(): string
    {
        return match ($this->status) {
            'todo' => 'doing',
            'doing' => 'done',
            default => 'todo',
        };
    }

    public function nextLabel(): string
    {
        return match ($this->status) {
            'todo' => 'Başla',
            'doing' => 'Bitdi',
            default => 'Yenidən aç',
        };
    }

    /** "Bu gün 18:00", "Sabah", "2 gün gecikib" — the way it is said out loud. */
    public function dueLabel(): ?string
    {
        if (! $this->due_at) {
            return null;
        }

        $day = $this->due_at->copy()->startOfDay();
        $today = Carbon::now()->startOfDay();
        // Carbon hands back a float; the day count is a whole number here.
        $days = (int) $today->diffInDays($day, false);
        $hour = $this->due_at->format('H:i') === '00:00' ? '' : ' ' . $this->due_at->format('H:i');

        if ($this->isOverdue()) {
            $late = (int) abs($days);

            return $late === 0 ? 'bu gün gecikib' : $late . ' gün gecikib';
        }

        return match (true) {
            $days === 0 => 'bu gün' . $hour,
            $days === 1 => 'sabah' . $hour,
            $days <= 7 => $this->due_at->format('d.m') . $hour,
            default => $this->due_at->format('d.m.Y'),
        };
    }
}
