<x-filament-panels::page>
  {{-- The page as the visitor will read it is one tap away, so a change can
       be looked at rather than imagined. --}}
  <div style="display:flex; align-items:center; gap:.75rem; flex-wrap:wrap; font-size:.875rem;">
    <a href="{{ url('/sirketler-ucun') }}" target="_blank" rel="noopener"
       style="font-weight:600; text-decoration:underline;">Səhifəni aç ↗</a>
    <span style="opacity:.65;">Boş qoyduğunuz sahə səhifədə öz ilkin mətni ilə qalır.</span>
  </div>

  <form wire:submit="save" style="display:flex; flex-direction:column; gap:1.5rem;">
    {{ $this->form }}
    <div>
      <x-filament::button type="submit">Yadda saxla</x-filament::button>
    </div>
  </form>
</x-filament-panels::page>
