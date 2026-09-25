<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The owner does not want the long dash in the shop's own sentences, so it
 * leaves the texts that are already written: a dash joining two halves of a
 * sentence becomes a comma, a dash after a comma or colon simply goes, and a
 * time range keeps a short dash (10:00–14:00) because that is not punctuation.
 *
 * What a customer typed himself — an order's note, the words on his box — is
 * his, and is left exactly as he wrote it.
 */
return new class extends Migration
{
    /** The shop's own words, table by table. */
    private const FIELDS = [
        'gift_pages' => ['menu_label', 'link_text', 'title', 'meta_title', 'meta_description', 'eyebrow', 'intro', 'body', 'faq'],
        'hero_slides' => ['eyebrow', 'title', 'text', 'button1_label', 'button2_label', 'badges', 'i18n'],
        'products' => ['name', 'description', 'tag', 'i18n'],
        'delivery_methods' => ['name', 'description', 'i18n'],
        'wrappings' => ['name', 'i18n'],
        'live_photos' => ['title'],
    ];

    public function up(): void
    {
        foreach (self::FIELDS as $table => $fields) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $fields = array_values(array_filter($fields, fn ($f) => Schema::hasColumn($table, $f)));

            DB::table($table)->select(array_merge(['id'], $fields))->orderBy('id')->chunk(200, function ($rows) use ($table, $fields) {
                foreach ($rows as $row) {
                    $changes = [];
                    foreach ($fields as $field) {
                        $was = $row->{$field};
                        $now = self::clean($was);
                        if ($now !== $was) {
                            $changes[$field] = $now;
                        }
                    }
                    if ($changes) {
                        DB::table($table)->where('id', $row->id)->update($changes);
                    }
                }
            });
        }

        // The settings the owner writes for customers; the rest are numbers and keys.
        foreach (['payment_note', 'maintenance_message', 'contact_hours', 'delivery_slots', 'letter_page', 'chocolate_top_brands'] as $key) {
            // Forgotten either way: a setting the owner never touched is read
            // from the code's own defaults, and those have changed too.
            Cache::forget('setting:' . $key);

            $row = DB::table('settings')->where('key', $key)->first();
            if (! $row) {
                continue;
            }
            $now = self::clean($row->value);
            if ($now !== $row->value) {
                DB::table('settings')->where('key', $key)->update(['value' => $now]);
            }
        }

        // Orders already placed keep their hours readable next to the new ones.
        if (Schema::hasColumn('orders', 'delivery_slot')) {
            foreach (DB::table('orders')->whereNotNull('delivery_slot')->get(['id', 'delivery_slot']) as $order) {
                $now = self::clean($order->delivery_slot);
                if ($now !== $order->delivery_slot) {
                    DB::table('orders')->where('id', $order->id)->update(['delivery_slot' => $now]);
                }
            }
        }
    }

    public function down(): void
    {
        // The old wording is not kept: putting dashes back would be guesswork.
    }

    private static function clean(mixed $text): mixed
    {
        if (! is_string($text) || ! str_contains($text, '—')) {
            return $text;
        }

        // A range of hours is not a sentence: it keeps a dash, the short one.
        $text = preg_replace('/(\d{1,2}:\d{2})\s*—\s*(\d{1,2}:\d{2})/u', '$1–$2', $text);
        // After a comma, a colon or a bracket the dash has nothing left to do.
        $text = preg_replace('/(?<=[:,;(])\s+—\s+/u', ' ', $text);
        // Between two words it becomes a comma.
        return preg_replace('/(?<=[^\s\-—:,;(])\s+—\s+(?=[^\s—])/u', ', ', $text);
    }
};
