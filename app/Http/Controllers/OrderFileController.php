<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Hands the workshop a file off an order line as a download.
 *
 * A link to the picture itself is not enough: a browser shows a .jpg rather
 * than saving it, however politely the link asks, and the one thing the shop
 * does with these pictures is put them in a folder and print them. So the
 * file is served with a name and a disposition that leave the browser no
 * choice.
 *
 * Sent with `Storage::download()`, which is what works here. A file response
 * built from an absolute path was tried instead and delivered nothing at all
 * on the hosting, while this one hands over the exact bytes of the file.
 *
 * Nothing here takes a path from the address — only the line and which of its
 * own files is wanted — so no address can be bent into reading something else
 * off the disk.
 */
class OrderFileController extends Controller
{
    public function show(Request $request, OrderItem $item, string $which): StreamedResponse
    {
        $disk = Storage::disk('public');

        $path = $which === 'mektub'
            ? $item->letter_photo
            : (($item->customer_photos ?? [])[max(1, (int) $which) - 1] ?? null);

        abort_if(blank($path) || ! $disk->exists($path), 404);

        $extension = pathinfo((string) $path, PATHINFO_EXTENSION) ?: 'jpg';
        $name = 'sifaris-' . $item->order_id . '-' . $item->id . '-' . $which . '.' . $extension;

        return $disk->download($path, $name);
    }
}
