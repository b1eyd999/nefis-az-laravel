<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Lays one box's design onto another.
 *
 * The owner draws a family of boxes that differ in one thing — the shape of
 * the window, or its colours — and drawing each from nothing is an evening's
 * work for a difference of two settings. This copies the finished one and
 * changes only what the new one is named after.
 *
 * The pictures come along as copies in the new box's own folder: a design may
 * only point at its own, or deleting one box would empty the other. That is
 * the same rule the editor's "use another box's design" follows.
 */
class DesignCopier
{
    /**
     * @param  array<string, mixed>  $mapWindow  what to change about the map
     *                                           window: shape, map_style
     * @return array<string, int>  what was copied, for the log
     */
    public static function copy(Product $from, Product $to, array $mapWindow = []): array
    {
        $from->load(['layers', 'shapes', 'photoSlots', 'textSlots']);

        $disk = Storage::disk('public');
        $made = ['layers' => 0, 'shapes' => 0, 'photos' => 0, 'texts' => 0];

        /* Whatever was there goes first: this is only ever called on a box
           with nothing in it, but a half-finished copy must not survive. */
        $to->layers()->delete();
        $to->shapes()->delete();
        $to->photoSlots()->delete();
        $to->textSlots()->delete();

        foreach ($from->layers as $order => $layer) {
            $image = self::ownCopy($disk, (string) $layer->image, $to);
            if ($image === null) {
                continue;               // the box it came from has been cleared out
            }

            $to->layers()->create(
                ['image' => $image, 'sort_order' => $order] + $layer->attributesToArray()
            );
            $made['layers']++;
        }

        foreach ($from->shapes as $order => $shape) {
            $to->shapes()->create(['sort_order' => $order] + $shape->attributesToArray());
            $made['shapes']++;
        }

        foreach ($from->photoSlots as $order => $slot) {
            $fields = $slot->attributesToArray();
            if ($slot->isMap()) {
                /* The one thing the new box is named after. */
                $fields = $mapWindow + $fields;
            }
            $to->photoSlots()->create(['sort_order' => $order] + $fields);
            $made['photos']++;
        }

        foreach ($from->textSlots as $order => $slot) {
            $to->textSlots()->create(['sort_order' => $order] + $slot->attributesToArray());
            $made['texts']++;
        }

        /* The flat picture some designs are built on, and the colour of the
           box underneath. The cover in the catalogue is the box's own and is
           never touched. */
        $own = [];
        if (filled($from->template_image)) {
            $copied = self::ownCopy($disk, (string) $from->template_image, $to);
            if ($copied !== null) {
                $own['template_image'] = $copied;
            }
        }
        foreach (['box_color', 'template_width', 'template_height'] as $field) {
            if (blank($to->{$field}) && filled($from->{$field})) {
                $own[$field] = $from->{$field};
            }
        }
        if ($own) {
            $to->forceFill($own)->save();
        }

        return $made;
    }

    /** The same picture, in the new box's own folder. */
    private static function ownCopy(\Illuminate\Contracts\Filesystem\Filesystem $disk, string $source, Product $to): ?string
    {
        if ($source === '' || ! Str::startsWith($source, 'boxes/') || Str::contains($source, '..') || ! $disk->exists($source)) {
            return null;
        }

        if (Str::startsWith($source, $to->assetDirectory() . '/')) {
            return $source;
        }

        $target = $to->assetDirectory() . '/copy-' . Str::lower(Str::random(10)) . '.' . Str::afterLast($source, '.');
        $disk->copy($source, $target);

        return $target;
    }
}
