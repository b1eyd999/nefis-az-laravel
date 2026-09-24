@extends('layouts.app')

@section('title', __('Şokolad qutusu dizaynları — şəkilli fərdi hədiyyə | Nefis'))
@section('meta_description', 'Kinder, Milka, Love story, Netflix, Spotify və başqa şokolad qutusu dizaynları. Bəyəndiyinizi seçin, şəklinizi və sözünüzü əlavə edin — hədiyyə hazırdır.')

@push('jsonld')
  {{ \App\Support\Seo::jsonLd(['@graph' => [
      \App\Support\Seo::breadcrumbs([
          [__('Ana səhifə'), lroute('home')],
          [__('Dizaynlar'), lroute('designs.index')],
      ]),
      [
          '@type' => 'ItemList',
          'name' => __('Şokolad qutusu dizaynları'),
          'itemListElement' => $designs->flatten()->filter->isCustomizable()->values()
              ->map(fn ($d, $i) => [
                  '@type' => 'ListItem',
                  'position' => $i + 1,
                  'url' => lroute('products.customize', $d->slug),
                  'name' => $d->name,
              ])->all(),
      ],
  ]]) }}
@endpush

@section('page_style')
  .filter-bar{ display:flex; flex-wrap:wrap; gap:.6rem; justify-content:center; margin-bottom:3.5rem; }
  .chip{
    padding:.6rem 1.15rem; border-radius:999px; border:1px solid var(--line); background:var(--paper);
    font-size:.875rem; font-weight:600; color:var(--cocoa-soft);
    transition:background .25s, color .25s, border-color .25s, transform .25s var(--ease);
  }
  .chip:hover{ border-color:var(--gold); color:var(--cocoa); transform:translateY(-2px); }
  .chip.active{ background:var(--cocoa); color:var(--cream); border-color:var(--cocoa); }

  .cat-block{ margin-bottom:4rem; }
  .cat-block:last-child{ margin-bottom:0; }
  .cat-block[hidden]{ display:none; }
  .cat-head{ display:flex; align-items:baseline; gap:.75rem; margin-bottom:1.75rem; }
  .cat-head h2{ font-size:clamp(1.5rem,2.6vw,2rem); }
  .cat-count{ font-size:.8125rem; font-weight:600; color:var(--cocoa-faint); }

  .designs-grid{ display:grid; gap:1.25rem; grid-template-columns:repeat(2,1fr); }
  @media (min-width:700px){ .designs-grid{ grid-template-columns:repeat(3,1fr); } }
  @media (min-width:1100px){ .designs-grid{ grid-template-columns:repeat(4,1fr); } }

  .d-card{
    background:var(--paper); border:1px solid var(--line); border-radius:var(--radius-sm);
    overflow:hidden; cursor:zoom-in; padding:0; text-align:left; display:flex; flex-direction:column; color:inherit;
    transition:transform .4s var(--ease), box-shadow .4s var(--ease);
  }
  .d-card:hover{ transform:translateY(-6px); box-shadow:var(--shadow-sm); }
  .d-card:focus-visible{ outline:2px solid var(--gold); outline-offset:3px; }
  .d-card-media{ aspect-ratio:4/5; overflow:hidden; background:var(--cream-2); position:relative; }
  .d-card-media img{ width:100%; height:100%; object-fit:cover; transition:transform .6s var(--ease); }
  .d-card:hover .d-card-media img{ transform:scale(1.04); }
  .d-card-media .pill{
    position:absolute; top:.75rem; right:.75rem; background:var(--glass); color:var(--cocoa);
    font-size:.6875rem; font-weight:700; letter-spacing:.05em; text-transform:uppercase;
    padding:.28rem .6rem; border-radius:999px;
  }
  .d-card-body{ padding:.9rem 1rem 1.1rem; }
  .d-card-body h3{ font-family:var(--sans); font-size:.9375rem; font-weight:600; line-height:1.35; }
  .d-card-body span{ display:block; margin-top:.25rem; font-size:.75rem; color:var(--cocoa-faint); }

  .empty-note{ text-align:center; padding:4rem 1rem; color:var(--cocoa-soft); }

  /* ---------- lightbox ---------- */
  .lb{
    position:fixed; inset:0; z-index:120; background:rgba(28,16,8,.88); backdrop-filter:blur(6px);
    display:flex; align-items:center; justify-content:center; padding:1.25rem;
    opacity:0; visibility:hidden; transition:opacity .3s var(--ease), visibility .3s;
  }
  .lb.open{ opacity:1; visibility:visible; }
  .lb-inner{
    position:relative; background:var(--paper); border-radius:var(--radius); overflow:hidden;
    width:100%; max-width:54rem; max-height:90vh; display:grid; grid-template-rows:auto auto;
  }
  @media (min-width:800px){ .lb-inner{ grid-template-rows:none; grid-template-columns:1fr .78fr; } }
  .lb-inner [hidden]{ display:none !important; }
  .lb-img{ background:var(--cream-2); display:flex; align-items:center; justify-content:center; overflow:hidden; min-width:0; min-height:0; }
  .lb-img img{ width:100%; height:100%; max-height:52vh; object-fit:contain; }
  @media (min-width:800px){ .lb-img img{ max-height:90vh; } }
  .lb-side{ padding:1.75rem; display:flex; flex-direction:column; gap:.9rem; justify-content:center; min-width:0; }
  .lb-side h3{ font-size:1.375rem; }
  .lb-side .cat{ font-size:.8125rem; color:var(--gold-deep); font-weight:600; }
  .lb-side p{ font-size:.9375rem; color:var(--cocoa-soft); }
  .lb-side .soon{
    font-size:.875rem; color:var(--cocoa-soft); background:var(--cream); border:1px dashed var(--line);
    border-radius:.7rem; padding:.85rem 1rem;
  }
  .lb-close{
    position:absolute; top:.9rem; right:.9rem; z-index:2; width:2.5rem; height:2.5rem; border-radius:50%;
    background:var(--glass); border:1px solid var(--line); font-size:1rem; color:var(--cocoa);
    display:flex; align-items:center; justify-content:center;
  }
