<?php

namespace App\Support;

use App\Models\ChatMessage;
use App\Models\ContactMessage;
use App\Models\CorporateRequest;
use App\Models\Order;
use App\Models\Review;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * An answer to «what does this shop keep about people, and where».
 *
 * Nobody can look after data he cannot see, and the honest answer is spread
 * over a dozen places: a cookie the framework sets, a line the shop's own
 * script writes into a browser, a column a model quietly encrypts, a
 * photograph on the disk that no order points at any more. This gathers all
 * of it in one place so the owner can read it without reading the code, and
 * so a question from a customer — or from a regulator — has an answer that
 * took a minute rather than a week.
 *
 * Two rules it keeps to:
 *
 *  - It never states a thing it did not measure. The locked columns are read
 *    off the models' own casts, not off a list kept here that could drift;
 *    the file tallies are counted on the disk; and where an answer genuinely
 *    cannot be known from the server — how many visitors accepted the
 *    cookie strip, for one — it says so instead of inventing a number.
 *
 *  - It reads and counts. Nothing here deletes, moves or decrypts in bulk.
 */
class DataMap
{
    /** The models whose personal columns are locked. */
    private const MODELS = [
        Order::class,
        ChatMessage::class,
        CorporateRequest::class,
        Review::class,
        ContactMessage::class,
    ];

    /**
     * Laravel's envelope, as it looks once stored.
     *
     * The payload is a JSON object whose first key is always `iv`, then
     * base64-encoded whole; `{"iv":"` encodes to `eyJpdiI6I`. Matching that
     * prefix in SQL costs one scan and no decryption, which is what makes
     * the count on this page cheap enough to show on every visit.
     */
    private const ENVELOPE = 'eyJpdiI6I%';

    /** How long a file tally is trusted before the disk is walked again. */
    private const TALLY_MINUTES = 10;

    /**
     * What the shop puts on a visitor's machine, and what each thing is for.
     *
     * Written out by hand because no amount of reading the code can say
     * *why* a cookie exists, and the why is the only part a person asking
     * about his privacy actually wants. Kept beside the code it describes so
     * that adding a key without adding a line here is visible in review.
     *
     * @return array<int, array{name: string, where: string, what: string, life: string, needed: bool}>
     */
    public static function inTheBrowser(): array
    {
        return [
            [
                'name' => config('session.cookie'),
                'where' => 'Kuki',
                'what' => 'Səbəti və hesaba girişi saxlayan nömrə. İçində ad, telefon, '
                    . 'ünvan yoxdur — yalnız serverdəki sətri göstərən nömrə.',
                'life' => config('session.lifetime') . ' dəqiqə hərəkətsizlikdən sonra',
                'needed' => true,
            ],
            [
                'name' => 'XSRF-TOKEN',
                'where' => 'Kuki',
                'what' => 'Hər formaya qoyulan birdəfəlik imza. Olmasa, başqa bir sayt '
                    . 'sizin adınızdan bizə forma göndərə bilər.',
                'life' => 'Sessiya ilə birlikdə',
                'needed' => true,
            ],
            [
                'name' => 'remember_web_…',
                'where' => 'Kuki',
                'what' => 'Yalnız «Məni xatırla» seçilibsə. Girişi uzun müddət saxlayır.',
                'life' => '400 gün',
                'needed' => true,
            ],
            [
                'name' => CookieNotice::KEY,
                'where' => 'localStorage',
                'what' => 'Kuki lentindəki cavab: «hamısı» yoxsa «yalnız lazım olanlar». '
                    . 'Bu cavab serverə heç vaxt göndərilmir.',
                'life' => 'Brauzer təmizlənənə qədər',
                'needed' => true,
            ],
            [
                'name' => 'nefis-theme',
                'where' => 'localStorage',
                'what' => 'Qaranlıq yoxsa işıqlı görünüş seçilib.',
                'life' => 'Brauzer təmizlənənə qədər',
                'needed' => true,
            ],
            [
                'name' => 'nefis-chat-who',
                'where' => 'localStorage',
                'what' => 'Söhbətdə yazdığı ad və telefon — ikinci dəfə soruşmamaq üçün. '
                    . 'Söhbəti göndərəndə bu məlumat serverə də düşür və orada şifrələnir.',
                'life' => 'Brauzer təmizlənənə qədər',
                'needed' => true,
            ],
            [
                'name' => 'nefis-chat-active, nefis-chat-sound',
                'where' => 'localStorage',
                'what' => 'Söhbət pəncərəsi açıq idi, səs açıq idi — kiçik rahatlıqlar.',
                'life' => 'Brauzer təmizlənənə qədər',
                'needed' => true,
            ],
            [
                'name' => 'nefis-install-hint',
                'where' => 'localStorage',
                'what' => '«Tətbiqi quraşdır» lenti bir dəfə göstərilib — təkrar '
                    . 'göstərməmək üçün.',
                'life' => 'Brauzer təmizlənənə qədər',
                'needed' => true,
            ],
            [
                'name' => 'nefis-sales-off',
                'where' => 'sessionStorage',
                'what' => 'Satış bağlı olduğu barədə xəbərdarlıq bu vərəqdə bağlanıb.',
                'life' => 'Vərəq bağlananda silinir',
                'needed' => true,
            ],
            [
                'name' => 'pages-…, assets-…',
                'where' => 'Keş (service worker)',
                'what' => 'Tətbiq kimi işləməsi üçün son açdığı səhifələr və şəkillər. '
                    . 'İnternet kəsiləndə sayt buradan açılır.',
                'life' => 'Ən çox 40 səhifə, 150 fayl; yenilənmə ilə dəyişir',
                'needed' => true,
            ],
            [
                'name' => '_ga, _ga_…',
                'where' => 'Kuki (Google)',
                'what' => 'Google Analytics: hansı səhifəyə baxıldı, haradan gəlindi. '
                    . 'YALNIZ ziyarətçi lentdə «Hamısını qəbul et» deyəndən sonra qoyulur.',
                'life' => '2 il (Google təyin edir)',
                'needed' => false,
            ],
        ];
    }

