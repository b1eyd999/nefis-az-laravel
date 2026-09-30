@extends('layouts.app')

{{-- The design exists and the owner is still drawing it; a customer who
     followed a link should be told that, not shown a wrong-address page.
     There is nothing here for a search engine yet, so: noindex. --}}
@section('robots', 'noindex, follow')
@section('title', $product->tr('name') . ' | Nefis')

@section('content')
<section class="page-hero">
  <div class="wrap" style="max-width:40rem;">
    <span class="eyebrow" style="justify-content:center;">{{ $product->tr('name') }}</span>
    <h1>{{ __('Bu dizayn hazırlanır') }}</h1>
    <p class="lede">{{ __('Dizayn yenidir və hələ tam hazır deyil — bir azdan sifariş üçün açılacaq. O vaxta qədər qalan dizaynlara baxa bilərsiniz.') }}</p>
    <div style="display:flex; flex-wrap:wrap; gap:.75rem; justify-content:center; margin-top:1.75rem;">
      <a class="btn btn-primary" href="{{ lroute('designs.index') }}">{{ __('Dizaynlara bax') }}</a>
      <a class="btn" href="{{ lroute('home') }}">{{ __('Ana səhifə') }}</a>
    </div>
  </div>
</section>
@endsection
