<?php

namespace App\Http\Controllers;

use App\Models\DesignLayer;
use App\Models\Font;
use App\Models\Product;
use App\Models\TextSlot;
use App\Support\ImageStore;
use App\Support\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

        $product->load(['layers', 'photoSlots', 'textSlots']);
        $canvas = config('boxes.canvas');

        $design = [
            'canvas' => $canvas,
            'visual' => $product->preview_image ? Media::url($product->preview_image) : null,
            'box_color' => $product->box_color,
            // What the box turns without a colour of its own: read off the visual.
            'box_color_auto' => $product->box_color ? $product->box_color_auto : $product->effectiveBoxColor(),
            'layers' => $product->layers->map(fn (DesignLayer $l) => [
                'name' => $l->name, 'image' => $l->image, 'url' => Media::url($l->image),
                'x' => $l->x, 'y' => $l->y, 'width' => $l->width, 'height' => $l->height,
                'rotation' => $l->rotation, 'opacity' => $l->opacity,
                'placement' => $l->placement, 'locked' => $l->locked,
            ])->values(),
            'photos' => $product->photoSlots->map(fn ($s) => [
                'label' => $s->label, 'x' => $s->x, 'y' => $s->y,
                'width' => $s->width, 'height' => $s->height,
                'rotation' => $s->rotation, 'shape' => $s->shape,
            ])->values(),
            'texts' => $product->textSlots->map(fn ($s) => [
                'label' => $s->label, 'kind' => $s->kind ?: TextSlot::KIND_TEXT, 'fixed' => (bool) $s->fixed,
                'default_value' => $s->default_value, 'placeholder' => $s->placeholder,
                'x' => $s->x, 'y' => $s->y, 'max_width' => $s->max_width, 'font_size' => $s->font_size,
                'color' => $s->color, 'align' => $s->align, 'rotation' => (int) $s->rotation,
                'font_family' => $s->font_family, 'font_file' => $s->font_file,
                'font_weight' => $s->font_weight ?: 400,
                'stroke_color' => $s->stroke_color, 'stroke_width' => (float) $s->stroke_width,
                'shadow_color' => $s->shadow_color, 'shadow_blur' => (int) $s->shadow_blur,
                'shadow_x' => (int) $s->shadow_x, 'shadow_y' => (int) $s->shadow_y,
                'max_lines' => max(1, (int) $s->max_lines), 'max_length' => (int) $s->max_length,
                'link_key' => $s->link_key,
            ])->values(),
        ];

        $fonts = Font::orderBy('name')->get()->map->toEditor()->values();

        return view('admin.box-editor', compact('product', 'design', 'fonts'));
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
            'layers.*.placement' => ['required', 'in:below,above'],
            'layers.*.locked' => ['boolean'],

            'photos' => ['present', 'array'],
            'photos.*.label' => ['nullable', 'string', 'max:60'],
            'photos.*.x' => ['required', 'numeric'],
            'photos.*.y' => ['required', 'numeric'],
            'photos.*.width' => ['required', 'numeric', 'min:1'],
            'photos.*.height' => ['required', 'numeric', 'min:1'],
            'photos.*.rotation' => ['required', 'numeric', 'between:-360,360'],
            'photos.*.shape' => ['required', 'in:rectangle,ellipse'],

            'texts' => ['present', 'array'],
            'texts.*.label' => ['nullable', 'string', 'max:60'],
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

        DB::transaction(function () use ($product, $data, $canvas) {
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
                    'placement' => $l['placement'], 'locked' => (bool) ($l['locked'] ?? false),
                    'sort_order' => $order,
                ]);
            }

            $product->photoSlots()->delete();
            foreach (array_values($data['photos']) as $order => $p) {
                $product->photoSlots()->create([
                    'label' => $p['label'] ?? null,
                    'x' => (int) round($p['x']), 'y' => (int) round($p['y']),
                    'width' => (int) round($p['width']), 'height' => (int) round($p['height']),
                    'rotation' => (int) round($p['rotation']), 'shape' => $p['shape'],
                    'sort_order' => $order,
                ]);
            }

            $product->textSlots()->delete();
            foreach (array_values($data['texts']) as $order => $t) {
                $product->textSlots()->create([
                    'label' => $t['label'] ?? null,
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
                    // An explicit 0 means "no stroke"; null would bring back the
                    // soft outline older slots draw with.
                    'stroke_color' => $t['stroke_color'] ?? null,
                    'stroke_width' => $t['stroke_width'] ?? 0,
                    'shadow_color' => $t['shadow_color'] ?? null,
                    'shadow_blur' => (int) round($t['shadow_blur'] ?? 0),
                    'shadow_x' => (int) round($t['shadow_x'] ?? 0),
                    'shadow_y' => (int) round($t['shadow_y'] ?? 0),
                    'max_lines' => $t['max_lines'], 'max_length' => $t['max_length'],
                    'link_key' => $t['link_key'] ?? null,
                    'sort_order' => $order,
                ]);
            }
        });

        $this->pruneUnusedAssets($product);

        return response()->json([
            'ok' => true,
            'saved_at' => now()->format('H:i:s'),
            // The editor redraws the catalogue cover when the box colour may
            // have changed it.
            'cover' => $product->fresh()->coverJob(),
        ]);
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
