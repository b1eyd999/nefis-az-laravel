@extends('layouts.app')

@php $w = $page->words(); @endphp

@section('lang', $page->locale)
@section('title', $page->metaTitle())
@section('meta_description', $page->metaDescription())

{{-- Its address is a different word in each language, so this page lists its
     own twins instead of letting the layout guess them. --}}
@section('own_hreflang', 1)
@php
  $__az = $page->locale === 'az' ? $page : ($page->alt ?? null);
  $__family = collect([$page, $alternate, $__az])->filter()
      ->merge($__az?->alternates()->where('is_active', true)->get() ?? collect())
      ->unique('id')->filter->is_active;
@endphp
@push('head')
  @foreach($__family as $__twin)
    <link rel="alternate" hreflang="{{ $__twin->locale }}" href="{{ \App\Support\Seo::canonical($__twin->url()) }}">
  @endforeach
  @if($__az)
    <link rel="alternate" hreflang="x-default" href="{{ \App\Support\Seo::canonical($__az->url()) }}">
  @endif
@endpush

@push('jsonld')
  {{ \App\Support\Seo::jsonLd(['@graph' => array_values(array_filter([
      \App\Support\Seo::breadcrumbs([
          [$w['home'], lroute('home')],
          [$w['hub'], \App\Models\GiftPage::hubUrl($page->locale)],
          [$page->menu_label, $page->url()],
      ]),
      $page->questions() ? \App\Support\Seo::faq($page->questions()) : null,
      $products->isNotEmpty() ? [
          '@type' => 'ItemList',
          'name' => $page->title,
          'itemListElement' => $products->values()->map(fn ($p, $i) => [
              '@type' => 'ListItem',
              'position' => $i + 1,
              'url' => lroute('products.customize', $p->slug),
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
      <nav class="crumbs" aria-label="{{ $w['hub'] }}">
        <a href="{{ lroute('home') }}">{{ $w['home'] }}</a><span aria-hidden="true">›</span>
        <a href="{{ \App\Models\GiftPage::hubUrl($page->locale) }}">{{ $w['hub'] }}</a><span aria-hidden="true">›</span>
        <span aria-current="page">{{ $page->menu_label }}</span>
        @if($alternate)
          <span aria-hidden="true">·</span>
          <a href="{{ $alternate->url() }}" lang="{{ $alternate->locale }}" hreflang="{{ $alternate->locale }}">{{ \App\Support\Locale::NAMES[$alternate->locale] ?? $alternate->locale }}</a>
        @endif
      </nav>
      <span class="eyebrow">{{ $page->emoji }} {{ $page->eyebrow ?: $page->menu_label }}</span>
      <h1>{{ $page->title }}</h1>
      @if($page->intro)
        <p class="lede">{{ $page->intro }}</p>
      @endif
      <div class="gift-facts">
        @if($from)<span>{{ str_replace(':price', \App\Support\Price::format($from), $w['fromPrice']) }}</span>@endif
        <span>{{ $w['yours'] }}</span>
        <span>{{ $w['delivery'] }}</span>
      </div>
      <div class="gift-actions">
        <a href="#designs" class="btn btn-primary">{{ $w['pick'] }}
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </a>
        <a href="{{ lroute('home') }}#how" class="btn btn-ghost">{{ $w['how'] }}</a>
      </div>
    </div>
  </section>

  @if($products->isNotEmpty())
    <section id="designs" style="padding-top:1rem;">
      <div class="wrap">
        <div class="section-head center reveal">
          <span class="eyebrow" style="justify-content:center;">{{ $page->designCount($products->count()) }}</span>
          <h2>{{ $w['designs'] }}</h2>
          <p class="lede" style="margin-inline:auto;">{{ $w['lede'] }}</p>
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
          <span class="eyebrow" style="justify-content:center;">{{ $w['faq'] }}</span>
          <h2>{{ $w['faqTitle'] }}</h2>
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
        <h2>{{ $w['others'] }}</h2>
        <div class="occ-chips">
          @foreach($others as $other)
            <a class="occ-chip" href="{{ $other->url() }}">{{ $other->emoji }} {{ $other->linkText() }}</a>
          @endforeach
        </div>
      @endif
      <a href="{{ lroute('designs.index') }}" class="btn btn-primary">{{ $w['all'] }}
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
      </a>
    </div>
  </section>

@endsection
