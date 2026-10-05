@php use App\Support\Contact; @endphp
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
                    {{-- The owner's own words, kept exactly as he typed them:
                         his line breaks are his, and nothing he writes is read
                         as markup. --}}
                    <td style="padding:26px 24px 8px;font-size:16px;line-height:1.65;white-space:pre-wrap;">{{ $body }}</td>
                </tr>
                <tr>
                    <td style="padding:22px 24px 4px;">
                        <table role="presentation" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="background:#d97706;border-radius:10px;">
                                    <a href="{{ $link }}" style="display:inline-block;padding:13px 24px;color:#fff;text-decoration:none;font-weight:700;font-size:16px;">
                                        {{ __('Sifarişə bax') }}
                                    </a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:22px 24px 26px;font-size:13px;line-height:1.7;color:#8a7a68;">
                        {{ __('Bu məktuba cavab yaza bilərsiniz.') }}
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
