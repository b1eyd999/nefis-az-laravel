<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Scene;
use App\Models\SceneAsset;
use App\Support\ImageStore;
use App\Support\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The admin's scene editor: the mockups every box is shown to customers in.
 *
 * The owner picks a background and a rendered empty box from the library,
 * stacks them, and corner-pins the flat design onto the box's face by hand.
 * Nothing is placed automatically.
 */
class SceneEditorController extends Controller
{
    /** Longest side a library picture is kept at; the editor shrinks to it. */
    public const MAX_SIDE = 2000;

    public function edit(Request $request, Scene $scene): View
    {
        $this->authorizeAdmin($request);

        $payload = [
            'name' => $scene->name,
            'is_active' => (bool) $scene->is_active,
            'width' => (int) $scene->width,
            'height' => (int) $scene->height,
            'background' => $scene->background,
            'background_url' => Media::url($scene->background),
            'background_color' => $scene->background_color,
            'elements' => collect($scene->elements ?? [])
                ->map(fn (array $el) => ($el['type'] ?? null) === 'image' ? $el + ['url' => Media::url($el['image'] ?? null)] : $el)
                ->values(),
        ];

        $library = SceneAsset::latest('id')->get()->map->toEditor()->values();

        // Finished visuals make the best stand-in for "a design" while placing.
        $samples = Product::whereNotNull('preview_image')->orderBy('sort_order')->orderBy('name')->get()
            ->map(fn (Product $p) => ['name' => $p->name, 'url' => Media::url($p->preview_image), 'box_color' => $p->effectiveBoxColor()])
            ->values();

        $liveBox = Product::where('is_active', true)->has('layers')->orderBy('sort_order')->first();

        return view('admin.scene-editor', [
            'scene' => $scene,
            'payload' => $payload,
            'library' => $library,
            'samples' => $samples,
            'canvas' => config('boxes.canvas'),
            'liveUrl' => $liveBox ? route('products.customize', $liveBox->slug) : null,
        ]);
    }

