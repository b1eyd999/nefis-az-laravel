<x-filament-panels::page>
  @php $s = $this->state(); @endphp

  <div style="font-size:.875rem; opacity:.8; line-height:1.55;">
    Samira kompüterinizdəki köməkçidir. Bu açarla o, saytı <b>yalnız oxuyur</b>: ödənilmiş yeni sifarişləri
    (nömrə, vaxt, məbləğ, nə sifariş olunub, çatdırılma günü, müştərinin yalnız adı), bu günün rəqəmlərini və
    gözləyən sifarişləri. Telefon nömrəsi, ünvan, e-poçt və kart məlumatı ona heç vaxt verilmir;
    statusu, qiyməti və ya başqa bir şeyi dəyişə bilməz.
  </div>

  @if($freshKey)
    <div x-data="{ done: false }"
         style="border:2px solid rgb(245 158 11); border-radius:.75rem; padding:1rem 1.1rem; display:flex; flex-direction:column; gap:.75rem;">
      <div style="font-weight:600;">Yeni açar — yalnız indi göstərilir</div>
      <div style="display:flex; gap:.5rem; flex-wrap:wrap; align-items:center;">
        <input type="text" readonly value="{{ $freshKey }}" x-ref="key" x-on:focus="$el.select()"
               aria-label="Samira üçün açar"
               style="flex:1 1 22rem; min-width:0; font-family:ui-monospace,monospace; font-size:.8125rem; padding:.55rem .7rem; border-radius:.5rem; border:1px solid rgba(128,128,128,.45); background:transparent;">
        <x-filament::button type="button" icon="heroicon-o-clipboard-document"
            x-on:click="navigator.clipboard.writeText($refs.key.value).then(() => { done = true; setTimeout(() => done = false, 2500) }).catch(() => { $refs.key.focus(); $refs.key.select() })">
          <span x-show="! done">Kopyala</span>
          <span x-show="done" x-cloak>Kopyalandı</span>
        </x-filament::button>
      </div>
      <div lang="ru" style="font-size:.9375rem;">Вставьте его в окно „Подключить сайты к Самире“ на компьютере.</div>
      <div style="font-size:.8125rem; opacity:.75;">
        Sayt açarın özünü saxlamır, yalnız onun izini — bu səhifədən çıxandan sonra onu heç kim, siz də daxil olmaqla,
        bir daha görə bilməz. İtirsəniz, «Yeni açar yarat» basın: köhnəsi dərhal işləməyəcək.
      </div>
    </div>
  @endif

  <div style="overflow-x:auto;">
    <table style="width:100%; border-collapse:collapse; font-size:.875rem;">
      <tbody>
        <tr style="border-bottom:1px solid rgba(128,128,128,.15);">
          <td style="padding:.6rem .5rem; width:14rem; opacity:.75;">Vəziyyət</td>
          <td style="padding:.6rem .5rem;">
            @if($s['on'])
              <span style="color:rgb(22 163 74); font-weight:600;">Qoşulub</span> — açar var
            @else
              <span style="color:rgb(220 38 38); font-weight:600;">Söndürülüb</span> — açar yoxdur, Samira saytı oxuya bilmir.
              Qoşmaq üçün yuxarıda «Açar yarat» basın.
            @endif
          </td>
        </tr>
        @if($s['on'])
          <tr style="border-bottom:1px solid rgba(128,128,128,.15);">
            <td style="padding:.6rem .5rem; opacity:.75;">Açar yaradılıb</td>
            <td style="padding:.6rem .5rem;">{{ $s['made']?->format('d.m.Y H:i') ?? '—' }}</td>
          </tr>
          <tr style="border-bottom:1px solid rgba(128,128,128,.15);">
            <td style="padding:.6rem .5rem; opacity:.75;">Samira son dəfə oxuyub</td>
            <td style="padding:.6rem .5rem;">
              @if($s['seen'])
                {{ $s['seen']->format('d.m.Y H:i') }}
                <span style="opacity:.7;">({{ $s['seen']->diffForHumans() }}{{ $s['from'] ? ', IP ' . $s['from'] : '' }})</span>
              @else
                Hələ oxumayıb — açarı kompüterdəki pəncərəyə yapışdırandan sonra burada vaxt görünəcək.
              @endif
            </td>
          </tr>
        @endif
        <tr>
          <td style="padding:.6rem .5rem; opacity:.75;">Ünvan</td>
          <td style="padding:.6rem .5rem; font-family:ui-monospace,monospace; font-size:.8125rem;">{{ $s['feed'] }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</x-filament-panels::page>
