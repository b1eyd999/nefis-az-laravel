@php
  $r = $this->report;
  $fmt = fn ($v) => \App\Support\Price::format($v);
  [$from, $to] = $this->range();
@endphp
<x-filament-panels::page>
  <style>
    .bl-periods{ display:flex; flex-wrap:wrap; gap:.5rem; }
    .bl-periods button{ padding:.45rem .9rem; border-radius:999px; border:1px solid rgba(128,128,128,.3); font-size:.875rem; font-weight:500; }
    .bl-periods button.on{ background:#d97706; border-color:#d97706; color:#fff; }
    .bl-grid{ display:grid; gap:1rem; grid-template-columns:repeat(auto-fit, minmax(13rem, 1fr)); }
    .bl-card{ border:1px solid rgba(128,128,128,.25); border-radius:.9rem; padding:1rem 1.1rem; background:rgba(128,128,128,.04); }
    .bl-card .k{ font-size:.8rem; opacity:.7; margin-bottom:.35rem; }
    .bl-card .v{ font-size:1.5rem; font-weight:700; font-variant-numeric:tabular-nums; }
    .bl-card .s{ font-size:.78rem; opacity:.65; margin-top:.35rem; line-height:1.45; }
    .bl-card.net{ border-color:#d97706; background:rgba(217,119,6,.08); }
    .bl-card.net .v{ color:#d97706; }
    .bl-neg{ color:#dc2626 !important; }
    .bl-h{ font-size:1rem; font-weight:600; margin:.25rem 0 .75rem; }
    .bl-table{ width:100%; border-collapse:collapse; font-size:.875rem; }
    .bl-table th{ text-align:left; font-weight:600; opacity:.7; padding:.5rem .6rem; border-bottom:1px solid rgba(128,128,128,.25); white-space:nowrap; }
    .bl-table td{ padding:.55rem .6rem; border-bottom:1px solid rgba(128,128,128,.12); font-variant-numeric:tabular-nums; }
    .bl-table td.n, .bl-table th.n{ text-align:right; }
    .bl-note{ font-size:.8rem; opacity:.7; line-height:1.6; }
  </style>

  <div class="bl-periods">
    @foreach(\App\Filament\Pages\Balance::PERIODS as $key => $label)
      <button type="button" wire:click="$set('period', '{{ $key }}')" class="{{ $period === $key ? 'on' : '' }}">{{ $label }}</button>
    @endforeach
    <span class="bl-note" style="align-self:center; margin-left:.5rem;">
      {{ $from ? $from->format('d.m.Y') . ' — ' . $to->format('d.m.Y') : 'bütün vaxt' }} · {{ $r['orders'] }} sifariş, {{ $r['boxes'] }} qutu
    </span>
  </div>

  <div class="bl-grid">
    <div class="bl-card">
      <div class="k">Gəlir (satış)</div>
      <div class="v">{{ $fmt($r['revenue']) }}</div>
      <div class="s">qutular və şokolad + çatdırılma {{ $fmt($r['delivery']) }}</div>
    </div>
    <div class="bl-card">
      <div class="k">Maya dəyəri</div>
      <div class="v">{{ $fmt($r['chocolate'] + $r['materials']) }}</div>
      <div class="s">şokolad {{ $fmt($r['chocolate']) }} + material {{ $fmt($r['materials']) }}</div>
    </div>
    <div class="bl-card">
      <div class="k">Digər xərclər</div>
      <div class="v">{{ $fmt($r['expenses']) }}</div>
      <div class="s">"Xərclər" bölməsindən</div>
    </div>
    <div class="bl-card net">
      <div class="k">Xalis mənfəət</div>
      <div class="v {{ $r['net'] < 0 ? 'bl-neg' : '' }}">{{ $fmt($r['net']) }}</div>
      <div class="s">gəlir − maya dəyəri − xərclər</div>
    </div>
    <div class="bl-card">
      <div class="k">Kassa</div>
      <div class="v {{ $r['cash'] < 0 ? 'bl-neg' : '' }}">{{ $fmt($r['cash']) }}</div>
      <div class="s">gəlir − şokolad − anbara alış {{ $fmt($r['purchases']) }} − xərclər</div>
    </div>
  </div>

  <div>
    <div class="bl-h">Mənfəətin bölgüsü</div>
    <div class="bl-grid">
      @forelse($r['shares'] as $share)
        <div class="bl-card">
          <div class="k">{{ $share['name'] }} · {{ rtrim(rtrim(number_format($share['percent'], 2, '.', ''), '0'), '.') }}%</div>
          <div class="v {{ $share['amount'] < 0 ? 'bl-neg' : '' }}">{{ $fmt($share['amount']) }}</div>
        </div>
      @empty
        <p class="bl-note">Pay bölgüsü "Tənzimləmələr"də qurulur.</p>
      @endforelse
    </div>
  </div>

  <div>
    <div class="bl-h">Sifarişlər üzrə</div>
    @if($r['rows'])
      <div style="overflow-x:auto;">
        <table class="bl-table">
          <thead>
            <tr>
              <th>№</th><th>Tarix</th><th>Müştəri</th><th class="n">Qutu</th>
              <th class="n">Gəlir</th><th class="n">Şokolad</th><th class="n">Material</th><th class="n">Mənfəət</th>
            </tr>
          </thead>
          <tbody>
            @foreach($r['rows'] as $row)
              <tr>
                <td><a href="{{ \App\Filament\Resources\OrderResource::getUrl('edit', ['record' => $row['order']]) }}" style="text-decoration:underline;">#{{ $row['order']->id }}</a></td>
                <td>{{ $row['order']->created_at->format('d.m.Y') }}</td>
                <td>{{ $row['order']->user?->name ?? '—' }}</td>
                <td class="n">{{ $row['boxes'] }}</td>
                <td class="n">{{ $fmt($row['revenue']) }}</td>
                <td class="n">{{ $fmt($row['chocolate']) }}</td>
                <td class="n">{{ $fmt($row['materials']) }}</td>
                <td class="n {{ $row['profit'] < 0 ? 'bl-neg' : '' }}" style="font-weight:600;">{{ $fmt($row['profit']) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @else
      <p class="bl-note">Bu dövrdə sifariş yoxdur.</p>
    @endif
  </div>

  <p class="bl-note">
    Ləğv edilmiş sifarişlər sayılmır və onların materialı anbara qayıdır. Şokoladın maya dəyəri — sifariş anında marketdəki qiyməti (endirim varsa endirimli),
    materialın — o anda bir vahidin qiyməti. Anbara alış mənfəətdən çıxılmır: material qutulara sərf olunduqca xərcə çevrilir.
  </p>
</x-filament-panels::page>
