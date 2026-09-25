<?php

namespace App\Support;

use App\Models\GiftPage;
use Illuminate\Support\Facades\Cache;

/**
 * The little pictures in the shop's pages.
 *
 * On an iPhone or a Mac they are Apple's own, which is what most customers
 * see and what the owner wants. Apple's font may not be served from a site,
 * so for Windows and the rest we load Google's coloured set instead of the
 * flat one Windows draws, asking Google Fonts for exactly the characters
 * this site prints: three dozen pictures weigh about 25 KB, the whole font
 * would be 25 MB.
 */
class Emoji
{
    /** What the pages themselves print, gathered from the views. */
    private const OWN = '⏱⏳⚙⚡✓✕✨⭐️🍫🎁🎉🎬💌💕💫💬💳📄📞📦📱📷📸🔇🔊🔒🗓🚚🛍🤎';

    /** The stylesheet for those characters, or null when there is nothing to ask for. */
    public static function stylesheet(): ?string
    {
        $chars = Cache::remember('emoji:chars', 3600, function () {
            // The owner's own gift pages carry a picture each; they are his to change.
            $own = self::OWN . GiftPage::query()->pluck('emoji')->implode('');

            $seen = [];
            foreach (preg_split('//u', $own, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
                $seen[$ch] = true;
            }
            ksort($seen);

            return implode('', array_keys($seen));
        });

        return $chars === ''
            ? null
            : 'https://fonts.googleapis.com/css2?family=Noto+Color+Emoji&text=' . rawurlencode($chars) . '&display=swap';
    }
}
