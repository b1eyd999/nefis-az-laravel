<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a WhatsApp conversation, whichever way it went.
 *
 * `wam_id` is WhatsApp's own name for the message and is unique on purpose:
 * Meta delivers the same notice again whenever our answer is slow, and
 * without that the customer's question would appear twice in the panel.
 */
class WhatsAppMessage extends Model
{
    protected $table = 'whatsapp_messages';

    public const IN = 'in';

    public const OUT = 'out';

    protected $fillable = [
        'wa_id', 'direction', 'wam_id', 'type', 'body',
        'media_path', 'media_mime', 'media_name',
        'status', 'error', 'user_id', 'sent_at',
    ];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(WhatsAppChat::class, 'wa_id', 'wa_id');
    }

    /** Who in the shop wrote it; nobody, for what the customer wrote. */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function mine(): bool
    {
        return $this->direction === self::OUT;
    }

    public function hasMedia(): bool
    {
        return filled($this->media_path);
    }

    /** What it says, or what kind of thing it is when it says nothing. */
    public function summary(): string
    {
        if (filled($this->body)) {
            return $this->body;
        }

        return match ($this->type) {
            'image' => '📷 Şəkil',
            'video' => '🎬 Video',
            'audio' => '🎤 Səs',
            'document' => '📎 Sənəd',
            'sticker' => '🙂 Stiker',
            'location' => '📍 Yer',
            default => '—',
        };
    }
}
