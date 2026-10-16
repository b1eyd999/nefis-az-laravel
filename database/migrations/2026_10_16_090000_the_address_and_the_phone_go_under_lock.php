<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Locks the personal lines already in the database.
 *
 * From here the models cast these columns as `encrypted`, so everything
 * written from now on is ciphertext. This walks what is already there and
 * does the same to it, so the file holds no readable address or phone number
 * at all — not in a stolen backup, not in the hosting company's own copy.
 *
 * Three things to know about it:
 *
 *  - It is idempotent. A value that already decrypts is left alone, so the
 *    migration can be run twice, or re-run after a half-finished deploy,
 *    without double-encrypting anything.
 *
 *  - The columns are widened to TEXT first. Ciphertext is roughly a third
 *    longer than what went in plus about eighty characters of envelope, and
 *    a long address in a varchar(255) would be silently cut in half on
 *    MySQL. SQLite does not care, but the schema should say what is true.
 *
 *  - It is reversible: down() walks the same columns and decrypts them.
 *
 * What is NOT here, and why: users.email and users.phone, because the shop
 * signs people in by both and the database has to match them by value;
 * users.name, contact and corporate names and numbers, and the words of a
 * review, because the owner searches the admin by all of them and SQL cannot
 * search what it cannot read.
 */
return new class extends Migration
{
    /** @var array<string, array<int, string>> */
    private const COLUMNS = [
        'orders' => ['contact_phone', 'recipient_name', 'delivery_address',
            'postal_index', 'metro_station', 'note', 'courier_name'],
        'chat_messages' => ['name', 'phone'],
        'corporate_requests' => ['person', 'note'],
        'reviews' => ['shown_name'],
        'contact_messages' => ['note'],
    ];

    public function up(): void
    {
        $this->widen();
        $this->walk(fn (string $value) => $this->locked($value) ? $value : Crypt::encryptString($value));
    }

    public function down(): void
    {
        $this->walk(fn (string $value) => $this->locked($value) ? Crypt::decryptString($value) : $value);
    }

    /** A value that is already ciphertext this key can open. */
    private function locked(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function widen(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table, $columns) {
                foreach ($columns as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        $blueprint->text($column)->nullable()->change();
                    }
                }
            });
        }
    }

    /** Runs one change over every stored value, in chunks and in a transaction. */
    private function walk(callable $change): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $present = array_values(array_filter(
                $columns,
                fn (string $c) => Schema::hasColumn($table, $c)
            ));

            if ($present === []) {
                continue;
            }

            DB::transaction(function () use ($table, $present, $change) {
                DB::table($table)->select(array_merge(['id'], $present))
                    ->orderBy('id')
                    ->chunkById(200, function ($rows) use ($table, $present, $change) {
                        foreach ($rows as $row) {
                            $write = [];
                            foreach ($present as $column) {
                                $value = $row->{$column};
                                // Nothing written stays nothing written.
                                if ($value === null || $value === '') {
                                    continue;
                                }
                                $new = $change((string) $value);
                                if ($new !== $value) {
                                    $write[$column] = $new;
                                }
                            }
                            if ($write !== []) {
                                DB::table($table)->where('id', $row->id)->update($write);
                            }
                        }
                    });
            });
        }
    }
};
