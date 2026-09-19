<?php

namespace App\Models;

use App\Support\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * A picture in the scene editor's library: a background, or an object laid
 * over it — usually a rendered empty box the design is corner-pinned onto.
 * Uploaded once, used by any number of scenes.
 */
class SceneAsset extends Model
{
    public const BACKGROUND = 'background';

    public const OBJECT = 'object';

    public const DIRECTORY = 'scenes/library';

    protected $fillable = ['name', 'kind', 'image', 'width', 'height'];

    protected static function booted(): void
    {
        static::deleted(fn (SceneAsset $asset) => Storage::disk('public')->delete($asset->image));
    }

    /** Names of the scenes that still show this picture. */
    public function usedIn(): array
    {
        return Scene::all()
            ->filter(fn (Scene $scene) => in_array($this->image, $scene->imagePaths(), true))
            ->pluck('name')
            ->values()
            ->all();
    }

    public function toEditor(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'kind' => $this->kind,
            'image' => $this->image,
            'url' => Media::url($this->image),
            'width' => (int) $this->width,
            'height' => (int) $this->height,
        ];
    }
}
