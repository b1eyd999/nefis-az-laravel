<?php

namespace App\Http\Controllers;

use App\Models\LivePhoto;
use App\Support\Cart;
use App\Support\LiveMaterials;
use App\Support\Media;
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
    /** The product page: the customer's own picture and video, made into a live photo. */
    public function create(): View
    {
        abort_unless(LiveMaterials::enabled(), 404);

        return view('live.create', ['price' => LiveMaterials::price(), 'maxMb' => LiveMaterials::videoMb()]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(LiveMaterials::enabled(), 404);
        $request->validate(LiveMaterials::rules(true), LiveMaterials::messages());

        Cart::addLive(LiveMaterials::fromRequest($request));

        return redirect()->route('cart.index')->with('status', 'Canlı şəkil səbətə əlavə olundu.');
    }

    public function show(string $code): View
    {
        $live = LivePhoto::where('code', $code)->firstOrFail();
        abort_unless($live->is_active, 404);

        if ($live->isReady()) {
            $live->increment('views');
        }

        return view('live.show', ['live' => $live]);
    }

    /**
     * The video, straight from Yandex Disk: this site only points the way.
     * A customer's video not moved there yet plays from the hosting meanwhile.
     */
    public function video(string $code): RedirectResponse
    {
        $live = LivePhoto::where('code', $code)->where('is_active', true)->firstOrFail();

        if (filled($live->video_url)) {
            $href = YandexDisk::href($live->video_url);
            abort_if($href === null, 503, 'Video hazırda əlçatan deyil.');

            return redirect()->away($href, 302, ['Cache-Control' => 'private, max-age=600']);
        }

        abort_unless($live->video_path && Storage::disk('public')->exists($live->video_path), 404);

        return redirect()->away(Media::url($live->video_path), 302, ['Cache-Control' => 'private, max-age=60']);
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
