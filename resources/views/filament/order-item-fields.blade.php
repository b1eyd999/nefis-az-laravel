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

  @if(! $fields['photos'] && ! $fields['texts'])
    <span style="opacity:.6;">—</span>
  @endif
</div>
