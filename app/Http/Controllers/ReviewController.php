<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Review;
use App\Support\Telegram;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * What customers say, and where they say it.
 *
 * A review belongs to an order that was handed over: before the handover
 * there is nothing to say about the box, and tying it to the order is what
 * makes every review on this page a review by somebody who actually bought
 * something. The owner reads each one before anybody else does.
 */
class ReviewController extends Controller
{
    /** The page a visitor reads before he buys. */
    public function index(): View
    {
        $reviews = Review::shown()
            ->with(['user', 'product'])
            ->latest('approved_at')
            ->paginate(20);

        return view('reviews.index', [
            'reviews' => $reviews,
            'standing' => Review::standing(),
            // How many of each, for the bars beside the average.
            'spread' => Review::shown()
                ->selectRaw('stars, count(*) as how_many')
                ->groupBy('stars')
                ->pluck('how_many', 'stars')
                ->all(),
        ]);
    }

    /** The form, reached from the customer's own order. */
    public function create(Order $order): View
    {
        abort_unless(Review::invited($order, request()->user()), 404);

        return view('reviews.create', ['order' => $order->load('items')]);
    }

    public function store(Request $request, Order $order): RedirectResponse
    {
        abort_unless(Review::invited($order, $request->user()), 404);

        $data = $request->validate([
            'stars' => ['required', 'integer', 'min:1', 'max:' . Review::MOST],
            'body' => ['nullable', 'string', 'max:2000'],
            'shown_name' => ['nullable', 'string', 'max:60'],
            /* His own photograph of the box, which is worth more than
               anything the shop can shoot itself. Checked by what the file
               is, not by what it is called. */
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,heic', 'max:8192'],
        ], [
            'stars.required' => __('Neçə ulduz verdiyinizi seçin.'),
            'photo.image' => __('Şəkil JPG, PNG və ya WEBP olmalıdır.'),
            'photo.max' => __('Şəkil 8 MB-dan böyük ola bilməz.'),
        ]);

        $review = Review::create([
            'order_id' => $order->id,
            'user_id' => $request->user()->id,
            // Only when the order was one design: a review of three different
            // boxes says nothing about any one of them.
            'product_id' => self::theOneDesign($order),
            'stars' => $data['stars'],
            'body' => $data['body'] ?? null,
            'shown_name' => $data['shown_name'] ?? null,
            'photo' => $request->hasFile('photo')
                ? $request->file('photo')->store('reviews', 'public')
                : null,
            'locale' => \App\Support\Locale::current(),
        ]);

        defer(fn () => Telegram::review($review));

        return redirect(lroute('orders.index'))->with('status', (int) $data['stars'] >= 4
            ? __('Rəyiniz üçün təşəkkür edirik! Yoxlayıb saytda yerləşdirəcəyik.')
            : __('Yazdıqlarınız üçün təşəkkür edirik — oxuyub sizinlə əlaqə saxlayacağıq.'));
    }

    /**
     * The design the order was about, when it was about one.
     *
     * Several lines of the same box still count as that box; several
     * different boxes do not count as any of them.
     */
    private static function theOneDesign(Order $order): ?int
    {
        $ids = $order->items->pluck('product_id')->filter()->unique();

        return $ids->count() === 1 ? (int) $ids->first() : null;
    }
}