@endsection

@section('content')

  <section class="page-hero">
    <div class="wrap">
      <span class="eyebrow">{{ __('Kolleksiya') }}</span>
      <h1>{{ __('Dizaynlar') }}</h1>
      <p class="lede">Şokolad qutularından posterlərə qədər — bəyəndiyiniz dizaynı seçin, sonra öz şəklinizi və sözünüzü əlavə edin.</p>
      @if($gifts->isNotEmpty())
        <nav class="occ-chips" style="margin-top:1.75rem;" aria-label="Hədiyyə fikirləri">
          <span style="width:100%; font-size:.8125rem; color:var(--cocoa-faint);">{{ __('Münasibətə görə seçin:') }}</span>
          @foreach($gifts as $gift)
            <a class="occ-chip" href="{{ $gift->url() }}">{{ $gift->emoji }} {{ $gift->menu_label }}</a>
          @endforeach
        </nav>
      @endif
    </div>
  </section>

  <section style="padding-top:0;">
    <div class="wrap">

      @php $total = $designs->flatten()->count(); @endphp

      @if($total === 0)
        <div class="empty-note">
          <p>{{ __('Hələ dizayn əlavə olunmayıb.') }}</p>
        </div>
      @else
        <div class="filter-bar">
          <button type="button" class="chip active" data-filter="all">{{ __('Hamısı') }} ({{ $total }})</button>
          @foreach(\App\Models\Product::CATEGORIES as $key => $label)
            @if($designs->has($key))
              <button type="button" class="chip" data-filter="{{ $key }}">{{ $label }} ({{ $designs[$key]->count() }})</button>
            @endif
          @endforeach
        </div>

        @foreach(\App\Models\Product::CATEGORIES as $key => $label)
          @continue(! $designs->has($key))
          <div class="cat-block" data-category="{{ $key }}">
            <div class="cat-head">
              <h2>{{ $label }}</h2>
              <span class="cat-count">{{ __(':count dizayn', ['count' => $designs[$key]->count()]) }}</span>
            </div>
            <div class="designs-grid">
              @foreach($designs[$key] as $design)
                <a class="d-card" href="{{ $design->isCustomizable() ? lroute('products.customize', $design->slug) : lroute('designs.index') }}"
                        data-name="{{ $design->name }}"
                        data-category="{{ $label }}"
                        data-image="{{ \App\Support\Media::url($design->catalogImage()) }}"
                        data-url="{{ $design->isCustomizable() ? lroute('products.customize', $design->slug) : '' }}">
                  <div class="d-card-media">
                    @if($design->isCustomizable())<span class="pill">{{ __('Fərdiləşdir') }}</span>@endif
                    <img src="{{ \App\Support\Media::url($design->catalogImage()) }}" alt="{{ $design->name }} — {{ __('şəkilli şokolad qutusu') }}" loading="lazy">
                  </div>
                  <div class="d-card-body">
                    <h3>{{ $design->name }}</h3>
                    <span>{{ $label }}</span>
                  </div>
                </a>
              @endforeach
            </div>
          </div>
        @endforeach
      @endif

    </div>
  </section>

  <section class="tinted">
    <div class="wrap">
      <article class="prose">
        <h2>{{ __('Şəkilli şokolad qutusu necə seçilir?') }}</h2>
        <p>Hər dizayn hazır şablondur: içində şəkliniz üçün yer və yazı sahələri var. Dizaynı açın, şəklinizi yükləyin,
          adı və sözlərinizi yazın — qutunun necə görünəcəyini elə saytda, sifarişdən əvvəl görürsünüz.</p>
        <p>Sonra qutunun içindəki şokoladı seçirsiniz (Milka, Alpen Gold və digər 90–105 qramlıq plitkalar).
          İstəsəniz qutunu hədiyyə kağızına bükürük, içinə polaroid məktub qoyuruq, yaxud
          <a href="{{ lroute('live.create') }}">canlı şəkil</a> əlavə edirik — telefonu şəklə tutanda videonuz oynayır.</p>
        <h2>{{ __('Hansı dizaynı kimə?') }}</h2>
        <p>Cütlüklər üçün "Love story" və "Love is…", uşaqlar üçün Kinder və Barbie, maşın sevənlər üçün "Avtomobil",
          zarafat üçün Netflix, Google və Spotify üslubunda dizaynlar var. Ailə şəkli üçün isə "Family Frame" uyğundur.
          Münasibətə görə seçmək istəyirsinizsə, <a href="{{ lroute('gifts.index') }}">hədiyyə fikirlərinə</a> baxın.</p>
        <p>Sifariş adətən 1–3 iş günü ərzində hazırlanır: Bakıda ünvana çatdırırıq, bölgələrə poçtla göndəririk.</p>
      </article>
    </div>
  </section>

  <div class="lb" id="lightbox" role="dialog" aria-modal="true" aria-label="Dizayn önizləməsi">
    <div class="lb-inner">
      <button type="button" class="lb-close" id="lb-close" aria-label="Bağla">✕</button>
      <div class="lb-img"><img id="lb-image" src="" alt=""></div>
      <div class="lb-side">
        <span class="cat" id="lb-cat"></span>
        <h3 id="lb-title"></h3>
        <p>{{ __('Öz şəklinizi və istədiyiniz mətni bu dizaynın üzərinə əlavə edə bilərsiniz.') }}</p>
        <a class="btn btn-primary" id="lb-action" href="#">
          Fərdiləşdir
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </a>
        <div class="soon" id="lb-soon">Bu dizayn üçün fərdiləşdirmə tezliklə açılacaq. Sifariş üçün bizə Instagramda yazın.</div>
      </div>
    </div>
  </div>

