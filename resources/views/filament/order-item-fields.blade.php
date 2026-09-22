{{-- What the customer sent for one order line, laid out like the form they
     filled in: each field's name above what they gave. Inline styles, because
     the panel's stylesheet only carries the classes Filament itself uses. --}}
@php $fields = $getRecord()->fields(); @endphp
<div style="display:flex; flex-direction:column; gap:.85rem; padding:.5rem 0; min-width:16rem; max-width:26rem;">
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
    <div style="border:1px dashed rgba(124,58,237,.6); border-radius:.75rem; padding:.6rem .75rem;">
      <div style="font-size:.8rem; font-weight:700; margin-bottom:.45rem;">🎬 Canlı video (AR)</div>
      @if($video = $item->arVideoUrl())
        <video src="{{ $video }}" controls preload="metadata" style="width:100%; max-width:16rem; border-radius:.5rem; display:block;"></video>
        <a href="{{ $video }}" download style="font-size:.75rem; text-decoration:underline;">Videonu yüklə</a>
      @endif
      <div style="font-size:.78rem; opacity:.75; margin:.4rem 0;">Videonu Yandex Diskə qoyun, sonra canlı şəkli yaradın.</div>
      @php $live = $item->livePhotos()->latest('id')->first(); @endphp
      @if($live)
        <a href="{{ \App\Filament\Resources\LivePhotoResource::getUrl('edit', ['record' => $live]) }}" style="font-size:.85rem; font-weight:600; text-decoration:underline;">
          Canlı şəkil: {{ $live->title }} {{ filled($live->target_mind) ? '✓ hazır' : '— hazırlanmayıb' }}</a>
      @else
        <a href="{{ \App\Filament\Resources\LivePhotoResource::getUrl('create') }}?order_item={{ $item->id }}" style="font-size:.85rem; font-weight:600; text-decoration:underline;">＋ AR yarat</a>
      @endif
    </div>
  @endif

  @if(! $fields['photos'] && ! $fields['texts'] && ! $item->hasLetter() && $item->ar_price === null)
    <span style="opacity:.6;">—</span>
  @endif
</div>
