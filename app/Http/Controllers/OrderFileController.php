<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Hands the workshop a file off an order line as a download.
 *
 * A link to the picture itself is not enough: a browser shows a .jpg rather
 * than saving it, however politely the link asks, and the one thing the shop
 * does with these pictures is put them in a folder and print them. So the
 * file is served with a name and a disposition that leave the browser no
 * choice.
 *
 * It is sent as a file rather than as a stream. `Storage::download()` streams
 * it through PHP, and on this hosting a customer's photograph — several
 * megabytes off a phone, against the letter's few hundred kilobytes — stopped
 * part way and the browser threw the half away. A file response carries its
 * own length and lets the web server do the sending.
 *
 * Nothing here takes a path from the address — only the line and which of its
 * own files is wanted — so no address can be bent into reading something else
 * off the disk.
 */
class OrderFileController extends Controller
{
    public function show(Request $request, OrderItem $item, string $which): BinaryFileResponse
    {
        $disk = Storage::disk('public');

        $path = $which === 'mektub'
            ? $item->letter_photo
            : (($item->customer_photos ?? [])[max(1, (int) $which) - 1] ?? null);

        abort_if(blank($path) || ! $disk->exists($path), 404);

        $extension = pathinfo((string) $path, PATHINFO_EXTENSION) ?: 'jpg';
        $name = 'sifaris-' . $item->order_id . '-' . $item->id . '-' . $which . '.' . $extension;

        return response()->download($disk->path($path), $name);
    }
}
