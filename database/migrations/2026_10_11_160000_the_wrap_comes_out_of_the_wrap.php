<?php

use App\Models\Material;
use App\Models\Wrapping;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wrapping paper and ribbon come out of the wrap, not out of every box.
 *
 * Until now every material in the warehouse was spent «per box»: the order was
 * counted in boxes and each material's `per_box` was multiplied by that. That
 * is true of the box paper, the glue and the print — and false of the wrapping,
 * which only some customers buy. The owner said so plainly: the wrapping has to
 * go down when a customer orders a wrapping, any wrapping.
 *
 * So a material now says what it is spent on:
 *   box     every box          — Qutu kağızı, Yapışqan, Çap boyası
 *   wrap    every wrapped box  — Bükmə kağızı
 *   ribbon  only the boxes whose wrap uses that very ribbon
 *
 * The ribbon needs a link. The owner chooses it on the wrap, because that is
 * where he thinks about it — a wrap is a paper and a ribbon — but the choice is
 * then **frozen onto the order line**, in `order_items.ribbon_material_id`, the
 * way the wrap's name and price already are. Otherwise re-pointing a wrap at a
 * different roll, or deleting a wrap altogether, would quietly rewrite what
 * orders placed months ago took off the shelves.
 *
 * Three pieces of his own data are put right while we are here:
 *   · «Bükmə kağızı» becomes a wrap material, so the per-box figure stops
 *     carrying it and every unwrapped box stops eating a sheet of it;
 *   · «A4», which he added himself and has now asked to be rid of, is switched
 *     off rather than deleted — its sheets, its purchases and the cost of every
 *     order that used it stay where they are, and switching it back on is one
 *     toggle if he ever wants it;
 *   · a ribbon is created for every colour his wraps actually use, and each
 *     wrap is tied to its own, so the links are right before he opens the page.
 *
 * What does change for orders already in the books: an order that is still open
 * and gets edited is brought in line with the new rule, so the wrapping sheets
 * charged to its unwrapped boxes are handed back. Orders nobody touches again
 * keep the cost they were made at.
 *
 * Rerun-safe: production is MySQL, which keeps DDL that ran before a failure.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('materials', 'spent_on')) {
            Schema::table('materials', function (Blueprint $table) {
                $table->string('spent_on', 10)->default(Material::BOX)->after('per_box');
            });
        }

        if (! Schema::hasColumn('wrappings', 'ribbon_material_id')) {
            Schema::table('wrappings', function (Blueprint $table) {
                $table->unsignedBigInteger('ribbon_material_id')->nullable()->after('ribbon_color');
            });
        }

        // Frozen onto the line, like `wrapping_name` and `wrapping_price`: what
        // this order took is what it took, whatever the owner changes later.
        if (! Schema::hasColumn('order_items', 'ribbon_material_id')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->unsignedBigInteger('ribbon_material_id')->nullable()->after('wrapping_price');
            });
        }

        $this->wrapPaper();
        $this->switchOffA4();
        $this->ribbons();
        $this->freezeWhatIsAlreadyOrdered();
    }

    /**
     * The wrapping sheet stops being charged to every box.
     *
     * Matched by the name this shop's own earlier migration gave it. If the
     * owner has renamed it since, a name beginning «Bükmə» is taken instead,
     * because the alternative is a migration that reports success and changes
     * nothing — the one failure nobody would notice.
     */
    private function wrapPaper(): void
    {
        $changed = DB::table('materials')->where('name', 'Bükmə kağızı')
            ->update(['spent_on' => Material::WRAP, 'updated_at' => now()]);

        if ($changed === 0) {
            DB::table('materials')->where('name', 'like', 'Bükmə%')->where('spent_on', Material::BOX)
                ->update(['spent_on' => Material::WRAP, 'updated_at' => now()]);
        }
    }

    /**
     * «A4» off the warehouse page, by the owner's own request.
     *
     * He typed the name himself, so it is matched without caring about case or
     * the spaces around it; on MySQL the collation would forgive both anyway,
     * on SQLite it would not, and a migration that behaves differently in the
     * tests than in the shop is worse than no migration.
     */
    private function switchOffA4(): void
    {
        DB::table('materials')
            ->whereRaw('LOWER(TRIM(name)) = ?', ['a4'])
            ->update(['is_active' => false, 'updated_at' => now()]);
    }

    /**
     * One warehouse ribbon per colour the shop's wraps actually use, and every
     * wrap tied to its own.
     *
     * Nothing is invented: the colours are read off the wraps themselves, so a
     * fresh installation with no wraps gets no ribbons. Two wraps in two shades
     * of the same red are one roll on the shelf, so the rows are grouped by the
     * colour's name rather than by its hex. Twine is twine whatever shade it
     * was dyed — all the «kəndir» wraps share one.
     *
     * No price is claimed and nothing is said to be on the shelf: a new ribbon
     * sits at zero until the owner writes what a roll costs, and how many boxes
     * it does, with «Alış». A pack of a hundred boxes' worth is only the figure
     * that form opens with, and it is his to change.
     */
    private function ribbons(): void
    {
        $wraps = DB::table('wrappings')
            ->whereIn('ribbon', [Wrapping::SATIN, Wrapping::TWINE])
            ->whereNull('ribbon_material_id')
            ->get(['id', 'ribbon', 'ribbon_color']);

        if ($wraps->isEmpty()) {
            return;
        }

        $now = now();
        $sort = (int) DB::table('materials')->max('sort_order');

        foreach ($wraps->groupBy(fn ($w) => $this->ribbonName($w->ribbon, $w->ribbon_color)) as $name => $group) {
            $id = DB::table('materials')->where('name', $name)->value('id');

            if ($id) {
                // He may have added it himself after reading this was coming.
                // A row left on «hər qutuya» would charge red ribbon to every
                // box in the shop, so it is put right rather than linked to.
                DB::table('materials')->where('id', $id)->where('spent_on', '!=', Material::RIBBON)
                    ->update(['spent_on' => Material::RIBBON, 'updated_at' => $now]);
            } else {
                $id = DB::table('materials')->insertGetId([
                    'name' => $name,
                    // Counted in boxes' worth, like the glue and the ink: a roll
                    // is metres on a spool and nobody measures those by hand.
                    'unit' => 'qutuluq',
                    'pack_price' => 0,
                    'pack_size' => 100,
                    'per_box' => 1,
                    'spent_on' => Material::RIBBON,
                    'stock' => 0,
                    // No warning line until he has counted one: a roll lying in
                    // his drawer must not open the shop's day in red.
                    'low_stock' => null,
                    'is_active' => true,
                    'sort_order' => ++$sort,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('wrappings')->whereIn('id', $group->pluck('id'))
                ->update(['ribbon_material_id' => $id, 'updated_at' => $now]);
        }
    }

    /**
     * Orders already placed get the ribbon their wrap names today.
     *
     * From here on the line keeps its own answer. Only lines that are still
     * blank are filled, and only from a wrap that really has a ribbon on it.
     */
    private function freezeWhatIsAlreadyOrdered(): void
    {
        $wraps = DB::table('wrappings')
            ->whereNotNull('ribbon_material_id')
            ->whereIn('ribbon', [Wrapping::SATIN, Wrapping::TWINE])
            ->pluck('ribbon_material_id', 'id');

        foreach ($wraps as $wrapping => $material) {
            DB::table('order_items')
                ->where('wrapping_id', $wrapping)
                ->whereNull('ribbon_material_id')
                ->update(['ribbon_material_id' => $material]);
        }
    }

    /** «Qırmızı lent», «Ağ lent», «Kəndir» — the roll as he would name it. */
    private function ribbonName(string $ribbon, ?string $hex): string
    {
        if ($ribbon === Wrapping::TWINE) {
            return 'Kəndir';
        }

        return $this->colour($hex) . ' lent';
    }

    /** A hex colour in Azerbaijani, near enough for a label he can rename. */
    private function colour(?string $hex): string
    {
        $rgb = sscanf((string) $hex, '#%02x%02x%02x');

        if ($rgb === null || count(array_filter($rgb, 'is_int')) !== 3) {
            return 'Rəngsiz';
        }

        [$r, $g, $b] = array_map(fn ($v) => $v / 255, $rgb);
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        $d = $max - $min;
        $s = $d == 0.0 ? 0.0 : $d / (1 - abs(2 * $l - 1));

        // No colour left in it: white, black or something between.
        if ($s < 0.12 || $d < 0.04) {
            return $l > 0.85 ? 'Ağ' : ($l < 0.2 ? 'Qara' : 'Boz');
        }

        $h = match (true) {
            $max === $r => fmod((($g - $b) / $d) + 6, 6),
            $max === $g => (($b - $r) / $d) + 2,
            default => (($r - $g) / $d) + 4,
        } * 60;

        // Brown and beige are an orange read at the ends of the lightness
        // scale; left to the hue alone they would both come out «narıncı».
        if ($h >= 15 && $h < 50) {
            if ($l < 0.35) {
                return 'Qəhvəyi';
            }
            if ($l > 0.75) {
                return 'Bej';
            }
        }

        $name = match (true) {
            $h < 15, $h >= 345 => 'Qırmızı',
            $h < 50 => 'Narıncı',
            $h < 70 => 'Sarı',
            $h < 165 => 'Yaşıl',
            $h < 200 => 'Firuzəyi',
            $h < 255 => 'Mavi',
            $h < 290 => 'Bənövşəyi',
            default => 'Çəhrayı',
        };

        return $l > 0.8 ? 'Açıq ' . mb_strtolower($name) : $name;
    }

    /**
     * Going back is careful with what is not ours.
     *
     * The columns go, so every ribbon would read as a per-box material again —
     * red ribbon charged to every box in the shop. The rows this migration made
     * are untouched by anybody and can simply go; a ribbon the owner has since
     * bought or counted is kept, and switched off instead, so nothing of his is
     * thrown away and nothing is quietly charged to every box either.
     */
    public function down(): void
    {
        DB::table('materials')->whereRaw('LOWER(TRIM(name)) = ?', ['a4'])->update(['is_active' => true]);
        DB::table('materials')->where('name', 'Bükmə kağızı')->update(['spent_on' => Material::BOX]);

        $ribbons = DB::table('materials')->where('spent_on', Material::RIBBON)->pluck('id');
        $touched = DB::table('stock_movements')->whereIn('material_id', $ribbons)->pluck('material_id')->unique();

        DB::table('materials')->whereIn('id', $ribbons)->whereNotIn('id', $touched)->delete();
        DB::table('materials')->whereIn('id', $touched)->update(['is_active' => false]);

        foreach (['wrappings' => 'ribbon_material_id', 'order_items' => 'ribbon_material_id', 'materials' => 'spent_on'] as $table => $column) {
            if (Schema::hasColumn($table, $column)) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn($column));
            }
        }
    }
};
