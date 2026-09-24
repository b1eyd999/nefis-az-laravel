<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\Telegram;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * What the couriers' bot tells us. Only one thing so far: a courier tapped
 * "Mən götürürəm" under an order, so his name goes under the message and the
 * order carries it from then on.
 */
class TelegramController extends Controller
{
    public function courier(Request $request, string $secret): Response
    {
        // Two locks on the same door: the address Telegram was given, and the
        // header it sends with every call. Anyone else gets nothing back.
        $expected = Telegram::hookSecret();
        abort_unless(
            hash_equals($expected, $secret)
                && hash_equals($expected, (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '')),
            404,
        );

        $tap = (array) $request->input('callback_query', []);
        $data = (string) ($tap['data'] ?? '');

        if (! preg_match('/^take:(\d+)$/', $data, $m) || ! isset($tap['id'])) {
            return response('', 200);
        }

        $order = Order::find((int) $m[1]);
        if (! $order) {
            $this->answer($tap['id'], 'Sifariş tapılmadı.');

            return response('', 200);
        }

        if ($order->courier_name) {
            $this->answer($tap['id'], 'Bu sifarişi artıq ' . $order->courier_name . ' götürüb.');

            return response('', 200);
        }

        $order->forceFill([
            'courier_name' => $this->nameOf((array) ($tap['from'] ?? [])),
            'courier_taken_at' => now(),
        ])->saveQuietly();

        Telegram::courierTaken($order);
        $this->answer($tap['id'], 'Sifariş sizindir. Uğurlar!');

        return response('', 200);
    }

    /** What a courier is called in Telegram, whichever of it he has filled in. */
    private function nameOf(array $from): string
    {
        $name = trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? ''));
        if ($name === '' && isset($from['username'])) {
            $name = '@' . $from['username'];
        }

        return $name !== '' ? mb_substr($name, 0, 100) : 'Kuryer';
    }

    /** Stops the little clock on the courier's button. */
    private function answer(string $id, string $text): void
    {
        Telegram::call('answerCallbackQuery', ['callback_query_id' => $id, 'text' => $text]);
    }
}
