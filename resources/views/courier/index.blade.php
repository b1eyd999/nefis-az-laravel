@extends('courier.layout')

@section('title', 'Sifarişlərim — Kuryer')
@section('heading', 'Sifarişlərim')
@section('sub', $orders->count() . ' sifariş' . ($done->count() ? ' · bu gün ' . $done->count() . ' təhvil' : ''))

@section('content')
  @php
    $pills = [
      'awaiting_payment' => 'pill-gray', 'payment_check' => 'pill-warn', 'pending' => 'pill-warn',
      'confirmed' => 'pill-info', 'ready' => 'pill-flame',
    ];
    $today = now()->toDateString();
    $tomorrow = now()->addDay()->toDateString();
    $lastDay = null;
  @endphp

  @forelse($orders as $order)
    @php
      $day = $order->delivery_date?->toDateString();
      $heading = $day === null ? 'Tarix seçilməyib'
        : ($day === $today ? 'Bu gün' : ($day === $tomorrow ? 'Sabah' : \App\Support\DeliveryTime::day($order->delivery_date)));
      $collect = $order->isPaidFor() ? $order->outstanding() : round($order->total() + $order->outstanding(), 2);
    @endphp
    @if($heading !== $lastDay)
      @php $lastDay = $heading; @endphp
      <div class="ph-day {{ $day === $today ? 'today' : '' }}">{{ $heading }}</div>
    @endif

    <a class="ph-card" href="{{ route('courier.show', $order) }}">
      <div class="row">
        <span class="who">{{ $order->user?->name ?? $order->recipient_name ?? '—' }}</span>
        <span class="num">#{{ $order->id }}</span>
      </div>
      <div class="ku-addr">{{ $order->delivery_address ?: $order->deliverySummary() ?: 'Ünvan yoxdur' }}</div>
      <div class="when">
        <span class="pill {{ $pills[$order->status] ?? 'pill-gray' }}">{{ $order->statusLabel() }}</span>
        @if($order->delivery_slot)<span class="small"> · {{ $order->delivery_slot }}</span>@endif
        @if($order->isOnTheWay())<span class="small"> · yoldasınız</span>@endif
      </div>
      {{-- The one number he needs at the door: what to take, or nothing. --}}
      <div class="money">
        @if($collect > 0)
          <b>Alınacaq: {{ \App\Support\Price::format($collect) }}</b>
        @else
          <b>Ödənilib</b> · heç nə alınmır
        @endif
      </div>
    </a>
  @empty
    <div class="ph-empty">
      <b>Hələ sifariş yoxdur</b>
      @if($me->isCourier())
        Admin sizə sifariş verəndə burada görünəcək.
      @else
        Sifarişi kuryerə vermək üçün: admin panel → «Sifarişlər» → sifarişi açın → «Kuryerə ver».
        Həmin kuryer öz telefonunda bu səhifəni açanda sifarişi burada görəcək.
      @endif
    </div>
  @endforelse

  @if($done->count())
    <div class="ph-day">Bu gün təhvil verildi</div>
    @foreach($done as $order)
      <div class="ph-line">
        <span>#{{ $order->id }} — {{ \Illuminate\Support\Str::limit($order->delivery_address, 40) ?: '—' }}</span>
        <b>{{ $order->delivered_at?->format('H:i') }}</b>
      </div>
    @endforeach
  @endif
@endsection
