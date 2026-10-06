<?php

namespace App\Http\Controllers;

use App\Models\DesignLayer;
use App\Models\DesignShape;
use App\Models\DesignTemplate;
use App\Models\Font;
use App\Models\LibraryAsset;
use App\Models\PhotoSlot;
use App\Models\Product;
use App\Models\TextSlot;
use App\Support\DesignStack;
use App\Support\ImageStore;
use App\Support\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The admin's Canva-style box editor.
 *
 * The owner uploads a box's artwork as separate transparent layers, places
 * them by hand over a reference visual, and adds the photo areas and
 * captions the customer fills in. Nothing is placed automatically.
 */
class BoxEditorController extends Controller
{
    public function edit(Request $request, Product $product): View
    {
        $this->authorizeAdmin($request);

        $design = $this->designOf($product);

        $fonts = Font::orderBy('name')->get()->map->toEditor()->values();
        // The shelf of frames, patterns and stickers, ready for the picker.
        $library = LibraryAsset::offered()->get()->map->toEditor()->values();

        return view('admin.box-editor', compact('product', 'design', 'fonts', 'library'));
    }

    /**
     * A design as the editor holds it: the artwork, the windows, the captions.
     *
     * The page is built from this, and so is a template — which is the same
     * design, waiting to be laid on another box.
     */
    private function designOf(Product $product): array
    {
        $product->load(['layers', 'shapes', 'photoSlots', 'textSlots']);
        $canvas = config('boxes.canvas');

        return [
            'canvas' => $canvas,
            'visual' => $product->preview_image ? Media::url($product->preview_image) : null,
            'box_color' => $product->box_color,
            // What the box turns without a colour of its own: read off the visual.
            'box_color_auto' => $product->box_color ? $product->box_color_auto : $product->effectiveBoxColor(),
            'layers' => $product->layers->map(fn (DesignLayer $l) => [
                'name' => $l->name, 'image' => $l->image, 'url' => Media::url($l->image),
                'x' => $l->x, 'y' => $l->y, 'width' => $l->width, 'height' => $l->height,
                'rotation' => $l->rotation, 'opacity' => $l->opacity,
                'placement' => $l->placement, 'locked' => $l->locked, 'z' => $l->z,
            ])->values(),
            'shapes' => $product->shapes->map(fn ($s) => [
                'kind' => $s->kind, 'x' => $s->x, 'y' => $s->y, 'width' => $s->width, 'height' => $s->height,
                'rotation' => $s->rotation, 'fill' => $s->fill, 'stroke_color' => $s->stroke_color,
                'stroke_width' => (float) $s->stroke_width, 'radius' => (int) $s->radius,
                'opacity' => (int) $s->opacity, 'placement' => $s->placement, 'locked' => (bool) $s->locked,
                'z' => $s->z,
            ])->values(),
            'photos' => $product->photoSlots->map(fn ($s) => [
                'label' => $s->label, 'i18n' => $s->i18n,
                'fill' => $s->fill ?: PhotoSlot::PHOTO, 'sky_style' => $s->sky_style ?: 'night',
                'sky_ring_kind' => $s->sky_ring_kind ?: 'degrees', 'sky_choices' => $s->sky_choices,
                'sky_lines' => (bool) $s->sky_lines, 'sky_labels' => (bool) $s->sky_labels,
                'sky_milky' => (bool) $s->sky_milky, 'sky_heart' => (bool) $s->sky_heart,
                'map_style' => $s->map_style ?: 'ink', 'map_marker' => $s->map_marker ?: 'heart',
                'map_zoom' => (int) ($s->map_zoom ?: 15), 'map_choices' => $s->map_choices,
                'map_pin' => (bool) $s->map_pin,
                'x' => $s->x, 'y' => $s->y,
                'width' => $s->width, 'height' => $s->height,
                'rotation' => $s->rotation, 'shape' => $s->shape, 'cutout' => (bool) $s->cutout,
                'locked' => (bool) $s->locked, 'z' => $s->z,
            ])->values(),
            'texts' => $product->textSlots->map(fn ($s) => [
                'label' => $s->label, 'i18n' => $s->i18n, 'kind' => $s->kind ?: TextSlot::KIND_TEXT, 'fixed' => (bool) $s->fixed,
                'default_value' => $s->default_value, 'placeholder' => $s->placeholder,
                'x' => $s->x, 'y' => $s->y, 'max_width' => $s->max_width, 'font_size' => $s->font_size,
                'color' => $s->color, 'align' => $s->align, 'rotation' => (int) $s->rotation,
                'font_family' => $s->font_family, 'font_file' => $s->font_file,
                'font_weight' => $s->font_weight ?: 400,
                'tracking' => (int) $s->tracking, 'line_height' => (int) ($s->line_height ?: 120),
                'text_case' => $s->text_case ?: 'none',
                'scale_x' => (int) ($s->scale_x ?: 100), 'scale_y' => (int) ($s->scale_y ?: 100),
                'baseline_shift' => (int) $s->baseline_shift,
                'stroke_color' => $s->stroke_color, 'stroke_width' => (float) $s->stroke_width,
                'shadow_color' => $s->shadow_color, 'shadow_blur' => (int) $s->shadow_blur,
                'shadow_x' => (int) $s->shadow_x, 'shadow_y' => (int) $s->shadow_y,
                'max_lines' => max(1, (int) $s->max_lines), 'max_length' => (int) $s->max_length,
                'link_key' => $s->link_key, 'auto' => $s->auto ?: 'none', 'locked' => (bool) $s->locked,
                'z' => $s->z,
            ])->values(),
        ];
    }

