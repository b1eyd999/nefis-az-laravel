{{-- "No signal."

     Everything on this page is in the page: its colours, its type, its one
     picture. It is shown at the one moment nothing can be fetched, so a
     stylesheet or a web font would mean a blank screen with a sentence on it.
     It is also the page the worker keeps for precisely that moment. --}}
<!DOCTYPE html>
<html lang="{{ \App\Support\Locale::tag() }}">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<title>{{ __('İnternet yoxdur') }} — Nefis.az</title>
<meta name="theme-color" content="#FBF4EA">
<style>
  :root{ --cream:#FBF4EA; --paper:#FFFDF9; --cocoa:#3A2617; --soft:rgba(58,38,23,.7);
    --gold:#C08A3E; --line:rgba(58,38,23,.14); }
  @media (prefers-color-scheme: dark){
    :root{ --cream:#17110D; --paper:#1F1712; --cocoa:#F3E6D6; --soft:rgba(243,230,214,.7);
      --gold:#D6A35A; --line:rgba(243,230,214,.12); }
  }
  *{ box-sizing:border-box; }
  body{ margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;
    padding:2rem 1.25rem calc(2rem + env(safe-area-inset-bottom));
    background:var(--cream); color:var(--cocoa);
    font:16px/1.55 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, system-ui, sans-serif;
    text-align:center; }
  .card{ max-width:22rem; }
  .mark{ width:4.5rem; height:4.5rem; border-radius:1.35rem; margin:0 auto 1.25rem; display:block;
    box-shadow:0 14px 30px -18px rgba(58,38,23,.6); }
  h1{ font-size:1.4rem; margin:0 0 .5rem; letter-spacing:-.01em; }
  p{ margin:0 0 1.5rem; color:var(--soft); font-size:.95rem; }
  button{ font:inherit; font-weight:650; color:#fff; background:var(--gold); border:0;
    border-radius:999px; padding:.85rem 1.75rem; width:100%; }
  button:active{ transform:translateY(1px); }
  .again{ margin-top:.9rem; font-size:.85rem; color:var(--soft); }
  .again a{ color:inherit; }
</style>
</head>
<body>
  <div class="card">
    <img class="mark" src="{{ \App\Support\Assets::url('images/icon-192.png') }}" alt="Nefis.az" width="72" height="72">
    <h1>{{ __('İnternet yoxdur') }}</h1>
    <p>{{ __('Bağlantı qayıdan kimi hər şey yerindədir. Əvvəl açdığınız səhifələr indi də işləyir.') }}</p>
    <button type="button" onclick="location.reload()">{{ __('Yenidən cəhd et') }}</button>
    <div class="again"><a href="{{ url('/') }}">{{ __('Ana səhifə') }}</a></div>
  </div>
  <script>
    /* The moment the phone finds a signal, carry on where he was going. */
    window.addEventListener('online', function () { location.reload(); });
  </script>
</body>
</html>
