<?php

namespace App\Filament\Pages;

use Filament\Actions;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

/**
 * The last things that went wrong, on a screen the owner can reach.
 *
 * There is no terminal on that host. Until now a «500 Server Error» in front
 * of him meant a screenshot and a guess from here: the one record of what
 * actually threw sat in storage/logs/laravel.log, which nothing in the shop
 * could read.
 *
 * Only the tail of it: the file grows into megabytes, and the whole of it
 * would neither fit on a page nor be worth reading. Only faults, too — the
 * file carries a line from every payment and every message sent, and the one
 * question this screen answers is what broke.
 *
 * It changes nothing. The single button empties the file, so that what is
 * shown after it is fresh.
 */
class ErrorLog extends Page implements HasActions
{
    use InteractsWithActions;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationLabel = 'Xəta jurnalı';

    protected static ?string $title = 'Xəta jurnalı';

    protected static ?string $slug = 'error-log';

    protected static ?int $navigationSort = 92;

    protected static string $view = 'filament.pages.error-log';

    /** How much of the end of the file is read. */
    private const TAIL_BYTES = 400000;

    /** How many entries are shown. */
    private const SHOWN = 40;

    /** The levels worth showing: everything the shop would call a fault. */
    private const LOUD = ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('clear')
                ->label('Jurnalı təmizlə')
                ->icon('heroicon-o-trash')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Jurnal təmizlənsin?')
                ->modalDescription('Fayl boşalır. Bundan sonra yalnız yeni xətalar görünür. '
                    . 'Saytın işinə toxunmur.')
                ->modalSubmitActionLabel('Təmizlə')
                ->visible(fn () => $this->file() !== null)
                ->action(function () {
                    $file = $this->file();

                    if ($file && @file_put_contents($file, '') !== false) {
                        Notification::make()->success()->title('Jurnal təmizləndi')->send();

                        return;
                    }

                    Notification::make()->danger()->title('Təmizlənmədi')
                        ->body('Fayla yazmaq olmadı.')->send();
                }),
        ];
    }

    /** The log the shop is writing into, or null when there is none yet. */
    public function file(): ?string
    {
        $files = glob(storage_path('logs') . DIRECTORY_SEPARATOR . '*.log') ?: [];

        if ($files === []) {
            return null;
        }

        /* The newest by when it was last written rather than by name: the
           shop writes one laravel.log, but a host switched to daily files
           leaves a row of dated ones beside it. */
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        return $files[0];
    }

    public function fileSize(): int
    {
        $file = $this->file();

        return $file ? (int) filesize($file) : 0;
    }

    /**
     * The faults in the tail of the log, newest first.
     *
     * @return array<int, array{at: ?Carbon, level: string, headline: string, where: ?string, text: string}>
     */
    public function entries(): array
    {
        $raw = $this->tail();

        if ($raw === '') {
            return [];
        }

        /* Monolog writes one entry as «[date] channel.LEVEL: …», and a stack
           trace runs over the lines beneath it. Split on the stamp at the
           start of a line and each trace stays with the line it belongs to. */
        $parts = preg_split(
            '/^\[(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2})[^\]]*\] [^.\s]+\.([A-Z]+): /m',
            $raw,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        ) ?: [];

        // Whatever stood before the first stamp is the tail of an older entry.
        array_shift($parts);

        $out = [];

        for ($i = 0; $i + 2 < count($parts); $i += 3) {
            $level = strtoupper((string) $parts[$i + 1]);

            if (! in_array($level, self::LOUD, true)) {
                continue;
            }

            $body = (string) $parts[$i + 2];

            $out[] = [
                'at' => $this->readStamp((string) $parts[$i]),
                'level' => $level,
                'headline' => $this->headline($body),
                'where' => $this->where($body),
                'text' => rtrim(mb_substr($body, 0, 8000)),
            ];
        }

        return array_slice(array_reverse($out), 0, self::SHOWN);
    }

    /** How many of the faults shown happened in the last day. */
    public function todayCount(): int
    {
        $since = now()->subDay();

        return count(array_filter(
            $this->entries(),
            fn (array $e) => $e['at'] !== null && $e['at']->greaterThanOrEqualTo($since)
        ));
    }

    /** The end of the file, read without pulling the whole of it into memory. */
    private function tail(): string
    {
        $file = $this->file();

        if (! $file || ! is_readable($file)) {
            return '';
        }

        $size = (int) filesize($file);
        $handle = @fopen($file, 'rb');

        if (! $handle) {
            return '';
        }

        if ($size > self::TAIL_BYTES) {
            fseek($handle, -self::TAIL_BYTES, SEEK_END);
        }

        $raw = (string) stream_get_contents($handle);
        fclose($handle);

        return $raw;
    }

    private function readStamp(string $stamp): ?Carbon
    {
        if ($stamp === '') {
            return null;
        }

        try {
            /* Read back in the shop's own timezone, because that is the one
               it was written in — otherwise every fault would be shown four
               hours before it happened. */
            return Carbon::parse(str_replace('T', ' ', $stamp), config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    /** The sentence that says what broke, without the trace under it. */
    private function headline(string $body): string
    {
        $line = trim(explode("\n", $body)[0]);

        // Everything from « in /path/file.php:12» on is said by «where».
        $line = (string) preg_replace('/ in [\/A-Za-z]:?[^ ]*\.php:\d+.*$/', '', $line);
        $line = (string) preg_replace('/\s*\{"exception".*$/s', '', $line);

        return mb_substr(trim($line), 0, 400);
    }

    /** The file and line it threw at, shortened to the part worth reading. */
    public function where(string $body): ?string
    {
        /* Written two ways: PHP's own « in /path/file.php:12», and the line
           Laravel adds under it, «… at /path/file.php:12)». Either answers
           the only question this line is for. */
        if (! preg_match('/ (?:in|at) ((?:\/|[A-Za-z]:\\\\)[^ ]+\.php):(\d+)/', $body, $m)) {
            return null;
        }

        $path = str_replace('\\', '/', $m[1]);
        $root = rtrim(str_replace('\\', '/', base_path()), '/') . '/';

        return str_replace($root, '', $path) . ':' . $m[2];
    }
}
