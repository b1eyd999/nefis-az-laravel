<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One line of the chat on the site: either what a visitor wrote from the
 * corner of the page, or what the shop answered from Telegram.
 */
class ChatMessage extends Model
{
    public const VISITOR = 'visitor';

    public const SHOP = 'shop';

    protected $fillable = ['thread', 'side', 'body', 'image', 'name', 'phone', 'page', 'tg_message_id'];

    public function fromShop(): bool
    {
        return $this->side === self::SHOP;
    }

    /** The line as the little window shows it. */
    public function toWindow(): array
    {
        return [
            'id' => $this->id,
            'side' => $this->side,
            'body' => $this->body,
            'image' => $this->image ? asset('storage/' . $this->image) : null,
            'at' => $this->created_at?->format('H:i'),
        ];
    }
}
