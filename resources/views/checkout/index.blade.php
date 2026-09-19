@extends('layouts.app')

@section('title', 'Sifarişi Tamamla — Nefis Şokolad Evi')

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
      g.querySelectorAll('input, select').forEach(function(el){ el.disabled = !on; el.required = on; });
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
        @php $texts = array_filter($item['custom_texts'] ?? []); @endphp
        <div class="cart-row">
          <div class="thumb">
            @if(! empty($item['photo_paths']))
              <img src="{{ \App\Support\Media::url($item['photo_paths'][0]) }}" alt="Yüklənmiş şəkil">
              @if(count($item['photo_paths']) > 1)
                <span class="thumb-more">+{{ count($item['photo_paths']) - 1 }}</span>
              @endif
            @else
              <img src="{{ \App\Support\Media::url($item['product']->catalogImage()) }}" alt="{{ $item['product']->name }}">
            @endif
          </div>
          <div class="info">
            <h3>{{ $item['product']->name }}</h3>
            <p>
              @if($texts) "{{ implode('" · "', $texts) }}" &middot; @endif
              {{ $item['quantity'] }} ədəd
              @php $unit = \App\Support\Cart::unitPrice($item, $item['product']); @endphp
              @if($unit > 0) &middot; {{ \App\Support\Price::format($unit * $item['quantity']) }} @endif
            </p>
            @if(! empty($item['chocolate']))
              <p style="margin-top:.2rem;">🍫 {{ $item['chocolate']['name'] }}</p>
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
            <div class="field">
              <label for="delivery_address">Ünvan (yalnız Bakı)</label>
              <input type="text" id="delivery_address" name="delivery_address" value="{{ old('delivery_address') }}" placeholder="Rayon, küçə, ev, mənzil">
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
