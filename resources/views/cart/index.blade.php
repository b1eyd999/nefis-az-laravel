@extends('layouts.app')

@section('title', 'Səbət — Nefis Şokolad Evi')

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
          <a href="{{ route('home') }}#collections" class="btn btn-primary">Dizaynlara Bax</a>
        </div>
      </div>
    @else
      <div class="cart-list">
        @foreach($items as $item)
          <div class="cart-row">
            <div class="thumb">
              <img src="{{ asset('storage/' . $item['photo_path']) }}" alt="Yüklənmiş şəkil">
            </div>
            <div class="info">
              <h3>{{ $item['product']->name }}</h3>
              <p>
                @if($item['custom_text']) "{{ $item['custom_text'] }}" &middot; @endif
                {{ $item['quantity'] }} ədəd
                @if($item['product']->price)
                  &middot; {{ number_format($item['product']->price * $item['quantity']) }} ₼
                @endif
              </p>
            </div>
            <form method="POST" action="{{ route('cart.remove', $item['id']) }}">
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
            @php $total = $items->sum(fn($i) => ($i['product']->price ?? 0) * $i['quantity']); @endphp
            {{ $total > 0 ? number_format($total) . ' ₼' : 'Qiymət sorğu ilə' }}
          </span>
        </div>
        <a href="{{ route('checkout.index') }}" class="btn btn-primary btn-block">Sifarişi Tamamla</a>
      </div>
    @endif
  </div>
</section>
@endsection
