<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * The menu line stops promising and starts pointing.
 *
 * «Xonça və nişan» has stood on the top bar with «Tezliklə» beside it and no
 * address behind it, because there was nothing to show. There are thirteen
 * designs on that shelf now.
 *
 * The badge and the link are the owner's to set, and whatever he has set is
 * his. This touches the line only while it still carries exactly what the
 * shop put there itself — the «Tezliklə» and the closed door — so a word he
 * typed, or a line he deliberately shut, is left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        $saved = json_decode((string) Setting::get(Setting::MENU), true);

        if (! is_array($saved) || ! isset($saved['xonca']) || ! is_array($saved['xonca'])) {
            return;         // never edited: the defaults already answer
        }

        $line = $saved['xonca'];
        $untouched = trim((string) ($line['badge'] ?? '')) === 'Tezliklə'
            && ! ($line['link'] ?? false);

        if (! $untouched) {
            return;
        }

        $saved['xonca']['badge'] = '';
        $saved['xonca']['link'] = true;

        Setting::put(Setting::MENU, json_encode($saved, JSON_UNESCAPED_UNICODE));
    }

    public function down(): void
    {
        // Nothing to undo: the owner's own wording was never overwritten.
    }
};
