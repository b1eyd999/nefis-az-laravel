@extends('layouts.app')

@section('title', __('Şokolad qutusu dizaynları, şəkilli fərdi hədiyyə | Nefis'))
@section('meta_description', __('Kinder, Milka, Love story, Netflix, Spotify və başqa şokolad qutusu dizaynları. Bəyəndiyinizi seçin, şəklinizi və sözünüzü əlavə edin, hədiyyə hazırdır.'))

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
                  'name' => $d->tr('name'),
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

  /* Two bands of chips stood between the visitor and the first box —
     three lines of occasions and two of categories, which on a telephone
     is the whole screen. Folded, a band is one line that slides under a
     finger; the button opens the lot for anyone with a mouse.

     The folding is put on by script, so without one the bands wrap exactly
     as they always did — and either way every chip stays in the page,
     which is what a crawler reads. */
  .roll{ display:flex; flex-direction:column; align-items:center; gap:.55rem; }
  .roll-label{ font-size:.8125rem; color:var(--cocoa-faint); }
  .filter-roll{ margin-bottom:3.5rem; }
  .filter-roll .filter-bar{ margin-bottom:0; }

  .strip{ max-width:100%; }
  .strip.folded{
    flex-wrap:nowrap; justify-content:flex-start;
    overflow-x:auto; scrollbar-width:none; -ms-overflow-style:none;
    /* room for the lift a chip makes on hover, which the scroll box would
       otherwise cut off at the top */
    padding:.3rem .15rem;
  }
  .strip.folded::-webkit-scrollbar{ display:none; }
  .strip.folded > *{ flex:0 0 auto; }

  .roll-more{
    border:0; background:none; cursor:pointer; padding:.2rem .6rem;
    font-size:.8125rem; font-weight:600; color:var(--cocoa-faint);
    text-decoration:underline; text-underline-offset:3px; transition:color .2s;
  }
  .roll-more:hover{ color:var(--cocoa); }

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
  .lb-side h3, .lb-title{ font-size:1.375rem; font-weight:700; margin:0; }
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
      <h1>{{ __('Şəkilli şokolad qutusu dizaynları') }}</h1>
      <p class="lede">{{ __('Şokolad qutularından posterlərə qədər, bəyəndiyiniz dizaynı seçin, sonra öz şəklinizi və sözünüzü əlavə edin.') }}</p>
      @if($gifts->isNotEmpty())
        {{-- The label is its own line now rather than a full-width item
             inside the band: the band has to be able to stop wrapping. --}}
        <div class="roll" style="margin-top:1.75rem;">
          <span class="roll-label">{{ __('Münasibətə görə seçin:') }}</span>
          <nav class="occ-chips strip" id="occ-strip" aria-label="{{ __('Hədiyyə fikirləri') }}">
            @foreach($gifts as $gift)
              <a class="occ-chip" href="{{ $gift->url() }}">{{ $gift->emoji }} {{ $gift->menu_label }}</a>
            @endforeach
          </nav>
          <button type="button" class="roll-more" hidden
                  data-strip="occ-strip" aria-controls="occ-strip" aria-expanded="false"
                  data-open="{{ __('Hamısını göstər') }}"
                  data-shut="{{ __('Yığ') }}">{{ __('Hamısını göstər') }}</button>
        </div>
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
        <div class="roll filter-roll">
          <div class="filter-bar strip" id="cat-strip">
            <button type="button" class="chip active" data-filter="all">{{ __('Hamısı') }} ({{ $total }})</button>
            @foreach($blocks as $block)
              <button type="button" class="chip" data-filter="{{ $block['key'] }}">{{ $block['label'] }} ({{ $block['designs']->count() }})</button>
            @endforeach
          </div>
          <button type="button" class="roll-more" hidden
                  data-strip="cat-strip" aria-controls="cat-strip" aria-expanded="false"
                  data-open="{{ __('Hamısını göstər') }}"
                  data-shut="{{ __('Yığ') }}">{{ __('Hamısını göstər') }}</button>
        </div>

        @foreach($blocks as $block)
          @php [$key, $label, $items] = [$block['key'], $block['label'], $block['designs']]; @endphp
          <div class="cat-block" data-category="{{ $key }}">
            <div class="cat-head">
              <h2>{{ $label }}</h2>
              <span class="cat-count">{{ __(':count dizayn', ['count' => $items->count()]) }}</span>
            </div>
            <div class="designs-grid">
              @foreach($items as $design)
                <a class="d-card" href="{{ lroute('products.customize', $design->slug) }}"
                        data-name="{{ $design->tr('name') }}"
                        data-category="{{ $label }}"
                        data-image="{{ \App\Support\Media::url($design->catalogImage()) }}"
                        data-url="{{ lroute('products.customize', $design->slug) }}">
                  <div class="d-card-media">
                    @if($design->isCustomizable())<span class="pill">{{ __('Fərdiləşdir') }}</span>@endif
                    {{-- The same flame the home page shows, on whatever is selling now. --}}
                    @if(\App\Support\BestSellers::has($design->id))
                      <span class="p-hot" title="{{ __('Ən çox sifariş olunanlardan') }}">
                        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                          <path d="M13.5 2c.3 3-1 4.6-2.4 6.1C9.6 9.7 8 11.3 8 14a6 6 0 0 0 12 0c0-3.6-2.4-5.6-4-7.3-.4 1.3-1.2 2-2 2.3.3-2.6-.2-5.3-.5-7z"/>
                          <path d="M11 22a4 4 0 0 1-1.6-6.6c.1 1 .7 1.7 1.5 2.1-.2-1.6.4-3 1.6-4 .2 1.4 1 2 1.8 2.6A4 4 0 0 1 11 22z" opacity=".55"/>
                        </svg>
                        <b>HOT</b>
                      </span>
                    @endif
                    {{-- The first cards of the first family are the phone's
                         largest paint; they are asked for at once. --}}
                    @php $small = $design->catalogImageSmall(); @endphp
                    <img src="{{ \App\Support\Media::url($design->catalogImage()) }}"
                         @if($small) srcset="{{ \App\Support\Media::url($small) }} 400w, {{ \App\Support\Media::url($design->catalogImage()) }} 1080w"
                                     sizes="(max-width: 760px) 45vw, 300px" @endif
                         alt="{{ $design->tr('name') }}, {{ __('şəkilli şokolad qutusu') }}"
                         width="1080" height="1350" decoding="async"
                         @if($loop->parent->first && $loop->index < 2) fetchpriority="high" @else loading="lazy" @endif>
                  </div>
                    {{-- For somebody who only wants a closer look at the
                         artwork: the card itself now goes to the design. --}}
                    <button type="button" class="d-zoom" aria-label="{{ __('Şəkli böyüt') }}">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                    </button>
                  <div class="d-card-body">
                    <h3>{{ $design->tr('name') }}</h3>
                    <span>{{ $label }}</span>
                    {{-- The catalogue was the one page that hid the price, so
                         a gift-buyer with a figure in his head had to open a
                         design to find out whether this shop was for him. --}}
                    <b class="d-card-price">{{ $design->priceFrom()
                      ? __(':price-dan', ['price' => \App\Support\Price::format($design->priceFrom())])
                      : __('Qiymət sorğu ilə') }}</b>
                  </div>
                </a>
              @endforeach
            </div>
          </div>
        @endforeach
      @endif

      @include('partials.design-rights')

    </div>
  </section>

  <section class="tinted">
    <div class="wrap">
      <article class="prose">
        <h2>{{ __('Şəkilli şokolad qutusu necə seçilir?') }}</h2>
        <p>{{ __('Hər dizayn hazır şablondur: içində şəkliniz üçün yer və yazı sahələri var. Dizaynı açın, şəklinizi yükləyin, adı və sözlərinizi yazın, qutunun necə görünəcəyini elə saytda, sifarişdən əvvəl görürsünüz.') }}</p>
        <p>{{ __('Sonra qutunun içindəki şokoladı seçirsiniz (Milka, Alpen Gold və digər 90–105 qramlıq plitkalar). İstəsəniz qutunu hədiyyə kağızına bükürük, içinə polaroid məktub qoyuruq, yaxud') }}
          <a href="{{ lroute('live.create') }}">{{ __('canlı şəkil') }}</a> {{ __('əlavə edirik, telefonu şəklə tutanda videonuz oynayır.') }}</p>
        <h2>{{ __('Hansı dizaynı kimə?') }}</h2>
        <p>{{ __('Cütlüklər üçün "Love story" və "Love is…", uşaqlar üçün Kinder və Barbie, maşın sevənlər üçün "Avtomobil", zarafat üçün Netflix, Google və Spotify üslubunda dizaynlar var. Ailə şəkli üçün isə "Family Frame" uyğundur. Münasibətə görə seçmək istəyirsinizsə,') }}
          <a href="{{ lroute('gifts.index') }}">{{ __('hədiyyə fikirlərinə') }}</a> {{ __('baxın.') }}</p>
        <p>{{ __('Sifariş adətən 1–3 iş günü ərzində hazırlanır: Bakıda ünvana çatdırırıq, bölgələrə poçtla göndəririk.') }}</p>
      </article>
    </div>
  </section>

  <div class="lb" id="lightbox" role="dialog" aria-modal="true" aria-label="{{ __('Dizayn önizləməsi') }}">
    <div class="lb-inner">
      <button type="button" class="lb-close" id="lb-close" aria-label="{{ __('Bağla') }}">✕</button>
      <div class="lb-img"><img id="lb-image" src="" alt=""></div>
      <div class="lb-side">
        <span class="cat" id="lb-cat"></span>
        <p class="lb-title" id="lb-title"></p>
        <p>{{ __('Öz şəklinizi və istədiyiniz mətni bu dizaynın üzərinə əlavə edə bilərsiniz.') }}</p>
        <a class="btn btn-primary" id="lb-action" href="#">
          {{ __('Fərdiləşdir') }}
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </a>
        <div class="soon" id="lb-soon">{{ __('Bu dizayn üçün fərdiləşdirmə tezliklə açılacaq. Sifariş üçün bizə Instagramda yazın.') }}</div>
      </div>
    </div>
  </div>

