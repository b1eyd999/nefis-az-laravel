<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * A copy of the database, made on the hosting the shop actually runs on.
 *
 * There is no shell here and no `mysqldump` to call: this is cPanel, the
 * repository is deployed by Git Version Control and PHP is all there is. So
 * the dump is written in PHP — the schema as the server itself states it,
 * then the rows, in batches, as INSERT statements that MySQL will read back.
 *
 * The file goes under storage/app/private, which no web address reaches, and
 * the owner downloads it through the admin. Old ones are swept so the disk
 * does not fill with a year of them.
 *
 * Four things here are not obvious and were each got wrong first:
 *
 *  - **Every table's shape is written, even when its rows are not.** The
 *    volatile tables below hold yesterday's sessions and cache and are not
 *    worth keeping — but `migrations` IS kept, and it says those tables were
 *    created. Leave their CREATE out and the restored database imports
 *    perfectly, then every page dies on "Base table 'sessions' doesn't
 *    exist", and `artisan migrate` answers "Nothing to migrate" because the
 *    restored migrations table swears it already ran. With no SSH, that is a
 *    dead shop.
 *  - **The whole read happens inside one transaction.** Without it the tables
 *    are read minutes apart, and an order written at 03:40 comes back with no
 *    items — which `SET FOREIGN_KEY_CHECKS=0` then imports without a murmur.
 *    This is what `mysqldump --single-transaction` is for.
 *  - **Rows are paged by the primary key**, never by position. `ORDER BY 1`
 *    means "the first column", which need not be unique, and paging over a
 *    non-unique order can repeat one row and lose another.
 *  - **Nothing is called a backup until it is finished.** The file is written
 *    under a `.part` name and only given its real name once the end marker is
 *    there, so a run that dies halfway cannot leave something that looks like
 *    a copy, fills one of the kept slots, and pushes a good one out.
 */
class Backup
{
    /** Where the copies are kept: outside the web root, by construction. */
    public const FOLDER = 'private/backups';

    /** The last line of a finished dump. Its absence means "unfinished". */
    public const MARKER = '-- end of dump';

    /** How many rows are read at a time. */
    private const BATCH = 500;

    /**
     * And how large one INSERT may grow, in bytes.
     *
     * A shared host's `max_allowed_packet` is often 4 MB, and 500 orders with
     * their photograph paths and captions pass that easily — the import then
     * dies on one statement in the middle of the file.
     */
    private const MOST_BYTES = 512 * 1024;

    /**
     * Tables whose rows are not worth keeping — yesterday's sessions, the
     * cache, the queue. Their SHAPE is still written: see the note above.
     */
    private const VOLATILE = ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'];

    /** How many copies are kept until the owner says otherwise. */
    public const KEEP = 14;

    public static function keep(): int
    {
        return max(1, (int) (Setting::get(Setting::BACKUP_KEEP) ?: self::KEEP));
    }

    public static function enabled(): bool
    {
        return Setting::get(Setting::BACKUP) === '1';
    }

    public static function directory(): string
    {
        $path = storage_path('app/' . self::FOLDER);
        if (! is_dir($path)) {
            @mkdir($path, 0755, true);
        }

        return $path;
    }

    /** MySQL and MariaDB answer the same questions the same way. */
    private static function mysqlish(string $driver): bool
    {
        return in_array($driver, ['mysql', 'mariadb'], true);
    }

    /**
     * Make one, and return the file it wrote.
     *
     * SQLite — which is what this runs on in development — is one file, so
     * the copy is a copy. MySQL is written out statement by statement.
     */
    public static function make(?string $stamp = null): string
    {
        $stamp ??= now()->format('Y-m-d_His');

        return DB::connection()->getDriverName() === 'sqlite'
            ? self::copySqlite($stamp)
            : self::dump($stamp);
    }

