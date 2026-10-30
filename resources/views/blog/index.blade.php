@extends('layouts.app')

@section('title', __('Bloq: hədiyyə, şəkil və şokolad haqqında | Nefis'))
@section('meta_description', __('Hədiyyə seçimi, qutuya hansı şəkli və hansı sözləri yazmaq, qutu necə hazırlanır — Nefis Şokolad Evinin bloqu.'))

@push('jsonld')
  {{ \App\Support\Seo::jsonLd(['@graph' => [
      \App\Support\Seo::breadcrumbs([
          [__('Ana səhifə'), lroute('home')],
          [__('Bloq'), \App\Models\Post::hubUrl($locale)],
      ]),
      [
          '@type' => 'Blog',
          'name' => __('Nefis bloqu'),
          'url' => \App\Support\Seo::canonical(\App\Models\Post::hubUrl($locale)),
          'inLanguage' => $locale,
          'blogPost' => $posts->values()->map(fn ($p) => [
              '@type' => 'BlogPosting',
              'headline' => $p->title,
              'url' => \App\Support\Seo::canonical($p->url()),
              'datePublished' => $p->published_at->toAtomString(),
          ])->all(),
      ],
  ]]) }}
@endpush

@section('page_style')
  .bl-grid{ display:grid; grid-template-columns:repeat(auto-fill, minmax(18rem, 1fr)); gap:1.25rem; }
  .bl-card{ display:flex; flex-direction:column; overflow:hidden;
    border:1px solid var(--line); border-radius:var(--radius-sm); background:var(--paper);
    transition:transform .3s var(--ease), box-shadow .3s var(--ease), border-color .3s; }
  .bl-card:hover{ transform:translateY(-5px); box-shadow:0 20px 34px -22px rgba(58,38,23,.45); border-color:rgba(250,117,18,.35); }
  .bl-shot{ position:relative; aspect-ratio:16 / 9; background:linear-gradient(140deg, var(--cream-2), var(--paper)); overflow:hidden; }
  .bl-shot img{ width:100%; height:100%; object-fit:cover; display:block; }
  .bl-shot span{ position:absolute; inset:0; display:grid; place-items:center; font-size:2.75rem; }
  .bl-body{ display:flex; flex-direction:column; gap:.5rem; padding:1.1rem 1.25rem 1.35rem; }
  .bl-meta{ display:flex; flex-wrap:wrap; align-items:center; gap:.4rem .7rem; font-size:.8125rem; color:var(--cocoa-faint); }
  .bl-topic{ padding:.2rem .6rem; border-radius:999px; background:var(--cream-2); color:var(--gold-deep); font-weight:600; }
  .bl-card h2{ font-family:var(--sans); font-size:1.0625rem; font-weight:700; line-height:1.4; color:var(--cocoa); }
  .bl-card p{ font-size:.9rem; color:var(--cocoa-soft); line-height:1.6; }
@endsection

@section('content')

  <section class="page-hero">
    <div class="wrap">
      <nav class="crumbs" aria-label="{{ __('Bloq') }}">
        <a href="{{ lroute('home') }}">{{ __('Ana səhifə') }}</a><span aria-hidden="true">›</span>
        <span aria-current="page">{{ __('Bloq') }}</span>
      </nav>
      <span class="eyebrow">{{ __('Oxumaq üçün') }}</span>
      <h1>{{ __('Bloq') }}</h1>
      <p class="lede">
        {{ __('Hədiyyə seçmək, şəkil seçmək və qutuya nə yazmaq barədə — iş görərkən öyrəndiklərimizi burada yazırıq.') }}
      </p>
    </div>
  </section>

  <section style="padding-top:0;">
    <div class="wrap">
      @if($posts->isEmpty())
        <p class="lede" style="margin-inline:auto; text-align:center;">{{ __('Tezliklə.') }}</p>
      @else
        <div class="bl-grid">
          @foreach($posts as $post)
            <a class="bl-card reveal" href="{{ $post->url() }}">
              <div class="bl-shot">
                @if($post->coverUrl())
                  <img src="{{ $post->coverUrl() }}" alt="{{ $post->title }}"
                       loading="{{ $loop->index < 2 ? 'eager' : 'lazy' }}" decoding="async">
                @else
                  <span aria-hidden="true">{{ $post->emoji ?: '📖' }}</span>
                @endif
              </div>
              <div class="bl-body">
                <div class="bl-meta">
                  @if($post->topic)<span class="bl-topic">{{ $post->topic }}</span>@endif
                  <time datetime="{{ $post->published_at->toDateString() }}">{{ $post->dateText() }}</time>
                  {{-- No separator between these two: on a narrow phone the
                       line wraps here, and a lone «·» at the end of a line
                       reads as a mistake. The gap does the same work. --}}
                  <span>{{ $post->readingText() }}</span>
                </div>
                <h2>{{ $post->title }}</h2>
                <p>{{ $post->lede() }}</p>
              </div>
            </a>
          @endforeach
        </div>
      @endif
    </div>
  </section>

@endsection
