@extends('layouts.app')

@section('title', 'Sifarişlərim — Nefis Şokolad Evi')

@section('content')
<section class="page-hero" style="padding-bottom:0;">
  <div class="wrap">
    <span class="eyebrow" style="justify-content:center;">Hesabım</span>
    <h1>Sifarişlərim</h1>
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
        <p>Hələ heç bir sifarişiniz yoxdur.</p>
        <div style="margin-top:1.5rem;">
          <a href="{{ route('designs.index') }}" class="btn btn-primary">Dizaynlara Bax</a>
        </div>
      </div>
    @else
      <div class="cart-list">
        @foreach($orders as $order)
          <div class="cart-summary" style="margin-bottom:0;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
              <span style="font-weight:700;">Sifariş #{{ $order->id }}</span>
              <span class="chip" style="border:1px solid var(--line); padding:.25rem .75rem; border-radius:999px; font-size:.75rem; text-transform:uppercase;">
                @switch($order->status)
                  @case('pending') Gözləmədə @break
                  @case('confirmed') Təsdiqləndi @break
                  @case('completed') Tamamlandı @break
                  @case('cancelled') Ləğv edildi @break
                  @default {{ $order->status }}
                @endswitch
              </span>
            </div>
            @foreach($order->items as $item)
              @php
                $photo = ($item->customer_photos ?? [])[0] ?? $item->customer_photo ?? $item->product?->catalogImage();
                $texts = collect($item->fields()['texts'])->reject(fn ($t) => $t['fixed'] || $t['value'] === '')->pluck('value')->all();
              @endphp
              <div class="cart-row" style="background:var(--cream); margin-bottom:.5rem;">
                <div class="thumb">@if($photo)<img src="{{ \App\Support\Media::url($photo) }}" alt="Yüklənmiş şəkil">@endif</div>
                <div class="info">
                  <h3>{{ $item->product->name ?? $item->product_name ?? 'Silinmiş məhsul' }}</h3>
                  <p>
                    @if($texts) "{{ implode('" · "', $texts) }}" &middot; @endif
                    {{ $item->quantity }} ədəd
                    @if($item->unitPrice() > 0) &middot; {{ \App\Support\Price::format($item->unitPrice() * $item->quantity) }} @endif
                  </p>
                  @if($item->chocolate_name)
                    <p style="margin-top:.2rem;">🍫 {{ $item->chocolate_name }}</p>
                  @endif
                </div>
              </div>
            @endforeach
            <p style="font-size:.8125rem; color:var(--cocoa-soft); margin-top:.75rem;">{{ $order->created_at->format('d.m.Y H:i') }}</p>
          </div>
        @endforeach
      </div>
    @endif
  </div>
</section>
@endsection