    /**
     * One file in, one file out.
     *
     * Asked of SQLite itself rather than taken with copy(): `VACUUM INTO`
     * reads the database under its own lock and writes a settled copy, so a
     * checkout committing at 03:40 cannot be caught half-written. A plain
     * copy() of a live file can take pages from before and after the same
     * transaction and leave a copy that only looks fine — and the journal
     * that would have undone it is not copied with it.
     *
     * Older SQLite builds have no VACUUM INTO, and a hosting account can
     * refuse it; a copy taken the old way is still far better than none.
     */
    private static function copySqlite(string $stamp): string
    {
        $from = (string) DB::connection()->getConfig('database');

        if ($from === ':memory:' || ! is_file($from)) {
            // A database held in memory cannot be copied, so what is written
            // is what a copy is for: the data, as statements.
            return self::dump($stamp);
        }

        $to = self::directory() . '/nefis_' . $stamp . '.sqlite';
        $part = $to . '.part';
        @unlink($part);

        try {
            DB::statement('VACUUM INTO ?', [$part]);
        } catch (\Throwable $e) {
            @unlink($part);
            Log::warning('VACUUM INTO alınmadı, adi kopya götürülür: ' . $e->getMessage());
            if (! copy($from, $part)) {
                throw new \RuntimeException('Could not copy ' . $from);
            }
        }

        if (! is_file($part) || filesize($part) === 0) {
            @unlink($part);
            throw new \RuntimeException('The copy came out empty');
        }

        rename($part, $to);

        return self::finish($to);
    }

    /**
     * The whole database as SQL: every table's own CREATE, then its rows.
     *
     * Written straight to the handle rather than built in memory — a year of
     * orders with their photograph paths is not something to hold in a string
     * on a shared host.
     */
    private static function dump(string $stamp): string
    {
        $path = self::directory() . '/nefis_' . $stamp . '.sql';
        $part = $path . '.part';
        $driver = DB::connection()->getDriverName();

        $out = fopen($part, 'w');
        if ($out === false) {
            throw new \RuntimeException('Could not open ' . $part . ' for writing');
        }

        /* One snapshot for the whole dump. Read-only, so it is rolled back
           rather than committed; on InnoDB's REPEATABLE READ that is what
           makes an order and its items come from the same instant. */
        DB::beginTransaction();

        try {
            self::put($out, '-- Nefis.az, ' . now()->toDateTimeString() . "\n");
            self::put($out, '-- ' . $driver . ' / ' . DB::connection()->getDatabaseName() . "\n\n");

            if (self::mysqlish($driver)) {
                self::put($out, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");
            }

            foreach (self::tables() as $table) {
                self::put($out, "\n-- " . $table . "\n");

                if ($create = self::createStatement($table)) {
                    self::put($out, 'DROP TABLE IF EXISTS `' . $table . "`;\n" . $create . ";\n\n");
                }

                // The shape of these is worth keeping; what is in them today
                // is not.
                if (in_array($table, self::VOLATILE, true)) {
                    self::put($out, "-- (rows deliberately not kept)\n");

                    continue;
                }

                self::rows($out, $table);
            }

            if (self::mysqlish($driver)) {
                self::put($out, "\nSET FOREIGN_KEY_CHECKS=1;\n");
            }

            self::put($out, "\n" . self::MARKER . "\n");
        } catch (\Throwable $e) {
            DB::rollBack();
            fclose($out);
            // Nothing half-written is left lying about to be mistaken for a
            // copy, or to fill one of the kept slots.
            @unlink($part);

            throw $e;
        }

        DB::rollBack();

        if (! fclose($out)) {
            @unlink($part);
            throw new \RuntimeException('Could not close ' . $part);
        }

        // Only now is it a backup.
        rename($part, $path);

        return self::finish($path);
    }

    /**
     * One table's rows, as INSERT statements.
     *
     * Paged by the primary key where there is one; where there is not, read
     * in a single streaming query rather than by position — paging over a
     * column that repeats can return one row twice and never return another.
     *
     * @param  resource  $out
     */
    private static function rows($out, string $table): void
    {
        $key = self::primaryKey($table);
        $batch = [];
        $bytes = 0;
        $columns = null;

        $write = function () use (&$batch, &$bytes, &$columns, $out, $table) {
            if ($batch === []) {
                return;
            }
            self::put($out, 'INSERT INTO `' . $table . '` (' . $columns . ") VALUES\n"
                . implode(",\n", $batch) . ";\n");
            $batch = [];
            $bytes = 0;
        };

        $take = function (object $row) use (&$batch, &$bytes, &$columns, $write) {
            $fields = (array) $row;
            $columns ??= collect(array_keys($fields))->map(fn ($c) => '`' . $c . '`')->implode(',');

            $line = '(' . collect($fields)->map(fn ($v) => self::literal($v))->implode(',') . ')';
            $batch[] = $line;
            $bytes += strlen($line) + 2;

            // Capped by size as well as by count: a shared host's
            // max_allowed_packet is often 4 MB, and one statement over it
            // kills the import in the middle of the file.
            if (count($batch) >= self::BATCH || $bytes >= self::MOST_BYTES) {
                $write();
            }
        };

        if ($key !== null) {
            DB::table($table)->orderBy($key)->chunk(self::BATCH, function ($rows) use ($take) {
                foreach ($rows as $row) {
                    $take($row);
                }
            });
        } else {
            foreach (DB::table($table)->cursor() as $row) {
                $take($row);
            }
        }

        $write();
    }

    /** @param resource $out */
    private static function put($out, string $text): void
    {
        $written = fwrite($out, $text);

        // A short write is a full disk, and a dump with a hole in it is worse
        // than no dump: it would be restored and trusted.
        if ($written === false || $written !== strlen($text)) {
            throw new \RuntimeException('Short write — the disk is probably full');
        }
    }

    /** The single column that identifies a row, when there is one. */
    private static function primaryKey(string $table): ?string
    {
        try {
            foreach (DB::connection()->getSchemaBuilder()->getIndexes($table) as $index) {
                if (($index['primary'] ?? false) && count($index['columns'] ?? []) === 1) {
                    return (string) $index['columns'][0];
                }
            }
        } catch (\Throwable) {
            // A server that will not describe its own indexes still gives up
            // its rows; they are then read in one streaming query.
        }

        return null;
    }

    /** @return array<int, string> */
    private static function tables(): array
    {
        return collect(DB::connection()->getSchemaBuilder()->getTables())
            ->pluck('name')
            ->values()
            ->all();
    }

    /** The table as the server itself states it, when the server will say. */
    private static function createStatement(string $table): ?string
    {
        $driver = DB::connection()->getDriverName();

        try {
            if (self::mysqlish($driver)) {
                $row = (array) DB::selectOne('SHOW CREATE TABLE `' . $table . '`');

                return $row['Create Table'] ?? null;
            }

            if ($driver === 'sqlite') {
                $row = DB::selectOne('select sql from sqlite_master where type = ? and name = ?', ['table', $table]);

                return $row?->sql;
            }
        } catch (\Throwable) {
            // A server that will not describe its own table still gives up
            // its rows, and rows are what is worth having.
        }

        return null;
    }

    /** One value, written so the server reads back exactly what came out. */
    private static function literal(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            // Enough digits to come back as the same double, and never in
            // the exponent form or a comma-decimal locale.
            return rtrim(rtrim(number_format($value, 10, '.', ''), '0'), '.') ?: '0';
        }

        $text = (string) $value;

        /* Anything that is not text goes in as hex. A photograph or a key
           written as a quoted string comes back broken, and one broken byte
           in the middle of a dump stops the whole restore. */
        if (! mb_check_encoding($text, 'UTF-8')) {
            return '0x' . bin2hex($text);
        }

        return "'" . str_replace(
            ['\\', "'", "\n", "\r", "\0", "\x1a"],
            ['\\\\', "\\'", '\\n', '\\r', '\\0', '\\Z'],
            $text
        ) . "'";
    }

