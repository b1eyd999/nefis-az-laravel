<!DOCTYPE html>
<html lang="{{ \App\Support\Locale::tag() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Nefis.az — sifarişiniz #:id ödəniş gözləyir', ['id' => $order->id]) }}</title>
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
                        <p style="margin:0 0 14px;font-size:18px;font-weight:700;line-height:1.4;">{{ __('Sifarişiniz bizdə saxlanılır') }}</p>
                        <p style="margin:0 0 18px;font-size:15px;line-height:1.6;">
                            {{ $name ? $name . ', ' : '' }}{{ __('sifariş #:id hazırlanmağı gözləyir — sadəcə ödəniş tamamlanmalıdır. Seçdiyiniz şəkil və yazılar olduğu kimi yerindədir.', ['id' => $order->id]) }}
                        </p>
                        <p style="margin:0 0 6px;font-size:14px;line-height:1.6;color:#6b5b49;">{{ __('Ödənilməli məbləğ') }}</p>
                        <p style="margin:0 0 18px;font-size:22px;font-weight:700;line-height:1.3;">{{ $total }}</p>
                        <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 18px;">
                            <tr>
                                <td style="background:#d97706;border-radius:10px;">
                                    <a href="{{ $link }}" style="display:inline-block;padding:12px 22px;color:#fff;font-size:16px;font-weight:700;text-decoration:none;">{{ __('Ödənişi tamamla') }}</a>
                                </td>
                            </tr>
                        </table>
                        @if($hoursLeft > 0)
                            <p style="margin:0 0 10px;font-size:14px;line-height:1.6;color:#6b5b49;">
                                {{ __('Ödənilməyən sifariş :hours saatdan sonra özü ləğv olunur — materiallar başqa sifarişlərə keçir.', ['hours' => $hoursLeft]) }}
                            </p>
                        @endif
                        <p style="margin:0 0 10px;font-size:14px;line-height:1.6;color:#6b5b49;">
                            {{ __('Fikrinizi dəyişmisinizsə, heç nə etməyə ehtiyac yoxdur — bu, sizdən son məktubdur.') }}
                        </p>
                        <p style="margin:0;font-size:13px;line-height:1.6;color:#8a7a68;word-break:break-all;">
                            {{ __('Düymə işləmirsə, bu ünvanı brauzerə yapışdırın:') }}<br>
                            <a href="{{ $link }}" style="color:#d97706;">{{ $link }}</a>
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
