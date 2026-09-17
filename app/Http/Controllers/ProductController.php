<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductAngle;

class ProductController extends Controller
{
    public function index()
    {
        $designs = Product::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->groupBy('category');

        return view('designs.index', compact('designs'));
    }

    public function customize(Product $product)
    {
        abort_unless($product->is_active && $product->isCustomizable(), 404);

        $product->load(['photoSlots', 'textSlots', 'angles.photoSlots', 'angles.textSlots']);

        $viewData = collect([$product])
            ->concat($product->angles)
            ->map(fn ($view) => $this->viewPayload($view))
            ->values()
            ->all();

        return view('products.customize', compact('product', 'viewData'));
    }

    /**
     * The front view lives on the product itself; each extra angle is a
     * ProductAngle. Both carry the same artwork + slot shape.
     */
    private function viewPayload(Product|ProductAngle $view): array
    {
        return [
            'url' => asset('storage/' . $view->template_image),
            'overlay' => $view->overlay_image ? asset('storage/' . $view->overlay_image) : null,
            'tw' => (int) $view->template_width,
            'th' => (int) $view->template_height,
            'bg' => $view->background_image ? asset('storage/' . $view->background_image) : null,
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
                'fontFile' => $slot->font_file ? asset('storage/' . $slot->font_file) : null,
            ])->values()->all(),
        ];
    }
}
