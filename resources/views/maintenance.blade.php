<!DOCTYPE html>
<html lang="az">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>{{ __('Texniki işlər') }} — Nefis Şokolad Evi</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=Inter:wght@400;500&display=swap" rel="stylesheet">
<style>
  :root{ --bg:#17110D; --paper:#1F1712; --cocoa:#F3E6D6; --soft:rgba(243,230,214,.72); --gold:#D6A35A; --line:rgba(243,230,214,.12); }
  @media (prefers-color-scheme: light){
    :root{ --bg:#F7F1E8; --paper:#FFFDF9; --cocoa:#3A2617; --soft:rgba(58,38,23,.72); --gold:#C08A3E; --line:rgba(58,38,23,.14); }
  }
  *{ box-sizing:border-box; }
  body{ margin:0; min-height:100vh; display:grid; place-items:center; padding:24px 16px; background:var(--bg); color:var(--cocoa);
    font-family:Inter, system-ui, sans-serif; background-image:radial-gradient(60rem 30rem at 80% -10%, rgba(214,163,90,.18), transparent 60%); }
  .card{ max-width:32rem; width:100%; text-align:center; background:var(--paper); border:1px solid var(--line); border-radius:1.5rem; padding:2.5rem 1.75rem;
    box-shadow:0 30px 80px -40px rgba(0,0,0,.6); }
  img{ height:2.6rem; margin-bottom:1.5rem; }
  .ico{ font-size:2.5rem; margin-bottom:.75rem; }
  h1{ font-family:'Playfair Display', Georgia, serif; font-weight:600; font-size:1.9rem; margin:0 0 .75rem; }
  p{ color:var(--soft); line-height:1.6; margin:0 0 1.5rem; white-space:pre-line; }
  a{ display:inline-block; color:var(--gold); text-decoration:none; border:1px solid var(--line); border-radius:999px; padding:.6rem 1.2rem; font-weight:500; }
  a:hover{ border-color:var(--gold); }
</style>
</head>
<body>
  <main class="card">
    <img src="/images/logo.svg" alt="Nefis">
    <div class="ico">🍫</div>
    <h1>{{ __('Tezliklə qayıdırıq') }}</h1>
    <p>{{ $message }}</p>
    <a href="https://www.instagram.com/nefis.az" target="_blank" rel="noopener">{{ __('Instagramda yazın') }}</a>
  </main>
</body>
</html>
