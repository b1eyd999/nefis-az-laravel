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
    .ku-ord{ font-size:.85rem; padding:.35rem 0; border-top:1px solid rgba(128,128,128,.15); }
    .ku-ord a{ font-weight:600; text-decoration:underline; }
    .ku-empty{ font-size:.875rem; opacity:.7; line-height:1.6; }
    .ku-pin{ font-size:1.35rem; line-height:1; filter:drop-shadow(0 2px 3px rgba(0,0,0,.45)); }
  </style>

<div>

  <div class="ku-wrap">
    <div id="ku-map" wire:ignore data-url="{{ route('couriers.live') }}"></div>
    <div id="ku-list">
      <div class="ku-empty">Yüklənir…</div>
    </div>
  </div>

  <p class="ku-empty" style="margin-top:1rem">
    Kuryer lokasiyanı özü yandırır — telefonunda «Kuryer» səhifəsində. Siz onu yalnız yandırılı olanda
    canlı görürsünüz; söndürüləndə son məlum yer və saat qalır. İzlər bir neçə saatdan sonra silinir.
  </p>

</div>

<script defer src="{{ asset('js/courier-map.js') }}?v={{ \App\Support\Assets::version('js/courier-map.js') }}"></script>
</x-filament-panels::page>
