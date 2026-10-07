@extends('layouts.app')

@section('title', $page['title'] . ', ' . __('nişan və toy xonçası üçün') . ' | Nefis')
@section('description', \App\Support\Seo::snippet($page['lede']))

@section('page_style')
  /* The designs run as a row the finger pushes, the way a xonça is actually
     looked at — one after another — and settle into a grid where there is
     room for one. */
  .xo-rail{ display:grid; grid-auto-flow:column; grid-auto-columns:minmax(15rem, 1fr);
    gap:1rem; overflow-x:auto; scroll-snap-type:x mandatory; padding:.25rem .25rem 1rem;
    scrollbar-width:thin; }
  .xo-rail > *{ scroll-snap-align:start; }
  @media (min-width:900px){
    .xo-rail{ grid-auto-flow:row; grid-template-columns:repeat(auto-fill, minmax(15rem, 1fr));
      overflow:visible; padding-bottom:0; }
  }
  .xo-facts{ display:grid; gap:.6rem; grid-template-columns:repeat(auto-fit, minmax(13rem, 1fr)); margin-top:1.5rem; }
  .xo-fact{ border:1px solid var(--line); border-radius:.9rem; padding:.85rem 1rem; background:var(--paper); }
  .xo-fact span{ display:block; font-size:.75rem; font-weight:700; letter-spacing:.08em;
    text-transform:uppercase; color:var(--gold-deep); margin-bottom:.25rem; }
  .xo-steps{ counter-reset:xo; display:grid; gap:.7rem; margin-top:1rem; }
  .xo-step{ display:flex; gap:.8rem; align-items:flex-start; }
  .xo-step b{ flex:none; width:1.7rem; height:1.7rem; border-radius:50%; display:grid; place-items:center;
    background:var(--cream-2); color:var(--gold-deep); font-size:.8125rem; }
  .xo-empty{ border:1px dashed var(--line); border-radius:1rem; padding:2rem 1.25rem; text-align:center;
    color:var(--cocoa-soft); background:var(--paper); }
@endsection

@section('content')
@include('partials.hero')

<section class="page-hero" style="padding-bottom:0;">
  <div class="wrap">
    <nav class="crumbs" aria-label="{{ __('Səhifənin yeri') }}">
      <a href="{{ lroute('home') }}">{{ __('Ana səhifə') }}</a><span aria-hidden="true">›</span>
      <span aria-current="page">{{ $page['title'] }}</span>
    </nav>
    <span class="eyebrow" style="justify-content:center;">{{ $page['eyebrow'] }}</span>
    <h1>{{ $page['title'] }}</h1>
    <p class="lede" style="margin-inline:auto;">{{ $page['lede'] }}</p>

    <div class="xo-facts">
      <div class="xo-fact"><span>{{ $page['size_label'] }}</span>{{ $page['size'] }}</div>
      <div class="xo-fact"><span>{{ $page['note_title'] }}</span>{{ $page['note'] }}</div>
    </div>
  </div>
</section>

<section>
  <div class="wrap">
    @if($designs->isEmpty())
      <p class="xo-empty">{{ $page['empty'] }}</p>
    @else
      <div class="xo-rail">
        @foreach($designs as $i => $design)
          @include('partials.p-card', ['product' => $design, 'first' => $i < 2])
        @endforeach
      </div>
    @endif
  </div>
</section>

<section class="tinted">
  <div class="wrap wrap-narrow">
    <h2>{{ $page['how_title'] }}</h2>
    <div class="xo-steps">
      @foreach(['how_1', 'how_2', 'how_3', 'how_4'] as $n => $key)
        <div class="xo-step"><b>{{ $n + 1 }}</b><span>{{ $page[$key] }}</span></div>
      @endforeach
    </div>
  </div>
</section>
@endsection
