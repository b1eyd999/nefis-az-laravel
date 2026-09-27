@extends('phone.layout')

@section('title', 'Sifarişlər — Nefis admin')
@section('heading', 'Sifarişlər')
@section('tab', 'orders')
@section('sub', $orders->total() . ' sifariş' . ($status === '' ? ' — iş növbəsi' : ''))

@section('chips')
  @php
    $chips = ['' => 'İş növbəsi', 'all' => 'Hamısı'] + \App\Models\Order::STATUSES;
  @endphp
  <div class="ph-chips">
    @foreach($chips as $key => $label)
      <a class="ph-chip {{ $status === $key ? 'on' : '' }}"
         href="{{ route('phone.orders.index', $key === '' ? [] : ['status' => $key]) }}">
        {{ $label }}<i>{{ $counts[$key] ?? 0 }}</i>
      </a>
    @endforeach
  </div>
@endsection

@section('content')
  @php
    $pills = [
      'awaiting_payment' => 'pill-gray', 'payment_check' => 'pill-warn', 'pending' => 'pill-warn',
      'confirmed' => 'pill-info', 'ready' => 'pill-flame', 'completed' => 'pill-ok', 'cancelled' => 'pill-bad',
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
    @endphp
    @if($heading !== $lastDay)
      @php $lastDay = $heading; @endphp
      <div class="ph-day {{ $day === $today ? 'today' : '' }}">{{ $heading }}</div>
    @endif

    <a class="ph-card" href="{{ route('phone.orders.show', $order) }}">
      <div class="row">
        <span class="who">{{ $order->user?->name ?? $order->recipient_name ?? '—' }}</span>
        <span class="num">#{{ $order->id }}</span>
      </div>
      <div class="when">
        <span class="pill {{ $pills[$order->status] ?? 'pill-gray' }}">{{ $order->statusLabel() }}</span>
        @if($order->isRush())<span class="rush">· Təcili</span>@endif
        @if($order->delivery_slot)<span class="small"> · {{ $order->delivery_slot }}</span>@endif
      </div>
      <div class="money"><b>{{ \App\Support\Price::format($order->total()) }}</b> · {{ $order->items_count }} məhsul</div>
    </a>
  @empty
    <div class="ph-empty">
      <b>Bu siyahı boşdur</b>
      Başqa bir vəziyyət seçin, ya da «Hamısı»na baxın.
    </div>
  @endforelse

  @if($orders->hasPages())
    <div class="ph-btns">
      @if($orders->onFirstPage())
        <span class="ph-btn" aria-disabled="true" style="opacity:.45">‹ Əvvəlki</span>
      @else
        <a class="ph-btn" href="{{ $orders->previousPageUrl() }}">‹ Əvvəlki</a>
      @endif
      @if($orders->hasMorePages())
        <a class="ph-btn" href="{{ $orders->nextPageUrl() }}">Növbəti ›</a>
      @else
        <span class="ph-btn" aria-disabled="true" style="opacity:.45">Növbəti ›</span>
      @endif
    </div>
  @endif
@endsection
