<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductAngle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Visual placement editor: drag the photo and text areas straight onto the
 * artwork instead of typing pixel coordinates.
 */
class LayoutController extends Controller
{
    public function edit(Request $request, Product $product): View
    {
        abort_unless($request->user()?->is_admin, 403);
        abort_unless($product->isCustomizable(), 404);

        $product->load(['photoSlots', 'textSlots', 'angles.photoSlots', 'angles.textSlots']);

        $views = collect([$this->payload($product, 'product', $product->id, 'Ön görünüş')])
            ->concat($product->angles->map(
                fn (ProductAngle $a) => $this->payload($a, 'angle', $a->id, $a->label ?: 'Bucaq')
            ))
            ->values()
            ->all();

        return view('admin.layout', compact('product', 'views'));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        abort_unless($request->user()?->is_admin, 403);

        $data = $request->validate([
            'views' => ['required', 'array'],
            'views.*.type' => ['required', 'in:product,angle'],
            'views.*.id' => ['required', 'integer'],
            'views.*.photo_slots' => ['array'],
            'views.*.photo_slots.*.label' => ['nullable', 'string', 'max:60'],
            'views.*.photo_slots.*.x' => ['required', 'integer'],
            'views.*.photo_slots.*.y' => ['required', 'integer'],
            'views.*.photo_slots.*.width' => ['required', 'integer', 'min:1'],
            'views.*.photo_slots.*.height' => ['required', 'integer', 'min:1'],
            'views.*.photo_slots.*.rotation' => ['required', 'integer'],
            'views.*.photo_slots.*.shape' => ['required', 'in:rectangle,ellipse'],
            'views.*.text_slots' => ['array'],
            'views.*.text_slots.*.label' => ['nullable', 'string', 'max:60'],
            'views.*.text_slots.*.x' => ['required', 'integer'],
            'views.*.text_slots.*.y' => ['required', 'integer'],
            'views.*.text_slots.*.max_width' => ['required', 'integer', 'min:10'],
            'views.*.text_slots.*.font_size' => ['required', 'integer', 'min:6'],
            'views.*.text_slots.*.color' => ['required', 'string', 'max:7'],
            'views.*.text_slots.*.align' => ['required', 'in:left,center,right'],
            'views.*.text_slots.*.font_family' => ['nullable', 'string', 'max:80'],
            'views.*.text_slots.*.font_file' => ['nullable', 'string', 'max:255'],
            'views.*.text_slots.*.default_value' => ['nullable', 'string', 'max:255'],
            'views.*.text_slots.*.placeholder' => ['nullable', 'string', 'max:255'],
            'views.*.text_slots.*.max_length' => ['required', 'integer', 'min:1', 'max:255'],
            'views.*.text_slots.*.max_lines' => ['required', 'integer', 'min:1', 'max:10'],
        ]);

        foreach ($data['views'] as $view) {
            $owner = $view['type'] === 'product'
                ? ($product->id === (int) $view['id'] ? $product : null)
                : $product->angles()->find($view['id']);

            if (! $owner) {
                continue;
            }

            $owner->photoSlots()->delete();
            foreach ($view['photo_slots'] ?? [] as $order => $slot) {
                $owner->photoSlots()->create($slot + ['sort_order' => $order]);
            }

            $owner->textSlots()->delete();
            foreach ($view['text_slots'] ?? [] as $order => $slot) {
                $owner->textSlots()->create($slot + ['sort_order' => $order]);
            }
        }

        return back()->with('status', 'Yerləşdirmə yadda saxlanıldı.');
    }

    private function payload(Product|ProductAngle $owner, string $type, int $id, string $label): array
    {
        return [
            'type' => $type,
            'id' => $id,
            'label' => $label,
            'template' => asset('storage/' . $owner->template_image),
            'overlay' => $owner->overlay_image ? asset('storage/' . $owner->overlay_image) : null,
            'width' => (int) $owner->template_width,
            'height' => (int) $owner->template_height,
            'photo_slots' => $owner->photoSlots->map(fn ($s) => [
                'label' => $s->label,
                'x' => (int) $s->x, 'y' => (int) $s->y,
                'width' => (int) $s->width, 'height' => (int) $s->height,
                'rotation' => (int) $s->rotation, 'shape' => $s->shape,
            ])->values()->all(),
            'text_slots' => $owner->textSlots->map(fn ($s) => [
                'label' => $s->label,
                'x' => (int) $s->x, 'y' => (int) $s->y,
                'max_width' => (int) $s->max_width, 'font_size' => (int) $s->font_size,
                'color' => $s->color, 'align' => $s->align,
                'font_family' => $s->font_family, 'font_file' => $s->font_file,
                'default_value' => $s->default_value, 'placeholder' => $s->placeholder,
                'max_length' => (int) $s->max_length,
                'max_lines' => max(1, (int) $s->max_lines),
            ])->values()->all(),
        ];
    }
}
