<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * The Apple file's visitor list starts empty of my own checks.
 *
 * Verifying the deployed file meant fetching it, and one of those fetches
 * carried a hand-written Apple-looking user agent to prove nothing on the
 * hosting blocks such a request. The route had just started recording
 * visits, so those lines are now in the list — shown in green, next to a
 * tick, as though Apple's verifier had come. It had not.
 *
 * Only the exact user agent I sent is removed. Apple's own verifier does
 * not name itself with that string, so a real visit cannot be swept away
 * with it.
 */
return new class extends Migration
{
    private const MINE = 'Mozilla/5.0 (Macintosh) AppleWebKit/605.1.15 ApplePay/1.0';

    public function up(): void
    {
        $seen = array_filter((array) json_decode((string) Setting::get(Setting::APPLE_PAY_FETCHED), true), 'is_array');

        $kept = array_values(array_filter(
            $seen,
            fn (array $visit) => ($visit['ua'] ?? null) !== self::MINE,
        ));

        if ($kept !== $seen) {
            Setting::put(Setting::APPLE_PAY_FETCHED, json_encode($kept, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
    }

    public function down(): void
    {
        // There is nothing to put back: these were my requests, not anyone's.
    }
};
