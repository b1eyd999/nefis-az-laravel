<?php

namespace App\Http\Controllers\Phone;

use App\Http\Controllers\Controller;
use App\Models\LivePhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

/**
 * The customers' live photos, on a phone — really one job: notice that a
 * video is stuck on the hosting and send it on to Yandex Disk.
 *
 * The sending itself is handed to the same command the deploy runs, after the
 * response has gone: an upload can take minutes and hold a phone request open
 * until it dies. Preparing a picture for the camera stays on the computer —
 * it is half a minute of canvas work, and iOS puts a backgrounded tab to
 * sleep in the middle of it.
 */
class LiveController extends Controller
{
    public function index(Request $request): View
    {
        $onlyStuck = $request->boolean('stuck');

        return view('phone.live', [
            'lives' => LivePhoto::with('orderItem:id,order_id,product_name')
                ->when($onlyStuck, fn ($q) => $q->whereNotNull('video_path')->whereNull('video_url'))
                ->latest('id')
                ->paginate(20)
                ->withQueryString(),
            'onlyStuck' => $onlyStuck,
            'stuck' => LivePhoto::whereNotNull('video_path')->whereNull('video_url')->count(),
        ]);
    }

    public function push(): RedirectResponse
    {
        defer(fn () => Artisan::call('live:push'));

        return back()->with('phone.flash', [
            'title' => 'Videolar köçürülür',
            'body' => 'Bir az sonra bu səhifəni yeniləyin.',
        ]);
    }
}
