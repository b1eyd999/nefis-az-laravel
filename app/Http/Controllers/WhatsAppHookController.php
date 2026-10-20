<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\WhatsAppChat;
use App\Models\WhatsAppMessage;
use App\Support\Telegram;
use App\Support\WhatsApp;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Where Meta pushes everything that happens on the shop's WhatsApp number.
 *
 * Two different calls arrive here. A GET once, when the hook is first set up:
 * Meta sends a word it was given beforehand and expects it echoed back. And
 * a POST for every message afterwards, signed with the app secret.
 *
 * Answered quickly and always with 200. Meta repeats anything it thinks was
 * not delivered, and a shop that is slow to answer gets the same message
 * three times — which is why every message is written under WhatsApp's own
 * id and a repeat changes nothing.
 */
class WhatsAppHookController extends Controller
{
    /** The handshake, once. */
    public function verify(Request $request): Response
    {
        $word = WhatsApp::verifyWord();
        $given = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        /* Read as words or not at all. An array here — ?hub_verify_token[]=x,
           which anybody may send — used to be cast to a string, and PHP's
           «array to string» warning is an exception in Laravel: a public 500
           with a stack trace in the log, on a route with no throttle. */
        if (blank($word)
            || $request->query('hub_mode') !== 'subscribe'
            || ! is_scalar($given) || ! is_scalar($challenge)
            || ! hash_equals($word, (string) $given)) {
            return response('no', 403);
        }

        return response((string) $challenge, 200)
            ->header('Content-Type', 'text/plain');
    }

    public function receive(Request $request): Response
    {
        if (! WhatsApp::signed($request->getContent(), $request->header('X-Hub-Signature-256'))) {
            Log::warning('whatsapp: a notice arrived unsigned or signed wrong');

            // Not 200: a signature that does not match is not ours to swallow.
            return response('bad signature', 403);
        }

        foreach ((array) $request->input('entry', []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                $value = $change['value'] ?? [];
                $this->names($value);

                /* Each on its own. Meta packs several messages into one
                   notice, and a single odd field used to abort the lot — the
                   good messages were lost with the bad one, and Meta then
                   resent the same notice for ever because it never saw a
                   200. What cannot be written is written in the log and the
                   rest goes on. */
                foreach ((array) ($value['messages'] ?? []) as $message) {
                    try {
                        $this->wrote(is_array($message) ? $message : [], $value);
                    } catch (\Throwable $e) {
                        Log::error('whatsapp: a message could not be written', [
                            'id' => is_array($message) ? ($message['id'] ?? null) : null,
                            'why' => $e->getMessage(),
                        ]);
                    }
                }
                foreach ((array) ($value['statuses'] ?? []) as $status) {
                    try {
                        $this->moved(is_array($status) ? $status : []);
                    } catch (\Throwable $e) {
                        Log::error('whatsapp: a status could not be written', ['why' => $e->getMessage()]);
                    }
                }
            }
        }

        return response('ok', 200);
    }

    /** What the sender calls himself, kept for the panel to show. */
    private array $called = [];

    private function names(array $value): void
    {
        foreach ((array) ($value['contacts'] ?? []) as $contact) {
            if (filled($contact['wa_id'] ?? null)) {
                $this->called[$contact['wa_id']] = (string) ($contact['profile']['name'] ?? '');
            }
        }
    }

