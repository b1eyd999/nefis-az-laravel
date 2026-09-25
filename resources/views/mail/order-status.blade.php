@php
    use App\Support\Contact;
    use App\Support\DeliveryTime;
    use App\Support\Price;
@endphp
<!DOCTYPE html>
<html lang="{{ \App\Support\Locale::tag() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Nefis.az, sifariş #') }}{{ $order->id }}</title>
</head>
<body style="margin:0;padding:0;background:#faf7f2;font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;color:#2b2118;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#faf7f2;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 1px 3px rgba(43,33,24,.08);">
                <tr>
                    <td style="background:#d97706;padding:18px 24px;">
                        <a href="{{ url('/') }}" style="color:#fff;font-size:20px;font-weight:700;text-decoration:none;letter-spacing:.5px;">Nefis.az</a>
                    </td>
                </tr>
                <tr>
                    <td style="padding:24px;">
                        <p style="margin:0 0 6px;font-size:14px;color:#8a7a68;">{{ __('Sifariş') }} #{{ $order->id }}</p>
                        <p style="margin:0 0 18px;font-size:18px;font-weight:700;line-height:1.4;">{{ $line }}</p>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:15px;line-height:1.6;">
                            @foreach ($order->items as $item)
                                <tr>
                                    <td style="padding:6px 0;border-bottom:1px solid #f0eae1;">
                                        {{ $item->product_name }}@if ($item->quantity > 1) × {{ $item->quantity }}@endif
                                        @if ($item->chocolate_name)
                                            <br><span style="color:#8a7a68;font-size:13px;">{{ $item->chocolate_name }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            @if ($order->delivery_date)
                                <tr>
                                    <td style="padding:10px 0 0;">
                                        <span style="color:#8a7a68;">{{ __('Çatdırılma:') }}</span>
                                        {{ DeliveryTime::day($order->delivery_date) }}@if ($order->delivery_slot), {{ $order->delivery_slot }}@endif
                                    </td>
                                </tr>
                            @endif
                            @if ($order->delivery_name)
                                <tr>
                                    <td style="padding:2px 0;"><span style="color:#8a7a68;">{{ __('Üsul:') }}</span> {{ $order->delivery_name }}</td>
                                </tr>
                            @endif
                            @if ($order->total() > 0)
                                <tr>
                                    <td style="padding:2px 0;font-weight:700;">
                                        <span style="color:#8a7a68;font-weight:400;">{{ __('Məbləğ:') }}</span> {{ Price::format($order->total()) }}
                                    </td>
                                </tr>
                            @endif
                        </table>

                        <p style="margin:22px 0 0;">
                            <a href="{{ $link }}" style="display:inline-block;background:#d97706;color:#fff;text-decoration:none;padding:12px 22px;border-radius:9px;font-weight:700;">
                                {{ $order->awaitsPayment() ? __('Ödənişi tamamla') : __('Sifarişə bax') }}
                            </a>
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:18px 24px;background:#faf7f2;font-size:13px;color:#8a7a68;line-height:1.6;">
                        {{ __('Sualınız var? Yazın və ya zəng edin:') }}
                        @if (Contact::has())
                            <a href="{{ Contact::whatsapp() }}" style="color:#d97706;">{{ Contact::display() }}</a>
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
