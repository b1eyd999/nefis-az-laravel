@extends('layouts.app')

@section('title', 'Hədiyyə qablaşdırması — Nefis Şokolad Evi')
@section('meta_description', 'Şokolad qutunuzu hədiyyə kağızına büküb lentlə bağlayırıq. Naxışları və qiymətləri burada görün.')

@section('page_style')
  .wr-group{ margin-bottom:3.5rem; }
  .wr-group:last-child{ margin-bottom:0; }
  .wr-head{ display:flex; align-items:center; gap:.9rem; margin-bottom:1.5rem; }
  .wr-head h2{ font-size:clamp(1.4rem,2.4vw,1.85rem); }
  .wr-badge{ background:var(--gold); color:#fff; font-weight:800; font-size:.95rem; padding:.25rem .8rem; border-radius:999px; }
  .wr-grid{ display:grid; gap:1.25rem; grid-template-columns:repeat(2,1fr); }
  @media (min-width:700px){ .wr-grid{ grid-template-columns:repeat(3,1fr); } }
  @media (min-width:1100px){ .wr-grid{ grid-template-columns:repeat(5,1fr); } }
  .wr-card{ position:relative; background:var(--paper); border:1px solid var(--line); border-radius:var(--radius-sm); overflow:hidden;
    display:flex; flex-direction:column; padding:0; text-align:left; color:inherit; cursor:zoom-in;
    transition:transform .4s var(--ease), box-shadow .4s var(--ease); }
  .wr-card:focus-visible{ outline:2px solid var(--gold); outline-offset:3px; }
  .wr-360{ position:absolute; top:.6rem; right:.6rem; z-index:2; background:var(--glass); color:var(--cocoa); font-size:.68rem; font-weight:800;
    letter-spacing:.04em; padding:.25rem .55rem; border-radius:999px; }
  .wr-card:hover{ transform:translateY(-6px); box-shadow:var(--shadow-sm); }
  .wr-card .gift{ background:radial-gradient(ellipse at 50% 35%, var(--cream-2), transparent 72%); }
  .wr-body{ padding:.8rem 1rem 1rem; display:flex; justify-content:space-between; align-items:baseline; gap:.5rem; border-top:1px solid var(--line); }
  .wr-name{ font-size:.95rem; font-weight:700; }
  .wr-rib{ font-size:.8rem; color:var(--cocoa-soft); white-space:nowrap; }
  .wr-cta{ text-align:center; margin-top:3.5rem; }
  .wr-cta p{ color:var(--cocoa-soft); margin-bottom:1rem; }
@endsection

@section('content')
  <section class="page-hero">
    <div class="wrap">
      <span class="eyebrow">Hədiyyə üçün</span>
      <h1>Qablaşdırma</h1>
      <p class="lede">Qutunuzu seçdiyiniz kağıza büküb lentlə bağlayırıq — açılana qədər sürpriz qalsın.</p>
    </div>
  </section>

  <section style="padding-top:0;">
    <div class="wrap">
      @forelse($groups as $price => $wraps)
        <div class="wr-group">
          <div class="wr-head">
            <span class="wr-badge">{{ \App\Support\Price::format((float) $price) }}</span>
            <h2>{{ $wraps->count() }} naxış</h2>
          </div>
          <div class="wr-grid">
            @foreach($wraps as $w)
              <button type="button" class="wr-card" data-gift-open data-name="{{ $w['name'] }}"
                      data-price="{{ \App\Support\Price::format($w['price']) }}" data-ribbon-label="{{ \App\Models\Wrapping::RIBBONS[$w['ribbon']] ?? '' }}"
                      aria-label="{{ $w['name'] }} — hər tərəfdən bax">
                <span class="wr-360">360°</span>
                @include('partials.gift-box', ['wrap' => $w])
                <span class="wr-body">
                  <span class="wr-name">{{ $w['name'] }}</span>
                  <span class="wr-rib">{{ \App\Models\Wrapping::RIBBONS[$w['ribbon']] ?? '' }}</span>
                </span>
              </button>
            @endforeach
          </div>
        </div>
      @empty
        <div class="empty-note"><p>Qablaşdırmalar tezliklə əlavə olunacaq.</p></div>
      @endforelse

      <div class="wr-cta">
        <p>Qutuya hər tərəfdən baxmaq üçün üzərinə klikləyin. Qablaşdırmanı dizaynı seçəndə, şokoladdan sonra seçirsiniz.</p>
        <a href="{{ lroute('designs.index') }}" class="btn btn-primary">Dizayn seç</a>
      </div>
    </div>
  </section>
@endsection

@section('page_script')
<script src="{{ asset('js/wrap-render.js') }}"></script>
<script src="{{ asset('js/gift-box.js') }}"></script>
@endsection
