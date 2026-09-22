@extends('layouts.app')

@section('title', 'Sifarişi Tamamla — Nefis Şokolad Evi')
@section('robots', 'noindex, nofollow')

{{-- The map services check where their requests come from. --}}
@section('referrer', 'strict-origin-when-cross-origin')

@section('page_style')
  .dlv-grid{ display:grid; gap:.6rem; margin-top:.4rem; }
  .dlv-card{ position:relative; display:flex; flex-direction:column; gap:.2rem; padding:.8rem 1rem; border:1.5px solid var(--line); border-radius:.9rem;
    background:var(--paper); cursor:pointer; margin:0; font-weight:400; transition:border-color .15s, box-shadow .15s; }
  .dlv-card:hover{ border-color:var(--gold); }
  .dlv-card input{ position:absolute; opacity:0; pointer-events:none; }
  .dlv-card:has(input:checked){ border-color:var(--gold); box-shadow:0 0 0 3px var(--ring); }
  .dlv-card:has(input:focus-visible){ outline:2px solid var(--gold); outline-offset:2px; }
  .dlv-top{ display:flex; justify-content:space-between; gap:1rem; align-items:baseline; color:var(--cocoa); }
  .dlv-top span{ color:var(--gold-deep); font-weight:700; white-space:nowrap; }
  .dlv-desc{ font-size:.8125rem; color:var(--cocoa-soft); }
  .dlv-sum{ border:1px solid var(--line); border-radius:.9rem; padding:.75rem 1rem; display:flex; flex-direction:column; gap:.35rem; margin:1rem 0; }
  .dlv-sum div{ display:flex; justify-content:space-between; gap:1rem; color:var(--cocoa-soft); }
  .dlv-sum .total{ color:var(--cocoa); font-weight:700; font-size:1.0625rem; border-top:1px solid var(--line); padding-top:.45rem; margin-top:.1rem; }

  /* ---------- the delivery map, in the page's own colours ---------- */
  .map-tools{ display:flex; gap:.5rem; margin:.4rem 0 .6rem; }
  .map-search{ position:relative; z-index:5; flex:1; min-width:0; }
  .map-search input{ width:100%; }
  .map-btn{ flex:none; display:inline-flex; align-items:center; gap:.4rem; padding:0 .9rem; border:1px solid var(--line); border-radius:.75rem;
    background:var(--paper); color:var(--cocoa); font-size:.875rem; cursor:pointer; white-space:nowrap; }
  .map-btn:hover{ border-color:var(--gold); color:var(--gold-deep); }
  .map-results{ position:absolute; z-index:1000; left:0; right:0; top:calc(100% + .3rem); margin:0; padding:.3rem; list-style:none;
    background:var(--paper); border:1px solid var(--line); border-radius:.75rem; box-shadow:0 14px 34px -14px rgba(0,0,0,.55); max-height:16rem; overflow:auto; }
  .map-results li{ padding:.55rem .7rem; border-radius:.5rem; font-size:.875rem; color:var(--cocoa); cursor:pointer; line-height:1.35; }
  .map-results li:hover, .map-results li.on{ background:var(--cream-2, rgba(0,0,0,.05)); color:var(--gold-deep); }
  .map-results li.none{ color:var(--cocoa-soft); cursor:default; }
  /* Its own stacking context: the map's controls never cover the search list. */
  .map-box{ position:relative; z-index:0; isolation:isolate; height:20rem; border-radius:.9rem; overflow:hidden; border:1px solid var(--line); background:var(--cream-2, #f3eadc);
    box-shadow:inset 0 0 0 1px rgba(0,0,0,.04); }
  /* OpenStreetMap's tiles turned into the site's dark brown, or its cream. */
  .map-box.map-dark .leaflet-tile-pane{ filter:invert(1) hue-rotate(180deg) brightness(.82) contrast(.92) sepia(.45) saturate(1.25); }
  .map-box:not(.map-dark) .leaflet-tile-pane{ filter:sepia(.28) saturate(.9) brightness(1.02); }
  .map-box .leaflet-container{ background:transparent; font:inherit; }
  .map-box .leaflet-control-zoom{ border:none; box-shadow:0 6px 18px -8px rgba(0,0,0,.5); border-radius:.6rem; overflow:hidden; }
  .map-box .leaflet-control-zoom a{ background:var(--paper); color:var(--cocoa); border-bottom-color:var(--line); }
  .map-box .leaflet-control-zoom a:hover{ background:var(--cream-2, #f3eadc); color:var(--gold-deep); }
  .map-box .leaflet-control-attribution{ background:rgba(0,0,0,.35); color:rgba(255,255,255,.75); font-size:10px; border-radius:.4rem 0 0 0; }
  .map-box .leaflet-control-attribution a{ color:inherit; }
  .map-box:not(.map-dark) .leaflet-control-attribution{ background:rgba(255,253,249,.75); color:#5c4330; }
  .map-pin{ background:none; border:none; }
  .map-pin svg{ filter:drop-shadow(0 4px 6px rgba(0,0,0,.45)); }
  .map-hint{ font-size:.8125rem; color:var(--cocoa-soft); margin-top:.45rem; }
  .map-hint.ok{ color:var(--gold-deep); }
  .map-hint.err{ color:var(--red, #c0392b); }
  @media (max-width:520px){ .map-btn span{ display:none; } .map-box{ height:17rem; } }
@endsection

@section('page_script')
<script>
/* Show only the fields the chosen delivery needs, require only those, and
   keep the total up to date. */
(function(){
  var radios = Array.prototype.slice.call(document.querySelectorAll('input[name="delivery_method_id"]'));
  if (!radios.length) return;
  var groups = Array.prototype.slice.call(document.querySelectorAll('.dlv-fields'));
  var sum = document.getElementById('dlv-sum');
  var items = parseFloat(sum.dataset.items) || 0;
  function fmt(v){ v = Math.round(v * 100) / 100; return (v % 1 === 0 ? v.toFixed(0) : v.toFixed(2)) + ' ₼'; }
  function update(){
    var picked = radios.filter(function(r){ return r.checked; })[0];
    var type = picked ? picked.dataset.type : null;
    groups.forEach(function(g){
      var on = g.dataset.for === type;
      g.hidden = !on;
      g.querySelectorAll('input, select').forEach(function(el){ el.disabled = !on; el.required = on && !el.hasAttribute('data-optional'); });
    });
    var price = picked ? parseFloat(picked.dataset.price) || 0 : 0;
    document.getElementById('sum-delivery').textContent = picked ? (price > 0 ? fmt(price) : 'Pulsuz') : 'seçilməyib';
    var total = items + price;
    document.getElementById('sum-grand').textContent = total > 0 ? fmt(total) : '—';
  }
  radios.forEach(function(r){ r.addEventListener('change', update); });
  update();
})();
</script>
<script src="{{ asset('js/map-picker.js') }}"></script>
<script>
/* The door-delivery map: loaded only once that way is chosen. A point is
   required while the map works; the address fills itself in from it. */
(function(){
  var box = document.getElementById('dlv-map');
  if (!box) return;
  var lat = document.getElementById('delivery_lat'), lng = document.getElementById('delivery_lng');
  var address = document.getElementById('delivery_address');
  var hint = document.getElementById('map-hint');
  var q = document.getElementById('map-q'), results = document.getElementById('map-results');
  var picker = null, loading = null, typedByHand = !!address.value, mapFailed = false;
  var bounds = JSON.parse(box.dataset.bounds);

  function say(text, cls){ hint.textContent = text; hint.className = 'map-hint' + (cls ? ' ' + cls : ''); }
  function inBaku(a, b){ return a >= bounds.south && a <= bounds.north && b >= bounds.west && b <= bounds.east; }

  function picked(a, b){
    if (!inBaku(a, b)) { say('Bu yer Bakıdan kənardadır — qapıya çatdırılma yalnız Bakı daxilindədir.', 'err'); return; }
    lat.value = a.toFixed(7); lng.value = b.toFixed(7);
    say('Yer seçildi. Ünvanı yoxlayın, mənzil və mərtəbəni əlavə edin.', 'ok');
    picker.reverse(a, b).then(function(text){
      if (text && !typedByHand) { address.value = text; }
    });
  }

  function ensure(){
    if (picker || loading) return loading;
    var value = lat.value && lng.value ? { lat: +lat.value, lng: +lng.value } : null;
    loading = NefisMapPicker.mount(box, {
      google: box.dataset.google || null,
      bounds: bounds, center: JSON.parse(box.dataset.center), value: value,
      reverseUrl: box.dataset.reverse, searchUrl: box.dataset.search,
      onPick: picked
    }).then(function(p){ picker = p; setTimeout(p.refresh, 50); return p; })
      .catch(function(){ mapFailed = true; box.hidden = true; say('Xəritə açılmadı — ünvanı aşağıda yazın.', 'err'); });
    return loading;
  }

  document.querySelectorAll('input[name="delivery_method_id"]').forEach(function(r){
    r.addEventListener('change', function(){ if (r.checked && r.dataset.type === 'door') ensure(); });
    if (r.checked && r.dataset.type === 'door') ensure();
  });
  address.addEventListener('input', function(){ typedByHand = address.value.trim() !== ''; });

  /* Search: Enter or a pause after typing; the choice drops the pin there. */
  var timer = null, found = [];
  function showResults(list){
    found = list;
    results.innerHTML = list.length
      ? list.map(function(r, i){ return '<li data-i="' + i + '">' + r.label.replace(/[&<>]/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;'}[c]; }) + '</li>'; }).join('')
      : '<li class="none">Heç nə tapılmadı</li>';
    results.hidden = false;
  }
  function runSearch(){
    var text = q.value.trim();
    if (text.length < 3 || !picker) { results.hidden = true; return; }
    picker.search(text).then(showResults);
  }
  q.addEventListener('input', function(){ clearTimeout(timer); timer = setTimeout(runSearch, 700); });
  q.addEventListener('keydown', function(e){ if (e.key === 'Enter') { e.preventDefault(); clearTimeout(timer); runSearch(); } });
  results.addEventListener('click', function(e){
    var li = e.target.closest('li[data-i]');
    if (!li) return;
    var r = found[+li.dataset.i];
    results.hidden = true;
    q.value = r.label;
    typedByHand = false;
    picker.place(r.lat, r.lng, true);
    picked(r.lat, r.lng);
  });
  document.addEventListener('click', function(e){ if (!e.target.closest('.map-search')) results.hidden = true; });

  document.getElementById('map-locate').addEventListener('click', function(){
    if (!navigator.geolocation) { say('Brauzeriniz yerinizi göstərə bilmir.', 'err'); return; }
    say('Yeriniz müəyyən edilir…');
    (ensure() || Promise.resolve()).then(function(){
      navigator.geolocation.getCurrentPosition(function(pos){
        var a = pos.coords.latitude, b = pos.coords.longitude;
        if (!inBaku(a, b)) { say('Siz Bakıdan kənardasınız — yeri xəritədə əl ilə seçin.', 'err'); return; }
        typedByHand = false;
        picker.place(a, b, true);
        picked(a, b);
      }, function(){ say('Yerinizə icazə verilmədi — xəritədə özünüz seçin.', 'err'); }, { enableHighAccuracy: true, timeout: 10000 });
    });
  });

  document.getElementById('checkout-form').addEventListener('submit', function(e){
    var door = document.querySelector('input[name="delivery_method_id"]:checked');
    if (door && door.dataset.type === 'door' && !mapFailed && !lat.value) {
      e.preventDefault();
      say('Çatdırılma yerini xəritədə seçin.', 'err');
      box.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  });
})();
</script>
@endsection

@section('content')
<section class="page-hero" style="padding-bottom:0;">
  <div class="wrap">
    <span class="eyebrow" style="justify-content:center;">Son Addım</span>
    <h1>Sifarişi Tamamlayın</h1>
    <p class="lede" style="margin-inline:auto;">Sifarişiniz göndəriləcək, biz tezliklə sizinlə əlaqə saxlayıb təsdiqləyəcəyik.</p>
  </div>
</section>

<section>
  <div class="wrap" style="max-width:44rem;">
    @if($errors->any())
      <div class="alert alert-error">
        <ul style="margin:0; padding-left:1.1rem;">
          @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <div class="cart-list">
      @foreach($items as $item)
        @php $texts = array_filter($item['custom_texts'] ?? []); $isLetter = \App\Support\Cart::isLetter($item); $isLive = \App\Support\Cart::isLive($item); @endphp
        <div class="cart-row">
          <div class="thumb">
            @if($isLive)
              <img src="{{ \App\Support\Media::url($item['ar']['image'] ?? null) }}" alt="Canlı şəkil">
            @elseif($isLetter)
              <div class="thumb-polaroid">@include('partials.polaroid', ['photo' => \App\Support\Media::url($item['letter']['photo'] ?? null), 'text' => $item['letter']['text'] ?? null])</div>
            @elseif(! empty($item['photo_paths']))
              <img src="{{ \App\Support\Media::url($item['photo_paths'][0]) }}" alt="Yüklənmiş şəkil">
              @if(count($item['photo_paths']) > 1)
                <span class="thumb-more">+{{ count($item['photo_paths']) - 1 }}</span>
              @endif
            @else
              <img src="{{ \App\Support\Media::url($item['product']->catalogImage()) }}" alt="{{ $item['product']->name }}">
            @endif
          </div>
          <div class="info">
            <h3>{{ $isLive ? 'Canlı şəkil' : ($isLetter ? 'Polaroid məktub' : $item['product']->name) }}</h3>
            <p>
              @if($texts) "{{ implode('" · "', $texts) }}" &middot; @endif
              {{ $item['quantity'] }} ədəd
              @php $unit = \App\Support\Cart::unitPrice($item, $item['product']); @endphp
              @if($unit > 0) &middot; {{ \App\Support\Price::format($unit * $item['quantity']) }} @endif
            </p>
            @if(! empty($item['chocolate']))
              <p style="margin-top:.2rem;">🍫 {{ $item['chocolate']['name'] }}</p>
            @endif
            @if(! empty($item['wrapping']))
              <p style="margin-top:.2rem;">🎁 Qablaşdırma: {{ $item['wrapping']['name'] }}</p>
            @endif
            @if(! empty($item['ar']) && ! $isLive)
              <p style="margin-top:.2rem;">🎬 Canlı şəkil (AR)</p>
            @endif
            @if(! empty($item['letter']) && ! $isLetter)
              <p style="margin-top:.2rem;">💌 Polaroid məktub</p>
            @endif
          </div>
        </div>
      @endforeach
    </div>

    <div class="auth-card">
      <form method="POST" action="{{ route('checkout.store') }}" id="checkout-form">
        @csrf
        @if($methods->isNotEmpty())
          {{-- How it reaches the customer; each way asks for what it needs. --}}
          <div class="field">
            <label>Çatdırılma üsulu</label>
            <div class="dlv-grid">
              @foreach($methods as $m)
                <label class="dlv-card">
                  <input type="radio" name="delivery_method_id" value="{{ $m->id }}" data-type="{{ $m->type }}" data-price="{{ $m->price }}" required
                         @checked((string) old('delivery_method_id', $methods->count() === 1 ? $m->id : null) === (string) $m->id)>
                  <span class="dlv-top">
                    <b>{{ $m->name }}</b>
                    <span>{{ $m->price > 0 ? \App\Support\Price::format($m->price) : 'Pulsuz' }}</span>
                  </span>
                  @if($m->description)<span class="dlv-desc">{{ $m->description }}</span>@endif
                </label>
              @endforeach
            </div>
          </div>

          <div class="dlv-fields" data-for="post" hidden>
            <div class="field">
              <label for="recipient_name">Ad və soyad</label>
              <input type="text" id="recipient_name" name="recipient_name" value="{{ old('recipient_name', auth()->user()->name) }}" placeholder="Məs. Aysel Məmmədova">
            </div>
            <div class="field">
              <label for="postal_index">Poçt şöbəsinin indeksi</label>
              <input type="text" id="postal_index" name="postal_index" value="{{ old('postal_index') }}" placeholder="Məs. AZ1000" maxlength="8" autocapitalize="characters">
            </div>
          </div>

          <div class="dlv-fields" data-for="door" hidden>
            {{-- The spot on the map; the address fills itself in from it. --}}
            <div class="field">
              <label>Çatdırılma yeri (xəritədə seçin)</label>
              <div class="map-tools">
                <div class="map-search">
                  <input type="search" id="map-q" placeholder="Ünvan və ya yer axtarın…" autocomplete="off" data-optional>
                  <ul class="map-results" id="map-results" hidden></ul>
                </div>
                <button type="button" class="map-btn" id="map-locate" title="Olduğum yeri göstər">
                  <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="3.5"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/><circle cx="12" cy="12" r="8"/></svg>
                  <span>Mənim yerim</span>
                </button>
              </div>
              <div class="map-box" id="dlv-map"
                   data-google="{{ \App\Models\DeliveryMethod::googleMapsKey() }}"
                   data-bounds='@json(\App\Models\DeliveryMethod::BAKU_BOUNDS)'
                   data-center='@json(\App\Models\DeliveryMethod::BAKU_CENTER)'
                   data-reverse="{{ route('map.reverse') }}" data-search="{{ route('map.search') }}"></div>
              <p class="map-hint" id="map-hint">Xəritəyə toxunun və ya işarəni sürüşdürün — ünvan özü yazılacaq.</p>
              <input type="hidden" name="delivery_lat" id="delivery_lat" value="{{ old('delivery_lat') }}" data-optional>
              <input type="hidden" name="delivery_lng" id="delivery_lng" value="{{ old('delivery_lng') }}" data-optional>
            </div>
            <div class="field">
              <label for="delivery_address">Ünvan (yalnız Bakı)</label>
              <input type="text" id="delivery_address" name="delivery_address" value="{{ old('delivery_address') }}" placeholder="Küçə, ev — mənzil və mərtəbəni əlavə edin">
            </div>
          </div>

          @php $metro = $methods->firstWhere('type', \App\Models\DeliveryMethod::METRO); @endphp
          @if($metro)
            <div class="dlv-fields" data-for="metro" hidden>
              <div class="field">
                <label for="metro_station">Metro stansiyası</label>
                <select id="metro_station" name="metro_station">
                  <option value="">Stansiyanı seçin</option>
                  @foreach($metro->stations() as $station)
                    <option value="{{ $station }}" @selected(old('metro_station') === $station)>{{ $station }}</option>
                  @endforeach
                </select>
              </div>
            </div>
          @endif
        @else
          <div class="field">
            <label for="delivery_address">Çatdırılma Ünvanı</label>
            <input type="text" id="delivery_address" name="delivery_address" value="{{ old('delivery_address') }}" required placeholder="Şəhər, rayon, ünvan">
          </div>
        @endif

        <div class="field">
          <label for="contact_phone">Telefon nömrəsi</label>
          <input type="tel" id="contact_phone" name="contact_phone" value="{{ old('contact_phone', auth()->user()->phone) }}" required placeholder="+994 XX XXX XX XX">
        </div>
        <div class="field">
          <label for="note">Əlavə Qeyd (istəyə bağlı)</label>
          <textarea id="note" name="note" rows="3">{{ old('note') }}</textarea>
        </div>

        <div class="dlv-sum" id="dlv-sum" data-items="{{ $itemsTotal }}">
          <div><span>Məhsullar</span><span>{{ $itemsTotal > 0 ? \App\Support\Price::format($itemsTotal) : '—' }}</span></div>
          @if($methods->isNotEmpty())
            <div><span>Çatdırılma</span><span id="sum-delivery">seçilməyib</span></div>
          @endif
          <div class="total"><span>Cəmi</span><span id="sum-grand">{{ $itemsTotal > 0 ? \App\Support\Price::format($itemsTotal) : '—' }}</span></div>
        </div>

        <button type="submit" class="btn btn-primary btn-block">Sifarişi Göndər</button>
      </form>
    </div>
  </div>
</section>
@endsection