    /**
     * Squeeze it, if the server can, and drop the loose copy.
     *
     * A dump is mostly repeated words — table names, column names, the same
     * dates — so gzip takes a great deal off it, and the owner is downloading
     * this over a telephone connection as often as not. The squeezed file is
     * read back before the plain one is deleted: a gzip that ran out of disk
     * half way would otherwise replace a good dump with a broken one.
     */
    private static function finish(string $path): string
    {
        if (! function_exists('gzopen')) {
            return $path;
        }

        $zipped = $path . '.gz';
        $part = $zipped . '.part';
        $in = fopen($path, 'rb');
        $out = gzopen($part, 'wb6');

        if (! $in || ! $out) {
            if ($in) {
                fclose($in);
            }
            @unlink($part);

            return $path;
        }

        $ok = true;
        while (! feof($in)) {
            $piece = (string) fread($in, 262144);
            if ($piece !== '' && gzwrite($out, $piece) !== strlen($piece)) {
                $ok = false;
                break;
            }
        }

        fclose($in);
        gzclose($out);

        // Read it back before anything is thrown away.
        if ($ok && is_file($part) && filesize($part) > 0 && self::readable($part, filesize($path))) {
            rename($part, $zipped);
            @unlink($path);

            return $zipped;
        }

        @unlink($part);

        return $path;
    }