    /** A customer has written. */
    private function wrote(array $message, array $value): void
    {
        $waId = preg_replace('/\D/', '', (string) ($message['from'] ?? ''));
        $wamId = (string) ($message['id'] ?? '');
        if ($waId === '' || $wamId === '') {
            return;
        }

        // Said again because our answer was slow. Nothing to do.
        if (WhatsAppMessage::where('wam_id', $wamId)->exists()) {
            return;
        }

        $type = (string) ($message['type'] ?? 'text');
        $body = match ($type) {
            'text' => (string) ($message['text']['body'] ?? ''),
            'button' => (string) ($message['button']['text'] ?? ''),
            'interactive' => (string) ($message['interactive']['list_reply']['title']
                ?? $message['interactive']['button_reply']['title'] ?? ''),
            /* A named place comes with its name; «send my current
               location» comes with nothing but the two numbers, and those
               used to be dropped — so the one message whose whole point is
               where the customer is arrived empty. */
            'location' => trim((string) ($message['location']['name'] ?? '') . ' '
                . ($message['location']['address'] ?? '')) ?: self::whereHeIs($message['location'] ?? []),
            default => (string) ($message[$type]['caption'] ?? ''),
        };

        $media = null;
        $mediaId = $message[$type]['id'] ?? null;
        if (filled($mediaId) && in_array($type, ['image', 'video', 'audio', 'document', 'sticker'], true)) {
            $media = WhatsApp::fetchMedia((string) $mediaId);
        }

        $at = filled($message['timestamp'] ?? null)
            ? now()->setTimestamp((int) $message['timestamp'])
            : now();

        DB::transaction(function () use ($waId, $wamId, $type, $body, $media, $at, $message) {
            $chat = WhatsAppChat::firstOrCreate(
                ['wa_id' => $waId],
                ['phone' => WhatsApp::written($waId), 'unread' => 0],
            );

            $name = $this->called[$waId] ?? null;
            $chat->forceFill(array_filter([
                'name' => filled($name) ? $name : $chat->name,
                'last_incoming_at' => $at,
                'last_message_at' => $at,
                'unread' => $chat->unread + 1,
            ], fn ($v) => $v !== null))->save();

            WhatsAppMessage::create([
                'wa_id' => $waId,
                'direction' => WhatsAppMessage::IN,
                'wam_id' => $wamId,
                'type' => $type,
                'body' => $body ?: null,
                'media_path' => $media['path'] ?? null,
                'media_mime' => $media['mime'] ?? null,
                'media_name' => $message['document']['filename'] ?? null,
                'sent_at' => $at,
            ]);
        });

        /* The owner is not sitting in the panel all day, and a question
           nobody sees is a lost order. The same bell as everything else. */
        $this->ring($waId, $body, $type);
    }

    /** The two numbers, as a line somebody can tap. */
    private static function whereHeIs(array $location): string
    {
        $lat = $location['latitude'] ?? null;
        $lng = $location['longitude'] ?? null;

        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return '';
        }

        return '📍 ' . round((float) $lat, 6) . ', ' . round((float) $lng, 6)
            . "\n" . 'https://www.google.com/maps?q=' . round((float) $lat, 6) . ',' . round((float) $lng, 6);
    }

    /** What became of something the shop sent. */
    private function moved(array $status): void
    {
        $wamId = (string) ($status['id'] ?? '');
        $state = (string) ($status['status'] ?? '');
        if ($wamId === '' || $state === '') {
            return;
        }

        $line = WhatsAppMessage::where('wam_id', $wamId)->first();
        if (! $line) {
            return;
        }

        // Never backwards: «read» must not become «delivered» again.
        $order = ['sending' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3, 'failed' => 9];
        if (($order[$state] ?? 0) < ($order[$line->status] ?? 0) && $state !== 'failed') {
            return;
        }

        $line->forceFill([
            'status' => $state,
            'error' => $state === 'failed'
                ? Str::limit((string) ($status['errors'][0]['title'] ?? 'göndərilmədi'), 400)
                : $line->error,
        ])->save();
    }

    private function ring(string $waId, string $body, string $type): void
    {
        if (Setting::get(Setting::WHATSAPP_ENABLED) !== '1') {
            return;
        }

        $what = filled($body) ? Str::limit($body, 300) : '(' . $type . ')';
        defer(fn () => Telegram::send(
            '💬 <b>WhatsApp</b> — ' . e(WhatsApp::written($waId)) . "\n\n" . e($what)
            . "\n\n" . e(route('filament.admin.pages.whatsapp'))
        ));
    }
}
