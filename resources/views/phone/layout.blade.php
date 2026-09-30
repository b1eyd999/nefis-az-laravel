{{-- The shop in a pocket: its own thin shell, not the shop's.

     Written in Azerbaijani literally rather than through __(), like the box
     editor — this is the owner's own screen, it has one language, and a
     missing translation key here would be a blank label on a working day. --}}
<!DOCTYPE html>
<html lang="az">
<head>
<meta charset="UTF-8">
{{-- Before any paint, so he never sees the wrong theme flash at 2am. --}}
<script>
  try {
    var t = localStorage.getItem('nefis-theme');
    if (t === 'dark' || t === 'light') document.documentElement.setAttribute('data-theme', t);
  } catch (e) {}
</script>
{{-- The artwork comes from a Yandex Disk share, which refuses any request
     carrying a referer from elsewhere. --}}
<meta name="referrer" content="no-referrer">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<title>@yield('title', 'Nefis admin')</title>

{{-- Installed on the home screen it runs without Safari's chrome.
     The file sits beside the folder, not inside one called admin-phone: a real
     directory of that name would shadow the route, because Apache only hands a
     request to Laravel when no such folder exists. --}}
<link rel="manifest" href="{{ asset('admin-phone.webmanifest') }}">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Nefis">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">
<meta name="theme-color" content="#FBF4EA" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#17110D" media="(prefers-color-scheme: dark)">

<link rel="stylesheet" href="{{ asset('css/admin-phone.css') }}?v={{ \App\Support\Assets::version('css/admin-phone.css') }}">
<meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body>

<header class="ph-top">
  @hasSection('back')
    <a class="ph-back" href="@yield('back')" aria-label="Geri">‹</a>
  @endif
  <div class="grow">
    <h1>@yield('heading', 'Nefis admin')</h1>
    @hasSection('sub')<div class="sub">@yield('sub')</div>@endif
  </div>
  @yield('top-right')
</header>

@yield('chips')

<main class="ph-wrap">
  @php $flash = session('phone.flash'); @endphp
  @if($flash)
    <div class="ph-flash" role="status">
      <b>{{ $flash['title'] }}</b>
      {{ $flash['body'] ?? '' }}
      @if(! empty($flash['whatsapp']))
        · <a href="{{ $flash['whatsapp'] }}" target="_blank" rel="noopener">WhatsApp-a yaz ↗</a>
      @endif
    </div>
  @endif

  @if($errors->any())
    <div class="ph-err">
      @foreach($errors->all() as $message)<div>{{ $message }}</div>@endforeach
    </div>
  @endif

  @yield('content')
</main>

@php
  $owner = auth()->user()?->isAdmin();
  $tab = trim($__env->yieldContent('tab'));
@endphp
<nav class="ph-tabs" aria-label="Bölmələr">
  <a class="ph-tab {{ $tab === 'orders' ? 'on' : '' }}" href="{{ route('phone.orders.index') }}">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M6 2h9l5 5v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Z"/><path d="M14 2v6h6"/><path d="M8 13h8M8 17h5"/>
    </svg>
    Sifarişlər
  </a>
  {{-- The board belongs to everyone who works here, not only to the owner. --}}
  <a class="ph-tab {{ $tab === 'tasks' ? 'on' : '' }}" href="{{ route('phone.tasks.index') }}">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <rect x="3.5" y="4.5" width="17" height="16" rx="2.5"/><path d="M8 3v3M16 3v3"/><path d="m8.5 13.5 2.2 2.2 4.3-4.6"/>
    </svg>
    İşlər
    @php $late = \App\Models\Task::tasks()->open()->whereNotNull('due_at')->where('due_at', '<', now())->count(); @endphp
    @if($late)<span class="ph-badge">{{ $late }}</span>@endif
  </a>
  @if($owner)
    <a class="ph-tab {{ $tab === 'money' ? 'on' : '' }}" href="{{ route('phone.money.index') }}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <rect x="2.5" y="5.5" width="19" height="13" rx="2.5"/><circle cx="12" cy="12" r="2.8"/><path d="M6 9.5v5M18 9.5v5"/>
      </svg>
      Kassa
    </a>
    <a class="ph-tab {{ $tab === 'stock' ? 'on' : '' }}" href="{{ route('phone.stock.index') }}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M3 8.5 12 4l9 4.5-9 4.5-9-4.5Z"/><path d="M3 12.5 12 17l9-4.5"/><path d="M3 16.5 12 21l9-4.5"/>
      </svg>
      Anbar
    </a>
    <a class="ph-tab {{ $tab === 'live' ? 'on' : '' }}" href="{{ route('phone.live.index') }}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <rect x="2.5" y="6" width="13" height="12" rx="2.5"/><path d="m15.5 10.5 6-3.2v9.4l-6-3.2z"/>
      </svg>
      Canlı
    </a>
  @endif
</nav>

<script>
  /* A sheet is opened by anything that names one, and closed by tapping the
     dark part outside it — the gesture a phone expects. */
  document.addEventListener('click', function (e) {
    var open = e.target.closest('[data-sheet]');
    if (open) {
      e.preventDefault();
      var el = document.getElementById(open.dataset.sheet);
      if (el && typeof el.showModal === 'function') el.showModal();
      return;
    }
    if (e.target.closest('[data-close-sheet]')) {
      e.preventDefault();
      var sheet = e.target.closest('dialog');
      if (sheet) sheet.close();
    }
  });
  document.querySelectorAll('dialog.sheet').forEach(function (d) {
    d.addEventListener('click', function (e) { if (e.target === d) d.close(); });
  });

  /* A tap that changes an order must not become two because the phone lost
     the network for a second and he pressed again. */
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
@yield('page-script')
<script defer src="{{ asset('js/date-picker.js') }}?v={{ \App\Support\Assets::version('js/date-picker.js') }}"></script>
<script defer src="{{ asset('js/select-picker.js') }}?v={{ \App\Support\Assets::version('js/select-picker.js') }}"></script>
</body>
</html>
