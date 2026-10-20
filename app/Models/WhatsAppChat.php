<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One person the shop is talking to on WhatsApp.
 *
 * Named by his own number, because that is the only name WhatsApp gives a
 * conversation; whatever he calls himself in the application is kept beside
 * it for the panel to show.
 */
class WhatsAppChat extends Model
{
    protected $table = 'whatsapp_chats';

    protected $fillable = ['wa_id', 'name', 'phone', 'last_incoming_at', 'last_message_at', 'unread', 'order_id', 'note'];

    /**
     * How long WhatsApp lets a business answer after the customer writes.
     *
     * Outside it nothing may be sent but a message Meta has approved in
     * advance, so the panel has to say plainly which side of the line a
     * conversation is on — otherwise the answer is simply swallowed and
     * the customer waits for a reply nobody can send.
     */
    public const WINDOW_HOURS = 24;

    protected function casts(): array
    {
        return [
            'last_incoming_at' => 'datetime',
            'last_message_at' => 'datetime',
            'unread' => 'integer',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'wa_id', 'wa_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** May the shop still write freely? */
    public function windowOpen(): bool
    {
        return $this->last_incoming_at !== null
            && $this->last_incoming_at->gt(now()->subHours(self::WINDOW_HOURS));
    }

    /** How much of the day is left to answer in, in whole minutes. */
    public function windowLeft(): int
    {
        if (! $this->windowOpen()) {
            return 0;
        }

        return (int) max(0, now()->diffInMinutes($this->last_incoming_at->copy()->addHours(self::WINDOW_HOURS), false));
    }

    /** The number written the way people write it here. */
    public function written(): string
    {
        return $this->phone ?: '+' . $this->wa_id;
    }

    public function scopeNewestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('last_message_at')->orderByDesc('id');
    }
}
