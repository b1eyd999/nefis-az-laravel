@extends('layouts.app')

@section('title', $page->metaTitle())
@section('meta_description', $page->metaDescription())

@push('jsonld')
  {{ \App\Support\Seo::jsonLd(['@graph' => array_values(array_filter([
      \App\Support\Seo::breadcrumbs([
          ['Ana səhifə', route('home')],
          ['Hədiyyə fikirləri', route('gifts.index')],
          [$page->menu_label, $page->url()],
      ]),
      $page->questions() ? \App\Support\Seo::faq($page->questions()) : null,
      $products->isNotEmpty() ? [
          '@type' => 'ItemList',
          'name' => $page->title,
          'itemListElement' => $products->values()->map(fn ($p, $i) => [
              '@type' => 'ListItem',
              'position' => $i + 1,
              'url' => route('products.customize', $p->slug),
              'name' => $p->name,
          ])->all(),
      ] : null,
  ]))]) }}
@endpush

@section('page_style')
  .p-card-media{ aspect-ratio:4/5; }
  .gift-facts{ display:flex; flex-wrap:wrap; justify-content:center; gap:.5rem 1.4rem; margin-top:1.5rem; font-size:.9rem; color:var(--cocoa-soft); }
  .gift-facts span::before{ content:"✓"; margin-right:.4rem; color:var(--gold); font-weight:700; }
  .gift-actions{ display:flex; flex-wrap:wrap; justify-content:center; gap:.75rem; margin-top:1.75rem; }
  .extras{ display:grid; grid-template-columns:repeat(auto-fit, minmax(15rem, 1fr)); gap:1rem; margin-top:2.5rem; }
  .gift-end{ text-align:center; }
  .gift-end .occ-chips{ margin-top:1.5rem; }
  .gift-end .btn{ margin-top:2.5rem; }
@endsection

@section('content')

  <section class="page-hero">
    <div class="wrap">
      <nav class="crumbs" aria-label="Səhifənin yeri">
        <a href="{{ route('home') }}">Ana səhifə</a><span aria-hidden="true">›</span>
        <a href="{{ route('gifts.index') }}">Hədiyyə fikirləri</a><span aria-hidden="true">›</span>
        <span aria-current="page">{{ $page->menu_label }}</span>
      </nav>
      <span class="eyebrow">{{ $page->emoji }} {{ $page->eyebrow ?: $page->menu_label }}</span>
      <h1>{{ $page->title }}</h1>
      @if($page->intro)
        <p class="lede">{{ $page->intro }}</p>
      @endif
      <div class="gift-facts">
        @if($from)<span>Qutu {{ \App\Support\Price::format($from) }}-dan</span>@endif
        <span>Öz şəkliniz və sözləriniz</span>
        <span>Bakıda və bölgələrə çatdırılma</span>
      </div>
      <div class="gift-actions">
        <a href="#designs" class="btn btn-primary">Dizayn seç
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </a>
        <a href="{{ route('home') }}#how" class="btn btn-ghost">Necə işləyir?</a>
      </div>
    </div>
  </section>

  @if($products->isNotEmpty())
    <section id="designs" style="padding-top:1rem;">
      <div class="wrap">
        <div class="section-head center reveal">
          <span class="eyebrow" style="justify-content:center;">{{ $products->count() }} dizayn</span>
          <h2>Bu münasibətə uyğun dizaynlar</h2>
          <p class="lede" style="margin-inline:auto;">Birini seçin, şəklinizi yükləyin və sözlərinizi yazın — qutunun necə görünəcəyini dərhal görəcəksiniz.</p>
        </div>
        <div class="cards-grid">
          @foreach($products as $product)
            @include('partials.p-card', ['product' => $product])
          @endforeach
        </div>
      </div>
    </section>
  @endif

  @if(filled($page->body) || $extras)
    <section class="tinted">
      <div class="wrap">
        @if(filled($page->body))
          <article class="prose">{{ $page->bodyHtml() }}</article>
        @endif
        @if($extras)
          <div class="extras">
            @foreach($extras as [$ico, $name, $text, $url])
              <a class="occ-card" href="{{ $url }}">
                <span class="occ-ico">{{ $ico }}</span>
                <div><h3>{{ $name }}</h3><p>{{ $text }}</p></div>
              </a>
            @endforeach
          </div>
        @endif
      </div>
    </section>
  @endif

  @if($page->questions())
    <section id="faq">
      <div class="wrap">
        <div class="section-head center reveal">
          <span class="eyebrow" style="justify-content:center;">Suallar</span>
          <h2>Tez-tez soruşulan suallar</h2>
        </div>
        <div class="faq-list reveal">
          @foreach($page->questions() as $i => $f)
            <details class="faq-item" @if($i === 0) open @endif>
              <summary>{{ $f['q'] }}<span class="plus"></span></summary>
              <div class="faq-a">{{ $f['a'] }}</div>
            </details>
          @endforeach
        </div>
      </div>
    </section>
  @endif

  <section class="tinted gift-end">
    <div class="wrap">
      @if($others->isNotEmpty())
        <h2>Başqa hədiyyə fikirləri</h2>
        <div class="occ-chips">
          @foreach($others as $other)
            <a class="occ-chip" href="{{ $other->url() }}">{{ $other->emoji }} {{ $other->linkText() }}</a>
          @endforeach
        </div>
      @endif
      <a href="{{ route('designs.index') }}" class="btn btn-primary">Bütün dizaynlara bax
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
      </a>
    </div>
  </section>

@endsection