    /** Does the squeezed file really unpack to the whole dump again? */
    private static function readable(string $zipped, int $expected): bool
    {
        $handle = gzopen($zipped, 'rb');
        if (! $handle) {
            return false;
        }

        $seen = 0;
        while (! gzeof($handle)) {
            $piece = gzread($handle, 262144);
            if ($piece === false) {
                gzclose($handle);

                return false;
            }
            $seen += strlen($piece);
        }
        gzclose($handle);

        return $seen === $expected;
    }

    /**
     * Whether a copy is whole.
     *
     * A dump says so itself with its last line; a copied SQLite file says so
     * by being a SQLite file at all. Anything else is something that was
     * being written when the lights went out.
     *
     * The `.gz` is taken off before deciding which of the two this is, and
     * both ends are read through the squeezing. Without that, every nightly
     * copy — which is a SQLite file AND squeezed — failed both tests at
     * once: it does not end in `.sqlite`, and a database has no last line of
     * a dump in it. `db:backup` deleted the file it had just written, every
     * night, and the shop kept only the copies made by hand.
     */
    public static function whole(string $path): bool
    {
        if (! is_file($path) || filesize($path) === 0) {
            return false;
        }

        $kind = str_ends_with($path, '.gz') ? substr($path, 0, -3) : $path;

        if (str_ends_with($kind, '.sqlite')) {
            return str_starts_with(self::head($path, 16), 'SQLite format 3');
        }

        return str_contains(self::tail($path, 256), self::MARKER);
    }

    /** The start of a file, squeezed or not. */
    private static function head(string $path, int $bytes): string
    {
        if (! str_ends_with($path, '.gz')) {
            return (string) file_get_contents($path, false, null, 0, $bytes);
        }

        $handle = gzopen($path, 'rb');
        if (! $handle) {
            return '';
        }
        $seen = (string) gzread($handle, $bytes);
        gzclose($handle);

        return $seen;
    }

    /** The end of a file, squeezed or not. */
    private static function tail(string $path, int $bytes): string
    {
        if (str_ends_with($path, '.gz')) {
            $handle = gzopen($path, 'rb');
            if (! $handle) {
                return '';
            }
            $seen = '';
            while (! gzeof($handle)) {
                $seen = substr($seen . gzread($handle, 262144), -$bytes);
            }
            gzclose($handle);

            return $seen;
        }

        $size = (int) filesize($path);
        $from = max(0, $size - $bytes);

        return (string) file_get_contents($path, false, null, $from, $bytes);
    }

    /**
     * The copies there are, newest first.
     *
     * Half-written files are not among them: a `.part` is not a backup, and
     * counting one would let it fill a kept slot and push a good copy out.
     *
     * @return array<int, array{name: string, path: string, size: int, at: \Illuminate\Support\Carbon}>
     */
    public static function all(): array
    {
        $files = glob(self::directory() . '/nefis_*') ?: [];

        $rows = [];
        foreach ($files as $path) {
            if (! is_file($path) || str_ends_with($path, '.part')) {
                continue;
            }
            $rows[] = [
                'name' => basename($path),
                'path' => $path,
                'size' => (int) filesize($path),
                /* In the shop's own hours. Carbon 3 reads a bare timestamp
                   as UTC, so every copy was listed four hours early: one
                   written at 03:40 on the 20th appeared as «19.10 23:40»,
                   which reads as a copy that was never taken that night.
                   The morning note measures the newest copy's age off this
                   too. */
                'at' => \Illuminate\Support\Carbon::createFromTimestamp((int) filemtime($path), config('app.timezone')),
            ];
        }

        /* By name, not by the file's own time: the name carries the stamp and
           sorts the same way, and two copies written inside one second share
           a modification time — which made "newest first" a coin toss and
           the sweep below throw away whichever it felt like. */
        usort($rows, fn (array $a, array $b) => strcmp($b['name'], $a['name']));

        return $rows;
    }

    /** Older copies, swept so a year of them does not fill the disk. */
    public static function sweep(?int $keep = null): int
    {
        $keep = $keep ?? self::keep();
        $gone = 0;

        foreach (array_slice(self::all(), $keep) as $old) {
            if (@unlink($old['path'])) {
                $gone++;
            }
        }

        return $gone;
    }

    /** One copy, by name — and only one that is really ours. */
    public static function find(string $name): ?string
    {
        // The name comes off a page, so it is read as a name and nothing
        // else: no folders, no going up, and it must be one of ours.
        $name = basename($name);
        if (! Str::startsWith($name, 'nefis_') || str_ends_with($name, '.part')) {
            return null;
        }

        $path = self::directory() . '/' . $name;

        return is_file($path) ? $path : null;
    }
}
