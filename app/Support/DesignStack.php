<?php

namespace App\Support;

use App\Models\Product;

/**
 * Where everything in a design sits, in one order.
 *
 * A box used to be drawn in six fixed bands around the customer's photograph,
 * and `placement` (below | above) was the only control the owner had. He could
 * not put a drawing between two faces. Now every row — artwork, shape, photo
 * window and caption — carries a `z`, and the box is drawn from the bottom of
 * that one stack to the top.
 *
 * `placement` is still a real, written column, derived from `z`: a layer is
 * "below" when it sits under the lowest window. Everything that reads it —
 * the shop's own fallback drawing, and a great many tests — keeps working.
 */
class DesignStack
{
    /**
     * The tie-break between two rows that want the same place. It has to read
     * the same here and in the editor's JavaScript; nothing can test that the
     * two agree, so they are both worth re-reading after any change here.
     */
    public const KINDS = ['layer' => 0, 'shape' => 1, 'photo' => 2, 'text' => 3];

    /**
     * The old six bands, as a number.
     *
     * Never change this. It is what a design saved before the stack existed
     * is ranked by, and what the editor falls back to for a row that has no
     * place of its own yet. New behaviour belongs in a new method.
     */
    public static function bandKey(string $kind, ?string $placement, int $sortOrder): int
    {
        $rank = match (true) {
            $kind === 'layer' && $placement === 'below' => 0,
            $kind === 'shape' && $placement === 'below' => 1,
            $kind === 'photo' => 2,
            $kind === 'shape' => 3,
            $kind === 'layer' => 4,
            default => 5,
        };

        return $rank * 1000 + $sortOrder;
    }

    /**
     * The product's stack, bottom first, as [['kind' => …, 'i' => …], …]
     * where `i` is the row's position inside its own relation.
     */
    public static function of(Product $product): array
    {
        $product->loadMissing(['layers', 'shapes', 'photoSlots', 'textSlots']);

        $rows = [];
        $take = function (string $kind, $items) use (&$rows) {
            foreach ($items->values() as $i => $row) {
                $rows[] = [
                    'kind' => $kind,
                    'i' => $i,
                    'z' => $row->z,
                    'band' => self::bandKey($kind, $row->placement ?? null, (int) $row->sort_order),
                    'id' => (int) $row->id,
                ];
            }
        };
        $take('layer', $product->layers);
        $take('shape', $product->shapes);
        $take('photo', $product->photoSlots);
        $take('text', $product->textSlots);

        usort($rows, function (array $a, array $b) {
            return [$a['z'] ?? $a['band'], self::KINDS[$a['kind']], $a['i'], $a['id']]
               <=> [$b['z'] ?? $b['band'], self::KINDS[$b['kind']], $b['i'], $b['id']];
        });

        return array_map(fn (array $r) => ['kind' => $r['kind'], 'i' => $r['i']], $rows);
    }

    /**
     * Where each row of a save goes, numbered 0…n-1 across the whole design.
     *
     * `$surviving` are shape rows the payload did not mention and so did not
     * delete — an editor tab older than shapes sends no `shapes` key at all,
     * and those rows live on. They have to be numbered with the rest or the
     * design comes back with two rows claiming one place.
     *
     * If a single row arrives without a place, the whole design is ranked by
     * the old bands instead: half a free order and half a grouping is not an
     * order, and a tab opened before this existed must leave the box looking
     * exactly as it showed it.
     */
    public static function renumber(array $payload, array $surviving = []): array
    {
        $rows = [];
        $free = true;

        foreach (['layer' => 'layers', 'shape' => 'shapes', 'photo' => 'photos', 'text' => 'texts'] as $kind => $key) {
            foreach (array_values($payload[$key] ?? []) as $i => $item) {
                $z = $item['z'] ?? null;
                if (! is_numeric($z)) {
                    $free = false;
                }
                $rows[] = [
                    'kind' => $kind, 'i' => $i, 'keep' => null,
                    'z' => is_numeric($z) ? (float) $z : null,
                    'band' => self::bandKey($kind, $item['placement'] ?? null, $i),
                ];
            }
        }

        foreach (array_values($surviving) as $n => $row) {
            $z = $row->z ?? null;
            if ($z === null) {
                $free = false;
            }
            /* A survivor has no position in the payload, so it sorts after
               anything the payload sent that wants the same place — and after
               the survivor before it. */
            $rows[] = [
                'kind' => 'shape', 'i' => 1000000 + $n, 'keep' => (int) $row->id,
                'z' => $z === null ? null : (float) $z,
                'band' => self::bandKey('shape', $row->placement ?? null, (int) $row->sort_order),
            ];
        }

        usort($rows, function (array $a, array $b) use ($free) {
            $ka = $free ? $a['z'] : $a['band'];
            $kb = $free ? $b['z'] : $b['band'];

            return [$ka, self::KINDS[$a['kind']], $a['i']] <=> [$kb, self::KINDS[$b['kind']], $b['i']];
        });

        $out = ['layers' => [], 'shapes' => [], 'photos' => [], 'texts' => [], 'keep' => []];
        $into = ['layer' => 'layers', 'shape' => 'shapes', 'photo' => 'photos', 'text' => 'texts'];
        foreach ($rows as $z => $row) {
            if ($row['keep'] !== null) {
                $out['keep'][$row['keep']] = $z;
                continue;
            }
            $out[$into[$row['kind']]][$row['i']] = $z;
        }

        return $out;
    }

    /** The lowest window in the stack — the line "below the photo" means. */
    public static function floorOfPhotos(array $photoZ): ?int
    {
        return $photoZ === [] ? null : (int) min($photoZ);
    }

    /** Write a dense stack onto a product that has rows without one. */
    public static function number(Product $product): void
    {
        $product->load(['layers', 'shapes', 'photoSlots', 'textSlots']);
        $lists = [
            'layer' => $product->layers->values(),
            'shape' => $product->shapes->values(),
            'photo' => $product->photoSlots->values(),
            'text' => $product->textSlots->values(),
        ];

        foreach (self::of($product) as $z => $at) {
            $row = $lists[$at['kind']][$at['i']] ?? null;
            if ($row && ($row->z === null || (int) $row->z !== $z)) {
                $row->forceFill(['z' => $z])->save();
            }
        }
    }
}
