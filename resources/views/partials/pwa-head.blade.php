{{-- The shop, told to a phone as an application.

     The manifest is what a phone reads to decide this is an app and not a
     tab: the name on the home screen, the icon, the window with no address
     bar in it. One per language, because all three are different in each.

     The rest is Apple's older way of saying the same thing. iPhones still
     read these, and the startup images are the screen the customer looks at
     while the app opens — without them that moment is white. --}}
@php
  $pwaLocale = \App\Support\Locale::current();
  /* Each iPhone insists on being named exactly: the size in its own points,
     and how many real pixels go into one of them. A screen not listed here
     falls back to the manifest's cream and icon, which is the same picture. */
  $pwaScreens = [
    [1320, 2868, 440, 956, 3], [1290, 2796, 430, 932, 3], [1284, 2778, 428, 926, 3],
    [1206, 2622, 402, 874, 3], [1179, 2556, 393, 852, 3], [1170, 2532, 390, 844, 3],
    [1125, 2436, 375, 812, 3], [1242, 2688, 414, 896, 3], [828, 1792, 414, 896, 2],
    [750, 1334, 375, 667, 2],
  ];
@endphp
<link rel="manifest" href="{{ route('manifest', ['lang' => $pwaLocale]) }}">
<meta name="application-name" content="Nefis">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Nefis">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
@foreach($pwaScreens as [$w, $h, $cw, $ch, $ratio])
<link rel="apple-touch-startup-image" href="{{ \App\Support\Assets::url('images/splash/' . $w . 'x' . $h . '.png') }}"
      media="(device-width: {{ $cw }}px) and (device-height: {{ $ch }}px) and (-webkit-device-pixel-ratio: {{ $ratio }}) and (orientation: portrait)">
@endforeach
