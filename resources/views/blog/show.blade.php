@extends('layouts.app')

@php $w = $post->words(); @endphp

@section('title', $post->metaTitle())
@section('meta_description', $post->metaDescription())
@section('og_type', 'article')
@if($post->coverUrl())
  @section('og_image', \App\Support\Seo::canonical($post->coverUrl()))
@endif

{{-- Its address is a different word in each language, so this page lists its
     own twins instead of letting the layout guess them. --}}
@section('own_hreflang', 1)
@php $__family = $post->family(); @endphp
@push('head')
  @foreach($__family as $__twin)
    <link rel="alternate" hreflang="{{ $__twin->locale }}" href="{{ \App\Support\Seo::canonical($__twin->url()) }}">
  @endforeach
  @if($__az = $__family->firstWhere('locale', 'az'))
    <link rel="alternate" hreflang="x-default" href="{{ \App\Support\Seo::canonical($__az->url()) }}">
  @endif
@endpush

@push('jsonld')
  {{ \App\Support\Seo::jsonLd(['@graph' => array_values(array_filter([
      \App\Support\Seo::breadcrumbs([
          [$w['home'], lroute('home')],
          [$w['hub'], \App\Models\Post::hubUrl($post->locale)],
          [$post->title, $post->url()],
      ]),
      array_filter([
          '@type' => 'BlogPosting',
          'headline' => $post->title,
          'description' => $post->metaDescription(),
          'url' => \App\Support\Seo::canonical($post->url()),
          'mainEntityOfPage' => \App\Support\Seo::canonical($post->url()),
          'inLanguage' => $post->locale,
          'datePublished' => $post->published_at->toAtomString(),
          'dateModified' => ($post->updated_at ?? $post->published_at)->toAtomString(),
          'image' => $post->coverUrl() ? \App\Support\Seo::canonical($post->coverUrl()) : null,
          'author' => ['@type' => 'Organization', 'name' => \App\Support\Seo::SHOP, 'url' => url('/')],
          'publisher' => [
              '@type' => 'Organization',
              'name' => \App\Support\Seo::SHOP,
              'logo' => ['@type' => 'ImageObject', 'url' => asset('images/logo.png')],
          ],
      ]),
      $post->questions() ? \App\Support\Seo::faq($post->questions()) : null,
  ]))]) }}
@endpush

@section('page_style')
  .bl-head{ max-width:44rem; margin-inline:auto; }
  .bl-meta{ display:flex; flex-wrap:wrap; justify-content:center; align-items:center; gap:.4rem .7rem;
    margin-top:1.1rem; font-size:.875rem; color:var(--cocoa-faint); }
  .bl-topic{ padding:.2rem .65rem; border-radius:999px; background:var(--cream-2); color:var(--gold-deep); font-weight:600; }
  .bl-cover{ max-width:52rem; margin:2.5rem auto 0; border:1px solid var(--line); border-radius:var(--radius);
    overflow:hidden; box-shadow:var(--shadow-sm); background:var(--cream-2); }
  .bl-cover img{ width:100%; height:auto; display:block; }
  .bl-chips{ display:flex; flex-wrap:wrap; justify-content:center; gap:.5rem; margin-top:1.5rem; }
  .bl-end{ text-align:center; }
  .bl-end .btn{ margin-top:1.75rem; }
  .p-card-media{ aspect-ratio:4/5; }
@endsection

@section('content')

  <section class="page-hero">
    <div class="wrap">
      <nav class="crumbs" aria-label="{{ $w['hub'] }}">
        <a href="{{ lroute('home') }}">{{ $w['home'] }}</a><span aria-hidden="true">›</span>
        <a href="{{ \App\Models\Post::hubUrl($post->locale) }}">{{ $w['hub'] }}</a><span aria-hidden="true">›</span>
        <span aria-current="page">{{ $post->topic ?: $w['hub'] }}</span>
        {{-- The same article in another language, where it is written. --}}
        @foreach($__family->where('locale', '!=', $post->locale) as $__twin)
          <span aria-hidden="true">·</span>
          <a href="{{ $__twin->url() }}" lang="{{ $__twin->locale }}" hreflang="{{ $__twin->locale }}">{{ \App\Support\Locale::NAMES[$__twin->locale] ?? $__twin->locale }}</a>
        @endforeach
      </nav>
      <div class="bl-head">
        @if($post->topic)
          <span class="eyebrow">{{ $post->emoji }} {{ $post->topic }}</span>
        @endif
        <h1>{{ $post->title }}</h1>
        @if($post->excerpt)
          <p class="lede">{{ $post->excerpt }}</p>
        @endif
        <div class="bl-meta">
          <time datetime="{{ $post->published_at->toDateString() }}">{{ $post->dateText() }}</time>
          <span>{{ $post->readingText() }}</span>
        </div>
      </div>
      @if($post->coverUrl())
        <figure class="bl-cover">
          <img src="{{ $post->coverUrl() }}" alt="{{ $post->title }}" fetchpriority="high" decoding="async">
        </figure>
      @endif
    </div>
  </section>

  @if(filled($post->body))
    <section style="padding-top:1rem;">
      <div class="wrap">
        <article class="prose">{{ $post->bodyHtml() }}</article>
      </div>
    </section>
  @endif

  @if($products->isNotEmpty())
    <section class="tinted">
      <div class="wrap">
        <div class="section-head center reveal">
          <h2>{{ $w['designs'] }}</h2>
        </div>
        <div class="cards-grid">
          @foreach($products as $product)
            @include('partials.p-card', ['product' => $product, 'first' => false])
          @endforeach
        </div>
      </div>
    </section>
  @endif

  @if($post->questions())
    <section id="faq">
      <div class="wrap">
        <div class="section-head center reveal">
          <h2>{{ $w['faqTitle'] }}</h2>
        </div>
        <div class="faq-list reveal">
          @foreach($post->questions() as $i => $f)
            <details class="faq-item" @if($i === 0) open @endif>
              <summary>{{ $f['q'] }}<span class="plus"></span></summary>
              <div class="faq-a">{{ $f['a'] }}</div>
            </details>
          @endforeach
        </div>
      </div>
    </section>
  @endif

  <section class="{{ $post->questions() ? '' : 'tinted' }} bl-end">
    <div class="wrap">
      @if($others->isNotEmpty())
        <h2>{{ $w['others'] }}</h2>
        <div class="bl-chips">
          @foreach($others as $other)
            <a class="occ-chip" href="{{ $other->url() }}">{{ $other->emoji ?: '📖' }} {{ $other->title }}</a>
          @endforeach
        </div>
      @endif
      <h2 style="margin-top:{{ $others->isNotEmpty() ? '2.5rem' : '0' }};">{{ $w['cta'] }}</h2>
      <p class="lede" style="margin-inline:auto;">{{ $w['ctaText'] }}</p>
      <a href="{{ lroute('designs.index') }}" class="btn btn-primary">{{ $w['all'] }}
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
      </a>
    </div>
  </section>

@endsection
