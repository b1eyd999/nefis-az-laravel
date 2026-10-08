@extends('layouts.app')

@section('title', __($page['title']) . ', ' . __('nişan və toy xonçası üçün') . ' | Nefis')
{{-- 'meta_description' is the name the layout yields; 'description' went
     nowhere and the page fell back to the shop's own sentence. --}}
@section('meta_description', \App\Support\Seo::snippet(__($page['lede'])))

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
  /* Three wide bands: a photograph holding one half, the words and a button
     on the other. They turn sides as they go down, so the page does not read
     as three of the same thing. On a phone the picture goes on top. */
  .xo-band{ display:grid; gap:0; align-items:stretch; border-radius:1.25rem; overflow:hidden;
    background:var(--paper); border:1px solid var(--line); margin-top:1.5rem; }
  .xo-band + .xo-band{ margin-top:1.25rem; }
  /* Three heights the owner picks from in the admin. The picture's own
     height is what makes the band tall; the words sit in the middle of
     whatever it comes to. */
  .xo-band-pic{ background:var(--cream-2); min-height:12rem; }
  .xo-band.is-alcaq .xo-band-pic{ min-height:9rem; }
  .xo-band.is-orta .xo-band-pic{ min-height:13rem; }
  .xo-band.is-hundur .xo-band-pic{ min-height:17rem; }
  @media (min-width:860px){
    .xo-band.is-alcaq .xo-band-pic{ min-height:12rem; }
    .xo-band.is-orta .xo-band-pic{ min-height:17rem; }
    .xo-band.is-hundur .xo-band-pic{ min-height:24rem; }
  }
  .xo-band-pic img{ width:100%; height:100%; object-fit:cover; display:block; }
  .xo-band-body{ padding:1.75rem 1.5rem 2rem; display:flex; flex-direction:column; justify-content:center; gap:.65rem; }
  .xo-band-body .eyebrow{ margin:0; }
  .xo-band-body h3{ font-size:clamp(1.3rem, 2.2vw, 1.85rem); line-height:1.2; margin:0; }
  .xo-band-body p{ font-size:.9375rem; line-height:1.7; color:var(--cocoa-soft); margin:0; max-width:34rem; }
  .xo-band-body .btn{ align-self:flex-start; margin-top:.35rem; }
  @media (min-width:860px){
    .xo-band{ grid-template-columns:1fr 1fr; }
    .xo-band-body{ padding:2.5rem 2.75rem; }
    /* The second band turns round: the picture moves to the right. */
    .xo-band.is-flipped .xo-band-pic{ order:2; }
  }
  /* A band with no picture is only its words, and takes the whole width. */
  .xo-band.no-pic{ grid-template-columns:1fr; }
@endsection

@section('content')
@include('partials.hero')

<section class="page-hero" style="padding-bottom:0;">
  <div class="wrap">
    <nav class="crumbs" aria-label="{{ __('Səhifənin yeri') }}">
      <a href="{{ lroute('home') }}">{{ __('Ana səhifə') }}</a><span aria-hidden="true">›</span>
      <span aria-current="page">{{ __($page['title']) }}</span>
    </nav>
    <span class="eyebrow" style="justify-content:center;">{{ __($page['eyebrow']) }}</span>
    <h1>{{ __($page['title']) }}</h1>
    <p class="lede" style="margin-inline:auto;">{{ __($page['lede']) }}</p>

    <div class="xo-facts">
      <div class="xo-fact"><span>{{ __($page['size_label']) }}</span>{{ __($page['size']) }}</div>
      <div class="xo-fact"><span>{{ __($page['note_title']) }}</span>{{ __($page['note']) }}</div>
    </div>
  </div>
</section>

@php
  /* Said in the visitor's language where we have the words, and in the
     owner's where we do not — an empty line is left alone, because __('')
     is not an empty string. */
  $say = fn (string $v) => $v === '' ? '' : (string) __($v);

  /* A band is worth drawing when the owner has put something in it. */
  $bands = collect([1, 2, 3])
    ->map(fn ($n) => [
      'eyebrow' => $say(trim((string) ($page['block' . $n . '_eyebrow'] ?? ''))),
      'title' => $say(trim((string) ($page['block' . $n . '_title'] ?? ''))),
      'text' => $say(trim((string) ($page['block' . $n . '_text'] ?? ''))),
      'button' => $say(trim((string) ($page['block' . $n . '_button'] ?? ''))),
      // An address is not a sentence; it is the same in every language.
      'url' => \App\Models\HeroSlide::href(trim((string) ($page['block' . $n . '_url'] ?? ''))),
      'image' => \App\Support\Xonca::image($n),
      'size' => \App\Support\Xonca::size($n),
    ])
    ->filter(fn ($b) => $b['title'] !== '' || $b['text'] !== '' || $b['image'])
    ->values();
@endphp
@if($bands->isNotEmpty())
  <section>
    <div class="wrap">
      @foreach($bands as $i => $band)
        <div class="xo-band is-{{ $band['size'] }}{{ $band['image'] ? ($i % 2 ? ' is-flipped' : '') : ' no-pic' }}">
          @if($band['image'])
            <div class="xo-band-pic">
              <img src="{{ $band['image'] }}" alt="{{ $band['title'] }}" loading="lazy" decoding="async">
            </div>
          @endif
          <div class="xo-band-body">
            @if($band['eyebrow'])<span class="eyebrow">{{ $band['eyebrow'] }}</span>@endif
            @if($band['title'])<h3>{{ $band['title'] }}</h3>@endif
            @if($band['text'])<p>{{ $band['text'] }}</p>@endif
            @if($band['button'] && $band['url'])
              <a href="{{ $band['url'] }}" class="btn btn-primary"
                 @if(preg_match('#^https?://#i', $band['url'])) target="_blank" rel="noopener" @endif>
                {{ $band['button'] }}
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M7 17L17 7M17 7H9M17 7V15"/></svg>
              </a>
            @endif
          </div>
        </div>
      @endforeach
    </div>
  </section>
@endif

<section>
  <div class="wrap">
    @if($designs->isEmpty())
      <p class="xo-empty">{{ __($page['empty']) }}</p>
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
    <h2>{{ __($page['how_title']) }}</h2>
    <div class="xo-steps">
      @foreach(['how_1', 'how_2', 'how_3', 'how_4'] as $n => $key)
        <div class="xo-step"><b>{{ $n + 1 }}</b><span>{{ __($page[$key]) }}</span></div>
      @endforeach
    </div>
  </div>
</section>
@endsection
