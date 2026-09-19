<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Spending that is not stock: ads, courier, rent… */
class Expense extends Model
{
    /** Offered in the admin; any other category can be typed. */
    public const CATEGORIES = ['Reklam', 'Kuryer', 'Çap xidməti', 'İcarə', 'Kommunal', 'Qablaşdırma', 'Digər'];

    protected $fillable = ['spent_on', 'category', 'amount', 'note', 'user_id'];

    protected function casts(): array
    {
        return ['spent_on' => 'date', 'amount' => 'float'];
    }
}
