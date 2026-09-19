<?php

namespace App\Http\Controllers;

use App\Models\Chocolate;
use App\Models\DesignLayer;
use App\Models\Product;
use App\Models\ProductAngle;
use App\Models\Scene;
use App\Support\Media;

class ProductController extends Controller
{
    public function index()
    {
        $designs = Product::where('is_active', true)
            ->withCount('layers')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->groupBy('category');

        return view('designs.index', compact('designs'));
    }

    public function customize(Product $product)
    {
        abort_unless($product->is_active && $product->isCustomizable(), 404);

        $product->load(['layers', 'photoSlots', 'textSlots', 'angles.photoSlots', 'angles.textSlots']);

        $viewData = $product->layers->isNotEmpty()
            ? $this->boxViews($product)
            : collect([$product])->concat($product->angles)
                ->map(fn ($view) => $this->viewPayload($view))
                ->values()
                ->all();

        // The bar that goes inside: chosen here, priced with the owner's markup.
        $chocolates = Chocolate::shown()->get()->map->toCustomer()->values();

        return view('products.customize', compact('product', 'viewData', 'chocolates'));
    }

    /**
     * A box built in the editor is one flat design, shown in every scene the
     * owner built in the scene editor (or only those picked for it), then on
     * its own. Until any scene exists, the stand-ins in config/boxes.php fill in.
     */
    private function boxViews(Product $product): array
    {
        $w = (int) $product->template_width ?: config('boxes.canvas.width');
        $h = (int) $product->template_height ?: config('boxes.canvas.height');

        $layer = fn (DesignLayer $l) => [
            'url' => Media::url($l->image),
            'x' => $l->x, 'y' => $l->y, 'width' => $l->width, 'height' => $l->height,
            'rotation' => $l->rotation, 'opacity' => $l->opacity,
        ];

        $flat = array_merge($this->viewPayload($product), [
            'url' => null,
            'overlay' => null,
            'bg' => null,
            'layers' => [
                'below' => $product->layers->where('placement', DesignLayer::BELOW)->map($layer)->values()->all(),
                'above' => $product->layers->where('placement', DesignLayer::ABOVE)->map($layer)->values()->all(),
            ],
            'tw' => $w,
            'th' => $h,
            'scene' => null,
            // Renders marked to follow it are dyed this colour in every scene.
            'boxColor' => $product->effectiveBoxColor(),
        ]);

        $scenes = $product->scenes()->where('is_active', true)->get();
        if ($scenes->isEmpty()) {
            $scenes = Scene::shown()->get();
        }
        $scenes = $scenes->map->toCustomer();
        if ($scenes->isEmpty()) {
            $scenes = collect(config('boxes.scenes'))->map(fn (array $s) => Scene::fromConfig($s, $w, $h));
        }

        return $scenes
            ->map(fn (array $scene) => array_merge($flat, ['label' => $scene['label'], 'scene' => $scene]))
            ->push(array_merge($flat, ['label' => 'Düz görünüş']))
            ->values()
            ->all();
    }

    /**
     * Designs from before the editor: the front view lives on the product
     * itself and each extra angle is a ProductAngle with its own slots.
     */
    private function viewPayload(Product|ProductAngle $view): array
    {
        return [
            'url' => Media::url($view->template_image),
            'overlay' => Media::url($view->overlay_image),
            'layers' => ['below' => [], 'above' => []],
            'label' => $view instanceof ProductAngle ? $view->label : null,
            'tw' => (int) $view->template_width,
            'th' => (int) $view->template_height,
            'bg' => Media::url($view->background_image),
            'bgW' => (int) ($view->background_width ?? 0),
            'bgH' => (int) ($view->background_height ?? 0),
            'boxArea' => [
                'x' => (int) ($view->box_area_x ?? 0),
                'y' => (int) ($view->box_area_y ?? 0),
                'w' => (int) ($view->box_area_width ?? 0),
                'h' => (int) ($view->box_area_height ?? 0),
                'rotation' => (int) ($view->box_area_rotation ?? 0),
            ],
            'contentBox' => [
                'x' => (int) ($view->content_x ?? 0),
                'y' => (int) ($view->content_y ?? 0),
                'w' => (int) ($view->content_width ?: $view->template_width),
                'h' => (int) ($view->content_height ?: $view->template_height),
                'rotation' => (int) ($view->content_rotation ?? 0),
            ],
            'areas' => $view->photoSlots->map(fn ($slot) => [
                'x' => (int) $slot->x,
                'y' => (int) $slot->y,
                'w' => (int) $slot->width,
                'h' => (int) $slot->height,
                'rotation' => (int) $slot->rotation,
                'shape' => $slot->shape,
            ])->values()->all(),
            'texts' => $view->textSlots->map(fn ($slot) => [
                'x' => (int) $slot->x,
                'y' => (int) $slot->y,
                'maxWidth' => (int) $slot->max_width,
                'fontSize' => (int) $slot->font_size,
                'color' => $slot->color,
                'align' => $slot->align,
                'maxLines' => max(1, (int) $slot->max_lines),
                'fontFamily' => $slot->font_family,
                'fontFile' => Media::url($slot->font_file),
                'fontWeight' => $slot->font_weight,
                'rotation' => (int) $slot->rotation,
                'strokeColor' => $slot->stroke_color,
                'strokeWidth' => $slot->stroke_width === null ? null : (float) $slot->stroke_width,
                'shadowColor' => $slot->shadow_color,
                'shadowBlur' => (int) $slot->shadow_blur,
                'shadowX' => (int) $slot->shadow_x,
                'shadowY' => (int) $slot->shadow_y,
            ])->values()->all(),
        ];
    }
}
