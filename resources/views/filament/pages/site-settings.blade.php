<x-filament-panels::page>
  @if(\App\Models\Setting::get(\App\Models\Setting::MAINTENANCE) === '1')
    <div style="border:1px solid #dc2626; background:rgba(220,38,38,.08); color:#dc2626; border-radius:.75rem; padding:.75rem 1rem; font-weight:600;">
      Sayt hazırda müştərilər üçün bağlıdır (texniki işlər).
    </div>
  @endif

  <form wire:submit="save" style="display:flex; flex-direction:column; gap:1.5rem;">
    {{ $this->form }}

    <div>
      <x-filament::button type="submit">Saxla</x-filament::button>
    </div>
  </form>
</x-filament-panels::page>
