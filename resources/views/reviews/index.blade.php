@extends('layouts.app')

@section('title', __('Müştəri rəyləri') . ' | Nefis')
@section('meta_description', __('Nefis.az-dan sifariş verənlərin rəyləri: qutu necə çıxdı, nə vaxt çatdı, şəkil necə göründü.'))

@if($standing['count'] > 0)
  @push('jsonld')
    {{-- The shop's own standing, said once, from the reviews themselves. --}}
    {{ \App\Support\Seo::jsonLd(\App\Support\Seo::rating($standing['average'], $standing['count'])) }}
  @endpush
@endif

@section('content')
<section class="page-hero">
  <div class="wrap-narrow">
    <span class="eyebrow">{{ __('Rəylər') }}</span>
    <h1>{{ __('Müştəri rəyləri') }}</h1>
    @if($standing['count'] > 0)
      <p class="lede">{{ __('Yalnız sifarişi əlinə çatmış müştərilər yazır — hər rəyin arxasında real sifariş var.') }}</p>
    @else
      <p class="lede">{{ __('Hələ rəy yoxdur. İlk rəyi siz yazın — sifarişiniz çatandan sonra.') }}</p>
    @endif
  </div>
</section>

@if($standing['count'] > 0)
  <section style="padding-top:0;">
    <div class="wrap-narrow">
      {{-- The average, and how the stars fall: one big number alone tells a
           visitor nothing about whether anybody was disappointed. --}}
      <div class="rv-standing">
        <div class="rv-avg">
          <b>{{ number_format($standing['average'], 1) }}</b>
          @include('partials.stars', ['of' => $standing['average']])
          <small>{{ trans_choice('{1} :count rəy|[2,*] :count rəy', $standing['count'], ['count' => $standing['count']]) }}</small>
        </div>
        <div class="rv-bars">
          @for($s = 5; $s >= 1; $s--)
            @php
              $how = (int) ($spread[$s] ?? 0);
              $part = $standing['count'] > 0 ? round($how / $standing['count'] * 100) : 0;
            @endphp
            <div class="rv-bar">
              <span class="rv-bar-n">{{ $s }}★</span>
              <span class="rv-bar-rail"><span class="rv-bar-fill" style="width:{{ $part }}%"></span></span>
              <span class="rv-bar-how">{{ $how }}</span>
            </div>
          @endfor
        </div>
      </div>

      <div class="rv-list">
        @foreach($reviews as $review)
          @include('reviews.one', ['review' => $review, 'showProduct' => true])
        @endforeach
      </div>

      {{ $reviews->links() }}
    </div>
  </section>
@endif

<section class="tinted">
  <div class="wrap-narrow" style="text-align:center;">
    <div class="how-cta" style="justify-content:center;">
      <a href="{{ lroute('designs.index') }}" class="btn btn-primary">{{ __('Dizaynlara bax') }}</a>
      @auth
        <a href="{{ lroute('orders.index') }}" class="btn btn-ghost">{{ __('Sifarişlərim') }}</a>
      @endauth
    </div>
  </div>
</section>
@endsection
