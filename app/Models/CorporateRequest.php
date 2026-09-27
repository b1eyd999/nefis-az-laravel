<?php

namespace App\Models;

use App\Support\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * A company that wants the small chocolate with its own logo on it.
 *
 * It is a request, not an order: what it costs depends on the number and on
 * what the logo needs, so the owner answers with a figure and the order — if
 * there is one — is written afterwards.
 */
class CorporateRequest extends Model
{
    /** Where the conversation stands. */
    public const STATUSES = [
        'new' => 'Yeni',
        'talking' => 'Danışılır',
        'sample' => 'Nümunə göndərilib',
        'won' => 'Sifariş verildi',
        'lost' => 'İmtina etdi',
    ];

    protected $fillable = [
        'company', 'person', 'phone', 'email', 'quantity',
        'logo', 'box_color', 'slogan', 'qr_target', 'note', 'status',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    protected static function booted(): void
    {
        // The logo was uploaded for this request alone; nothing else points
        // at it, so it goes when the request does.
        static::deleted(function (CorporateRequest $request) {
            if ($request->logo) {
                Storage::disk('public')->delete($request->logo);
            }
        });
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function logoUrl(): ?string
    {
        return $this->logo ? Media::url($this->logo) : null;
    }

    /** The shop writing back on WhatsApp, with the number as they left it. */
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
