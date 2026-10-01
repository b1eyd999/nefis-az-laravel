<?php

namespace App\Http\Controllers;

use App\Models\Chocolate;
use App\Models\DesignLayer;
use App\Models\GiftPage;
use App\Models\Product;
use App\Models\ProductAngle;
use App\Models\ProductCategory;
use App\Models\Scene;
use App\Models\Wrapping;
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

        $gifts = GiftPage::shown()->inLocale(\App\Support\Locale::current())->get();

        /* One block per shelf the owner put up, in his order, and then one
           for everything filed under a shelf that is not there any more — so
           a design is never counted in the total and then left undrawn. */
        $shelves = ProductCategory::shown()->get()->filter(fn (ProductCategory $c) => $designs->has($c->slug));
        $blocks = $shelves
            ->map(fn (ProductCategory $c) => ['key' => $c->slug, 'label' => $c->label(), 'designs' => $designs[$c->slug]])
            ->values();

        $spare = $designs->reject(fn ($group, $key) => $shelves->contains('slug', $key))->flatten();
        if ($spare->isNotEmpty()) {
            $blocks->push(['key' => 'basqa', 'label' => __('Digər'), 'designs' => $spare]);
        }

        return view('designs.index', compact('designs', 'gifts', 'blocks'));
    }

    public function customize(Product $product)
    {
        abort_unless($product->is_active, 404);

        /* Switched on but nothing drawn on it yet. Rather than a bare 404 —
           the design does exist, and the owner is working on it — the page
           says so and offers the rest of the catalogue. Still noindex: there
           is nothing here for a search engine yet. */
        if ($product->isBlank()) {
            return response()->view('products.not-ready', ['product' => $product], 404);
        }

        // Slugs match whatever their case, so every design has an endless
        // family of addresses. Send them all to the one we publish.
        if ((string) request()->route()->originalParameter('product') !== $product->slug) {
            return redirect(lroute('products.customize', $product->slug), 301);
        }

        $product->load(['layers', 'shapes', 'photoSlots', 'textSlots', 'angles.photoSlots', 'angles.textSlots']);

        /* A design built in the editor is one whose artwork lives here rather
           than in a single flat template image — layers, or shapes, which a
           star map needs no image at all beside. */
        $viewData = $product->layers->isNotEmpty() || $product->shapes->isNotEmpty()
            ? $this->boxViews($product)
            : collect([$product])->concat($product->angles)
                ->map(fn ($view) => $this->viewPayload($view))
                ->values()
                ->all();

        // Where this design is offered, and what else looks like it — so a
        // visitor (and a crawler) always has somewhere to go from here.
        // The designs are picked once, on the Azerbaijani page; the other
        // languages hang off it. So the roots are found by the design, and
        // each is read in the visitor's language where that page is written.
        $locale = \App\Support\Locale::current();
        $gifts = GiftPage::shown()->inLocale('az')
            ->whereHas('products', fn ($q) => $q->whereKey($product->id))->get()
            ->map(fn (GiftPage $root) => $locale === 'az'
                ? $root
                : ($root->alternates()->where('locale', $locale)->where('is_active', true)->first() ?? $root))
            ->values();
        // What else suits the same occasion. Nearly every design sits in the
        // same category, so ordering by category recommended the same four
        // boxes everywhere — a Valentine's design under a christening one.
        // The occasions the owner picked say far more about what goes with
        // what; category is only the fallback for a design on no page yet.
        $occasions = $product->giftPages()->pluck('gift_pages.id');

        // The count is a sub-select, so it cannot be filtered in SQL; the list
        // is short and the counting is done once, so it is filtered here.
        $related = $occasions->isNotEmpty()
            ? Product::where('is_active', true)->whereKeyNot($product->id)
                ->withCount(['giftPages' => fn ($q) => $q->whereIn('gift_pages.id', $occasions)])
                ->orderByDesc('gift_pages_count')->orderBy('sort_order')->orderBy('name')
                ->get()->filter(fn (Product $p) => $p->gift_pages_count > 0)
                ->filter->isCustomizable()->take(4)->values()
            : collect();

        // A design nobody has put on an occasion page yet still needs company.
        if ($related->isEmpty()) {
            $related = Product::where('is_active', true)->whereKeyNot($product->id)
                ->where('category', $product->category)
                ->orderBy('sort_order')->orderBy('name')->get()
                ->filter->isCustomizable()->take(4)->values();
        }

        // The bar that goes inside: chosen here, priced with the owner's markup.
        /* Cheapest first, inside every brand: the price is worked out from the
           market price and the markup, so it cannot be ordered in the query. */
        $chocolates = Chocolate::shown()->get()->map->toCustomer()->sortBy('price')->values();

        // Gift wraps, cheapest first; the page groups them by price.
        $wrappings = Wrapping::shown()->get()->map->toCustomer()->values();

        return view('products.customize', compact('product', 'viewData', 'chocolates', 'wrappings', 'gifts', 'related'));
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

        $shape = fn (\App\Models\DesignShape $s) => [
            'kind' => $s->kind, 'x' => $s->x, 'y' => $s->y, 'width' => $s->width, 'height' => $s->height,
            'rotation' => $s->rotation, 'fill' => $s->fill, 'strokeColor' => $s->stroke_color,
            'strokeWidth' => (float) $s->stroke_width, 'radius' => (int) $s->radius, 'opacity' => (int) $s->opacity,
        ];

        $flat = array_merge($this->viewPayload($product), [
            'url' => null,
            'overlay' => null,
            'bg' => null,
            'layers' => [
                'below' => $product->layers->where('placement', DesignLayer::BELOW)->map($layer)->values()->all(),
                'above' => $product->layers->where('placement', DesignLayer::ABOVE)->map($layer)->values()->all(),
            ],
            'shapes' => [
                'below' => $product->shapes->where('placement', DesignLayer::BELOW)->map($shape)->values()->all(),
                'above' => $product->shapes->where('placement', DesignLayer::ABOVE)->map($shape)->values()->all(),
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
            'shapes' => ['below' => [], 'above' => []],
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
                // The browser cuts the face out of whatever is uploaded here.
                'cutout' => (bool) $slot->cutout,
                // 'sky' means no photograph is asked for: the window is filled
                // with the stars over the place and hour the customer names.
                'fill' => $slot->fill ?: \App\Models\PhotoSlot::PHOTO,
                'skyStyle' => $slot->sky_style ?: 'night',
                'skyRing' => $slot->sky_ring_kind ?: 'degrees',
                // 'map' means the same, for the streets around a place.
                'mapStyle' => $slot->map_style ?: 'ink',
                'mapMarker' => $slot->map_marker ?: 'heart',
                'mapZoom' => (int) ($slot->map_zoom ?: 15),
                'mapPin' => (bool) $slot->map_pin,
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
                'tracking' => (int) $slot->tracking,
                'lineHeight' => (int) ($slot->line_height ?: 120),
                'textCase' => $slot->text_case ?: 'none',
                'scaleX' => (int) ($slot->scale_x ?: 100),
                'scaleY' => (int) ($slot->scale_y ?: 100),
                'baselineShift' => (int) $slot->baseline_shift,
                'rotation' => (int) $slot->rotation,
                'strokeColor' => $slot->stroke_color,
                'strokeWidth' => $slot->stroke_width === null ? null : (float) $slot->stroke_width,
                'shadowColor' => $slot->shadow_color,
                'shadowBlur' => (int) $slot->shadow_blur,
                'shadowX' => (int) $slot->shadow_x,
                'shadowY' => (int) $slot->shadow_y,
                // Filled from the star map, not from a field on the page.
                'auto' => $slot->auto ?: 'none',
            ])->values()->all(),
        ];
    }
}