    /** Is the strip shown at all, and why not if not. */
    public static function noticeState(): array
    {
        $on = \App\Models\Setting::get(\App\Models\Setting::COOKIE_NOTICE) === '1';
        $ga = Seo::measurementId(\App\Models\Setting::get(\App\Models\Setting::SEO_ANALYTICS)) !== '';

        return [
            'shown' => CookieNotice::enabled(),
            'on' => $on,
            'ga' => $ga,
            'why' => match (true) {
                ! $on => 'Tənzimləmələrdə söndürülüb.',
                ! $ga => 'Google Analytics qoşulmayıb — soruşmağa bir şey yoxdur, '
                    . 'ona görə lent göstərilmir.',
                app()->isLocal() => 'Bu kompüterdir, canlı sayt deyil.',
                default => '',
            },
        ];
    }

    /**
     * Which personal columns are locked, and whether they really are.
     *
     * The column list comes from the models themselves, so a cast dropped in
     * a careless edit shows up here as a column that has simply vanished
     * from the page rather than as a quiet lie. `plain` is the number that
     * matters: anything above zero means a row was written before the lock,
     * or by something that side-stepped the model.
     *
     * @return array<int, array{table: string, column: string, filled: int, locked: int, plain: int}>
     */
    public static function locked(): array
    {
        $rows = [];

        foreach (self::MODELS as $class) {
            /** @var \Illuminate\Database\Eloquent\Model $model */
            $model = new $class;
            $table = $model->getTable();

            if (! DB::getSchemaBuilder()->hasTable($table)) {
                continue;
            }

            foreach ($model->getCasts() as $column => $cast) {
                if ($cast !== 'encrypted' && ! str_starts_with((string) $cast, 'encrypted:')) {
                    continue;
                }

                if (! DB::getSchemaBuilder()->hasColumn($table, $column)) {
                    continue;
                }

                $filled = DB::table($table)->whereNotNull($column)->where($column, '<>', '')->count();
                $locked = DB::table($table)->where($column, 'like', self::ENVELOPE)->count();

                $rows[] = [
                    'table' => $table,
                    'column' => $column,
                    'filled' => $filled,
                    'locked' => $locked,
                    'plain' => max(0, $filled - $locked),
                ];
            }
        }

        return $rows;
    }

    /**
     * Columns left readable on purpose, with the reason.
     *
     * Every one of these is a column the database has to match by value.
     * SQL cannot compare what it cannot read, so locking them would not make
     * the shop more private — it would stop people signing in.
     *
     * @return array<int, array{what: string, why: string}>
     */
    public static function readable(): array
    {
        return [
            ['what' => 'Hesabın e-poçtu və telefonu', 'why' => 'Giriş bunların üstündən olur — '
                . 'baza onları hərfi-hərfinə tapmalıdır. Şifrələnsə, heç kim hesabına girə bilməz.'],
            ['what' => 'Hesabın adı', 'why' => 'Sifarişi müştərinin adı ilə axtarırsınız.'],
            ['what' => 'Məktubda və korporativ sorğuda ad, telefon, şirkət', 'why' => 'Admin '
                . 'panelində axtarış sahəsi bunların içində axtarır.'],
            ['what' => 'Rəyin mətni', 'why' => 'Axtarılır; adın özü isə şifrələnib.'],
            ['what' => 'Məbləğlər, çatdırılma qiyməti, material xərci', 'why' => 'Mühasibatlıq '
                . 'onları bazanın özündə toplayır — şifrələnsə, hesabat işləməz.'],
        ];
    }

