<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A message somebody wrote from the contact page.
 *
 * Sent to Telegram at once and kept here as well, because a message that
 * lives only in a chat is lost the first time the bot's token changes.
 */
class ContactMessage extends Model
{
    /**
     * The first six are what the writer fills in; the last three are the
     * owner's, written from the admin. Nothing here is filled from a request
     * array wholesale — the contact page passes only the keys it validated.
     */
    protected $fillable = [
        'name', 'phone', 'email', 'message', 'about', 'locale',
        'answered_at', 'answered_by', 'note',
    ];

    protected function casts(): array
    {
        return ['answered_at' => 'datetime'];
    }

    public function answerer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by');
    }

    public function scopeWaiting(Builder $query): Builder
    {
        return $query->whereNull('answered_at');
    }

    public function isAnswered(): bool
    {
        return $this->answered_at !== null;
    }

    /** The writer's own number on WhatsApp, for answering with one tap. */
    public function whatsapp(): ?string
    {
        $digits = preg_replace('~\D~', '', (string) $this->phone);
        if (strlen($digits) < 9) {
            return null;
        }
        if (! str_starts_with($digits, '994')) {
            $digits = '994' . ltrim($digits, '0');
        }

        return 'https://wa.me/' . $digits;
    }
}
