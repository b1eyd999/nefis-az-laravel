{{-- The courier's screen.

     The phone admin's own sheet, because it is the same kind of screen — one
     hand, outdoors, in a hurry — but none of its tabs: there is nothing else
     here for him to go to. Azerbaijani literally, like the rest of the shop's
     own tools. --}}
<!DOCTYPE html>
<html lang="az">
<head>
<meta charset="UTF-8">
<script>
  try {
    var t = localStorage.getItem('nefis-theme');
    if (t === 'dark' || t === 'light') document.documentElement.setAttribute('data-theme', t);
  } catch (e) {}
</script>
<meta name="referrer" content="no-referrer">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<title>@yield('title', 'Kuryer — Nefis')</title>

{{-- Installed on the home screen it opens straight on his orders. The file
     sits beside the folder, not inside one called kuryer: a real directory of
     that name would shadow the route. --}}
<link rel="manifest" href="{{ asset('kuryer.webmanifest') }}">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Kuryer">
<link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">
<meta name="theme-color" content="#FBF4EA" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#17110D" media="(prefers-color-scheme: dark)">

<link rel="stylesheet" href="{{ asset('css/admin-phone.css') }}?v={{ \App\Support\Assets::version('css/admin-phone.css') }}">
<meta name="csrf-token" content="{{ csrf_token() }}">
<style>
  /* No tab bar down there, so the room kept for one is given back. */
  body{ padding-bottom:2rem; }
  .ku-share{ display:flex; gap:.75rem; align-items:center; justify-content:space-between;
    background:var(--paper); border:1px solid var(--line); border-radius:var(--radius);
    padding:.85rem 1rem; box-shadow:var(--shadow-sm); margin:0 0 1rem; }
  .ku-share .state{ font-size:.82rem; color:var(--cocoa-soft); }
  .ku-share.on{ border-color:var(--ok-line); background:var(--ok-bg); }
  .ku-share.on .state{ color:var(--ok-fg); }
  .ku-dot{ display:inline-block; width:.55rem; height:.55rem; border-radius:50%;
    background:var(--green); margin-right:.35rem; animation:kupulse 1.6s infinite; }
  @keyframes kupulse{ 0%,100%{ opacity:1 } 50%{ opacity:.25 } }
  .ku-grid{ display:grid; grid-template-columns:1fr 1fr; gap:.6rem; margin:.75rem 0; }
  .ku-grid .ph-btn{ margin:0; }
  .ku-addr{ font-size:1.05rem; font-weight:650; line-height:1.35; }
  .ku-collect{ font-size:1.5rem; font-weight:800; letter-spacing:-.02em; }
  .ku-note{ font-size:.85rem; color:var(--cocoa-soft); }
</style>
</head>
<body>

<header class="ph-top">
  @hasSection('back')
    <a class="ph-back" href="@yield('back')" aria-label="Geri">‹</a>
  @endif
  <div class="grow">
    <h1>@yield('heading', 'Kuryer')</h1>
    @hasSection('sub')<div class="sub">@yield('sub')</div>@endif
  </div>
</header>

<main class="ph-wrap">
  @if(session('courier.flash'))
    <div class="ph-flash" role="status">{{ session('courier.flash') }}</div>
  @endif
  @if($errors->any())
    <div class="ph-err">@foreach($errors->all() as $m)<div>{{ $m }}</div>@endforeach</div>
  @endif

  {{-- The switch. He turns it on when he sets off and off when he is done;
       while it is on this screen says so, in words, the whole time. --}}
  @php $sharing = $me->isSharing(); @endphp
  <form method="POST" action="{{ route('courier.share') }}" class="ku-share {{ $sharing ? 'on' : '' }}" data-once>
    @csrf
    <input type="hidden" name="on" value="{{ $sharing ? 0 : 1 }}">
    <div>
      <b>Lokasiya</b>
      <div class="state" id="ku-state">
        @if($sharing)
          <span class="ku-dot"></span>Paylaşılır — admin sizi xəritədə görür
        @else
          Söndürülüb. Yola düşəndə yandırın.
        @endif
      </div>
    </div>
    <button class="ph-btn {{ $sharing ? '' : 'ph-btn-primary' }}" type="submit"
            data-busy="…">{{ $sharing ? 'Söndür' : 'Yandır' }}</button>
  </form>

  @yield('content')
</main>

<script>
  /* One tap must not become two because the network blinked. */
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form.matches('form[data-once]')) return;
    if (form.dataset.sent === '1') { e.preventDefault(); return; }
    form.dataset.sent = '1';
    form.querySelectorAll('button[type=submit], button:not([type])').forEach(function (b) {
      b.disabled = true;
      if (b.dataset.busy) b.textContent = b.dataset.busy;
    });
  });
</script>

@if($sharing)
<script>
  /* Where he is, while he has said it may be sent.
     The page only reports while it is open and in front of him — that is the
     phone's own rule, not a choice made here — so the switch is worded as
     something he turns off when he arrives, and the server lets it lapse by
     itself within the hour if these readings stop coming. */
  (function () {
    var URL = @json(route('courier.position'));
    var TOKEN = document.querySelector('meta[name=csrf-token]').content;
    var state = document.getElementById('ku-state');
    var last = 0;

    if (!navigator.geolocation) {
      if (state) state.textContent = 'Bu telefon yerini göstərə bilmir.';
      return;
    }

    /* The screen staying awake is the difference between a trail and two
       points; where the browser allows it, ask. */
    if ('wakeLock' in navigator) {
      navigator.wakeLock.request('screen').catch(function () {});
    }

    function say(text) { if (state) state.innerHTML = '<span class="ku-dot"></span>' + text; }

    function send(p) {
      var now = p.timestamp || +new Date();
      if (now - last < 12000) return;          // a reading every quarter minute is plenty
      last = now;
      fetch(URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': TOKEN, 'Accept': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({
          lat: p.coords.latitude, lng: p.coords.longitude,
          accuracy: p.coords.accuracy ? Math.round(p.coords.accuracy) : null
        })
      }).then(function (r) { return r.json(); }).then(function (d) {
        if (d && d.sharing === false) { window.location.reload(); return; }
        var t = new Date();
        say('Göndərildi ' + ('0' + t.getHours()).slice(-2) + ':' + ('0' + t.getMinutes()).slice(-2));
      }).catch(function () {
        say('Şəbəkə yoxdur — yenidən cəhd edilir');
      });
    }

    navigator.geolocation.watchPosition(send, function (err) {
      if (state) {
        state.textContent = err && err.code === 1
          ? 'Brauzer yeri göstərməyə icazə vermir — telefonun ayarlarından açın.'
          : 'Yer tapılmır — açıq havada bir az gözləyin.';
      }
    }, { enableHighAccuracy: true, maximumAge: 10000, timeout: 25000 });
  })();
</script>
@endif
@yield('page-script')
</body>
</html>
