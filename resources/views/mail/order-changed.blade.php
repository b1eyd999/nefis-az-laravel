@php
    use App\Support\Contact;
    use App\Support\Price;

    /* Money owed to the shop is the shop's amber; money going back to the
       customer is the same amber the refund letter uses, so the two read as
       one family rather than as good news and bad. */
    $charge = $adjustment->isCharge();
    $tone = $charge ? ['#d97706', '#fdf0dd'] : ['#b45309', '#fdf0dd'];
    $headline = $charge
        ? __('Sifarişiniz dəyişdi, kiçik bir fərq qaldı')
        : __('Sifarişiniz dəyişdi, fərqi sizə qaytarırıq');
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
<div style="display:none;max-height:0;overflow:hidden;opacity:0;">{{ $headline }}</div>
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
                                <td style="background:{{ $tone[1] }};color:{{ $tone[0] }};border-radius:999px;padding:7px 14px;font-size:14px;font-weight:700;">
                                    {{ $charge ? __('Əlavə ödəniş') : __('Məbləğ qaytarılır') }}
                                </td>
                            </tr>
                        </table>
                        <p style="margin:0 0 6px;font-size:17px;line-height:1.5;">{{ $headline }}</p>
                        @if ($adjustment->reason)
                            <p style="margin:0;font-size:15px;line-height:1.6;color:#6b5b49;">{{ $adjustment->reason }}</p>
                        @endif
                    </td>
                </tr>

                <tr>
                    <td style="padding:14px 24px 4px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:15px;line-height:1.9;">
                            <tr>
                                <td style="color:#8a7a68;">{{ __('Sifarişin yeni məbləği') }}</td>
                                <td align="right">{{ Price::format($order->total()) }}</td>
                            </tr>
                            <tr>
                                <td style="color:#8a7a68;">{{ __('Artıq ödənilib') }}</td>
                                <td align="right">{{ Price::format($order->paidSoFar()) }}</td>
                            </tr>
                            <tr>
                                <td style="color:#8a7a68;padding-top:10px;border-top:1px solid #f0eae1;">
                                    {{ $charge ? __('Əlavə ödəniləcək') : __('Sizə qaytarılacaq') }}
                                </td>
                                <td align="right" style="padding-top:10px;border-top:1px solid #f0eae1;font-size:18px;font-weight:700;color:{{ $tone[0] }};">
                                    {{ Price::format((float) $adjustment->amount) }}
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                @if ($charge)
                    <tr>
                        <td style="padding:22px 24px 4px;">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="background:#d97706;border-radius:10px;">
                                        <a href="{{ $link }}" style="display:inline-block;padding:13px 24px;color:#fff;text-decoration:none;font-weight:700;font-size:16px;">
                                            {{ __('Fərqi ödə') }}
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:10px 24px 0;font-size:13px;line-height:1.7;color:#8a7a68;">
                            {{ __('Yalnız fərq ödənilir — ilk ödənişiniz olduğu kimi qalır.') }}
                        </td>
                    </tr>
                @else
                    <tr>
                        <td style="padding:18px 24px 0;font-size:14px;line-height:1.7;color:#6b5b49;">
                            {{ __('Məbləğ bankınızdan asılı olaraq 1–7 iş gününə kartınıza düşəcək.') }}
                        </td>
                    </tr>
                @endif

                <tr>
                    <td style="padding:22px 24px 26px;font-size:13px;line-height:1.7;color:#8a7a68;">
                        {{ __('Sualınız olsa, bu məktuba cavab yazın və ya bizə yazın:') }}
                        @if (Contact::has())
                            <a href="{{ Contact::whatsapp() }}" style="color:#d97706;font-weight:600;text-decoration:none;">{{ Contact::display() }}</a>
                        @endif
                    </td>
                </tr>
            </table>

            <p style="max-width:560px;margin:14px auto 0;font-size:12px;color:#a2927f;text-align:center;">
                Nefis.az — {{ __('Bakıda şəkilli şokolad qutuları') }}
            </p>
        </td>
    </tr>
</table>
</body>
</html>