    public function save(Request $request, Scene $scene): JsonResponse
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'is_active' => ['boolean'],
            'background' => ['nullable', 'string', 'max:255'],
            'background_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'width' => ['required', 'integer', 'between:100,4000'],
            'height' => ['required', 'integer', 'between:100,4000'],
            'elements' => ['present', 'array', 'max:40'],
            'elements.*.id' => ['required', 'string', 'max:40', 'distinct'],
            'elements.*.type' => ['required', 'in:image,design'],
            'elements.*.name' => ['nullable', 'string', 'max:120'],
            'elements.*.opacity' => ['required', 'integer', 'between:0,100'],
            'elements.*.blend' => ['nullable', Rule::in(Scene::BLENDS)],
            'elements.*.locked' => ['boolean'],
            'elements.*.hidden' => ['boolean'],
            'elements.*.image' => ['nullable', 'string', 'max:255'],
            'elements.*.x' => ['nullable', 'numeric'],
            'elements.*.y' => ['nullable', 'numeric'],
            'elements.*.width' => ['nullable', 'numeric', 'min:1'],
            'elements.*.height' => ['nullable', 'numeric', 'min:1'],
            'elements.*.rotation' => ['nullable', 'numeric', 'between:-360,360'],
            'elements.*.flip_x' => ['boolean'],
            'elements.*.flip_y' => ['boolean'],
            'elements.*.tint' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'elements.*.sheen' => ['nullable', 'integer', 'between:0,100'],
            'elements.*.tint_strength' => ['nullable', 'integer', 'between:0,100'],
            'elements.*.recolor' => ['boolean'],
            'elements.*.tint_all' => ['boolean'],
            'elements.*.corners' => ['nullable', 'array', 'size:4'],
            'elements.*.corners.*' => ['array', 'size:2'],
            'elements.*.corners.*.*' => ['numeric', 'between:-20000,20000'],
            'elements.*.shade' => ['nullable', 'integer', 'between:0,200'],
            'elements.*.shade_from' => ['nullable', 'string', 'max:40'],
            // A small JPEG of the scene for the admin's list.
            'preview' => ['nullable', 'string', 'max:1500000'],
        ]);

        // Only pictures from the library may be referenced.
        $library = SceneAsset::pluck('image')->all();
        if (filled($data['background'] ?? null) && ! in_array($data['background'], $library, true)) {
            abort(422, 'Fon kitabxanada tapılmadı.');
        }

        $imageIds = collect($data['elements'])->where('type', 'image')->pluck('id')->all();
        $elements = [];
        foreach (array_values($data['elements']) as $i => $el) {
            $base = [
                'id' => $el['id'],
                'type' => $el['type'],
                'name' => $el['name'] ?? null,
                'opacity' => (int) $el['opacity'],
                'locked' => (bool) ($el['locked'] ?? false),
                'hidden' => (bool) ($el['hidden'] ?? false),
            ];

            if ($el['type'] === 'image') {
                abort_unless(in_array($el['image'] ?? null, $library, true), 422, 'Qat ' . ($i + 1) . ': şəkil kitabxanada tapılmadı.');
                foreach (['x', 'y', 'width', 'height'] as $k) {
                    abort_unless(isset($el[$k]), 422, 'Qat ' . ($i + 1) . ": {$k} yoxdur.");
                }
                $elements[] = $base + [
                    'image' => $el['image'],
                    'x' => round($el['x'], 2), 'y' => round($el['y'], 2),
                    'width' => round($el['width'], 2), 'height' => round($el['height'], 2),
                    'rotation' => round($el['rotation'] ?? 0, 2),
                    'blend' => $el['blend'] ?? 'source-over',
                    'flip_x' => (bool) ($el['flip_x'] ?? false),
                    'flip_y' => (bool) ($el['flip_y'] ?? false),
                    'tint' => $el['tint'] ?? null,
                    'sheen' => (int) ($el['sheen'] ?? 0),
                    'tint_strength' => (int) ($el['tint_strength'] ?? 70),
                    'recolor' => (bool) ($el['recolor'] ?? false),
                    'tint_all' => (bool) ($el['tint_all'] ?? false),
                ];
            } else {
                abort_unless(isset($el['corners']) && count($el['corners']) === 4, 422, 'Dizayn yeri ' . ($i + 1) . ': 4 künc lazımdır.');
                $shadeFrom = $el['shade_from'] ?? null;
                $elements[] = $base + [
                    'corners' => array_map(fn ($p) => [round((float) $p[0], 2), round((float) $p[1], 2)], array_values($el['corners'])),
                    'blend' => in_array($el['blend'] ?? null, ['source-over', 'multiply'], true) ? $el['blend'] : 'source-over',
                    'shade' => (int) ($el['shade'] ?? 0),
                    'shade_from' => in_array($shadeFrom, $imageIds, true) ? $shadeFrom : null,
                ];
            }
        }

        $scene->fill([
            'name' => $data['name'],
            'is_active' => (bool) ($data['is_active'] ?? true),
            'background' => $data['background'] ?? null,
            'background_color' => $data['background_color'] ?? null,
            'width' => $data['width'],
            'height' => $data['height'],
            'elements' => $elements,
        ]);

        if (filled($data['preview'] ?? null)) {
            $this->storePreview($scene, $data['preview']);
        }
        $scene->save();

        return response()->json([
            'ok' => true,
            'saved_at' => now()->format('H:i:s'),
            // Catalogue covers drawn in this scene, for the editor to redraw.
            'covers' => Product::with('coverScene')->where('cover_scene_id', $scene->id)->get()
                ->map->coverJob()->filter()->values(),
            'preview' => Media::url($scene->preview_image),
        ]);
    }

    public function uploadAsset(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'file' => ['required', 'file', 'image', 'mimes:png,webp,jpg,jpeg', 'max:20480'],
            'kind' => ['required', Rule::in([SceneAsset::BACKGROUND, SceneAsset::OBJECT])],
            'name' => ['nullable', 'string', 'max:120'],
        ]);

        // A full-size render decoded by GD needs more than the default limit.
        @ini_set('memory_limit', '512M');

        $file = $request->file('file');
        $background = $data['kind'] === SceneAsset::BACKGROUND;
        [$path, $width, $height] = ImageStore::store($file, SceneAsset::DIRECTORY, $data['kind'], $background ? 85 : 90, self::MAX_SIDE + 600);

        $asset = SceneAsset::create([
            'name' => $data['name'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'kind' => $data['kind'],
            'image' => $path,
            'width' => $width,
            'height' => $height,
        ]);

        return response()->json($asset->toEditor());
    }

    public function destroyAsset(Request $request, SceneAsset $asset): JsonResponse
    {
        $this->authorizeAdmin($request);

        $scenes = $asset->usedIn();
        abort_if($scenes, 422, 'Bu şəkil istifadə olunur: ' . implode(', ', $scenes) . '. Əvvəlcə oradan çıxarın.');

        $asset->delete();

        return response()->json(['ok' => true]);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->is_admin, 403);
    }

    private function storePreview(Scene $scene, string $dataUrl): void
    {
        if (! Str::startsWith($dataUrl, 'data:image/jpeg;base64,')) {
            return;
        }
        $jpeg = base64_decode(substr($dataUrl, strlen('data:image/jpeg;base64,')), true);
        if ($jpeg === false || ! str_starts_with($jpeg, "\xFF\xD8\xFF")) {
            return;
        }

        $old = $scene->preview_image;
        $path = 'scenes/previews/scene-' . ($scene->id ?: 'new') . '-' . Str::lower(Str::random(6)) . '.jpg';
        Storage::disk('public')->put($path, $jpeg);
        $scene->preview_image = $path;
        if ($old && $old !== $path) {
            Storage::disk('public')->delete($old);
        }
    }
}
