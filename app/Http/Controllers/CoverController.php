<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\ImageStore;
use App\Support\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Catalogue covers: each product's design shown in the scene the owner picked.
 *
 * A cover is drawn by the same renderer as the customer page, so it has to be
 * drawn in a browser. The admin's browser does it and uploads the result,
 * either on this page (after the owner picks a cover scene in the panel) or
 * straight from the box and scene editors when something a cover shows
 * changes.
 */
class CoverController extends Controller
{
    /** Draws and uploads the covers of the given products, then goes back. */
    public function page(Request $request): View
    {
        $this->authorizeAdmin($request);

        $ids = collect(explode(',', (string) $request->query('ids')))->map(fn ($id) => (int) $id)->filter()->unique();
        $products = Product::with('coverScene')->whereIn('id', $ids)->get();

        $jobs = [];
        foreach ($products as $product) {
            if ($job = $product->coverJob()) {
                $jobs[] = $job;
            } elseif ($product->cover_image) {
                // No scene, or nothing to put in it: the plain visual shows.
                $this->dropCover($product);
            }
        }

        return view('admin.cover-render', [
            'jobs' => $jobs,
            'back' => $this->safeBack($request->query('back')),
        ]);
    }

    public function store(Request $request, Product $product): JsonResponse
    {
        $this->authorizeAdmin($request);
        $request->validate(['file' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240']]);

        [$path] = ImageStore::store($request->file('file'), $product->assetDirectory(), 'cover', 85);

        $old = $product->cover_image;
        $product->forceFill(['cover_image' => $path])->saveQuietly();
        if ($old && $old !== $path) {
            Storage::disk('public')->delete($old);
        }

        return response()->json(['ok' => true, 'url' => Media::url($path)]);
    }

    private function dropCover(Product $product): void
    {
        Storage::disk('public')->delete($product->cover_image);
        $product->forceFill(['cover_image' => null])->saveQuietly();
    }

    /** Only a path on this site: the page must not redirect anywhere else. */
    private function safeBack(mixed $back): string
    {
        return is_string($back) && Str::startsWith($back, '/') && ! Str::startsWith($back, ['//', '/\\'])
            ? $back
            : '/admin/products';
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->is_admin, 403);
    }
}
