<?php

namespace App\Models;

use App\Support\Media;
use Illuminate\Database\Eloquent\Model;

/**
 * A typeface the box editor offers for captions. Either a file the owner
 * uploaded, or a Google Fonts family the site layout already loads.
 */
class Font extends Model
{
    protected $fillable = ['name', 'family', 'file', 'weight'];

    public function toEditor(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'family' => $this->family,
            'file' => $this->file,
            'url' => $this->file ? Media::url($this->file) : null,
            'weight' => (int) $this->weight,
        ];
    }
}
