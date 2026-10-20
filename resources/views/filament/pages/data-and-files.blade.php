@php
  $browser = $this->inTheBrowser();
  $notice = $this->noticeState();
  $locked = $this->locked();
  $readable = $this->readable();
  $opens = $this->keyOpens();
  $files = $this->files();
  $strays = $this->strays();
  $happening = $this->happening();

  $size = fn (int $b) => \App\Support\DataMap::size($b);
  $totalFiles = array_sum(array_column($files, 'files'));
  $totalBytes = array_sum(array_column($files, 'bytes'));
  $plain = array_sum(array_column($locked, 'plain'));
  $lockedRows = array_sum(array_column($locked, 'locked'));
@endphp
<x-filament-panels::page>
  <style>
    .dm-h{ font-size:1.05rem; font-weight:600; margin:1.5rem 0 .25rem; }
    .dm-h:first-of-type{ margin-top:0; }
    .dm-note{ font-size:.82rem; opacity:.72; line-height:1.65; margin-bottom:.85rem; max-width:62rem; }
    .dm-grid{ display:grid; gap:.75rem; grid-template-columns:repeat(auto-fit, minmax(10rem, 1fr)); }
    .dm-card{ border:1px solid rgba(128,128,128,.25); border-radius:.9rem; padding:.85rem 1rem; background:rgba(128,128,128,.04); }
    .dm-card .k{ font-size:.78rem; opacity:.7; margin-bottom:.3rem; }
    .dm-card .v{ font-size:1.4rem; font-weight:700; font-variant-numeric:tabular-nums; }
    .dm-card .s{ font-size:.75rem; opacity:.62; margin-top:.3rem; line-height:1.45; }
    .dm-wrap{ overflow-x:auto; }
    .dm-table{ width:100%; border-collapse:collapse; font-size:.85rem; }
    .dm-table th{ text-align:left; font-weight:600; opacity:.7; padding:.5rem .6rem; border-bottom:1px solid rgba(128,128,128,.25); white-space:nowrap; }
    .dm-table td{ padding:.55rem .6rem; border-bottom:1px solid rgba(128,128,128,.12); line-height:1.55; vertical-align:top; }
    .dm-table td.n, .dm-table th.n{ text-align:right; font-variant-numeric:tabular-nums; white-space:nowrap; }
    .dm-mono{ font-family:ui-monospace, SFMono-Regular, Menlo, monospace; font-size:.8rem; }
    .dm-tag{ display:inline-block; padding:.12rem .5rem; border-radius:999px; font-size:.72rem; font-weight:600; white-space:nowrap; }
    .dm-ok{ background:rgba(22,163,74,.14); color:#15803d; }
    .dm-warn{ background:rgba(217,119,6,.16); color:#b45309; }
    .dm-bad{ background:rgba(220,38,38,.14); color:#dc2626; }
    .dm-box{ border:1px solid rgba(128,128,128,.25); border-radius:.9rem; padding:.9rem 1.1rem; font-size:.85rem; line-height:1.65; }
    .dm-box.warn{ border-color:#d97706; background:rgba(217,119,6,.07); }
    .dm-box.bad{ border-color:#dc2626; background:rgba(220,38,38,.07); }
    @media (prefers-color-scheme: dark){
      .dm-ok{ color:#4ade80; } .dm-warn{ color:#fbbf24; } .dm-bad{ color:#f87171; }
    }
  </style>

  {{-- ——— what is going on right now ——— --}}
  <div class="dm-h">Saytda nə baş verir</div>
  <div class="dm-grid">
    @foreach($happening as $row)
      <div class="dm-card">
        <div class="k">{{ $row['label'] }}</div>
        <div class="v">{{ $row['value'] }}</div>
        <div class="s">{{ $row['hint'] }}</div>
      </div>
    @endforeach
  </div>

  {{-- ——— the browser ——— --}}
  <div class="dm-h">Ziyarətçinin brauzerində nə saxlanılır</div>
  <div class="dm-note">
    Bunlar saytın müştərinin telefonunda və ya kompüterində saxladığı şeylərdir. «Lazımlı» yazılanlar
    olmasa sayt işləmir: səbət yoxa çıxır, forma göndərilmir. Yalnız sonuncu — Google Analytics —
    ziyarətçinin razılığından sonra qoşulur.
  </div>

  @if($notice['shown'])
    <div class="dm-box" style="margin-bottom:.85rem;">
      <strong>Kuki lenti göstərilir.</strong> Ziyarətçi «Hamısını qəbul et» deməyincə Google-dan heç nə
      yüklənmir.
      <div style="opacity:.72; margin-top:.3rem;">
        Verilən cavab ziyarətçinin öz brauzerində qalır və serverə göndərilmir — ona görə burada
        «neçə nəfər razılaşdı» rəqəmi yoxdur. Bu, qəsdən belədir: razılığı soruşmaq üçün adamın
        izini saxlamaq mənasızdır.
      </div>
    </div>
  @else
    <div class="dm-box warn" style="margin-bottom:.85rem;">
      <strong>Kuki lenti göstərilmir.</strong> {{ $notice['why'] }}
    </div>
  @endif

  <div class="dm-wrap">
    <table class="dm-table">
      <thead>
        <tr>
          <th>Ad</th>
          <th>Harada</th>
          <th>Nə üçün</th>
          <th>Nə qədər qalır</th>
          <th>Razılıq</th>
        </tr>
      </thead>
      <tbody>
        @foreach($browser as $row)
          <tr>
            <td class="dm-mono">{{ $row['name'] }}</td>
            <td style="white-space:nowrap;">{{ $row['where'] }}</td>
            <td style="min-width:18rem;">{{ $row['what'] }}</td>
            <td style="white-space:nowrap;">{{ $row['life'] }}</td>
            <td>
              @if($row['needed'])
                <span class="dm-tag dm-ok">lazımlı</span>
              @else
                <span class="dm-tag dm-warn">razılıqla</span>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  {{-- ——— which database ——— --}}
  @php($eng = $this->engine())
  <div class="dm-h">Saytın bazası</div>
  <div class="dm-note">
    Sayt <span class="dm-mono">{{ $eng['driver'] }}</span> bazası ilə işləyir
    (<span class="dm-mono">{{ $eng['name'] }}</span>), gecə kopyası isə
    <span class="dm-mono">{{ $eng['backup'] }}</span> şəklində yazılır — «Baza kopyaları»
    səhifəsindəki fayl adları buna uyğun olmalıdır.
  </div>

  {{-- ——— encryption ——— --}}
  <div class="dm-h">Bazada şifrələnən məlumat</div>
  <div class="dm-note">
    Bu sütunlar bazanın özündə oxunmur. Kim bazanın kopyasını əlinə keçirsə — hoster, oğurlanmış
    yedək, səhvən paylaşılmış fayl — orada ünvan da, telefon da anlaşılmaz hərflər yığınıdır. Admin
    panelində isə hər şey adi halda görünür: açar saytın özündədir.
  </div>

  @if($opens === false)
    <div class="dm-box bad" style="margin-bottom:.85rem;">
      <strong>Diqqət: açar uyğun gəlmir.</strong> Bazada şifrəli məlumat var, amma saytın açarı
      (<span class="dm-mono">APP_KEY</span>) onu açmır. Bu, bazanın başqa açarla işləyən saytdan
      gətirildiyi deməkdir. Heç nə yazmayın — əvvəlcə köhnə açarı qaytarın.
    </div>
  @elseif($opens === true)
    <div class="dm-box" style="margin-bottom:.85rem;">
      <strong>Açar yerindədir</strong> — yoxlanıldı: şifrəli sətir açılır.
      <div style="opacity:.72; margin-top:.3rem;">
        Vacibdir: baza kopyalarının içi artıq şifrəlidir. Kopyadan bərpa edəndə sayt <em>eyni</em>
        <span class="dm-mono">APP_KEY</span> ilə işləməlidir, yoxsa bütün ünvanlar və telefonlar
        birdəfəlik itir. Açar <span class="dm-mono">.env</span> faylındadır və heç vaxt git-ə düşmür.
      </div>
    </div>
  @endif

  @if($plain > 0)
    <div class="dm-box warn" style="margin-bottom:.85rem;">
      <strong>{{ $plain }} sətir hələ açıq qalıb.</strong> Yəni şifrələnmədən əvvəl yazılıb.
      Serverdə <span class="dm-mono">php artisan migrate --force</span> işləsə, onlar da bağlanacaq.
    </div>
  @endif

  <div class="dm-wrap">
    <table class="dm-table">
      <thead>
        <tr>
          <th>Cədvəl</th>
          <th>Sütun</th>
          <th class="n">Doldurulmuş</th>
          <th class="n">Şifrəli</th>
          <th class="n">Açıq</th>
        </tr>
      </thead>
      <tbody>
        @foreach($locked as $row)
          <tr>
            <td class="dm-mono">{{ $row['table'] }}</td>
            <td class="dm-mono">{{ $row['column'] }}</td>
            <td class="n">{{ $row['filled'] }}</td>
            <td class="n">{{ $row['locked'] }}</td>
            <td class="n">
              @if($row['plain'] > 0)
                <span class="dm-tag dm-warn">{{ $row['plain'] }}</span>
              @else
                0
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  <div class="dm-note" style="margin-top:.5rem;">
    Cəmi {{ $lockedRows }} şifrəli sətir. Bu siyahı modellərin özündən oxunur — yəni burada görünən
    sütun həqiqətən şifrələnir.
  </div>

  <div class="dm-h">Qəsdən açıq saxlanılanlar</div>
  <div class="dm-note">
    Hər şeyi şifrələmək olmur: baza oxumadığı sətri axtara da bilmir.
  </div>
  <div class="dm-wrap">
    <table class="dm-table">
      <thead><tr><th>Nə</th><th>Niyə</th></tr></thead>
      <tbody>
        @foreach($readable as $row)
          <tr>
            <td style="white-space:nowrap; font-weight:600;">{{ $row['what'] }}</td>
            <td>{{ $row['why'] }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  {{-- ——— files ——— --}}
  <div class="dm-h">Diskdəki fayllar</div>
  <div class="dm-note">
    Sayıldı: {{ number_format($totalFiles) }} fayl, {{ $size($totalBytes) }}. Rəqəmlər 10 dəqiqə
    saxlanılır — yuxarıdakı «Faylları yenidən say» düyməsi onları təzələyir.
  </div>

  <div class="dm-wrap">
    <table class="dm-table">
      <thead>
        <tr>
          <th>Qovluq</th>
          <th>Nə üçün</th>
          <th class="n">Fayl</th>
          <th class="n">Ölçü</th>
          <th class="n">Sonuncu</th>
        </tr>
      </thead>
      <tbody>
        @foreach($files as $row)
          <tr>
            <td class="dm-mono">{{ $row['folder'] }}</td>
            <td>{{ \App\Support\DataMap::folderMeaning($row['folder']) ?: '—' }}</td>
            <td class="n">{{ number_format($row['files']) }}</td>
            <td class="n">{{ $size($row['bytes']) }}</td>
            <td class="n">
              {{ $row['newest'] ? \Illuminate\Support\Carbon::createFromTimestamp($row['newest'])->timezone(config('app.timezone'))->format('d.m.Y') : '—' }}
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  @if($strays['files'] > 0)
    <div class="dm-box warn" style="margin-top:.85rem;">
      <strong>{{ number_format($strays['files']) }} sahibsiz şəkil — {{ $size($strays['bytes']) }}.</strong>
      Müştəri dizaynı açıb şəklini yükləyib, sonra fikrindən daşınıb. Heç bir sifariş, səbət və ya
      link onlara baxmır və {{ $strays['days'] }} gündən çoxdur ki, toxunulmur.
      <div style="opacity:.78; margin-top:.4rem;">
        Yuxarıdakı qırmızı «Sahibsiz şəkilləri sil» düyməsi onları silir. Silinmə geri alınmır, ona
        görə düymə əvvəlcə sayı göstərib soruşur.
      </div>
    </div>
  @else
    <div class="dm-box" style="margin-top:.85rem;">
      <strong>Sahibsiz şəkil yoxdur.</strong> Müştərilərin yüklədiyi bütün şəkillərə ya bir sifariş,
      ya səbət baxır — ya da hələ {{ $strays['days'] }} gün keçməyib.
    </div>
  @endif

  <div class="dm-note" style="margin-top:1.25rem;">
    Baza kopyaları bu siyahıda yoxdur: onlar <span class="dm-mono">storage/app/private</span>
    qovluğunda, internetdən çatılmayan yerdə saxlanılır — «Baza kopyaları» səhifəsinə baxın.
  </div>
</x-filament-panels::page>