@endsection

@section('page_script')
<script>
(function(){
  "use strict";

  var chips = document.querySelectorAll(".chip");
  var blocks = document.querySelectorAll(".cat-block");

  chips.forEach(function(chip){
    chip.addEventListener("click", function(){
      chips.forEach(function(c){ c.classList.remove("active"); });
      chip.classList.add("active");
      var filter = chip.dataset.filter;
      blocks.forEach(function(block){
        block.hidden = filter !== "all" && block.dataset.category !== filter;
      });
    });
  });

  var lb = document.getElementById("lightbox");
  var lbImage = document.getElementById("lb-image");
  var lbTitle = document.getElementById("lb-title");
  var lbCat = document.getElementById("lb-cat");
  var lbAction = document.getElementById("lb-action");
  var lbSoon = document.getElementById("lb-soon");

  function open(card){
    lbImage.src = card.dataset.image;
    lbImage.alt = card.dataset.name;
    lbTitle.textContent = card.dataset.name;
    lbCat.textContent = card.dataset.category;
    var url = card.dataset.url;
    lbAction.hidden = !url;
    lbSoon.hidden = !!url;
    if (url) lbAction.href = url;
    lb.classList.add("open");
    document.body.style.overflow = "hidden";
  }

  function close(){
    lb.classList.remove("open");
    document.body.style.overflow = "";
  }

  // Cards are links (search engines follow them); a plain click opens the preview instead.
  document.querySelectorAll(".d-card").forEach(function(card){
    card.addEventListener("click", function(e){
      if (e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) return;
      e.preventDefault();
      open(card);
    });
  });

  document.getElementById("lb-close").addEventListener("click", close);
  lb.addEventListener("click", function(e){ if (e.target === lb) close(); });
  document.addEventListener("keydown", function(e){ if (e.key === "Escape") close(); });
})();
</script>
@endsection
