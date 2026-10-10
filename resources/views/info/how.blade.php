@extends('layouts.app')

@section('title', __('Necə işləyir') . ' | Nefis')
@section('meta_description', __('Fərdi şokolad qutusu necə sifariş olunur: dizaynı seçirsiniz, şəklinizi yükləyirsiniz, ödəyirsiniz — biz hazırlayıb çatdırırıq.'))

@section('content')
<section class="page-hero">
  <div class="wrap-narrow">
    <span class="eyebrow">{{ __($page['how_eyebrow']) }}</span>
    <h1>{{ __($page['how_title']) }}</h1>
    <p class="lede">{{ __($page['how_lede']) }}</p>
  </div>
</section>

<section style="padding-top:0;">
  <div class="wrap">
    <div class="steps">
      @foreach(\App\Support\Info::steps() as $i => $step)
        <div class="step">
          @unless($loop->last)<div class="step-line"></div>@endunless
          <div class="num">{{ $i + 1 }}</div>
          <h3>{{ $step['title'] }}</h3>
          <p>{{ $step['text'] }}</p>
        </div>
      @endforeach
    </div>
  </div>
</section>

{{-- What the three steps leave out, taken from the shop's own settings
     rather than written here: the day the owner changes the lead time or a
     delivery price, this page changes with it. --}}
@php
  $lead = (int) \App\Models\Setting::get(\App\Models\Setting::DELIVERY_LEAD_DAYS);
  $ways = \App\Models\DeliveryMethod::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
  $freeFrom = \App\Models\DeliveryMethod::freeFrom();
@endphp
<section class="tinted">
  <div class="wrap-narrow legal">
    <h2>{{ __('Nə qədər çəkir') }}</h2>
    <p>{{ $lead > 0
        ? __('Qutular əl ilə hazırlanır: sifarişdən sonra ən azı :days gün lazımdır. Sifariş verərkən ən tez hansı günü seçə biləcəyinizi səhifə özü göstərir.', ['days' => $lead])
        : __('Sifariş verərkən ən tez hansı günü seçə biləcəyinizi səhifə özü göstərir.') }}</p>
    <p>{{ __('Təcili lazımdırsa, «Təcili hazırlansın» seçimi var — növbədənkənar hazırlanır, haqqı bütün sifariş üçün bir dəfə alınır.') }}</p>

    @if($ways->isNotEmpty())
      <h2>{{ __('Necə çatdırılır') }}</h2>
      <ul>
        @foreach($ways as $way)
          <li><b>{{ $way->tr('name') }}</b> —
            {{ $way->price > 0 ? \App\Support\Price::format($way->price) : __('pulsuz') }}@if($way->description), {{ $way->tr('description') }}@endif</li>
        @endforeach
      </ul>
      @if($freeFrom)
        <p>{{ __(':sum məbləğindən yuxarı sifarişlərdə çatdırılma bizdən.', ['sum' => \App\Support\Price::format($freeFrom)]) }}</p>
      @endif
    @endif

    <h2>{{ __('Necə ödənilir') }}</h2>
    <p>{{ __('Kartla saytın özündə: ödəniş səhifəsi bankın səhifəsidir, kart məlumatları bizə gəlmir. Köçürmə ilə də ödəmək olar — hesab nömrəsini sifarişdən sonra göstəririk, çeki isə elə həmin səhifəyə yükləyirsiniz.') }}</p>

    <p class="how-outro">{{ __($page['how_outro']) }}</p>
    <div class="how-cta">
      <a href="{{ lroute('designs.index') }}" class="btn btn-primary">{{ __('Dizaynlara bax') }}</a>
      <a href="{{ lroute('info.contact') }}" class="btn btn-ghost">{{ __('Bizə yazın') }}</a>
    </div>
  </div>
</section>
@endsection
