@extends('layouts.app')

@section('title', __('Tez-tez soruşulan suallar') . ' | Nefis')
@section('meta_description', __('Sifariş, şokolad seçimi, hazırlanma müddəti, çatdırılma, ödəniş və qaytarma — müştərilərin ən çox soruşduğu suallara cavablar.'))

@push('jsonld')
  {{-- The same questions the page shows, said again for search engines. --}}
  {{ \App\Support\Seo::jsonLd(\App\Support\Seo::faq($faq)) }}
@endpush

@section('content')
<section class="page-hero">
  <div class="wrap-narrow">
    <span class="eyebrow">{{ __($page['faq_eyebrow']) }}</span>
    <h1>{{ __($page['faq_title']) }}</h1>
    <p class="lede">{{ __($page['faq_lede']) }}</p>
  </div>
</section>

<section style="padding-top:0;">
  <div class="wrap-narrow">
    <div class="faq-list">
      @foreach($faq as $i => $f)
        <details class="faq-item" @if($i === 0) open @endif>
          <summary>{{ $f['q'] }}<span class="plus"></span></summary>
          <div class="faq-a">{{ $f['a'] }}</div>
        </details>
      @endforeach
    </div>

    <div class="how-cta" style="margin-top:2rem;">
      <a href="{{ lroute('info.contact') }}" class="btn btn-primary">{{ __('Cavabı tapmadım, yazmaq istəyirəm') }}</a>
      <a href="{{ lroute('info.how') }}" class="btn btn-ghost">{{ __('Necə işləyir') }}</a>
    </div>
  </div>
</section>
@endsection
