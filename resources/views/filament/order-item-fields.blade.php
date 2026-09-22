{{-- What the customer sent for one order line, laid out like the form they
     filled in: each field's name above what they gave. Inline styles, because
     the panel's stylesheet only carries the classes Filament itself uses. --}}
@php $fields = $getRecord()->fields(); $line = $getRecord(); @endphp
<div class="oi-root" style="display:flex; flex-direction:column; gap:.85rem; padding:.5rem 0; min-width:16rem; max-width:26rem;">
  {{-- On a phone the other columns are hidden: the line's name, count, price, bar and paper here instead. --}}
  <style>.oi-phone{ display:none; } @media (max-width: 767px){ .oi-phone{ display:block; } .oi-root{ min-width:0 !important; max-width:calc(100vw - 4.5rem) !important; white-space:normal; } }</style>
  <div class="oi-phone" style="border-bottom:1px solid rgba(128,128,128,.3); padding-bottom:.6rem;">
    <div style="font-weight:700; font-size:.95rem; white-space:normal;">{{ $line->product?->name ?? $line->product_name ?? 'Silinmiş məhsul' }}</div>
    <div style="font-size:.85rem; opacity:.85; margin-top:.2rem;">
      {{ $line->quantity }} ədəd
      @if($line->unitPrice() > 0) · <b>{{ \App\Support\Price::format($line->unitPrice() * $line->quantity) }}</b> @endif
    </div>
    @if($line->chocolate_name)
      <div style="font-size:.85rem; margin-top:.2rem; white-space:normal;">🍫 {{ $line->chocolate_name }}@if($line->chocolate_price) · {{ \App\Support\Price::format($line->chocolate_price) }}@endif</div>
    @endif
    @if($line->wrapping_name)
      <div style="font-size:.85rem; margin-top:.2rem; white-space:normal;">🎁 {{ $line->wrapping_name }}@if($line->wrapping_price) · {{ \App\Support\Price::format($line->wrapping_price) }}@endif</div>
    @endif
  </div>
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
          <a href="{{ $url }}" download style="font-size:.75rem; opacity:.8; text-decoration:underline; color:inherit;">Yüklə</a>
        </div>
      @endforeach
    </div>
  @endif

  @foreach($fields['texts'] as $text)
    <div>
      <div style="font-size:.75rem; font-weight:600; opacity:.75; margin-bottom:.3rem;">
        {{ $text['label'] }}
        @if($text['fixed'])<span style="font-weight:400; opacity:.8;">· dizaynda sabit</span>@endif
      </div>
      <div style="border:1px solid rgba(128,128,128,.35); border-radius:.6rem; padding:.5rem .75rem; font-size:.9rem; white-space:pre-wrap; word-break:break-word;{{ $text['fixed'] ? ' opacity:.65;' : '' }}">{{ $text['value'] !== '' ? $text['value'] : '—' }}</div>
    </div>
  @endforeach

  @php $item = $getRecord(); @endphp
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
            <a href="{{ $url }}" download style="font-size:.75rem; opacity:.8; text-decoration:underline; color:inherit;">Yüklə</a>
          </div>
        @endif
        <div style="flex:1; min-width:0; font-size:.9rem; white-space:pre-wrap; word-break:break-word;">{{ $item->letter_text ?: ($url ? 'Mətnsiz' : '—') }}</div>
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

  @if(! $fields['photos'] && ! $fields['texts'] && ! $item->hasLetter() && $item->ar_price === null)
    <span style="opacity:.6;">—</span>
  @endif
</div>
