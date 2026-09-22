<?php

namespace App\Http\Controllers;

use App\Models\LivePhoto;
use App\Support\YandexDisk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The page a live photo's QR code opens: the phone's camera, and the video
 * playing over the picture once it is found. Also takes the tracking data
 * the admin's browser makes from the picture.
 */
class LivePhotoController extends Controller
{
    public function show(string $code): View
    {
        $live = LivePhoto::where('code', $code)->firstOrFail();
        abort_unless($live->is_active, 404);

        if ($live->isReady()) {
            $live->increment('views');
        }

        return view('live.show', ['live' => $live]);
    }

    /** The video, straight from Yandex Disk: this site only points the way. */
    public function video(string $code): RedirectResponse
    {
        $live = LivePhoto::where('code', $code)->where('is_active', true)->firstOrFail();
        $href = YandexDisk::href($live->video_url);
        abort_if($href === null, 503, 'Video hazırda əlçatan deyil.');

        return redirect()->away($href, 302, ['Cache-Control' => 'private, max-age=600']);
    }

    /** The tracking data (.mind) compiled in the admin's browser from the picture. */
    public function storeMind(Request $request, LivePhoto $livePhoto): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $request->validate(['mind' => ['required', 'file', 'max:20480']]);

        $old = $livePhoto->target_mind;
        $path = $request->file('mind')->storeAs('live/' . $livePhoto->id, 'target-' . Str::lower(Str::random(6)) . '.mind', 'public');
        $livePhoto->forceFill(['target_mind' => $path])->saveQuietly();
        if ($old && $old !== $path) {
            Storage::disk('public')->delete($old);
        }

        return response()->json(['ok' => true, 'url' => $livePhoto->mindUrl()]);
    }
}
