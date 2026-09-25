<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Support\ChatBot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * The little chat window: the visitor writes, the shop answers from Telegram.
 * A visitor is known only by a key kept in his own session, so nothing has to
 * be asked of him before he can write.
 */
class ChatController extends Controller
{
    private const THREAD = 'chat_thread';

    public function send(Request $request): JsonResponse
    {
        if (! ChatBot::enabled()) {
            return response()->json(['ok' => false], 404);
        }

        $data = $request->validate([
            // Words, a screenshot, or both: one of them has to be there.
            'body' => ['required_without:image', 'nullable', 'string', 'max:1000'],
            'image' => ['nullable', 'image', 'max:5120'],
            'name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'page' => ['nullable', 'string', 'max:255'],
        ]);

        // A window is for writing to a shop, not for shouting at a bot.
        $key = 'chat:' . $request->session()->getId();
        if (RateLimiter::tooManyAttempts($key, 12)) {
            return response()->json(['ok' => false, 'wait' => RateLimiter::availableIn($key)], 429);
        }
        RateLimiter::hit($key, 60);

        $message = ChatMessage::create([
            'thread' => $this->thread($request),
            'side' => ChatMessage::VISITOR,
            'body' => (string) ($data['body'] ?? ''),
            'image' => $request->hasFile('image') ? $request->file('image')->store('chat', 'public') : null,
            'name' => $data['name'] ?? $request->user()?->name,
            'phone' => $data['phone'] ?? $request->user()?->phone,
            'page' => $data['page'] ?? null,
        ]);

        ChatBot::forward($message);

        return response()->json(['ok' => true, 'message' => $message->toWindow()]);
    }

    /** Everything said in this visitor's thread after the line he already has. */
    public function poll(Request $request): JsonResponse
    {
        $after = (int) $request->query('after', 0);

        $messages = ChatMessage::where('thread', $this->thread($request))
            ->where('id', '>', $after)
            ->orderBy('id')
            ->limit(50)
            ->get()
            ->map->toWindow()
            ->values();

        return response()->json(['ok' => true, 'messages' => $messages]);
    }

    /** The shop's answer, handed over by Telegram. */
    public function hook(Request $request, string $secret): JsonResponse
    {
        abort_unless(hash_equals(ChatBot::hookSecret(), $secret), 404);
        abort_unless(hash_equals(ChatBot::hookSecret(), (string) $request->header('X-Telegram-Bot-Api-Secret-Token')), 404);

        ChatBot::answer($request->all());

        return response()->json(['ok' => true]);
    }

    private function thread(Request $request): string
    {
        $thread = $request->session()->get(self::THREAD);
        if (! $thread) {
            $thread = (string) Str::uuid();
            $request->session()->put(self::THREAD, $thread);
        }

        return $thread;
    }
}