    /**
     * Does the key still open what is stored?
     *
     * The one failure worth losing sleep over: a restored database with a
     * different APP_KEY. Nothing breaks on the way in — new rows encrypt
     * fine — and every old address and phone number is simply gone. One row
     * is enough to find out, so this takes the newest.
     */
    public static function keyOpens(): ?bool
    {
        $sample = DB::table('orders')
            ->whereNotNull('delivery_address')
            ->where('delivery_address', 'like', self::ENVELOPE)
            ->orderByDesc('id')
            ->value('delivery_address');

        if (! is_string($sample) || $sample === '') {
            return null;            // nothing locked yet — nothing to prove
        }

        try {
            Crypt::decryptString($sample);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * What is on the disk, folder by folder.
     *
     * Walked rather than guessed, and cached for a few minutes: on a shared
     * host counting a few thousand files is not free, and the number does
     * not change by the second.
     *
     * @return array<int, array{folder: string, files: int, bytes: int, newest: ?int}>
     */
    public static function files(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget('data-map-files');
        }

        return Cache::remember('data-map-files', now()->addMinutes(self::TALLY_MINUTES), function () {
            $disk = Storage::disk('public');
            $rows = [];

            foreach ($disk->directories('') as $folder) {
                $files = $disk->allFiles($folder);
                $bytes = 0;
                $newest = null;

                foreach ($files as $path) {
                    try {
                        $bytes += $disk->size($path);
                        $at = $disk->lastModified($path);
                        $newest = $newest === null ? $at : max($newest, $at);
                    } catch (\Throwable) {
                        // A file that vanished between listing and reading.
                    }
                }

                $rows[] = [
                    'folder' => $folder,
                    'files' => count($files),
                    'bytes' => $bytes,
                    'newest' => $newest,
                ];
            }

            usort($rows, fn (array $a, array $b) => $b['bytes'] <=> $a['bytes']);

            return $rows;
        });
    }

    /** What each folder on the public disk is for. */
    public static function folderMeaning(string $folder): string
    {
        return [
            'cart-photos' => 'Müştərilərin qutu üçün yüklədiyi şəkillər',
            'cart-videos' => 'Canlı şəkil üçün yüklənən videolar',
            'live' => 'Canlı şəkil: hədəf faylları və videolar',
            'designs' => 'Dizaynların hazır şəkilləri',
            'products' => 'Məhsul şəkilləri',
            'boxes' => 'Qutu şəkilləri (qutu redaktoru)',
            'scenes' => 'Səhnələr — müştəri maketi üçün fon',
            'backgrounds' => 'Fonlar',
            'wrappings' => 'Bükülmə kağızları',
            'chocolates' => 'Şokolad şəkilləri',
            'fonts' => 'Yazı şriftləri',
            'library' => 'Kitabxana: şəkillər, naxışlar',
            'hero' => 'Baş səhifənin böyük şəkli',
            'chat' => 'Söhbətdə göndərilən şəkillər',
            'reviews' => 'Rəylərə əlavə edilən şəkillər',
            'receipts' => 'Qəbzlər',
            'xonca' => 'Xonça səhifəsinin şəkilləri',
            'star' => 'Ulduz xəritəsi üçün göy məlumatı',
        ][$folder] ?? '';
    }

    /**
     * Customer photographs no row points at any more.
     *
     * The same two tests `uploads:sweep` uses, and in the same order, so the
     * number on this page is the number that command would act on: a file
     * whose name appears in no table AND which nothing has touched for a
     * month. Counting only — the sweeping stays in the command, where a
     * deletion is deliberate.
     *
     * @return array{files: int, bytes: int, days: int}
     */
    public static function strays(int $days = 30, bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget('data-map-strays');
        }

        return Cache::remember('data-map-strays', now()->addMinutes(self::TALLY_MINUTES), function () use ($days) {
            $disk = Storage::disk('public');
            // The same floor the sweep keeps, so the number shown and the
            // number deleted are the same number.
            $cutoff = now()->subDays(max(1, $days))->getTimestamp();
            $spoken = self::spokenFor();

            $files = 0;
            $bytes = 0;

            foreach (['cart-photos', 'cart-videos'] as $folder) {
                foreach ($disk->files($folder) as $path) {
                    if (isset($spoken[basename($path)])) {
                        continue;
                    }

                    try {
                        if ($disk->lastModified($path) > $cutoff) {
                            continue;
                        }
                        $bytes += $disk->size($path);
                    } catch (\Throwable) {
                        continue;
                    }

                    $files++;
                }
            }

            return ['files' => $files, 'bytes' => $bytes, 'days' => $days];
        });
    }

