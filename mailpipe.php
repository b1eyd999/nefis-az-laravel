<?php

/**
 * The mail pipe: a letter in, a Telegram card out.
 *
 * cPanel runs this with the whole message on standard input — Email →
 * Forwarders → "Pipe to a Program". The deploy writes an executable copy of
 * this file at the application root with the hosting's own PHP in its first
 * line, because a piped program is run directly and must say what runs it.
 *
 * Two rules hold the whole file together:
 *
 *   1. It always exits 0. Exim reads the exit code, and a pipe that fails
 *      bounces the letter back to whoever wrote it. A customer must never be
 *      told his letter was refused because a notification did not go out.
 *   2. It never prints. Anything on standard output is treated as a reason
 *      for failure, so the only record of trouble is the application log.
 *
 * The letter still reaches the mailbox: this delivery is added to that one,
 * not put in its place.
 */

$raw = '';
$in = fopen('php://stdin', 'rb');
if ($in !== false) {
    // The whole message, however long: a letter with a photograph attached
    // runs to several megabytes and arrives in pieces.
    while (! feof($in)) {
        $chunk = fread($in, 65536);
        if ($chunk === false) {
            break;
        }
        $raw .= $chunk;
        // Past this there is nothing worth reading out, and the rest is an
        // attachment nobody is going to see in a Telegram card.
        if (strlen($raw) > 2 * 1024 * 1024) {
            break;
        }
    }
    fclose($in);
}

try {
    if (trim($raw) !== '') {
        require __DIR__ . '/vendor/autoload.php';

        /** @var Illuminate\Foundation\Application $app */
        $app = require __DIR__ . '/bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        App\Support\MailToTelegram::announce($raw);
    }
} catch (Throwable $e) {
    // Nowhere to report it to but the log, and even that may be unwritable
    // at the moment a letter arrives; silence is better than a bounce.
    try {
        Illuminate\Support\Facades\Log::error('mailpipe: ' . $e->getMessage());
    } catch (Throwable) {
        // nothing left to do
    }
}

exit(0);
