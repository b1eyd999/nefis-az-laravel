@extends('phone.layout')

@section('title', 'Canlı şəkillər — Nefis admin')
@section('heading', 'Canlı şəkillər')
@section('tab', 'live')
@section('sub', $lives->total() . ' şəkil' . ($stuck > 0 ? ' · ' . $stuck . ' video hostinqdə qalıb' : ''))

@section('chips')
  <div class="ph-chips">
    <a class="ph-chip {{ $onlyStuck ? '' : 'on' }}" href="{{ route('phone.live.index') }}">Hamısı</a>
    <a class="ph-chip {{ $onlyStuck ? 'on' : '' }}" href="{{ route('phone.live.index', ['stuck' => 1]) }}">
      Yandex-ə köçməyib<i>{{ $stuck }}</i>
    </a>
  </div>
@endsection

@section('content')
  @unless(\App\Support\YandexDisk::hasToken())
    <div class="ph-warn">
      <b>Yandex Disk qoşulmayıb</b>
      Videolar hostinqdə qalır. Tokeni kompüterdəki admin panelində yazın.
    </div>
  @endunless

  @if($stuck > 0)
    <div class="ph-block">
      <h2>Hostinqdə qalan videolar</h2>
      <p class="ph-note" style="margin-top:0;">{{ $stuck }} video Yandex Diskə köçməyib. Köçürmə arxa planda gedir —
        bir az sonra səhifəni yeniləyin.</p>
      <form method="POST" action="{{ route('phone.live.push') }}" data-once>
        @csrf
        <button class="ph-btn ph-btn-primary" data-busy="Başladılır…">Videoları Yandex-ə köçür</button>
      </form>
    </div>
  @endif

  @forelse($lives as $live)
    @php
      $place = $live->videoPlace();
      $ready = filled($live->target_mind);
    @endphp
    <div class="ph-block">
      <div class="live-row">
        {{-- The picture only; measuring it would open every file on the disk. --}}
        <img src="{{ \App\Support\Media::url($live->target_image) }}" alt="" loading="lazy">
        <div class="grow">
          <div style="font-weight:700; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
            {{ $live->title ?: ($live->orderItem?->product_name ?? 'Canlı şəkil') }}
          </div>
          <div class="ph-note" style="margin:0;">
            @if($live->orderItem?->order_id)
              <a class="ph-link" href="{{ route('phone.orders.show', $live->orderItem->order_id) }}">#{{ $live->orderItem->order_id }}</a>
            @else
              sifarişsiz
            @endif
          </div>
          <div class="chips-2">
            <span class="pill {{ $ready ? 'pill-ok' : 'pill-warn' }}">{{ $ready ? 'Hazır' : 'Hələ hazır deyil' }}</span>
            <span class="pill {{ $place === 'yandex' ? 'pill-ok' : ($place === 'hosting' ? 'pill-warn' : 'pill-gray') }}">
              {{ match ($place) { 'yandex' => 'Yandex Disk', 'hosting' => 'Hostinqdə', default => 'Video yoxdur' } }}
            </span>
          </div>
        </div>
      </div>
      @unless($ready)
        <p class="ph-note" style="margin:.5rem 0 0;">Kamera üçün hazırlanmayıb — bu, kompüterdəki admin panelində
          «Hədəfi hazırla» ilə edilir.</p>
      @endunless
    </div>
  @empty
    <div class="ph-empty">
      <b>{{ $onlyStuck ? 'Hamısı köçürülüb' : 'Hələ canlı şəkil yoxdur' }}</b>
      {{ $onlyStuck ? 'Hostinqdə qalan video yoxdur.' : 'Müştəri sifariş verəndə burada görünəcək.' }}
    </div>
  @endforelse

  @if($lives->hasPages())
    <div class="ph-btns">
      @if($lives->onFirstPage())
        <span class="ph-btn" aria-disabled="true" style="opacity:.45">‹ Əvvəlki</span>
      @else
        <a class="ph-btn" href="{{ $lives->previousPageUrl() }}">‹ Əvvəlki</a>
      @endif
      @if($lives->hasMorePages())
        <a class="ph-btn" href="{{ $lives->nextPageUrl() }}">Növbəti ›</a>
      @else
        <span class="ph-btn" aria-disabled="true" style="opacity:.45">Növbəti ›</span>
      @endif
    </div>
  @endif
@endsection
