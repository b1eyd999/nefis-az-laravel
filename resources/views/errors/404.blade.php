@php
  // No route matched, so nothing chose the language: take it from the address,
  // and speak Azerbaijani when it says nothing.
  $seg = (string) request()->segment(1);
  app()->setLocale(in_array($seg, \App\Support\Locale::published(), true) ? $seg : \App\Support\Locale::DEFAULT);
@endphp
@extends('layouts.app')

{{-- A wrong address is the cheapest place to save a visit: the shop's own
     header and footer are here, and three ways on. Crawlers may follow those
     links, so this is noindex but not nofollow. --}}
@section('robots', 'noindex, follow')
@section('title', __('Səhifə tapılmadı') . ' | Nefis')

@section('content')
<section class="page-hero">
  <div class="wrap" style="max-width:40rem;">
    <span class="eyebrow" style="justify-content:center;">404</span>
    <h1>{{ __('Belə bir səhifə yoxdur') }}</h1>
    <p class="lede">{{ __('Axtardığınız səhifə silinib və ya ünvanı dəyişib. Aşağıdakılardan davam edə bilərsiniz.') }}</p>
    <div style="display:flex; flex-wrap:wrap; gap:.75rem; justify-content:center; margin-top:1.75rem;">
      <a class="btn btn-primary" href="{{ lroute('designs.index') }}">{{ __('Dizaynlara bax') }}</a>
      <a class="btn" href="{{ \App\Models\GiftPage::hubUrl(\App\Support\Locale::current()) }}">{{ __('Hədiyyə fikirləri') }}</a>
      <a class="btn" href="{{ lroute('home') }}">{{ __('Ana səhifə') }}</a>
    </div>
  </div>
</section>
@endsection