@endsection

@section('page_script')
<script>
(function(){
  "use strict";

  /* A band of chips is folded to one sliding line and the button opens
     it. When the band already fits as it is — a wide screen, few chips —
     there is nothing to open, so the button takes itself away and the
     band is left to wrap. */
  document.querySelectorAll(".roll-more").forEach(function(btn){
    var strip = document.getElementById(btn.dataset.strip);
    if (!strip) return;

    function fold(){
      strip.classList.add("folded");
      btn.setAttribute("aria-expanded", "false");
      btn.textContent = btn.dataset.open;
      /* Measured while folded, which is the only state the question means
         anything in: nowrap makes scrollWidth the width of the whole row. */
      btn.hidden = strip.scrollWidth <= strip.clientWidth + 1;
      if (btn.hidden) strip.classList.remove("folded");
    }

    function open(){
      strip.classList.remove("folded");
      btn.setAttribute("aria-expanded", "true");
      btn.textContent = btn.dataset.shut;
    }

    btn.addEventListener("click", function(){
      if (btn.getAttribute("aria-expanded") === "true") fold(); else open();
    });

    fold();

    /* Turning a telephone changes the answer. Waited out rather than
       measured on every pixel of a window being dragged, and never while
       he has it open — that was his doing, not the screen's. */
    var later;
    window.addEventListener("resize", function(){
      clearTimeout(later);
      later = setTimeout(function(){
        if (btn.getAttribute("aria-expanded") !== "true") fold();
      }, 150);
    });
  });

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

  /* A tap on a card opens the design, which is what a card is for.
     It used to open a bigger copy of the same picture instead: the visitor
     learned nothing, shut it, tapped the next one, learned nothing again —
     and the catalogue, which is where the shop's own banner button sends
     him, could not be got out of. The magnifier below still opens the
     preview for somebody who only wants a closer look. */
  document.querySelectorAll(".d-card .d-zoom").forEach(function(btn){
    btn.addEventListener("click", function(e){
      e.preventDefault();
      e.stopPropagation();
      open(btn.closest(".d-card"));
    });
  });

  document.getElementById("lb-close").addEventListener("click", close);
  lb.addEventListener("click", function(e){ if (e.target === lb) close(); });
  document.addEventListener("keydown", function(e){ if (e.key === "Escape") close(); });
})();
</script>
@endsection
