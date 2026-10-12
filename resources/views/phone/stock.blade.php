@extends('phone.layout')

@section('title', 'Anbar — Nefis admin')
@section('heading', 'Anbar')
@section('tab', 'stock')
@php
  /* What a plain box costs, and what wrapping one adds on top. Two figures
     rather than one: most boxes are not wrapped, and the single figure that
     quietly carried the wrapping was what sent the whole maya dəyəri wrong. */
  $wrapSum = $perWrap + ($perRibbon ? $perRibbon[0] : 0);
  $wrapMax = $perWrap + ($perRibbon ? $perRibbon[1] : 0);
  $wrapped = $wrapSum <= 0 ? null : '+' . \App\Support\Price::format($wrapSum)
    . ($wrapMax > $wrapSum ? '…' . \App\Support\Price::format($wrapMax) : '');
@endphp
@section('sub', 'Bir qutunun materialı: ' . \App\Support\Price::format($perBox) . ' (şokoladsız)'
  . ($wrapped ? ' · bükülürsə ' . $wrapped : ''))

@section('content')
  @php $qty = fn ($v) => \App\Http\Controllers\Phone\StockController::qty((float) $v); @endphp

  @forelse($materials as $m)
    @php
      $left = $m->boxesLeft();
      $state = $m->isNew() ? '' : ($m->stock <= 0 ? 'bad' : ($m->isLow() ? 'warn' : 'ok'));
      /* What this one is spent on. A ribbon is only cut when a wrap that
         names it is ordered, so saying «hər qutuya» over it would be a lie
         the owner would plan his buying by. */
      $on = match ($m->spent_on) {
        \App\Models\Material::WRAP => 'hər bükməyə',
        \App\Models\Material::RIBBON => $m->wrappings_count
          ? $m->wrappings_count . ' qablaşdırmada'
          : 'heç bir qablaşdırmada seçilməyib',
        default => 'hər qutuya',
      };
    @endphp
    <div class="ph-block">
      <div class="mat">
        <div class="grow">
          <div class="name">{{ $m->name }} <span class="on {{ $m->spent_on }}">{{ $on }}</span></div>
          <div class="left {{ $state }}">{{ $m->isNew() ? 'sayılmayıb' : ($m->stock <= 0 ? 'bitib' : $qty($m->stock) . ' ' . $m->unit) }}</div>
          <div class="small">
            @if($left === null)
              bir dəfəyə nə qədər getdiyi yazılmayıb
            @elseif($m->isNew())
              hələ sayılmayıb — «Alış» və ya «Sayım» ilə yazın
            @elseif($m->stock <= 0)
              almaq lazımdır
            @else
              {{ $left }} {{ $m->coversTo() }} bəs edir{{ $m->isLow() ? ' — almaq lazımdır' : '' }}
            @endif
          </div>
          <div class="small">
            @if($m->pack_price <= 0)
              qiyməti hələ yazılmayıb — «Alış» ilə yazın
            @else
              paçka {{ \App\Support\Price::format($m->pack_price) }} / {{ $qty($m->pack_size) }} {{ $m->unit }}
              · {{ $m->coversTo() }} {{ \App\Support\Price::format($m->costPerBox()) }}
            @endif
          </div>
        </div>
      </div>
      <div class="ph-btns" style="margin-top:.6rem;">
        <button class="ph-btn ph-btn-sm ph-btn-primary" data-sheet="buy-{{ $m->id }}">Alış</button>
        <button class="ph-btn ph-btn-sm" data-sheet="count-{{ $m->id }}">Sayım</button>
      </div>
    </div>

    <dialog class="sheet" id="buy-{{ $m->id }}">
      <div class="sheet-in">
        <div class="sheet-grip"></div>
        <h2>{{ $m->name }} — alış</h2>
        <form method="POST" action="{{ route('phone.stock.purchase', $m) }}" data-once>
          @csrf
          <label class="ph-field">
            <span>Neçə paçka</span>
            <input type="number" name="packs" inputmode="decimal" step="0.001" min="0.001" placeholder="1" required>
          </label>
          <label class="ph-field">
            <span>Bir paçkanın qiyməti, ₼</span>
            <input type="number" name="pack_price" inputmode="decimal" step="0.01" min="0"
                   value="{{ $m->pack_price }}" required>
          </label>
          <p class="ph-note">Qiyməti dəyişsəniz, paçkanın qiyməti də yenilənəcək —
            bundan sonrakı hesablamalar yeni qiymətlə gedəcək.</p>
          <label class="ph-field">
            <span>Qeyd</span>
            <textarea name="note" placeholder="Haradan alındı, istəyə bağlı"></textarea>
          </label>
          <button class="ph-btn ph-btn-primary" data-busy="Yazılır…">Alışı yaz</button>
          <button type="button" class="ph-btn" style="margin-top:.5rem;" data-close-sheet>İmtina</button>
        </form>
      </div>
    </dialog>

    <dialog class="sheet" id="count-{{ $m->id }}">
      <div class="sheet-in">
        <div class="sheet-grip"></div>
        <h2>{{ $m->name }} — sayım</h2>
        <form method="POST" action="{{ route('phone.stock.adjust', $m) }}" data-once>
          @csrf
          <label class="ph-field">
            <span>Anbarda əslində neçə {{ $m->unit }} var</span>
            <input type="number" name="counted" inputmode="decimal" step="0.001" min="0"
                   value="{{ $qty($m->stock) }}" required>
          </label>
          <p class="ph-note">Fərq anbar hərəkəti kimi yazılacaq. Sayım anbardakı ilə eynidirsə, heç nə yazılmır.</p>
          <button class="ph-btn ph-btn-primary" data-busy="Yazılır…">Sayımı yaz</button>
          <button type="button" class="ph-btn" style="margin-top:.5rem;" data-close-sheet>İmtina</button>
        </form>
      </div>
    </dialog>
  @empty
    <div class="ph-empty">
      <b>Anbar boşdur</b>
      Materialları kompüterdəki admin panelində əlavə edin.
    </div>
  @endforelse

  <p class="ph-note">Paçkanın ölçüsü, bir qutuya gedən miqdar və həddi kompüterdə dəyişilir —
    səhv rəqəm bütün maya dəyərini pozur.</p>
@endsection
