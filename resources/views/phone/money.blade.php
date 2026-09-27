@extends('phone.layout')

@section('title', 'Kassa — Nefis admin')
@section('heading', 'Kassa')
@section('tab', 'money')
@section('sub', $from ? $from->format('d.m.Y') . ' — ' . $to->format('d.m.Y') : 'bütün vaxt')

@section('chips')
  <div class="ph-chips">
    @foreach(\App\Filament\Pages\Balance::PERIODS as $key => $label)
      <a class="ph-chip {{ $period === $key ? 'on' : '' }}"
         href="{{ route('phone.money.index', ['period' => $key]) }}">{{ $label }}</a>
    @endforeach
  </div>
@endsection

@section('content')
  @php $money = fn ($v) => \App\Support\Price::format((float) $v); @endphp

  <div class="ph-tiles">
    {{-- Money actually in hand: what came in, less the chocolate bought per
         order, the stock bought in packs and everything else paid out. --}}
    <div class="tile wide">
      <div class="k">Kassa</div>
      <div class="v {{ $r['cash'] < 0 ? 'bad' : '' }}">{{ $money($r['cash']) }}</div>
      <div class="n">gəlir − şokolad − anbara alış {{ $money($r['purchases']) }} − xərclər</div>
      @if($unpaid > 0)
        <div class="n" style="margin-top:.3rem;"><b>Bunun {{ $money($unpaid) }} manatı hələ ödənilməyib.</b></div>
      @endif
    </div>

    <div class="tile accent">
      <div class="k">Xalis mənfəət</div>
      <div class="v">{{ $money($r['net']) }}</div>
      <div class="n">gəlir − maya dəyəri − xərclər</div>
    </div>

    <div class="tile">
      <div class="k">Gəlir</div>
      <div class="v">{{ $money($r['revenue']) }}</div>
      <div class="n">çatdırılma {{ $money($r['delivery']) }}</div>
    </div>

    <div class="tile">
      <div class="k">Maya dəyəri</div>
      <div class="v">{{ $money($r['chocolate'] + $r['materials']) }}</div>
      <div class="n">şokolad {{ $money($r['chocolate']) }} + material {{ $money($r['materials']) }}</div>
    </div>

    <button class="tile" style="text-align:left; font:inherit; cursor:pointer;" data-sheet="expense-sheet">
      <div class="k">Digər xərclər</div>
      <div class="v">{{ $money($r['expenses']) }}</div>
      <div class="n">əlavə etmək üçün toxunun</div>
    </button>

    <a class="tile" href="{{ route('phone.orders.index') }}">
      <div class="k">Gözləyən sifariş</div>
      <div class="v">{{ $waiting }}</div>
      <div class="n">iş növbəsinə bax</div>
    </a>

    <div class="tile">
      <div class="k">Dövr</div>
      <div class="v" style="font-size:1.1rem;">{{ $r['orders'] }} sifariş</div>
      <div class="n">{{ $r['boxes'] }} qutu</div>
    </div>
  </div>

  @if($low->isNotEmpty())
    <div class="ph-warn">
      <b>Anbarda azalıb</b>
      @foreach($low as $m)
        <div>{{ $m->name }} — {{ $m->stock <= 0 ? 'bitib' : \App\Http\Controllers\Phone\StockController::qty($m->stock) . ' ' . $m->unit . ', almaq lazımdır' }}</div>
      @endforeach
      <a class="ph-link" href="{{ route('phone.stock.index') }}">Anbara keç →</a>
    </div>
  @endif

  {{-- The door of the shop. Never a bare switch on a thumb-sized screen. --}}
  <div class="ph-block">
    <h2>Mağaza</h2>
    <div style="display:flex; align-items:center; gap:.6rem;">
      <span class="pill {{ $shopClosed ? 'pill-bad' : 'pill-ok' }}">{{ $shopClosed ? 'Bağlıdır' : 'Açıqdır' }}</span>
      <span class="ph-note" style="margin:0; flex:1;">{{ $shopClosed ? 'Müştərilər sifariş verə bilmir.' : 'Sayt sifariş qəbul edir.' }}</span>
    </div>
    <form method="POST" action="{{ route('phone.money.shop') }}" data-once style="margin-top:.6rem;"
          onsubmit="return confirm('{{ $shopClosed ? 'Mağaza açılsın?' : 'Mağaza bağlansın? Müştərilər sifariş verə bilməyəcək.' }}')">
      @csrf
      <input type="hidden" name="close" value="{{ $shopClosed ? '0' : '1' }}">
      <button class="ph-btn {{ $shopClosed ? 'ph-btn-primary' : 'ph-btn-danger' }}">{{ $shopClosed ? 'Mağazanı aç' : 'Mağazanı bağla' }}</button>
    </form>
  </div>

  <details class="ph-block">
    <summary style="font-size:.85rem; color:var(--cocoa-soft); cursor:pointer;">Bu rəqəmlər necə sayılır?</summary>
    <div class="ph-note" style="margin-top:.5rem;">
      <b>Gəlir</b> — ləğv edilməmiş sifarişlərin qutu, şokolad və çatdırılma məbləği.
      <b>Maya dəyəri</b> — həmin sifarişlərə gedən şokolad və anbar materialı.
      <b>Kassa</b> isə real puldur: anbara paçka ilə edilən alış bütövlükdə çıxılır, sifariş üzrə deyil.
      Ona görə «Kassa» ilə «Xalis mənfəət» bir-birinə bərabər olmur.
      Təcili hazırlama haqqı sifarişin məbləğinə daxildir, gəlirə isə ayrıca yazılmır.
    </div>
  </details>

  {{-- An expense, written where it happens: standing at the till, not at a desk. --}}
  <dialog class="sheet" id="expense-sheet">
    <div class="sheet-in">
      <div class="sheet-grip"></div>
      <h2>Xərc əlavə et</h2>
      <form method="POST" action="{{ route('phone.money.expense') }}" data-once>
        @csrf
        <label class="ph-field">
          <span>Tarix</span>
          <input type="date" name="spent_on" value="{{ old('spent_on', now()->toDateString()) }}" required>
        </label>
        <label class="ph-field">
          <span>Məbləğ, ₼</span>
          <input type="number" name="amount" inputmode="decimal" step="0.01" min="0.01"
                 value="{{ old('amount') }}" placeholder="0.00" required>
        </label>
        <label class="ph-field">
          <span>Növ</span>
          <input type="text" name="category" list="expense-kinds" value="{{ old('category') }}"
                 placeholder="Reklam, Kuryer…" required>
          <datalist id="expense-kinds">
            @foreach($categories as $kind)<option value="{{ $kind }}"></option>@endforeach
          </datalist>
        </label>
        <label class="ph-field">
          <span>Qeyd</span>
          <textarea name="note" placeholder="İstəyə bağlı">{{ old('note') }}</textarea>
        </label>
        <button class="ph-btn ph-btn-primary" data-busy="Yazılır…">Xərc əlavə et</button>
        <button type="button" class="ph-btn" style="margin-top:.5rem;" data-close-sheet>İmtina</button>
      </form>
    </div>
  </dialog>
@endsection
