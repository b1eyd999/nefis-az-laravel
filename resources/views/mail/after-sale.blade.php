<!DOCTYPE html>
<html lang="{{ \App\Support\Locale::tag() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Nefis.az — sifarişiniz üçün təşəkkür edirik') }}</title>
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
                        <p style="margin:0 0 14px;font-size:18px;font-weight:700;line-height:1.4;">{{ __('Təşəkkür edirik! 🤎') }}</p>
                        <p style="margin:0 0 18px;font-size:15px;line-height:1.6;">
                            {{ $name ? $name . ', ' : '' }}{{ __('sifariş #:id sizə çatdı. Ümid edirik, qutu gözlədiyiniz kimi çıxıb.', ['id' => $order->id]) }}
                        </p>

                        @if($reviewLink)
                            <p style="margin:0 0 14px;font-size:15px;line-height:1.6;">
                                {{ __('Bir-iki cümlə yazsanız, bizə çox kömək edərdi: qutu necə çıxdı, şəkil necə göründü, nə vaxt çatdı. Öz çəkdiyiniz şəkli də əlavə edə bilərsiniz.') }}
                            </p>
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 18px;">
                                <tr>
                                    <td style="background:#d97706;border-radius:10px;">
                                        <a href="{{ $reviewLink }}" style="display:inline-block;padding:12px 22px;color:#fff;font-size:16px;font-weight:700;text-decoration:none;">{{ __('Rəy yazın') }}</a>
                                    </td>
                                </tr>
                            </table>
                        @endif

                        @if($promo)
                            {{-- The code, in a box of its own: this is the part he
                                 will come back to the letter for. --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 18px;">
                                <tr>
                                    <td style="background:#fdf6ec;border:1px dashed #d9a85a;border-radius:12px;padding:18px;text-align:center;">
                                        <p style="margin:0 0 6px;font-size:14px;line-height:1.5;color:#6b5b49;">
                                            {{ __('Növbəti sifarişinizə :percent% endirim', ['percent' => $percent]) }}
                                        </p>
                                        <p style="margin:0 0 6px;font-size:26px;font-weight:700;letter-spacing:.18em;line-height:1.2;">{{ $promo }}</p>
                                        @if($days > 0)
                                            <p style="margin:0;font-size:13px;line-height:1.5;color:#8a7a68;">
                                                {{ __('Kod :days gün işləyir. Yalnız bir dəfə, yalnız sizin üçün.', ['days' => $days]) }}
                                            </p>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        @endif

                        <p style="margin:0 0 10px;font-size:14px;line-height:1.6;color:#6b5b49;">
                            <a href="{{ $designs }}" style="color:#d97706;">{{ __('Dizaynlara bax') }}</a>
                        </p>
                        <p style="margin:0;font-size:13px;line-height:1.6;color:#8a7a68;">
                            {{ __('Nəsə düz getmədiyini düşünürsünüzsə, bu məktuba cavab yazın — özümüz həll edək.') }}
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
