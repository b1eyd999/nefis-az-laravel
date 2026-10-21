<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\Locale;
use App\Support\SamiraFeed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Samira's two questions to the shop, both read-only:
 *   GET /api/samira/feed?since=<unix>   what happened since, and today
 *   GET /api/samira/order/<number>      «что с заказом №…»
 *
 * Behind `SamiraOnly` (the key) and a per-address throttle; nothing here
 * writes to an order, and nothing here can.
 */
class SamiraController extends Controller
{
    /** How far back one question may reach. Older than this is history, not news. */
    public const FURTHEST_DAYS = 7;

    public function feed(Request $request): JsonResponse
    {
        $this->speakTheShopsLanguage();

        $tz = (string) config('app.timezone');
        /* The upper edge is this second, exclusive, and it is handed back as
           `now` for the next question's `since`: an order confirmed in this
           very second is not lost between two answers, and none is told twice. */
        $now = Carbon::createFromTimestamp(now()->getTimestamp(), $tz);
        $furthest = $now->copy()->subDays(self::FURTHEST_DAYS);

        $asked = $request->query('since');
        $since = is_string($asked) && ctype_digit($asked) && strlen($asked) <= 11
            ? Carbon::createFromTimestamp((int) $asked, $tz)
            : $now->copy()->startOfDay();

        if ($since->lt($furthest)) {
            $since = $furthest;
        }
        if ($since->gt($now)) {
            $since = $now->copy();
        }

        return response()->json(SamiraFeed::feed($since, $now), 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function order(string $number): JsonResponse
    {
        $this->speakTheShopsLanguage();

        $order = Order::query()->with(['items', 'user:id,name'])->find((int) $number);

        if (! $order) {
            return response()->json(['error' => 'not_found', 'number' => (int) $number], 404);
        }

        return response()->json(['site' => 'nefis.az', 'order' => SamiraFeed::order($order, whole: true)],
            200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Names of things — a wrap, a letter — come out in the shop's own
     * language, as the owner typed and reads them. Nothing in this group sets
     * a locale, and the hosting's APP_LOCALE is not the shop's.
     */
    private function speakTheShopsLanguage(): void
    {
        app()->setLocale(Locale::DEFAULT);
    }
}
