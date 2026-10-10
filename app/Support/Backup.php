<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;
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
 */
class Backup
{
    /** Where the copies are kept: outside the web root, by construction. */
    public const FOLDER = 'private/backups';

    /** How many rows are read at a time. Enough to be quick, small enough to hold. */
    private const BATCH = 500;

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

    /**
     * Make one, and return the file it wrote.
     *
     * SQLite — which is what this runs on in development — is one file, so
     * the copy is a copy. MySQL is written out statement by statement.
     */
    public static function make(?string $stamp = null): string
    {
        $stamp ??= now()->format('Y-m-d_His');
        $driver = DB::connection()->getDriverName();

        return $driver === 'sqlite'
            ? self::copySqlite($stamp)
            : self::dumpMysql($stamp);
    }

    /** One file in, one file out. */
    private static function copySqlite(string $stamp): string
    {
        $from = (string) DB::connection()->getConfig('database');
        $to = self::directory() . '/nefis_' . $stamp . '.sqlite';

        if ($from === ':memory:' || ! is_file($from)) {
            // A database held in memory cannot be copied, so what is written
            // is what a copy is for: the data, as statements.
            return self::dumpMysql($stamp);
        }

        copy($from, $to);

        return self::finish($to);
    }

    /**
     * The whole database as SQL: every table's own CREATE, then its rows.
     *
     * Written straight to the handle rather than built in memory — a year of
     * orders with their photograph paths is not something to hold in a string
     * on a shared host.
     */
    private static function dumpMysql(string $stamp): string
    {
        $path = self::directory() . '/nefis_' . $stamp . '.sql';
        $out = fopen($path, 'w');
        $driver = DB::connection()->getDriverName();

        fwrite($out, "-- Nefis.az, " . now()->toDateTimeString() . "\n");
        fwrite($out, "-- " . $driver . ' / ' . DB::connection()->getDatabaseName() . "\n\n");

        if ($driver === 'mysql') {
            fwrite($out, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
        }

        foreach (self::tables() as $table) {
            fwrite($out, "\n-- " . $table . "\n");

            if ($create = self::createStatement($table)) {
                fwrite($out, 'DROP TABLE IF EXISTS `' . $table . "`;\n" . $create . ";\n\n");
            }

            DB::table($table)->orderByRaw('1')->chunk(self::BATCH, function ($rows) use ($out, $table) {
                $values = [];
                foreach ($rows as $row) {
                    $values[] = '(' . collect((array) $row)
                        ->map(fn ($value) => self::literal($value))
                        ->implode(',') . ')';
                }
                if ($values === []) {
                    return;
                }
                $columns = collect(array_keys((array) $rows->first()))
                    ->map(fn ($c) => '`' . $c . '`')->implode(',');
                fwrite($out, 'INSERT INTO `' . $table . '` (' . $columns . ") VALUES\n"
                    . implode(",\n", $values) . ";\n");
            });
        }

        if ($driver === 'mysql') {
            fwrite($out, "\nSET FOREIGN_KEY_CHECKS=1;\n");
        }

        fclose($out);

        return self::finish($path);
    }

    /** @return array<int, string> */
    private static function tables(): array
    {
        return collect(DB::connection()->getSchemaBuilder()->getTables())
            ->pluck('name')
            // A dump of the session table is a dump of who was signed in
            // yesterday, which is of no use to anybody restoring the shop.
            ->reject(fn (string $name) => in_array($name, ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'], true))
            ->values()
            ->all();
    }

    /** The table as the server itself states it, when the server will say. */
    private static function createStatement(string $table): ?string
    {
        $driver = DB::connection()->getDriverName();

        try {
            if ($driver === 'mysql') {
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
        if (is_int($value) || is_float($value)) {
            return (string) $value;
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
     * this over a telephone connection as often as not.
     */
    private static function finish(string $path): string
    {
        if (! function_exists('gzopen')) {
            return $path;
        }

        $zipped = $path . '.gz';
        $in = fopen($path, 'rb');
        $out = gzopen($zipped, 'wb6');

        if (! $in || ! $out) {
            return $path;
        }

        while (! feof($in)) {
            gzwrite($out, (string) fread($in, 262144));
        }

        fclose($in);
        gzclose($out);

        // Only once the squeezed one is really there.
        if (is_file($zipped) && filesize($zipped) > 0) {
            @unlink($path);

            return $zipped;
        }

        return $path;
    }

    /**
     * The copies there are, newest first.
     *
     * @return array<int, array{name: string, path: string, size: int, at: \Illuminate\Support\Carbon}>
     */
    public static function all(): array
    {
        $files = glob(self::directory() . '/nefis_*') ?: [];

        $rows = array_map(fn (string $path) => [
            'name' => basename($path),
            'path' => $path,
            'size' => (int) filesize($path),
            'at' => \Illuminate\Support\Carbon::createFromTimestamp((int) filemtime($path)),
        ], array_filter($files, 'is_file'));

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
        if (! Str::startsWith($name, 'nefis_')) {
            return null;
        }

        $path = self::directory() . '/' . $name;

        return is_file($path) ? $path : null;
    }
}
