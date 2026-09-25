@extends('layouts.app')

@section('title', __('Sifarişlərim') . ', Nefis Şokolad Evi')
@section('robots', 'noindex, nofollow')

@section('content')
<section class="page-hero" style="padding-bottom:0;">
  <div class="wrap">
    <span class="eyebrow" style="justify-content:center;">{{ __('Hesabım') }}</span>
    <h1>{{ __('Sifarişlərim') }}</h1>
  </div>
</section>

<section>
  <div class="wrap" style="max-width:52rem;">
    @if(session('status'))
      <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if($orders->isEmpty())
      <div class="cart-empty">
        <div class="ico">📦</div>
        <p>{{ __('Hələ heç bir sifarişiniz yoxdur.') }}</p>
        <div style="margin-top:1.5rem;">
          <a href="{{ lroute('designs.index') }}" class="btn btn-primary">{{ __('Dizaynlara Bax') }}</a>
        </div>
      </div>
    @else
      <div class="cart-list">
        @foreach($orders as $order)
          <div class="cart-summary" style="margin-bottom:0;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
              <span style="font-weight:700;">{{ __('Sifariş') }} #{{ $order->id }}</span>
              <span class="chip" style="border:1px solid var(--line); padding:.25rem .75rem; border-radius:999px; font-size:.75rem;">
                {{ $order->statusLabel() }}
              </span>
            </div>
            @foreach($order->items as $item)
              @php
                $live = $item->ar_price !== null ? $item->livePhotos->sortByDesc('id')->first() : null;
                $photo = ($item->customer_photos ?? [])[0] ?? $item->customer_photo ?? $item->product?->catalogImage() ?? $live?->target_image;
                $texts = collect($item->fields()['texts'])->reject(fn ($t) => $t['fixed'] || $t['value'] === '')->pluck('value')->all();
              @endphp
              <div class="cart-row" style="background:var(--cream); margin-bottom:.5rem;">
                <div class="thumb">
                  @if($item->isLetterOnly())
                    <div class="thumb-polaroid">@include('partials.polaroid', ['photo' => $item->letterPhotoUrl(), 'text' => $item->letter_text])</div>
                  @elseif($photo)
                    <img src="{{ \App\Support\Media::url($photo) }}" alt="{{ __('Yüklənmiş şəkil') }}">
                  @endif
                </div>
                <div class="info">
                  <h3>{{ $item->product->name ?? $item->product_name ?? __('Silinmiş məhsul') }}</h3>
                  <p>
                    @if($texts) "{{ implode('" · "', $texts) }}" &middot; @endif
                    {{ __(':count ədəd', ['count' => $item->quantity]) }}
                    @if($item->unitPrice() > 0) &middot; {{ \App\Support\Price::format($item->unitPrice() * $item->quantity) }} @endif
                  </p>
                  @if($item->chocolate_name)
                    <p style="margin-top:.2rem;">🍫 {{ $item->chocolate_name }}</p>
                  @endif
                  @if($item->wrapping_name)
                    <p style="margin-top:.2rem;">🎁 {{ __('Qablaşdırma') }}: {{ $item->wrapping_name }}</p>
                  @endif
                  @if($item->ar_price !== null)
                    <p style="margin-top:.2rem;">🎬 {{ __('Canlı şəkil (AR)') }}
                      @if($live && ! $order->awaitsPayment() && $order->status !== 'cancelled')
                        &middot; <a href="{{ $live->url() }}" target="_blank" rel="noopener" style="text-decoration:underline;">{{ $live->isReady() ? __('Canlı şəklə bax') : __('hazırlanır') }}</a>
                      @endif
                    </p>
                  @endif
                  @if($item->hasLetter() && ! $item->isLetterOnly())
                    <p style="margin-top:.2rem;">💌 {{ __('Polaroid məktub') }}</p>
                  @endif
                </div>
              </div>
            @endforeach
            @if($order->delivery_name)
              <p style="font-size:.875rem; color:var(--cocoa-soft); margin-top:.5rem;">
                🚚 {{ $order->delivery_name }}, {{ $order->deliverySummary() }}
                @if($order->delivery_date)
                  <br>🗓 {{ \App\Support\DeliveryTime::day($order->delivery_date) }}@if($order->delivery_slot), {{ $order->delivery_slot }}@endif
                @endif
                &middot; {{ $order->delivery_price > 0 ? \App\Support\Price::format($order->delivery_price) : 'pulsuz' }}
              </p>
            @endif
            @if($order->total() > 0)
              <p style="font-weight:700; margin-top:.35rem;">{{ __('Cəmi') }}: {{ \App\Support\Price::format($order->total()) }}</p>
            @endif
            @if($order->awaitsPayment())
              <a href="{{ lroute('orders.pay', $order) }}" class="btn btn-primary" style="margin-top:.75rem;">
                {{ $order->payment_receipt ? __('Ödəniş səhifəsi') : __('Ödənişi tamamla') }}
              </a>
            @endif
            <p style="font-size:.8125rem; color:var(--cocoa-soft); margin-top:.75rem;">{{ $order->created_at->format('d.m.Y H:i') }}</p>
          </div>
        @endforeach
      </div>
    @endif
  </div>
</section>
@endsection
