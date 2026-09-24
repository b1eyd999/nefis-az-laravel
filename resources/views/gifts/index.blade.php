@extends('layouts.app')

@php
  $ru = $locale === 'ru';
  $labels = $pages->take(6)->pluck('menu_label')->implode(', ');
@endphp

@section('lang', $locale)
@section('title', $ru
    ? 'Идеи подарков — на день рождения, девушке, на 8 марта | Nefis'
    : 'Hədiyyə fikirləri — ad günü, sevgiliyə, 8 Mart | Nefis')
@section('meta_description', $ru
    ? 'Кому и на какой повод вы ищете подарок? ' . $labels . ' — коробка шоколада с вашим фото и словами. Доставка по Баку и регионам.'
    : 'Kimə və hansı münasibətə hədiyyə axtarırsınız? ' . $labels . ' — şəkil və sözlərinizlə fərdi şokolad qutusu fikirləri.')

@if($otherHub)
  @push('head')
    <link rel="alternate" hreflang="{{ $locale }}" href="{{ \App\Support\Seo::canonical(\App\Models\GiftPage::hubUrl($locale)) }}">
    <link rel="alternate" hreflang="{{ $ru ? 'az' : 'ru' }}" href="{{ \App\Support\Seo::canonical($otherHub) }}">
    <link rel="alternate" hreflang="x-default" href="{{ \App\Support\Seo::canonical(\App\Models\GiftPage::hubUrl('az')) }}">
  @endpush
@endif

@push('jsonld')
  {{ \App\Support\Seo::jsonLd(['@graph' => [
      \App\Support\Seo::breadcrumbs([
          [$ru ? 'Главная' : 'Ana səhifə', lroute('home')],
          [$ru ? 'Идеи подарков' : 'Hədiyyə fikirləri', \App\Models\GiftPage::hubUrl($locale)],
      ]),
      [
          '@type' => 'ItemList',
          'name' => $ru ? 'Идеи подарков' : 'Hədiyyə fikirləri',
          'itemListElement' => $pages->values()->map(fn ($p, $i) => [
              '@type' => 'ListItem', 'position' => $i + 1, 'url' => $p->url(), 'name' => $p->title,
          ])->all(),
      ],
  ]]) }}
@endpush

@section('content')

  <section class="page-hero">
    <div class="wrap">
      <nav class="crumbs" aria-label="{{ $ru ? 'Идеи подарков' : 'Hədiyyə fikirləri' }}">
        <a href="{{ lroute('home') }}">{{ $ru ? 'Главная' : 'Ana səhifə' }}</a><span aria-hidden="true">›</span>
        <span aria-current="page">{{ $ru ? 'Идеи подарков' : 'Hədiyyə fikirləri' }}</span>
        @if($otherHub)
          <span aria-hidden="true">·</span>
          <a href="{{ $otherHub }}" lang="{{ $ru ? 'az' : 'ru' }}" hreflang="{{ $ru ? 'az' : 'ru' }}">{{ $ru ? 'Azərbaycanca' : 'На русском' }}</a>
        @endif
      </nav>
      <span class="eyebrow">{{ $ru ? 'Кому и на какой повод?' : 'Kimə, nə üçün?' }}</span>
      <h1>{{ $ru ? 'Идеи подарков' : 'Hədiyyə fikirləri' }}</h1>
      <p class="lede">
        {{ $ru
            ? 'День рождения, девушке, маме, ребёнку или к празднику — выберите, кому ищете подарок, и мы покажем подходящие дизайны.'
            : 'Ad günü, sevgiliyə, anaya, körpəyə və ya bayrama — kimə hədiyyə axtardığınızı seçin, sizə uyğun dizaynları göstərək.' }}
      </p>
    </div>
  </section>

  <section style="padding-top:0;">
    <div class="wrap">
      @if($pages->isEmpty())
        <p class="lede" style="margin-inline:auto; text-align:center;">{{ $ru ? 'Скоро.' : 'Tezliklə.' }}</p>
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
