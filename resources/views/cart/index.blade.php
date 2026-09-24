@extends('layouts.app')

@section('title', 'Səbət — Nefis Şokolad Evi')
@section('robots', 'noindex, nofollow')

@section('content')
<section class="page-hero" style="padding-bottom:0;">
  <div class="wrap">
    <span class="eyebrow" style="justify-content:center;">Səbətiniz</span>
    <h1>Sifariş Səbəti</h1>
  </div>
</section>

<section>
  <div class="wrap" style="max-width:52rem;">
    @if(session('status'))
      <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if($items->isEmpty())
      <div class="cart-empty">
        <div class="ico">🛍️</div>
        <p>Səbətiniz hələ boşdur.</p>
        <div style="margin-top:1.5rem;">
          <a href="{{ lroute('designs.index') }}" class="btn btn-primary">Dizaynlara Bax</a>
        </div>
      </div>
    @else
      <div class="cart-list">
        @foreach($items as $item)
          @php $texts = array_filter($item['custom_texts'] ?? []); $isLetter = \App\Support\Cart::isLetter($item); $isLive = \App\Support\Cart::isLive($item); @endphp
          <div class="cart-row">
            <div class="thumb">
              @if($isLive)
                <img src="{{ \App\Support\Media::url($item['ar']['image'] ?? null) }}" alt="Canlı şəkil">
              @elseif($isLetter)
                <div class="thumb-polaroid">@include('partials.polaroid', ['photo' => \App\Support\Media::url($item['letter']['photo'] ?? null), 'text' => $item['letter']['text'] ?? null])</div>
              @elseif(! empty($item['photo_paths']))
                <img src="{{ \App\Support\Media::url($item['photo_paths'][0]) }}" alt="Yüklənmiş şəkil">
                @if(count($item['photo_paths']) > 1)
                  <span class="thumb-more">+{{ count($item['photo_paths']) - 1 }}</span>
                @endif
              @else
                <img src="{{ \App\Support\Media::url($item['product']->catalogImage()) }}" alt="{{ $item['product']->name }}">
              @endif
            </div>
            <div class="info">
              <h3>{{ $isLive ? 'Canlı şəkil' : ($isLetter ? 'Polaroid məktub' : $item['product']->name) }}</h3>
              <p>
                @if($texts) "{{ implode('" · "', $texts) }}" &middot; @endif
                {{ $item['quantity'] }} ədəd
                @php $unit = \App\Support\Cart::unitPrice($item, $item['product']); @endphp
                @if($unit > 0)
                  &middot; {{ \App\Support\Price::format($unit * $item['quantity']) }}
                @endif
              </p>
              @if(! empty($item['chocolate']))
                <p style="margin-top:.2rem;">🍫 {{ $item['chocolate']['name'] }} &middot; {{ \App\Support\Price::format($item['chocolate']['price']) }}</p>
              @endif
              @if(! empty($item['wrapping']))
                <p style="margin-top:.2rem;">🎁 Qablaşdırma: {{ $item['wrapping']['name'] }} &middot; {{ \App\Support\Price::format($item['wrapping']['price']) }}</p>
              @endif
              @if($isLive)
                <p style="margin-top:.2rem;">🎬 Şəkil və video — QR kodla çap olunur</p>
              @elseif(! empty($item['ar']))
                <p style="margin-top:.2rem;">🎬 Canlı şəkil (AR) &middot; {{ \App\Support\Price::format($item['ar']['price']) }}</p>
              @endif
              @if(! empty($item['letter']))
                <p style="margin-top:.2rem;">💌 {{ $isLetter ? '' : 'Polaroid məktub · ' }}{{ \Illuminate\Support\Str::limit(str_replace("\n", ' ', $item['letter']['text'] ?? ''), 60) ?: 'şəkilli' }}
                  @unless($isLetter) &middot; {{ \App\Support\Price::format($item['letter']['price']) }} @endunless</p>
              @endif
            </div>
            <form method="POST" action="{{ lroute('cart.remove', $item['id']) }}">
              @csrf
              @method('DELETE')
              <button type="submit" class="remove-btn">Sil</button>
            </form>
          </div>
        @endforeach
      </div>

      <div class="cart-summary">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
          <span style="font-weight:700; font-size:1.125rem;">Cəmi</span>
          <span style="font-weight:700; font-size:1.125rem; color:var(--gold-deep);">
            @php $total = $items->sum(fn($i) => \App\Support\Cart::unitPrice($i, $i['product']) * $i['quantity']); @endphp
            {{ $total > 0 ? \App\Support\Price::format($total) : 'Qiymət sorğu ilə' }}
          </span>
        </div>
        <a href="{{ lroute('checkout.index') }}" class="btn btn-primary btn-block">Sifarişi Tamamla</a>
      </div>
    @endif
  </div>
</section>
@endsection