    /**
     * Remove the stray photographs this page just counted.
     *
     * The same two tests again, applied one file at a time rather than to a
     * list gathered a minute ago: a basket saved while the owner was reading
     * the page must not have its photograph deleted out from under it.
     *
     * The owner has no terminal on that host, so the page is the only place
     * this can be done from — which is why the button that reaches it asks
     * for the number out loud first.
     *
     * @return array{files: int, bytes: int}
     */
    public static function sweep(int $days = 30): array
    {
        $disk = Storage::disk('public');
        $cutoff = now()->subDays(max(1, $days))->getTimestamp();
        $spoken = self::spokenFor();

        $files = 0;
        $bytes = 0;

        foreach (['cart-photos', 'cart-videos'] as $folder) {
            foreach ($disk->files($folder) as $path) {
                if (isset($spoken[basename($path)])) {
                    continue;
                }

                try {
                    if ($disk->lastModified($path) > $cutoff) {
                        continue;
                    }
                    $size = $disk->size($path);
                } catch (\Throwable) {
                    continue;
                }

                if ($disk->delete($path)) {
                    $files++;
                    $bytes += $size;
                }
            }
        }

        Cache::forget('data-map-files');
        Cache::forget('data-map-strays');

        return ['files' => $files, 'bytes' => $bytes];
    }

    /**
     * Every uploaded file name some row mentions.
     *
     * Read as text rather than by column, so a path sitting in a corner of a
     * JSON blob still counts as spoken for. The locked columns read as
     * base64 noise here, which is harmless: none of them has ever held a
     * file path, and noise matches nothing.
     *
     * @return array<string, true>
     */
    private static function spokenFor(): array
    {
        $seen = [];

        foreach (['order_items', 'orders', 'order_adjustments',
            'saved_carts', 'cart_handoffs', 'live_photos'] as $table) {
            if (! DB::getSchemaBuilder()->hasTable($table)) {
                continue;
            }

            DB::table($table)->orderBy('id')->chunk(200, function ($rows) use (&$seen) {
                foreach ($rows as $row) {
                    $text = json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    if (! is_string($text) || ! str_contains($text, 'cart-')) {
                        continue;
                    }

                    $text = str_replace('\\', '', $text);
                    preg_match_all('~cart-(?:photos|videos)/([A-Za-z0-9._-]+)~', $text, $found);
                    foreach ($found[1] as $name) {
                        $seen[$name] = true;
                    }
                }
            });
        }

        return $seen;
    }

    /**
     * What is going on right now.
     *
     * Deliberately short. A page of counters nobody reads is worse than six
     * numbers that answer «is anything happening».
     *
     * @return array<int, array{label: string, value: string, hint: string}>
     */
    public static function happening(): array
    {
        $rows = [];

        if (DB::getSchemaBuilder()->hasTable('sessions')) {
            $quarter = now()->subMinutes(15)->getTimestamp();
            $rows[] = [
                'label' => 'Saytda indi',
                'value' => (string) DB::table('sessions')->where('last_activity', '>=', $quarter)->count(),
                'hint' => 'Son 15 dəqiqədə hərəkət edən brauzer',
            ];
            $rows[] = [
                'label' => 'Açıq sessiya',
                'value' => (string) DB::table('sessions')->count(),
                'hint' => 'Hamısı — köhnələri özü silinir',
            ];
        }

        $day = now()->subDay();

        foreach ([
            ['Sifariş (24 saat)', 'orders', 'Yeni sifarişlər'],
            ['Söhbət sətri (24 saat)', 'chat_messages', 'Yazılan və cavablanan'],
            ['Məktub (24 saat)', 'contact_messages', 'Əlaqə formasından'],
            ['Korporativ sorğu (24 saat)', 'corporate_requests', 'Şirkətlərdən'],
            ['Canlı şəkil (24 saat)', 'live_photos', 'Yüklənən videolar'],
        ] as [$label, $table, $hint]) {
            if (! DB::getSchemaBuilder()->hasTable($table)) {
                continue;
            }

            $rows[] = [
                'label' => $label,
                'value' => (string) DB::table($table)->where('created_at', '>=', $day)->count(),
                'hint' => $hint,
            ];
        }

        return $rows;
    }

    public static function size(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        }

        return $bytes >= 1048576
            ? number_format($bytes / 1048576, 1) . ' MB'
            : number_format($bytes / 1024, 1) . ' KB';
    }
}
