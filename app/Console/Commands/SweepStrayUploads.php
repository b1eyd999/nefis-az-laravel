<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The photographs of boxes nobody ordered.
 *
 * A customer opens a design, uploads his picture, changes his mind and
 * leaves. The picture stays on the disk for ever: nothing points at it and
 * nothing ever looked for it again. Over a year of that the hosting fills up
 * with strangers' photographs that the shop has no reason to keep and no
 * right to.
 *
 * Deleting is not undoable, so this errs twice on the side of keeping:
 * a file must be older than the window AND its name must appear nowhere in
 * any table that could point at it. The reference search reads the rows as
 * plain text rather than by column, so a path kept in some corner of a JSON
 * blob is still found.
 */
class SweepStrayUploads extends Command
{
    protected $signature = 'uploads:sweep
        {--days=30 : Leave anything touched more recently than this}
        {--dry-run : Say what would go, remove nothing}';

    protected $description = 'Delete customer photographs no order, basket or link points at';

    /** Where a customer's own uploads land while he is making a box. */
    private const FOLDERS = ['cart-photos', 'cart-videos'];

    /** Everything that could hold such a path, read as text. */
    private const TABLES = [
        'order_items', 'orders', 'order_adjustments',
        'saved_carts', 'cart_handoffs', 'live_photos',
    ];

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $dry = (bool) $this->option('dry-run');
        $cutoff = now()->subDays($days)->getTimestamp();

        $disk = Storage::disk('public');
        $spoken = $this->spokenFor();

        $gone = 0;
        $freed = 0;
        $kept = 0;

        foreach (self::FOLDERS as $folder) {
            foreach ($disk->files($folder) as $path) {
                $name = basename($path);

                if (isset($spoken[$name])) {
                    $kept++;

                    continue;
                }

                // A file still being worked on — the box is half made in a
                // basket this cannot see, because a session is not a table.
                if ($disk->lastModified($path) > $cutoff) {
                    $kept++;

                    continue;
                }

                $size = $disk->size($path);
                if (! $dry) {
                    $disk->delete($path);
                }
                $gone++;
                $freed += $size;
            }
        }

        $this->info(sprintf(
            '%s %d file(s), %s; kept %d.',
            $dry ? 'Would remove' : 'Removed',
            $gone,
            $this->inMegabytes($freed),
            $kept,
        ));

        return self::SUCCESS;
    }

    /**
     * Every file name any row mentions.
     *
     * By name rather than by full path: the same picture is written as
     * `cart-photos/x.webp` in one place and with a leading slash or a whole
     * address in another, and a name is unique enough — they are random.
     *
     * @return array<string, true>
     */
    private function spokenFor(): array
    {
        $seen = [];

        foreach (self::TABLES as $table) {
            if (! DB::getSchemaBuilder()->hasTable($table)) {
                continue;
            }

            DB::table($table)->orderBy('id')->chunk(200, function ($rows) use (&$seen) {
                foreach ($rows as $row) {
                    $text = json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    if (! is_string($text) || ! str_contains($text, 'cart-')) {
                        continue;
                    }

                    /* A JSON column arrives here as text that was encoded
                       once already, so by now its slashes carry two
                       backslashes: cart-photos\\/x.webp. Every backslash
                       goes — nothing being looked for contains one. */
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

    private function inMegabytes(int $bytes): string
    {
        return $bytes < 1048576
            ? round($bytes / 1024) . ' KB'
            : round($bytes / 1048576, 1) . ' MB';
    }
}
