@extends('layouts.app')

@section('title', 'Hədiyyə fikirləri — ad günü, sevgiliyə, 8 Mart | Nefis')
@section('meta_description', 'Kimə və hansı münasibətə hədiyyə axtarırsınız? ' . $pages->take(6)->pluck('menu_label')->implode(', ') . ' — şəkil və sözlərinizlə fərdi şokolad qutusu fikirləri.')

@push('jsonld')
  {{ \App\Support\Seo::jsonLd(['@graph' => [
      \App\Support\Seo::breadcrumbs([
          ['Ana səhifə', route('home')],
          ['Hədiyyə fikirləri', route('gifts.index')],
      ]),
      [
          '@type' => 'ItemList',
          'name' => 'Hədiyyə fikirləri',
          'itemListElement' => $pages->values()->map(fn ($p, $i) => [
              '@type' => 'ListItem', 'position' => $i + 1, 'url' => $p->url(), 'name' => $p->title,
          ])->all(),
      ],
  ]]) }}
@endpush

@section('content')

  <section class="page-hero">
    <div class="wrap">
      <nav class="crumbs" aria-label="Səhifənin yeri">
        <a href="{{ route('home') }}">Ana səhifə</a><span aria-hidden="true">›</span>
        <span aria-current="page">Hədiyyə fikirləri</span>
      </nav>
      <span class="eyebrow">Kimə, nə üçün?</span>
      <h1>Hədiyyə fikirləri</h1>
      <p class="lede">Ad günü, sevgiliyə, anaya, körpəyə və ya bayrama — kimə hədiyyə axtardığınızı seçin, sizə uyğun dizaynları göstərək.</p>
    </div>
  </section>

  <section style="padding-top:0;">
    <div class="wrap">
      @if($pages->isEmpty())
        <p class="lede" style="margin-inline:auto; text-align:center;">Tezliklə.</p>
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
