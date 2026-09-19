<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One change to a material's stock, with what it cost. */
class StockMovement extends Model
{
    public const PURCHASE = 'purchase';

    public const USAGE = 'usage';

    public const RETURN = 'return';

    public const ADJUST = 'adjust';

    public const TYPES = [
        self::PURCHASE => 'Alış',
        self::USAGE => 'Sifarişə sərf',
        self::RETURN => 'Ləğvdən qayıdış',
        self::ADJUST => 'Sayım düzəlişi',
    ];

    protected $fillable = ['material_id', 'order_id', 'type', 'quantity', 'unit_cost', 'amount', 'note', 'user_id'];

    protected function casts(): array
    {
        return ['quantity' => 'float', 'unit_cost' => 'float', 'amount' => 'float'];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
