{{-- What the customer sent for one order line, laid out like the form they
     filled in: each field's name above what they gave. Inline styles, because
     the panel's stylesheet only carries the classes Filament itself uses. --}}
@php $fields = $getRecord()->fields(); $line = $getRecord(); @endphp
<div class="oi-root" style="display:flex; flex-direction:column; gap:.85rem; padding:.5rem 0; min-width:16rem; max-width:26rem;">
  {{-- On a phone the other columns are hidden: the line's name, count, price, bar and paper here instead. --}}
  <style>.oi-phone{ display:none; } @media (max-width: 767px){ .oi-phone{ display:block; } .oi-root{ min-width:0 !important; max-width:calc(100vw - 4.5rem) !important; white-space:normal; } }
    .oi-btn{ display:inline-flex; align-items:center; gap:.3rem; font-size:.75rem; font-weight:600; line-height:1;
      padding:.3rem .55rem; border-radius:.4rem; border:1px solid rgba(128,128,128,.45); background:transparent;
      color:inherit; cursor:pointer; white-space:nowrap; }
    .oi-btn:hover{ border-color:#d97706; color:#d97706; }
    .oi-btn.ok{ border-color:#16a34a; color:#16a34a; }</style>
  {{-- Copying is Alpine's, not a <script> of our own: Livewire redraws this
       table, and a script tag that arrives in a redraw is never run — which is
       exactly how the first attempt at these buttons came to do nothing at
       all. Alpine is already on the page and binds itself to whatever appears.
       Downloading needs no script: it is a route that sends the file as an
       attachment, so the browser has nothing to decide. --}}
  <div class="oi-phone" style="border-bottom:1px solid rgba(128,128,128,.3); padding-bottom:.6rem;">
    <div style="font-weight:700; font-size:.95rem; white-space:normal;">{{ $line->title() }}</div>
    <div style="font-size:.85rem; opacity:.85; margin-top:.2rem;">
      {{ $line->quantity }} ədəd
      @if($line->unitPrice() > 0) · <b>{{ \App\Support\Price::format($line->unitPrice() * $line->quantity) }}</b> @endif
    </div>
  </div>

  {{-- What is actually put in the box, shown rather than named: the bar and
       the paper are picked off a shelf, and a name alone is slower to match
       than a picture. The names stay frozen on the line; these pictures are
       whatever the shop sells under them today, so a bar that has since been
       taken off the list simply shows without one. --}}
  @if($line->chocolate_name || $line->wrapping_name)
    @php
      $bar = $line->chocolate;
      $wrap = $line->wrapping;
      $paper = $wrap?->pattern ? \App\Support\Media::url($wrap->pattern) : null;
    @endphp
    <div style="display:flex; flex-wrap:wrap; gap:1rem;">
      @if($line->chocolate_name)
        <div style="min-width:0;">
          <div style="font-size:.75rem; font-weight:600; opacity:.75; margin-bottom:.3rem;">Şokolad</div>
          <div style="display:flex; align-items:center; gap:.55rem;">
            @if($bar?->imageUrl())
              <img src="{{ $bar->imageUrl() }}" alt="{{ $line->chocolate_name }}"
                   style="width:3.4rem; height:3.4rem; object-fit:contain; border-radius:.5rem;
                          border:1px solid rgba(128,128,128,.35); background:#fff; padding:.15rem; flex:none;">
            @endif
            <div style="font-size:.85rem; white-space:normal; min-width:0;">
              {{ $line->chocolate_name }}
              @if($line->chocolate_price)
                <div style="opacity:.75;">{{ \App\Support\Price::format($line->chocolate_price) }}</div>
              @endif
            </div>
          </div>
        </div>
      @endif

      @if($line->wrapping_name)
        <div style="min-width:0;">
          <div style="font-size:.75rem; font-weight:600; opacity:.75; margin-bottom:.3rem;">Qablaşdırma</div>
          <div style="display:flex; align-items:center; gap:.55rem;">
            <div style="width:3.4rem; height:3.4rem; border-radius:.5rem; flex:none; position:relative; overflow:hidden;
                        border:1px solid rgba(128,128,128,.35);
                        @if($paper) background-image:url('{{ $paper }}'); background-size:cover; background-position:center;
                        @else background:rgba(128,128,128,.15); @endif">
              @if($wrap && $wrap->ribbon !== \App\Models\Wrapping::NONE && $wrap->ribbon_color)
                {{-- The ribbon across the paper, the way the box is tied. --}}
                <span style="position:absolute; left:0; right:0; top:50%; height:.5rem; transform:translateY(-50%);
                             background:{{ $wrap->ribbon_color }};
                             @if($wrap->ribbon === \App\Models\Wrapping::TWINE) opacity:.85; @endif"></span>
              @endif
            </div>
            <div style="font-size:.85rem; white-space:normal; min-width:0;">
              {{ $line->wrapping_name }}
              @if($wrap)
                <div style="opacity:.75;">{{ \App\Models\Wrapping::RIBBONS[$wrap->ribbon] ?? '' }}</div>
              @endif
              @if($line->wrapping_price)
                <div style="opacity:.75;">{{ \App\Support\Price::format($line->wrapping_price) }}</div>
              @endif
            </div>
          </div>
        </div>
      @endif
    </div>
  @endif
  @if($fields['photos'])
    <div style="display:flex; flex-wrap:wrap; gap:.85rem;">
      @foreach($fields['photos'] as $photo)
        @php $url = \App\Support\Media::url($photo['path']); @endphp
        <div>
          <div style="font-size:.75rem; font-weight:600; opacity:.75; margin-bottom:.3rem;">{{ $photo['label'] }}</div>
          <a href="{{ $url }}" target="_blank" title="Tam ölçüdə aç">
            <img src="{{ $url }}" alt="{{ $photo['label'] }}"
                 style="width:7.5rem; height:7.5rem; object-fit:cover; border-radius:.6rem; border:1px solid rgba(128,128,128,.35); display:block;">
          </a>
          {{-- The click must not reach the table row: Filament opens the
               line's edit window on a row click and cancels the download with
               it. And the file opens in a tab of its own — an attachment never
               draws a page, so the tab closes itself and the file is saved. --}}
          <a class="oi-btn" style="margin-top:.35rem; text-decoration:none;"
             x-data @click.stop onclick="event.stopPropagation()"
             target="_blank" rel="noopener"
             href="{{ route('order.file', ['item' => $line, 'which' => $loop->iteration]) }}">⬇ Yüklə</a>
        </div>
        @if($f = $photo['frame'])
          {{-- The same photo as it sat in its window when the customer approved
               the preview: the same zoom, turn, mirror and shift the design
               page drew with (cover the window, then move about its centre). --}}
          @php
            $w = 120; $h = (int) round($w / max(0.2, $f['ratio']));
            $move = 'translate(' . round($f['panX'] * $w) . 'px,' . round($f['panY'] * $h) . 'px) rotate(' . $f['rotate'] . 'deg) scaleX(' . ($f['flip'] ? -1 : 1) . ') scale(' . $f['scale'] . ')';
            $said = array_filter([
                abs($f['scale'] - 1) >= 0.005 ? 'böyütmə ×' . $f['scale'] : null,
                abs($f['rotate']) >= 0.05 ? 'dönmə ' . $f['rotate'] . '°' : null,
                $f['flip'] ? 'güzgü' : null,
                (abs($f['panX']) >= 0.005 || abs($f['panY']) >= 0.005) ? 'sürüşdürmə ' . round($f['panX'] * 100) . '% / ' . round($f['panY'] * 100) . '%' : null,
            ]);
          @endphp
          <div>
            <div style="font-size:.75rem; font-weight:600; opacity:.75; margin-bottom:.3rem;">Müştərinin kadrı</div>
            <div style="width:{{ $w }}px; height:{{ $h }}px; overflow:hidden; border:1px solid rgba(128,128,128,.35); background:#ddd; {{ $f['shape'] === 'ellipse' ? 'border-radius:50%;' : 'border-radius:.4rem;' }}">
              <img src="{{ $url }}" alt="" style="width:100%; height:100%; object-fit:cover; display:block; transform-origin:50% 50%; transform:{{ $move }};">
            </div>
            <div style="font-size:.7rem; opacity:.75; margin-top:.25rem; max-width:{{ $w }}px; white-space:normal;">{{ implode(', ', $said) }}</div>
          </div>
        @endif
      @endforeach
    </div>
  @endif

  @php
    // What the workshop actually types out: the captions the customer wrote,
    // without the ones the design holds fixed.
    $written = collect($fields['texts'])->reject(fn ($t) => $t['fixed'] || $t['value'] === '');
  @endphp
  @if($written->count() > 1)
    <button type="button" class="oi-btn" style="align-self:flex-start;"
            x-data="{ done: false }" :class="done && 'ok'"
            @click.stop="navigator.clipboard.writeText($el.dataset.text).then(() => { done = true; setTimeout(() => done = false, 1500) })"
            data-text="{{ $written->map(fn ($t) => $t['label'] . ': ' . $t['value'])->implode(chr(10) . chr(10)) }}"
            x-text="done ? 'Kopyalandı ✓' : '⧉ Bütün mətnləri kopyala'">⧉ Bütün mətnləri kopyala</button>
  @endif
  @foreach($fields['texts'] as $text)
    <div>
      <div style="display:flex; align-items:center; gap:.5rem; margin-bottom:.3rem; flex-wrap:wrap;">
        <div style="font-size:.75rem; font-weight:600; opacity:.75;">
          {{ $text['label'] }}
          @if($text['fixed'])<span style="font-weight:400; opacity:.8;">· dizaynda sabit</span>@endif
        </div>
        @if($text['value'] !== '')
          <button type="button" class="oi-btn" x-data="{ done: false }" :class="done && 'ok'"
                  @click.stop="navigator.clipboard.writeText($el.dataset.text).then(() => { done = true; setTimeout(() => done = false, 1500) })"
                  data-text="{{ $text['value'] }}"
                  x-text="done ? 'Kopyalandı ✓' : '⧉ Kopyala'">⧉ Kopyala</button>
        @endif
      </div>
      <div style="border:1px solid rgba(128,128,128,.35); border-radius:.6rem; padding:.5rem .75rem; font-size:.9rem; white-space:pre-wrap; word-break:break-word;{{ $text['fixed'] ? ' opacity:.65;' : '' }}">{{ $text['value'] !== '' ? $text['value'] : '—' }}</div>
    </div>
  @endforeach

  @php $item = $getRecord(); @endphp
  @if(filled($line->star_map))
    {{-- The night the customer chose. Nothing is stored as a picture: these
         numbers redraw it at any size, today or in three years. --}}
    @php $sky = $line->star_map; @endphp
    <div style="border:1px dashed rgba(96,120,200,.7); border-radius:.75rem; padding:.6rem .75rem;">
      <div style="font-size:.8rem; font-weight:700; margin-bottom:.45rem;">✨ Ulduz xəritəsi</div>
      <div style="font-size:.85rem; white-space:normal;">
        {{ \Illuminate\Support\Carbon::parse($sky['date'])->format('d.m.Y') }}, saat {{ $sky['time'] }}
        @if(! empty($sky['place'])) · {{ $sky['place'] }} @endif
      </div>
      <div style="font-size:.78rem; opacity:.8; margin-top:.2rem;">
        {{ \App\Support\Sky::coordinates((float) $sky['lat'], (float) $sky['lon']) }}
      </div>
      <a href="{{ route('star.print', $line) }}" target="_blank" rel="noopener"
         style="display:inline-block; margin-top:.45rem; font-size:.85rem; font-weight:600; text-decoration:underline;">Çap üçün aç →</a>
    </div>
  @endif

  @if(filled($line->street_map))
    {{-- The place the customer chose. Nothing is stored as a picture here
         either: these numbers redraw the streets at any size. --}}
    @php $spot = $line->street_map; @endphp
    <div style="border:1px dashed rgba(200,140,60,.7); border-radius:.75rem; padding:.6rem .75rem;">
      <div style="font-size:.8rem; font-weight:700; margin-bottom:.45rem;">📍 Lokasiya xəritəsi</div>
      <div style="font-size:.85rem; white-space:normal;">
        {{ $spot['place'] ?: '—' }}
        @if(! empty($spot['date']))
          · {{ \Illuminate\Support\Carbon::parse($spot['date'])->format('d.m.Y') }}@if(! empty($spot['withTime']) && ! empty($spot['time'])), {{ $spot['time'] }}@endif
        @endif
      </div>
      <div style="font-size:.78rem; opacity:.8; margin-top:.2rem;">
        {{ \App\Support\Sky::coordinates((float) $spot['lat'], (float) $spot['lon']) }}
        · yaxınlıq {{ $spot['zoom'] ?? 15 }}
      </div>
      <a href="{{ route('place.print', $line) }}" target="_blank" rel="noopener"
         style="display:inline-block; margin-top:.45rem; font-size:.85rem; font-weight:600; text-decoration:underline;">Çap üçün aç →</a>
    </div>
  @endif

  @if($item->spotify_uri)
    {{-- The song the customer chose. The code is here ready to put on the box —
         svg for the press, png to glance at — and the link is here so the shop
         can hear what it plays before printing it on someone's present. --}}
    @php $song = $item->spotify_uri; @endphp
    <div style="border:1px dashed rgba(29,185,84,.7); border-radius:.75rem; padding:.6rem .75rem;">
      <div style="font-size:.8rem; font-weight:700; margin-bottom:.45rem;">
        🎧 Spotify kodu — {{ \App\Support\SpotifyCode::kindLabel($song) }}
      </div>
      <img src="{{ \App\Support\SpotifyCode::image($song, 'png', 'ffffff', 'black', 640) }}" alt="Spotify kodu"
           style="width:14rem; height:auto; background:#fff; padding:.3rem; border-radius:.35rem; border:1px solid rgba(128,128,128,.35); display:block;">
      <div style="display:flex; flex-wrap:wrap; gap:.75rem; margin-top:.45rem; font-size:.78rem;">
        <a href="{{ \App\Support\SpotifyCode::image($song, 'svg', 'ffffff', 'black', 1024) }}" target="_blank" rel="noopener" style="text-decoration:underline;">Çap üçün SVG</a>
        <a href="{{ \App\Support\SpotifyCode::image($song, 'png', 'ffffff', 'black', 1024) }}" target="_blank" rel="noopener" style="text-decoration:underline;">PNG</a>
        <a href="{{ \App\Support\SpotifyCode::link($song) }}" target="_blank" rel="noopener" style="text-decoration:underline;">Spotify-da dinlə ↗</a>
      </div>
    </div>
  @endif

  @if($item->hasLetter())
    {{-- The Polaroid letter to print: its photo in full size and its words. --}}
    <div style="border:1px dashed rgba(217,119,6,.6); border-radius:.75rem; padding:.6rem .75rem;">
      <div style="font-size:.8rem; font-weight:700; margin-bottom:.45rem;">
        💌 Polaroid məktub{{ $item->isLetterOnly() ? ' (ayrıca)' : ' — qutunun içinə' }}
      </div>
      <div style="display:flex; gap:.75rem; align-items:flex-start;">
        @if($url = $item->letterPhotoUrl())
          <div>
            <a href="{{ $url }}" target="_blank" title="Tam ölçüdə aç">
              <img src="{{ $url }}" alt="Məktubun şəkli" style="width:6rem; height:6rem; object-fit:cover; border:5px solid #fbfaf6; border-bottom-width:16px; box-shadow:0 2px 8px rgba(0,0,0,.35); display:block;">
            </a>
            <a class="oi-btn" style="margin-top:.35rem; text-decoration:none;"
               x-data @click.stop onclick="event.stopPropagation()"
               target="_blank" rel="noopener"
               href="{{ route('order.file', ['item' => $item, 'which' => 'mektub']) }}">⬇ Yüklə</a>
          </div>
        @endif
        <div style="flex:1; min-width:0;">
          @if($item->letter_text)
            <button type="button" class="oi-btn" style="margin-bottom:.4rem;" x-data="{ done: false }" :class="done && 'ok'"
                    @click.stop="navigator.clipboard.writeText($el.dataset.text).then(() => { done = true; setTimeout(() => done = false, 1500) })"
                    data-text="{{ $item->letter_text }}"
                    x-text="done ? 'Kopyalandı ✓' : '⧉ Məktubu kopyala'">⧉ Məktubu kopyala</button>
          @endif
          <div style="font-size:.9rem; white-space:pre-wrap; word-break:break-word;">{{ $item->letter_text ?: ($url ? 'Mətnsiz' : '—') }}</div>
        </div>
      </div>
    </div>
  @endif

  @if($item->ar_price !== null)
    {{-- The live photo the customer made: the picture to print, its QR code (on its page) and where the video is. --}}
    @php $live = $item->livePhotos()->latest('id')->first(); @endphp
    <div style="border:1px dashed rgba(124,58,237,.6); border-radius:.75rem; padding:.6rem .75rem;">
      <div style="font-size:.8rem; font-weight:700; margin-bottom:.45rem;">🎬 Canlı şəkil (AR){{ $item->product_id === null ? ' — ayrıca' : ' — qutunun üzərində' }}</div>
      @if($live)
        <div style="display:flex; gap:.75rem; align-items:flex-start;">
          @if($img = $live->imageUrl())
            <a href="{{ $img }}" target="_blank" title="Çap üçün şəkil">
              <img src="{{ $img }}" alt="Canlı şəkil" style="width:5rem; max-height:8rem; object-fit:contain; border-radius:.4rem; border:1px solid rgba(128,128,128,.35); display:block;">
            </a>
          @endif
          <div style="display:flex; flex-direction:column; gap:.3rem; font-size:.8rem;">
            <span>{{ filled($live->target_mind) ? '✓ Kamera üçün hazırdır' : '⚠ Kamera üçün hazırlanmayıb' }}</span>
            <span>{{ match ($live->videoPlace()) { 'yandex' => '✓ Video Yandex Diskdə', 'hosting' => '⏳ Video hostinqdə — Yandex-ə köçməyib', default => '⚠ Video yoxdur' } }}</span>
            <a href="{{ \App\Filament\Resources\LivePhotoResource::getUrl('edit', ['record' => $live]) }}" style="font-weight:600; text-decoration:underline;">QR kod və ayarlar →</a>
          </div>
        </div>
      @else
        @if($video = $item->arVideoUrl())
          <video src="{{ $video }}" controls preload="metadata" style="width:100%; max-width:16rem; border-radius:.5rem; display:block;"></video>
          <a href="{{ $video }}" download style="font-size:.75rem; text-decoration:underline;">Videonu yüklə</a>
        @endif
        <div style="font-size:.78rem; opacity:.75; margin:.4rem 0;">Videonu Yandex Diskə qoyun, sonra canlı şəkli yaradın.</div>
        <a href="{{ \App\Filament\Resources\LivePhotoResource::getUrl('create') }}?order_item={{ $item->id }}" style="font-size:.85rem; font-weight:600; text-decoration:underline;">＋ AR yarat</a>
      @endif
    </div>
  @endif

  @if(! $fields['photos'] && ! $fields['texts'] && ! $item->hasLetter() && $item->ar_price === null && ! $item->spotify_uri)
    <span style="opacity:.6;">—</span>
  @endif
</div>
