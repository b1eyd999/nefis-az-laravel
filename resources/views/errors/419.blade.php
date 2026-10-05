@php
  /* Nothing routed here either: the language comes from the address, and from
     Azerbaijani when the address says nothing. */
  $seg = (string) request()->segment(1);
  app()->setLocale(in_array($seg, \App\Support\Locale::published(), true) ? $seg : \App\Support\Locale::DEFAULT);
@endphp
@extends('layouts.app')

{{-- The page sat open until the session ran out, and the form it sent was no
     longer recognised. Laravel's own answer to that is a bare "Page Expired"
     in English, which on this shop is both a fright and the wrong language.

     The way out is on the page itself: this answer is drawn now, so the form
     below carries a token that is good — one tap and the thing he was trying
     to do goes through. --}}
@section('robots', 'noindex, nofollow')
@section('title', __('Səhifənin vaxtı bitdi') . ' | Nefis')

@section('content')
<section class="page-hero">
  <div class="wrap" style="max-width:40rem;">
    <span class="eyebrow" style="justify-content:center;">419</span>
    <h1>{{ __('Səhifənin vaxtı bitdi') }}</h1>
    <p class="lede">
      {{ __('Səhifə uzun müddət açıq qaldı və təhlükəsizlik üçün sessiya bağlandı. Heç nə itmədi — aşağıdan davam edin.') }}
    </p>
    <div style="display:flex; flex-wrap:wrap; gap:.75rem; justify-content:center; margin-top:1.75rem;">
      @auth
        {{-- Still signed in, so the sign-out he asked for is one tap away,
             this time with a token from this very page. --}}
        <form method="POST" action="{{ lroute('logout') }}">
          @csrf
          <button class="btn btn-primary" type="submit">{{ __('Çıxış') }}</button>
        </form>
      @else
        <a class="btn btn-primary" href="{{ lroute('login') }}">{{ __('Giriş') }}</a>
      @endauth
      <a class="btn" href="{{ lroute('home') }}">{{ __('Ana səhifə') }}</a>
    </div>
  </div>
</section>
@endsection
