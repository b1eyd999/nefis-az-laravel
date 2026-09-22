<x-filament-panels::page>
  {{-- The site's Polaroid look and its handwriting faces, so the preview here is the real thing. --}}
  <link rel="stylesheet" href="{{ asset('css/polaroid.css') }}">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Caveat:wght@600&family=Pacifico&family=Great+Vibes&family=Sacramento&display=swap">
  <style>
    .lt-grid{ display:grid; gap:1.5rem; align-items:start; }
    @media (min-width:1100px){ .lt-grid{ grid-template-columns:minmax(0,1fr) 20rem; } }
    .lt-preview{ position:sticky; top:5rem; display:flex; flex-direction:column; gap:1.5rem; padding:1.25rem; border-radius:1rem;
      border:1px solid rgba(128,128,128,.25); background:radial-gradient(ellipse at 50% 35%, rgba(217,119,6,.12), transparent 70%); }
    .lt-preview .polaroid{ max-width:13rem; }
    .lt-cap{ font-size:.75rem; opacity:.7; text-align:center; }
    .lt-table{ width:100%; border-collapse:collapse; font-size:.875rem; }
    .lt-table th{ text-align:left; font-weight:600; opacity:.7; padding:.5rem .6rem; border-bottom:1px solid rgba(128,128,128,.25); }
    .lt-table td{ padding:.55rem .6rem; border-bottom:1px solid rgba(128,128,128,.12); vertical-align:top; }
    .lt-thumb{ width:3.6rem; height:3.6rem; object-fit:cover; border:4px solid #fbfaf6; border-bottom-width:11px; box-shadow:0 2px 6px rgba(0,0,0,.35); display:block; }
    .lt-text{ white-space:pre-wrap; word-break:break-word; max-width:28rem; }
  </style>

  <div class="lt-grid">
    <form wire:submit="save" style="display:flex; flex-direction:column; gap:1.5rem;">
      {{ $this->form }}
      <div><x-filament::button type="submit">Saxla</x-filament::button></div>
    </form>

    {{-- Redrawn from the form as it is being filled in. --}}
    <div class="lt-preview"
         x-data="{
           paint() {
             const d = $wire.data || {}, f = this.$refs.fig;
             f.style.setProperty('--pol-frame', d.frame || '#FBFAF6');
             f.style.setProperty('--pol-ink', d.ink || '#2B2622');
             f.style.setProperty('--pol-tilt', (parseFloat(d.tilt) || 0) + 'deg');
             f.style.setProperty('--pol-font', `'${d.font || 'Caveat'}'`);
             ['polaroid','soft','bw','none'].forEach(k => f.classList.toggle('pol-f-' + k, (d.filter || 'polaroid') === k));
             this.$refs.fig2.setAttribute('style', f.getAttribute('style'));
             this.$refs.fig2.className = f.className.replace('pol-m', '').replace('pol-s', '') + ' no-photo pol-s';
             this.$refs.note.textContent = d.placeholder || @js(\App\Support\Letter::PAGE_DEFAULTS['placeholder']);
           },
         }"
         x-init="paint()"
         x-effect="$wire.data && [$wire.data.frame, $wire.data.ink, $wire.data.tilt, $wire.data.font, $wire.data.filter, $wire.data.placeholder]; paint()">
      <div>
        <figure class="polaroid pol-f-polaroid pol-m" x-ref="fig" style="{{ \App\Support\Letter::style() }}">
          <span class="pol-photo">
            <img src="data:image/svg+xml,{{ rawurlencode('<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 10 10\'><defs><linearGradient id=\'g\' x1=\'0\' y1=\'0\' x2=\'1\' y2=\'1\'><stop offset=\'0\' stop-color=\'#f6a05c\'/><stop offset=\'1\' stop-color=\'#3b6ea8\'/></linearGradient></defs><rect width=\'10\' height=\'10\' fill=\'url(#g)\'/><circle cx=\'5\' cy=\'4.3\' r=\'2\' fill=\'#fde7cf\'/></svg>') }}" alt="">
            <span class="pol-note"></span>
          </span>
          <figcaption class="pol-text">Ad günün mübarək, əzizim!</figcaption>
        </figure>
        <p class="lt-cap">Şəkilli</p>
      </div>
      <div>
        <figure class="polaroid pol-f-polaroid no-photo pol-s" x-ref="fig2" style="{{ \App\Support\Letter::style() }}">
          <span class="pol-photo"><img hidden alt=""><span class="pol-note is-placeholder" x-ref="note"></span></span>
          <figcaption class="pol-text"></figcaption>
        </figure>
        <p class="lt-cap">Şəkilsiz (boş)</p>
      </div>
    </div>
  </div>

  <x-filament::section>
    <x-slot name="heading">Sifariş olunan məktublar</x-slot>
    <x-slot name="description">Son 50 məktub, ləğv edilənlərdən başqa. Şəkli çap üçün "Yüklə" ilə götürün.</x-slot>
    @if($this->letters->isEmpty())
      <p style="opacity:.7;">Hələ məktub sifariş olunmayıb.</p>
    @else
      <div style="overflow-x:auto;">
        <table class="lt-table">
          <thead><tr><th>Sifariş</th><th>Şəkil</th><th>Mətn</th><th>Harada</th><th>Say</th></tr></thead>
          <tbody>
            @foreach($this->letters as $item)
              <tr>
                <td>
                  <a href="{{ \App\Filament\Resources\OrderResource::getUrl('edit', ['record' => $item->order_id]) }}" style="text-decoration:underline;">#{{ $item->order_id }}</a>
                  <div style="font-size:.75rem; opacity:.7;">{{ $item->order?->created_at?->format('d.m.Y') }} · {{ $item->order?->statusLabel() }}</div>
                </td>
                <td>
                  @if($url = $item->letterPhotoUrl())
                    <a href="{{ $url }}" target="_blank"><img src="{{ $url }}" alt="" class="lt-thumb"></a>
                    <a href="{{ $url }}" download style="font-size:.75rem; text-decoration:underline;">Yüklə</a>
                  @else
                    <span style="opacity:.6;">—</span>
                  @endif
                </td>
                <td><div class="lt-text">{{ $item->letter_text ?: '—' }}</div></td>
                <td>{{ $item->isLetterOnly() ? 'Ayrıca' : 'Qutunun içində' }}</td>
                <td>{{ $item->quantity }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </x-filament::section>
</x-filament-panels::page>
