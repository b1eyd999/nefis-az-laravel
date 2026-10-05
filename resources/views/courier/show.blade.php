@extends('courier.layout')

@section('title', 'Sifariş #' . $order->id . ' — Kuryer')
@section('heading', 'Sifariş #' . $order->id)
@section('back', route('courier.index'))
@section('sub', $order->statusLabel() . ($order->delivery_slot ? ' · ' . $order->delivery_slot : ''))

@section('content')
  @php
    $phone = $order->contact_phone ?: $order->user?->phone;
    $whatsapp = \App\Support\CustomerNotice::phone($phone);
    $map = $order->mapUrl();
    $boxes = $order->items->sum('quantity');
  @endphp

  {{-- Where, and how to get there. --}}
  <div class="ph-block">
    <div class="ku-addr">{{ $order->delivery_address ?: $order->deliverySummary() ?: 'Ünvan yoxdur' }}</div>
    @if($order->delivery_date)
      <div class="ku-note">{{ \App\Support\DeliveryTime::day($order->delivery_date) }}{{ $order->delivery_slot ? ', ' . $order->delivery_slot : '' }}</div>
    @endif
    @if($order->note)
      <div class="ph-note">{{ $order->note }}</div>
    @endif
    <div class="ku-grid">
      @if($map)
        <a class="ph-btn" href="{{ $map }}" target="_blank" rel="noopener">Xəritədə aç ↗</a>
      @endif
      @if($phone)
        <a class="ph-btn" href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}">Zəng et</a>
      @endif
      @if($whatsapp)
        <a class="ph-btn" href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener">WhatsApp</a>
      @endif
    </div>
  </div>

  {{-- Who is waiting for it, and what he is holding. --}}
  <div class="ph-block">
    <div class="ph-line"><span>Müştəri</span><b>{{ $order->user?->name ?? $order->recipient_name ?? '—' }}</b></div>
    @if($phone)<div class="ph-line"><span>Telefon</span><b>{{ $phone }}</b></div>@endif
    <div class="ph-line"><span>Qutu sayı</span><b>{{ $boxes }}</b></div>
    @foreach($order->items as $item)
      <div class="ph-line">
        <span>{{ $item->product?->name ?? 'Məhsul' }}</span>
        <b>× {{ $item->quantity }}</b>
      </div>
    @endforeach
  </div>

  {{-- The money, said once and plainly: either he takes something or he does
       not, and a part-paid order says what is left. --}}
  <div class="ph-block">
    @if($collect > 0)
      <div class="ph-line"><span>Qapıda alınacaq</span><span class="ku-collect">{{ \App\Support\Price::format($collect) }}</span></div>
      @if($order->isPaidFor())
        <div class="ku-note">Sifariş ödənilib, bu — sonradan edilən dəyişikliyin fərqidir.</div>
      @else
        <div class="ku-note">Sifariş hələ ödənilməyib.</div>
      @endif
    @else
      <div class="ph-line"><span>Ödəniş</span><b>Ödənilib — heç nə alınmır</b></div>
    @endif
    @if($order->owedBack() > 0)
      <div class="ku-note">Müştəriyə {{ \App\Support\Price::format($order->owedBack()) }} qaytarılmalıdır — bunu admin özü edir, siz deyil.</div>
    @endif
  </div>

  {{-- The two taps of the trip — the courier's own, and only his. The owner
       reading this page sees what the man sees and nothing he can press: he
       completes an order from the panel, under his own name. --}}
  @unless($me->isCourier())
    <div class="ph-note">Bu düymələr kuryerindədir. Sifarişi siz paneldən tamamlaya bilərsiniz.</div>
  @else
  <div class="ph-btns">
    @if(! $order->isOnTheWay())
      <form method="POST" action="{{ route('courier.way', $order) }}" data-once>
        @csrf
        <button class="ph-btn ph-btn-primary" data-busy="…">Yola düşdüm</button>
      </form>
    @else
      <span class="ph-chip on">Yoldasınız — {{ $order->on_the_way_at?->format('H:i') }}</span>
    @endif
  </div>

  {{-- The end of the trip. He confirms it himself at the door, and the hour
       he confirms is the hour the shop shows from then on — nobody has to
       remember it and nothing moves it afterwards. --}}
  @if($order->isDelivered())
    <div class="ph-block" style="text-align:center;">
      <div class="ku-collect" style="font-size:1.1rem;">Təhvil verildi ✓</div>
      <div class="ku-note">{{ $order->delivered_at->format('d.m.Y, H:i') }}</div>
    </div>
  @else
    <form method="POST" action="{{ route('courier.delivered', $order) }}" data-once
          onsubmit="return confirm('{{ $order->user?->name ?? 'Müştəri' }} sifarişi #{{ $order->id }} aldı?\nTəsdiqlənən vaxt qeyd olunacaq.')">
      @csrf
      <button class="ph-btn ph-btn-primary" style="width:100%" data-busy="…">Təhvil verdim</button>
    </form>
  @endif
  @endunless
@endsection
