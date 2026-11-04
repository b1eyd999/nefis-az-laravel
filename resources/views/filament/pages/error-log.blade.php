@php
  $entries = $this->entries();
  $file = $this->file();
  $size = \App\Support\DataMap::size($this->fileSize());
@endphp
<x-filament-panels::page>
  <style>
    .el-note{ font-size:.82rem; opacity:.72; line-height:1.65; max-width:62rem; }
    .el-row{ border:1px solid rgba(128,128,128,.25); border-radius:.9rem; padding:.8rem 1rem;
             background:rgba(128,128,128,.04); margin-bottom:.6rem; }
    .el-row.bad{ border-color:rgba(220,38,38,.45); background:rgba(220,38,38,.06); }
    .el-top{ display:flex; gap:.6rem; align-items:baseline; flex-wrap:wrap; }
    .el-when{ font-size:.78rem; opacity:.65; font-variant-numeric:tabular-nums; white-space:nowrap; }
    .el-level{ display:inline-block; padding:.1rem .45rem; border-radius:999px; font-size:.7rem;
               font-weight:700; letter-spacing:.03em; background:rgba(220,38,38,.14); color:#dc2626; }
    .el-head{ font-size:.9rem; font-weight:600; line-height:1.5; word-break:break-word; }
    .el-where{ font-family:ui-monospace, SFMono-Regular, Menlo, monospace; font-size:.76rem;
               opacity:.7; margin-top:.25rem; word-break:break-all; }
    .el-more{ margin-top:.5rem; font-size:.78rem; }
    .el-more summary{ cursor:pointer; opacity:.7; }
    .el-trace{ margin-top:.5rem; max-height:22rem; overflow:auto; white-space:pre-wrap;
               word-break:break-word; font-family:ui-monospace, SFMono-Regular, Menlo, monospace;
               font-size:.72rem; line-height:1.55; padding:.7rem .8rem; border-radius:.6rem;
               background:rgba(128,128,128,.12); }
    .el-empty{ border:1px solid rgba(22,163,74,.4); background:rgba(22,163,74,.07);
               border-radius:.9rem; padding:1rem 1.1rem; font-size:.9rem; line-height:1.6; }
    @media (prefers-color-scheme: dark){ .el-level{ color:#f87171; } }
  </style>

  <div class="el-note">
    Saytda nə vaxtsa «500 Server Error» görsəniz, səbəbi burada yazılır — ən yenisi yuxarıda.
    Mənə göndərmək üçün lazım olan şey birinci sətir və onun altındakı «Ətraflı»dır.
    Müştərilər bu səhifəni görmür.
    @if($file)
      <br>Fayl: <code>{{ str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file) }}</code> — {{ $size }}.
    @endif
  </div>

  @if($entries === [])
    <div class="el-empty">
      <b>Xəta yoxdur.</b>
      Jurnalın son hissəsində saytın xəta kimi yazdığı heç nə yoxdur.
      Səhv yenicə baş veribsə, səhifəni yeniləyin.
    </div>
  @else
    @foreach($entries as $e)
      <div class="el-row bad">
        <div class="el-top">
          <span class="el-level">{{ $e['level'] }}</span>
          <span class="el-when">{{ $e['at']?->format('d.m.Y H:i:s') ?? '—' }}</span>
        </div>
        <div class="el-head">{{ $e['headline'] }}</div>
        @if($e['where'])
          <div class="el-where">{{ $e['where'] }}</div>
        @endif
        <details class="el-more">
          <summary>Ətraflı</summary>
          <div class="el-trace">{{ $e['text'] }}</div>
        </details>
      </div>
    @endforeach
  @endif
</x-filament-panels::page>
