<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money taken out of the till: a share paid out, a private purchase, cash
 * put aside. It lowers what is left in hand, never the profit that was
 * earned - the profit happened whether or not the money is still there.
 */
class Withdrawal extends Model
{
    /** Offered in the admin; anything else can be typed. */
    public const PURPOSES = ['Pay ödənişi', 'Şəxsi', 'Avans', 'Vergi', 'Nağd saxlanc', 'Digər'];

    protected $fillable = ['taken_on', 'amount', 'purpose', 'note', 'user_id'];

    protected function casts(): array
    {
        return ['taken_on' => 'date', 'amount' => 'float'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
