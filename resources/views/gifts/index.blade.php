@extends('layouts.app')

@php
  $labels = $pages->take(6)->pluck('menu_label')->implode(', ');
@endphp

@section('title', __('Hədiyyə fikirləri — ad günü, sevgiliyə, 8 Mart | Nefis'))
@section('meta_description', __('Kimə və hansı münasibətə hədiyyə axtarırsınız?') . ' ' . $labels . ' — '
    . __('şəkil və sözlərinizlə fərdi şokolad qutusu fikirləri.'))

@if($otherHub)
  @push('head')
    <link rel="alternate" hreflang="{{ $locale }}" href="{{ \App\Support\Seo::canonical(\App\Models\GiftPage::hubUrl($locale)) }}">
    <link rel="alternate" hreflang="{{ \App\Support\Locale::current() === 'az' ? 'ru' : 'az' }}" href="{{ \App\Support\Seo::canonical($otherHub) }}">
    <link rel="alternate" hreflang="x-default" href="{{ \App\Support\Seo::canonical(\App\Models\GiftPage::hubUrl('az')) }}">
  @endpush
@endif

@push('jsonld')
  {{ \App\Support\Seo::jsonLd(['@graph' => [
      \App\Support\Seo::breadcrumbs([
          [__('Ana səhifə'), lroute('home')],
          [__('Hədiyyə fikirləri'), \App\Models\GiftPage::hubUrl($locale)],
      ]),
      [
          '@type' => 'ItemList',
          'name' => __('Hədiyyə fikirləri'),
          'itemListElement' => $pages->values()->map(fn ($p, $i) => [
              '@type' => 'ListItem', 'position' => $i + 1, 'url' => $p->url(), 'name' => $p->title,
          ])->all(),
      ],
  ]]) }}
@endpush

@section('content')

  <section class="page-hero">
    <div class="wrap">
      <nav class="crumbs" aria-label="{{ __('Hədiyyə fikirləri') }}">
        <a href="{{ lroute('home') }}">{{ __('Ana səhifə') }}</a><span aria-hidden="true">›</span>
        <span aria-current="page">{{ __('Hədiyyə fikirləri') }}</span>
        {{-- The same ideas in another language, where the owner has written them. --}}
        @foreach(\App\Models\GiftPage::shown()->pluck('locale')->unique()->diff([$locale]) as $__other)
          <span aria-hidden="true">·</span>
          <a href="{{ \App\Models\GiftPage::hubUrl($__other) }}" lang="{{ $__other }}" hreflang="{{ $__other }}">{{ \App\Support\Locale::NAMES[$__other] ?? $__other }}</a>
        @endforeach
      </nav>
      <span class="eyebrow">{{ __('Kimə, nə üçün?') }}</span>
      <h1>{{ __('Hədiyyə fikirləri') }}</h1>
      <p class="lede">
        {{ __('Ad günü, sevgiliyə, anaya, körpəyə və ya bayrama — kimə hədiyyə axtardığınızı seçin, sizə uyğun dizaynları göstərək.') }}
      </p>
    </div>
  </section>

  <section style="padding-top:0;">
    <div class="wrap">
      @if($pages->isEmpty())
        <p class="lede" style="margin-inline:auto; text-align:center;">{{ __('Tezliklə.') }}</p>
      @else
        <div class="occ-grid">
          @foreach($pages as $page)
            <a class="occ-card reveal" href="{{ $page->url() }}">
              <span class="occ-ico">{{ $page->emoji ?: '🎁' }}</span>
              <div>
                <h2>{{ $page->linkText() }}</h2>
                <p>{{ \Illuminate\Support\Str::limit((string) $page->intro, 110) }}</p>
              </div>
            </a>
          @endforeach
        </div>
      @endif
    </div>
  </section>

@endsection