    /**
     * The shelf the editor's "Şablon" button opens: designs kept aside, and
     * every other box whose design can simply be borrowed whole.
     */
    public function templates(Request $request, Product $product): JsonResponse
    {
        $this->authorizeAdmin($request);

        $boxes = Product::query()
            ->where('id', '!=', $product->id)
            ->where(fn ($q) => $q->has('layers')->orHas('shapes')->orHas('photoSlots')->orHas('textSlots'))
            ->orderBy('name')
            ->get()
            ->map(fn (Product $p) => [
                'slug' => $p->slug,
                'name' => $p->name,
                'url' => $p->catalogImage() ? Media::url($p->catalogImage()) : null,
            ])->values();

        return response()->json([
            'templates' => DesignTemplate::with('product')->orderByDesc('id')->get()->map->toEditor()->values(),
            'boxes' => $boxes,
        ]);
    }

    /** Puts this box's design on the shelf, under a name of its own. */
    public function keepTemplate(Request $request, Product $product): JsonResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);

        $design = $this->designOf($product);
        abort_if(
            ! $design['layers']->count() && ! $design['shapes']->count()
                && ! $design['photos']->count() && ! $design['texts']->count(),
            422,
            'Bu qutuda hələ dizayn yoxdur.'
        );

        $template = DesignTemplate::create([
            'name' => $data['name'],
            'product_id' => $product->id,
            'preview' => $product->catalogImage(),
            'payload' => self::templatePayload($design),
        ]);

        return response()->json($template->toEditor());
    }

    /**
     * Lays a kept design, or another box's design, on this one.
     *
     * The pictures come along as copies in this box's own folder: a design
     * may only point at its own, and two boxes sharing one file would mean
     * deleting one empties the other.
     */
    public function useTemplate(Request $request, Product $product): JsonResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'template' => ['nullable', 'integer', 'exists:design_templates,id'],
            'from' => ['nullable', 'string', 'exists:products,slug'],
        ]);

        if (! empty($data['template'])) {
            $design = (array) DesignTemplate::findOrFail($data['template'])->payload;
        } elseif (! empty($data['from'])) {
            $design = self::templatePayload($this->designOf(Product::where('slug', $data['from'])->firstOrFail()));
        } else {
            abort(422, 'Şablon seçilməyib.');
        }

        $disk = Storage::disk('public');
        $design['layers'] = collect($design['layers'] ?? [])->map(function (array $layer) use ($product, $disk) {
            $source = (string) ($layer['image'] ?? '');
            if (! Str::startsWith($source, 'boxes/') || Str::contains($source, '..') || ! $disk->exists($source)) {
                return null;                // the box it came from has been cleared out
            }

            $target = Str::startsWith($source, $product->assetDirectory() . '/')
                ? $source
                : $product->assetDirectory() . '/copy-' . Str::lower(Str::random(10)) . '.' . Str::afterLast($source, '.');

            if ($target !== $source) {
                $disk->copy($source, $target);
            }

            return ['image' => $target, 'url' => Media::url($target)] + $layer;
        })->filter()->values()->all();

        return response()->json($design);
    }

    public function forgetTemplate(Request $request, Product $product, DesignTemplate $template): JsonResponse
    {
        $this->authorizeAdmin($request);
        $template->delete();

        return response()->json(['ok' => true]);
    }

    /** A design without the things that belong to one box only. */
    private static function templatePayload(array $design): array
    {
        return [
            'box_color' => $design['box_color'] ?? null,
            'layers' => collect($design['layers'])->values()->all(),
            'shapes' => collect($design['shapes'])->values()->all(),
            'photos' => collect($design['photos'])->values()->all(),
            'texts' => collect($design['texts'])->values()->all(),
        ];
    }

    public function save(Request $request, Product $product): JsonResponse
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'layers' => ['present', 'array'],
            'layers.*.name' => ['nullable', 'string', 'max:120'],
            'layers.*.image' => ['required', 'string', 'max:255'],
            'layers.*.x' => ['required', 'numeric'],
            'layers.*.y' => ['required', 'numeric'],
            'layers.*.width' => ['required', 'numeric', 'min:1'],
            'layers.*.height' => ['required', 'numeric', 'min:1'],
            'layers.*.rotation' => ['required', 'numeric', 'between:-360,360'],
            'layers.*.opacity' => ['required', 'integer', 'between:0,100'],
            // Derived from the stack now, but still accepted: an editor tab
            // older than this change sends it, and sends no place at all.
            'layers.*.placement' => ['nullable', 'in:below,above'],
            'layers.*.z' => ['nullable', 'numeric', 'min:0', 'max:65535'],
            'layers.*.locked' => ['boolean'],

            'shapes' => ['nullable', 'array'],
            'shapes.*.kind' => ['required', Rule::in(DesignShape::KINDS)],
            'shapes.*.x' => ['required', 'numeric'],
            'shapes.*.y' => ['required', 'numeric'],
            'shapes.*.width' => ['required', 'numeric', 'min:1'],
            'shapes.*.height' => ['required', 'numeric', 'min:1'],
            'shapes.*.rotation' => ['required', 'numeric', 'between:-360,360'],
            'shapes.*.fill' => ['nullable', 'string', 'max:9'],
            'shapes.*.stroke_color' => ['nullable', 'string', 'max:9'],
            'shapes.*.stroke_width' => ['nullable', 'numeric', 'between:0,200'],
            'shapes.*.radius' => ['nullable', 'integer', 'between:0,2000'],
            'shapes.*.opacity' => ['nullable', 'integer', 'between:0,100'],
            'shapes.*.placement' => ['nullable', 'in:below,above'],
            'shapes.*.z' => ['nullable', 'numeric', 'min:0', 'max:65535'],
            'shapes.*.locked' => ['nullable', 'boolean'],

            'photos' => ['present', 'array'],
            'photos.*.label' => ['nullable', 'string', 'max:60'],
            'photos.*.i18n' => ['nullable', 'array'],
            'photos.*.i18n.*.label' => ['nullable', 'string', 'max:60'],
            'photos.*.x' => ['required', 'numeric'],
            'photos.*.y' => ['required', 'numeric'],
            'photos.*.width' => ['required', 'numeric', 'min:1'],
            'photos.*.height' => ['required', 'numeric', 'min:1'],
            'photos.*.rotation' => ['required', 'numeric', 'between:-360,360'],
            'photos.*.shape' => ['required', 'in:rectangle,ellipse,heart,home,full'],
            'photos.*.fill' => ['nullable', 'in:photo,sky,map'],
            'photos.*.sky_style' => ['nullable', Rule::in(PhotoSlot::SKY_STYLES)],
            'photos.*.sky_ring_kind' => ['nullable', Rule::in(PhotoSlot::SKY_RINGS)],
            'photos.*.sky_choices' => ['nullable', 'string', 'max:60'],
            'photos.*.sky_lines' => ['nullable', 'boolean'],
            'photos.*.sky_labels' => ['nullable', 'boolean'],
            'photos.*.sky_milky' => ['nullable', 'boolean'],
            'photos.*.sky_heart' => ['nullable', 'boolean'],
            'photos.*.map_style' => ['nullable', Rule::in(PhotoSlot::MAP_STYLES)],
            'photos.*.map_marker' => ['nullable', Rule::in(PhotoSlot::MAP_MARKERS)],
            'photos.*.map_zoom' => ['nullable', 'integer', 'between:11,18'],
            'photos.*.map_choices' => ['nullable', 'string', 'max:60'],
            'photos.*.map_pin' => ['nullable', 'boolean'],
            'photos.*.cutout' => ['nullable', 'boolean'],
            'photos.*.z' => ['nullable', 'numeric', 'min:0', 'max:65535'],
            'photos.*.locked' => ['nullable', 'boolean'],

            'texts' => ['present', 'array'],
            'texts.*.z' => ['nullable', 'numeric', 'min:0', 'max:65535'],
            'texts.*.label' => ['nullable', 'string', 'max:60'],
            'texts.*.i18n' => ['nullable', 'array'],
            'texts.*.i18n.*.label' => ['nullable', 'string', 'max:60'],
            'texts.*.i18n.*.placeholder' => ['nullable', 'string', 'max:255'],
            'texts.*.kind' => ['nullable', 'in:text,time'],
            'texts.*.fixed' => ['boolean'],
            'texts.*.default_value' => ['nullable', 'string', 'max:255'],
            'texts.*.placeholder' => ['nullable', 'string', 'max:255'],
            'texts.*.x' => ['required', 'numeric'],
            'texts.*.y' => ['required', 'numeric'],
            'texts.*.max_width' => ['required', 'numeric', 'min:10'],
            'texts.*.font_size' => ['required', 'numeric', 'min:4'],
            'texts.*.color' => ['required', 'string', 'max:9'],
            'texts.*.align' => ['required', 'in:left,center,right'],
            'texts.*.rotation' => ['required', 'numeric', 'between:-360,360'],
            'texts.*.font_family' => ['nullable', 'string', 'max:80'],
            // Photoshop's own VA scale, which is what the field says it is:
            // -1000 is letters touching, 10000 is a word strung right out.
            'texts.*.tracking' => ['nullable', 'integer', 'between:-1000,10000'],
            'texts.*.line_height' => ['nullable', 'integer', 'between:50,300'],
            'texts.*.text_case' => ['nullable', Rule::in(TextSlot::CASES)],
            'texts.*.scale_x' => ['nullable', 'integer', 'between:25,400'],
            'texts.*.scale_y' => ['nullable', 'integer', 'between:25,400'],
            'texts.*.baseline_shift' => ['nullable', 'integer', 'between:-500,500'],
            'texts.*.font_file' => ['nullable', 'string', 'max:255'],
            'texts.*.font_weight' => ['nullable', 'integer', 'between:100,900'],
            'texts.*.stroke_color' => ['nullable', 'string', 'max:9'],
            'texts.*.stroke_width' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'texts.*.shadow_color' => ['nullable', 'string', 'max:9'],
            'texts.*.shadow_blur' => ['nullable', 'numeric', 'min:0'],
            'texts.*.shadow_x' => ['nullable', 'numeric'],
            'texts.*.shadow_y' => ['nullable', 'numeric'],
            'texts.*.max_lines' => ['required', 'integer', 'between:1,10'],
            'texts.*.max_length' => ['required', 'integer', 'between:1,255'],
            'texts.*.link_key' => ['nullable', 'string', 'max:60'],
            'texts.*.auto' => ['nullable', Rule::in(TextSlot::AUTO)],
            'texts.*.locked' => ['nullable', 'boolean'],

            // The colour the box is dyed in the scenes (white renders).
            'box_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        foreach ($data['texts'] as $i => $t) {
            if (($t['kind'] ?? null) === TextSlot::KIND_TIME && ! preg_match(TextSlot::TIME_PATTERN, (string) ($t['default_value'] ?? ''))) {
                abort(422, 'Vaxt sahəsi ' . ($i + 1) . ' dəq:san şəklində olmalıdır, məs. 03:45.');
            }
        }

        // Only files this editor uploaded for this box may be referenced.
        $folder = $product->assetDirectory() . '/';
        foreach ($data['layers'] as $layer) {
            abort_unless(Str::startsWith($layer['image'], $folder), 422, 'Qat şəkli bu qutuya aid deyil.');
        }

        $canvas = config('boxes.canvas');

        /* Shapes an older editor tab never mentions are not deleted, so they
           live through this save and still have to be given a place with the
           rest — otherwise two rows come back claiming one. */
        $surviving = array_key_exists('shapes', $data)
            ? []
            : $product->shapes()->orderBy('sort_order')->orderBy('id')->get(['id', 'z', 'placement', 'sort_order'])->all();
        $stack = DesignStack::renumber($data, $surviving);
        $floor = DesignStack::floorOfPhotos($stack['photos']);
        /* The hosting copies the files into place before it runs migrations.
           In that minute the column does not exist yet, and a save must still
           work: the editor sends the placement it worked out, so the box keeps
           its order and the next save writes the stack. */
        $hasZ = Schema::hasColumn('design_layers', 'z');
        $at = fn (array $map, int $order) => $hasZ ? ['z' => $map[$order]] : [];
        /* "Below the photo" is no longer something the owner sets — it is read
           off the stack, so everything that still asks the column gets the
           truth without having to learn about z. */
        $side = fn (int $z) => $floor !== null && $z < $floor ? DesignLayer::BELOW : DesignLayer::ABOVE;

        DB::transaction(function () use ($product, $data, $canvas, $stack, $side, $hasZ, $at) {
            $product->forceFill([
                'template_width' => $canvas['width'],
                'template_height' => $canvas['height'],
                'box_color' => $data['box_color'] ?? null,
            ])->save();

            $product->layers()->delete();
            foreach (array_values($data['layers']) as $order => $l) {
                $product->layers()->create([
                    'name' => $l['name'] ?? null, 'image' => $l['image'],
                    'x' => (int) round($l['x']), 'y' => (int) round($l['y']),
                    'width' => (int) round($l['width']), 'height' => (int) round($l['height']),
                    'rotation' => (int) round($l['rotation']), 'opacity' => $l['opacity'],
                    'placement' => $hasZ ? $side($stack['layers'][$order]) : ($l['placement'] ?? 'above'),
                    'locked' => (bool) ($l['locked'] ?? false),
                    'sort_order' => $order,
                ] + $at($stack['layers'], $order));
            }

            // The slots are rewritten from scratch, so what they said about
            // face cutting is remembered first, in the order they are in. The
            // order is all we have to match them by, so it is only trusted
            // when the windows were neither added nor removed.
            /* An editor tab opened before shapes existed sends none at all;
               taking that as "delete them" would wipe a design's artwork on
               an ordinary save. Only a save that speaks about shapes rewrites
               them. */
            /* The lock is a working convenience, and an editor tab opened
               before it existed says nothing about it; remembered by position,
               the way the face cutting is. */
            $wasLocked = fn (string $relation, int $count) => $count === $product->{$relation}()->count()
                ? $product->{$relation}()->orderBy('sort_order')->pluck('locked')->all()
                : [];

            $shapeLocks = $wasLocked('shapes', count($data['shapes'] ?? []));
            if (array_key_exists('shapes', $data)) {
                $product->shapes()->delete();
            }
            foreach (array_values($data['shapes'] ?? []) as $order => $s) {
                $product->shapes()->create([
                    'kind' => $s['kind'],
                    'x' => (int) round($s['x']), 'y' => (int) round($s['y']),
                    'width' => (int) round($s['width']), 'height' => (int) round($s['height']),
                    'rotation' => (int) round($s['rotation']),
                    // An empty colour is "no fill at all", not black.
                    'fill' => $s['fill'] ?: null,
                    'stroke_color' => $s['stroke_color'] ?: null,
                    'stroke_width' => $s['stroke_width'] ?? 0,
                    'radius' => (int) ($s['radius'] ?? 0),
                    'opacity' => (int) ($s['opacity'] ?? 100),
                    'placement' => $hasZ ? $side($stack['shapes'][$order]) : ($s['placement'] ?? 'above'),
                    'locked' => array_key_exists('locked', $s) ? (bool) $s['locked'] : ($shapeLocks[$order] ?? false),
                    'sort_order' => $order,
                ] + $at($stack['shapes'], $order));
            }

            $photoLocks = $wasLocked('photoSlots', count($data['photos']));
            $textLocks = $wasLocked('textSlots', count($data['texts']));
            $kept = $product->photoSlots()->orderBy('sort_order')->pluck('cutout')->all();
            if (count($kept) !== count($data['photos'])) {
                $kept = [];
            }

            $product->photoSlots()->delete();
            foreach (array_values($data['photos']) as $order => $p) {
                $product->photoSlots()->create([
                    'label' => $p['label'] ?? null,
                    'i18n' => self::slotTranslations($p['i18n'] ?? null, ['label']),
                    'fill' => in_array($p['fill'] ?? null, [PhotoSlot::SKY, PhotoSlot::MAP], true)
                        ? $p['fill']
                        : PhotoSlot::PHOTO,
                    'sky_style' => in_array($p['sky_style'] ?? null, PhotoSlot::SKY_STYLES, true) ? $p['sky_style'] : 'night',
                    'sky_ring' => true,
                    'sky_ring_kind' => in_array($p['sky_ring_kind'] ?? null, PhotoSlot::SKY_RINGS, true) ? $p['sky_ring_kind'] : 'degrees',
                    'sky_choices' => implode(',', array_intersect(
                        array_filter(array_map('trim', explode(',', (string) ($p['sky_choices'] ?? '')))),
                        PhotoSlot::SKY_CHOICES
                    )),
                    'sky_lines' => (bool) ($p['sky_lines'] ?? true),
                    'sky_labels' => (bool) ($p['sky_labels'] ?? false),
                    'sky_milky' => (bool) ($p['sky_milky'] ?? false),
                    'sky_heart' => (bool) ($p['sky_heart'] ?? false),
                    'map_style' => in_array($p['map_style'] ?? null, PhotoSlot::MAP_STYLES, true) ? $p['map_style'] : 'ink',
                    'map_marker' => in_array($p['map_marker'] ?? null, PhotoSlot::MAP_MARKERS, true) ? $p['map_marker'] : 'heart',
                    'map_zoom' => \App\Support\StreetMap::zoom($p['map_zoom'] ?? 15),
                    'map_choices' => implode(',', array_intersect(
                        array_filter(array_map('trim', explode(',', (string) ($p['map_choices'] ?? '')))),
                        PhotoSlot::MAP_CHOICES
                    )),
                    'map_pin' => (bool) ($p['map_pin'] ?? true),
                    'x' => (int) round($p['x']), 'y' => (int) round($p['y']),
                    'width' => (int) round($p['width']), 'height' => (int) round($p['height']),
                    'rotation' => (int) round($p['rotation']), 'shape' => $p['shape'],
                    // An editor tab opened before this switch existed sends no
                    // "cutout" at all; taking that as "off" would quietly turn
                    // the face cutting off on a box that has it.
                    'cutout' => array_key_exists('cutout', $p) ? (bool) $p['cutout'] : ($kept[$order] ?? false),
                    'locked' => array_key_exists('locked', $p) ? (bool) $p['locked'] : ($photoLocks[$order] ?? false),
                    // Where it is drawn, and — separately — which field of the
                    // customer's form fills it. Moving a window up the stack
                    // must not hand an ordered photograph to another window.
                    'sort_order' => $order,
                ] + $at($stack['photos'], $order));
            }

            /* The shapes an older tab did not mention: left where they are,
               renumbered into the stack the rest were just given. */
            if ($hasZ) {
                foreach ($stack['keep'] as $id => $z) {
                    DesignShape::whereKey($id)->update(['z' => $z, 'placement' => $side($z)]);
                }
            }

            $product->textSlots()->delete();
            foreach (array_values($data['texts']) as $order => $t) {
                $product->textSlots()->create([
                    'label' => $t['label'] ?? null,
                    'i18n' => self::slotTranslations($t['i18n'] ?? null, ['label', 'placeholder']),
                    'kind' => $t['kind'] ?? TextSlot::KIND_TEXT,
                    'fixed' => (bool) ($t['fixed'] ?? false),
                    'default_value' => $t['default_value'] ?? null,
                    'placeholder' => $t['placeholder'] ?? null,
                    'x' => (int) round($t['x']), 'y' => (int) round($t['y']),
                    'max_width' => (int) round($t['max_width']), 'font_size' => (int) round($t['font_size']),
                    'color' => $t['color'], 'align' => $t['align'],
                    'rotation' => (int) round($t['rotation']),
                    'font_family' => $t['font_family'] ?? null, 'font_file' => $t['font_file'] ?? null,
                    'font_weight' => $t['font_weight'] ?? 400,
                    'tracking' => (int) ($t['tracking'] ?? 0),
                    'line_height' => (int) ($t['line_height'] ?? 120) ?: 120,
                    'text_case' => in_array($t['text_case'] ?? null, TextSlot::CASES, true) ? $t['text_case'] : 'none',
                    'scale_x' => (int) ($t['scale_x'] ?? 100) ?: 100,
                    'scale_y' => (int) ($t['scale_y'] ?? 100) ?: 100,
                    'baseline_shift' => (int) ($t['baseline_shift'] ?? 0),
                    // An explicit 0 means "no stroke"; null would bring back the
                    // soft outline older slots draw with.
                    'stroke_color' => $t['stroke_color'] ?? null,
                    'stroke_width' => $t['stroke_width'] ?? 0,
                    'shadow_color' => $t['shadow_color'] ?? null,
                    'shadow_blur' => (int) round($t['shadow_blur'] ?? 0),
                    'shadow_x' => (int) round($t['shadow_x'] ?? 0),
                    'shadow_y' => (int) round($t['shadow_y'] ?? 0),
                    // The owner's own wording always fits the slot it is written in.
                    'max_lines' => $t['max_lines'],
                    'max_length' => min(255, max((int) $t['max_length'], mb_strlen((string) ($t['default_value'] ?? '')))),
                    'link_key' => $t['link_key'] ?? null,
                    'locked' => array_key_exists('locked', $t) ? (bool) $t['locked'] : ($textLocks[$order] ?? false),
                    'auto' => in_array($t['auto'] ?? null, TextSlot::AUTO, true) ? $t['auto'] : 'none',
                    'sort_order' => $order,
                ] + $at($stack['texts'], $order));
            }
        });

        $this->pruneUnusedAssets($product);

        /* Saved with nothing on it: it cannot be sold, so it comes off the
           shelf by itself rather than meeting a customer as an empty box. */
        $blank = $product->fresh()->isBlank();
        if ($blank && $product->is_active) {
            $product->forceFill(['is_active' => false])->save();
        }

        return response()->json([
            'ok' => true,
            'saved_at' => now()->format('H:i:s'),
            'blank' => $blank,
            // The editor redraws the catalogue cover when the box colour may
            // have changed it.
            'cover' => $product->fresh()->coverJob(),
        ]);
    }

    /**
     * What the editor sent for the other languages, kept only where a word
     * was actually written — the shape the Translatable trait reads.
     *
     * @param  array<int, string>  $fields
     */
    private static function slotTranslations(mixed $sent, array $fields): ?array
    {
        if (! is_array($sent)) {
            return null;
        }

        $kept = [];
        foreach (\App\Support\Locale::all() as $locale) {
            if ($locale === \App\Support\Locale::DEFAULT || ! is_array($sent[$locale] ?? null)) {
                continue;
            }
            $words = array_filter(array_intersect_key($sent[$locale], array_flip($fields)), fn ($v) => is_string($v) && trim($v) !== '');
            if ($words !== []) {
                $kept[$locale] = array_map('trim', $words);
            }
        }

        return $kept ?: null;
    }

    public function uploadAsset(Request $request, Product $product): JsonResponse
    {
        $this->authorizeAdmin($request);
        $request->validate(['file' => ['required', 'file', 'image', 'mimes:png,webp,jpg,jpeg', 'max:20480']]);

        $file = $request->file('file');
        [$path, $width, $height] = $this->storeImage($file, $product->assetDirectory(), 'layer');

        return response()->json([
            'name' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'image' => $path, 'url' => Media::url($path),
            'width' => $width, 'height' => $height,
        ]);
    }

    /**
     * Puts a library picture on this box.
     *
     * The file is copied into the box's own folder rather than linked: a design
     * that is on sale must not depend on a picture the owner may later delete
     * from the shelf, and the editor only accepts layer images that live in the
     * box's folder anyway.
     */
    public function useLibrary(Request $request, Product $product, LibraryAsset $asset): JsonResponse
    {
        $this->authorizeAdmin($request);
        abort_unless($asset->is_active, 404);

        $disk = Storage::disk('public');
        abort_unless($disk->exists($asset->image), 422, 'Bu şəkil kitabxanada tapılmadı.');

        $target = $product->assetDirectory() . '/lib-' . Str::lower(Str::random(10))
            . '.' . Str::afterLast($asset->image, '.');
        $disk->copy($asset->image, $target);

        [$width, $height] = $asset->width && $asset->height
            ? [(int) $asset->width, (int) $asset->height]
            : $asset->measure();

        return response()->json([
            'name' => $asset->name, 'image' => $target, 'url' => Media::url($target),
            'width' => $width, 'height' => $height,
        ]);
    }

    /**
     * Copies a picture from another design onto this one.
     *
     * The editor lets a layer be copied and pasted into a different box, and
     * a design may only point at pictures in its own folder — so the file
     * comes along with it.
     */
    public function copyAsset(Request $request, Product $product): JsonResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate(['image' => ['required', 'string', 'max:255']]);

        $disk = Storage::disk('public');
        $source = $data['image'];
        abort_unless(Str::startsWith($source, 'boxes/') && ! Str::contains($source, '..'), 422, 'Bu şəkil qutu qatlarından deyil.');
        abort_unless($disk->exists($source), 422, 'Şəkil tapılmadı — borsə silinib.');

        $target = Str::startsWith($source, $product->assetDirectory() . '/')
            ? $source                       // already this box's own picture
            : $product->assetDirectory() . '/copy-' . Str::lower(Str::random(10)) . '.' . Str::afterLast($source, '.');

        if ($target !== $source) {
            $disk->copy($source, $target);
        }

        return response()->json(['image' => $target, 'url' => Media::url($target)]);
    }

    public function uploadVisual(Request $request, Product $product): JsonResponse
    {
        $this->authorizeAdmin($request);
        $request->validate(['file' => ['required', 'file', 'image', 'mimes:png,webp,jpg,jpeg', 'max:20480']]);

        [$path, $width, $height] = $this->storeImage($request->file('file'), $product->assetDirectory(), 'visual', 85);

        $old = $product->preview_image;
        $product->forceFill(['preview_image' => $path])->save();
        if ($old && $old !== $path && Str::startsWith($old, $product->assetDirectory() . '/')) {
            Storage::disk('public')->delete($old);
        }

        $canvas = config('boxes.canvas');
        $warning = ($width !== $canvas['width'] || $height !== $canvas['height'])
            ? "Vizualın ölçüsü {$width}×{$height}-dir, qutu isə {$canvas['width']}×{$canvas['height']}. Bələdçi uzanaraq göstəriləcək."
            : null;

        // A new visual may run out to a new colour, and the cover shows it.
        $product->refreshAutoBoxColor();

        return response()->json([
            'url' => Media::url($path),
            'warning' => $warning,
            'box_color_auto' => $product->box_color_auto,
            'cover' => $product->fresh()->coverJob(),
        ]);
    }

    public function uploadFont(Request $request, Product $product): JsonResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'name' => ['required', 'string', 'max:60', 'unique:fonts,name'],
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        abort_unless(in_array($ext, ['ttf', 'otf', 'woff', 'woff2'], true) && $this->looksLikeFont($file), 422,
            'Bu fayl şrift deyil. TTF, OTF, WOFF və ya WOFF2 yükləyin.');

        $path = $file->storeAs('fonts/custom', Str::slug($data['name']) . '-' . Str::lower(Str::random(6)) . '.' . $ext, 'public');

        $font = Font::create([
            'name' => $data['name'],
            // A family name of its own, so an uploaded face never clashes with a
            // system or Google font of the same name.
            'family' => 'NF ' . $data['name'],
            'file' => $path,
            // The file is the weight; asking a single-weight face for more
            // makes the browser fake a bold on top of it.
            'weight' => 400,
        ]);

        return response()->json($font->toEditor());
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->is_admin, 403);
    }

    /** @return array{0: string, 1: int, 2: int} */
    private function storeImage(UploadedFile $file, string $dir, string $prefix, int $quality = 90): array
    {
        return ImageStore::store($file, $dir, $prefix, $quality);
    }

    private function looksLikeFont(UploadedFile $file): bool
    {
        $head = (string) file_get_contents($file->getRealPath(), false, null, 0, 4);

        return in_array($head, ["\x00\x01\x00\x00", 'true', 'OTTO', 'wOFF', 'wOF2', 'typ1'], true);
    }

    /**
     * Removes uploads the saved design no longer uses. A day's grace keeps a
     * layer deleted and saved within reach of the editor's undo.
     */
    private function pruneUnusedAssets(Product $product): void
    {
        $disk = Storage::disk('public');
        $keep = $product->layers()->pluck('image')->push($product->preview_image, $product->cover_image)->filter()->all();
        $cutoff = now()->subDay()->getTimestamp();

        foreach ($disk->files($product->assetDirectory()) as $path) {
            if (! in_array($path, $keep, true) && $disk->lastModified($path) < $cutoff) {
                $disk->delete($path);
            }
        }
    }
}
