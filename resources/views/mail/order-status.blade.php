@php
    use App\Support\Contact;
    use App\Support\DeliveryTime;
    use App\Support\Price;

    /* One colour per ending, so the letter reads before it is read: the shop's
       amber while the box is being made, green when it is done, grey when it
       was called off, and the same amber for money going back. */
    $tone = match ($order->status) {
        'completed' => ['#15803d', '#eaf6ed'],
        'ready' => ['#15803d', '#eaf6ed'],
        'cancelled' => ['#6b5b49', '#f1ece5'],
        'refunded' => ['#b45309', '#fdf0dd'],
        default => ['#d97706', '#fdf0dd'],
    };
    $paid = $order->total() > 0;
@endphp
<!DOCTYPE html>
<html lang="{{ \App\Support\Locale::tag() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Nefis.az, sifariş #') }}{{ $order->id }}</title>
</head>
<body style="margin:0;padding:0;background:#faf7f2;font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;color:#2b2118;">
{{-- Read by e-mail clients that show one line before the letter is opened. --}}
<div style="display:none;max-height:0;overflow:hidden;opacity:0;">{{ $line }}</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#faf7f2;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 2px 10px rgba(43,33,24,.07);">
                <tr>
                    <td style="background:#d97706;padding:18px 24px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td><a href="{{ url('/') }}" style="color:#fff;font-size:20px;font-weight:700;text-decoration:none;letter-spacing:.5px;">Nefis.az</a></td>
                                <td align="right" style="color:#fde3bf;font-size:13px;">{{ __('Sifariş') }} #{{ $order->id }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:26px 24px 8px;">
                        <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 14px;">
                            <tr>
                                <td style="background:{{ $tone[1] }};border-radius:999px;padding:6px 14px;font-size:13px;font-weight:700;color:{{ $tone[0] }};">
                                    {{ __($order->statusLabel()) }}
                                </td>
                            </tr>
                        </table>
                        <p style="margin:0 0 20px;font-size:19px;font-weight:700;line-height:1.45;">{{ $line }}</p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:0 24px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#faf7f2;border-radius:12px;">
                            <tr>
                                <td style="padding:14px 16px;font-size:15px;line-height:1.6;">
                                    @foreach ($order->items as $item)
                                        <div style="padding:{{ $loop->first ? '0' : '8px' }} 0 8px;{{ $loop->last ? '' : 'border-bottom:1px solid #efe8de;' }}">
                                            <span style="font-weight:600;">{{ $item->product_name }}</span>@if ($item->quantity > 1) <span style="color:#8a7a68;">× {{ $item->quantity }}</span>@endif
                                            @if ($item->chocolate_name)
                                                <br><span style="color:#8a7a68;font-size:13px;">{{ $item->chocolate_name }}</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px 24px 0;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:15px;line-height:1.7;">
                            @if ($order->delivery_date)
                                <tr>
                                    <td style="color:#8a7a68;width:42%;">{{ __('Çatdırılma') }}</td>
                                    <td align="right">{{ DeliveryTime::day($order->delivery_date) }}@if ($order->delivery_slot), {{ $order->delivery_slot }}@endif</td>
                                </tr>
                            @endif
                            @if ($order->delivery_name)
                                <tr>
                                    <td style="color:#8a7a68;">{{ __('Üsul') }}</td>
                                    <td align="right">{{ $order->delivery_name }}</td>
                                </tr>
                            @endif
                            @if ($paid)
                                <tr>
                                    <td style="color:#8a7a68;padding-top:10px;border-top:1px solid #f0eae1;">
                                        {{ $order->status === 'refunded' ? __('Qaytarılan məbləğ') : __('Məbləğ') }}
                                    </td>
                                    <td align="right" style="padding-top:10px;border-top:1px solid #f0eae1;font-size:18px;font-weight:700;color:{{ $tone[0] }};">
                                        {{ Price::format($order->total()) }}
                                    </td>
                                </tr>
                            @endif
                        </table>
                    </td>
                </tr>
                @if ($order->status !== 'refunded' && $order->status !== 'cancelled')
                    <tr>
                        <td style="padding:22px 24px 4px;">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="background:#d97706;border-radius:10px;">
                                        <a href="{{ $link }}" style="display:inline-block;padding:13px 24px;color:#fff;text-decoration:none;font-weight:700;font-size:16px;">
                                            {{ $order->awaitsPayment() ? __('Ödənişi tamamla') : __('Sifarişə bax') }}
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                @endif
                <tr>
                    <td style="padding:22px 24px 0;">
                        <div style="height:1px;background:#f0eae1;line-height:1px;">&nbsp;</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px 24px 22px;font-size:13px;color:#8a7a68;line-height:1.7;">
                        {{ __('Sualınız var? Yazın və ya zəng edin:') }}
                        @if (Contact::has())
                            <a href="{{ Contact::whatsapp() }}" style="color:#d97706;font-weight:600;">{{ Contact::display() }}</a>
                        @endif
                        <br>{{ __('Nefis.az, əl ilə hazırlanan şəkilli şokolad qutuları, Bakı.') }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
