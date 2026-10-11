<x-filament-panels::page>
  <style>
    .ku-wrap{ display:grid; gap:1rem; grid-template-columns:1fr; }
    @media (min-width:1024px){ .ku-wrap{ grid-template-columns:1.4fr 1fr; align-items:start; } }
    #ku-map{ height:60vh; min-height:22rem; border-radius:.9rem; overflow:hidden;
      border:1px solid rgba(128,128,128,.25); background:rgba(128,128,128,.06); }
    #ku-map .leaflet-container{ font:inherit; background:transparent; }
    .ku-card{ border:1px solid rgba(128,128,128,.25); border-radius:.9rem; padding:.9rem 1rem;
      background:rgba(128,128,128,.04); margin-bottom:.75rem; }
    .ku-card h3{ font-size:1rem; font-weight:650; margin:0 0 .25rem; display:flex; gap:.5rem; align-items:center; }
    .ku-live{ display:inline-block; width:.55rem; height:.55rem; border-radius:50%; background:#16a34a;
      animation:kupulse 1.6s infinite; }
    .ku-off{ display:inline-block; width:.55rem; height:.55rem; border-radius:50%; background:rgba(128,128,128,.5); }
    @keyframes kupulse{ 0%,100%{opacity:1} 50%{opacity:.2} }
    .ku-meta{ font-size:.8rem; opacity:.7; }
    .ku-meta a{ text-decoration:underline; }
    .ku-ord{ font-size:.85rem; padding:.35rem 0; border-top:1px solid rgba(128,128,128,.15); }
    .ku-ord a{ font-weight:600; text-decoration:underline; }
    .ku-empty{ font-size:.875rem; opacity:.7; line-height:1.6; }
    .ku-pin{ font-size:1.35rem; line-height:1; filter:drop-shadow(0 2px 3px rgba(0,0,0,.45)); }
    /* Where this one is headed. The whole point of the page, so it is the one
       block with a colour of its own: blue while he is simply next in line,
       green from the moment he says he has set off. */
    .ku-head{ margin-top:.5rem; padding:.55rem .7rem; border-radius:.6rem;
      border-left:3px solid #2563eb; background:rgba(37,99,235,.08); }
    .ku-head.live{ border-left-color:#16a34a; background:rgba(22,163,74,.1); }
    .ku-head-to{ font-size:.9rem; line-height:1.45; }
    .ku-head-to b{ font-weight:650; }
    .ku-go{ display:flex; flex-wrap:wrap; gap:.25rem .9rem; margin-top:.35rem; font-size:.8rem; }
    .ku-go a{ font-weight:600; text-decoration:underline; }
    /* In, but with nothing in his hands — still listed, still callable. */
    .ku-card.ku-free{ opacity:.72; }
    .ku-none{ font-size:.85rem; opacity:.7; margin-top:.4rem; }
    .ku-more{ font-size:.75rem; text-transform:uppercase; letter-spacing:.04em;
      opacity:.6; margin-top:.6rem; }
    @media (min-width:1024px){ #ku-list{ max-height:60vh; overflow:auto; } }
  </style>

<div>

  <div class="ku-wrap">
    <div id="ku-map" wire:ignore data-url="{{ route('couriers.live') }}"></div>
    <div id="ku-list">
      <div class="ku-empty">Yüklənir…</div>
    </div>
  </div>

  <p class="ku-empty" style="margin-top:1rem">
    Bütün kuryerlər adları ilə burada görünür — əli dolu olanlar yuxarıda, boş olanlar aşağıda.
    Hər adın altında onun getdiyi ünvan yazılır: «Yolda» — telefonunda «Yola düşdüm» deyib,
    «Növbəti» — hələ deməyib. Xəritədə kəsik mavi xətt kuryerdən gedəcəyi qapıya çəkilir.
    Lokasiyanı kuryer özü yandırır; söndürüləndə son məlum yer və saat qalır,
    izlər bir neçə saatdan sonra silinir.
  </p>

</div>

<script defer src="{{ asset('js/courier-map.js') }}?v={{ \App\Support\Assets::version('js/courier-map.js') }}"></script>
</x-filament-panels::page>
