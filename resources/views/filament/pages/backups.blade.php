<x-filament-panels::page>
  <div style="font-size:.875rem; opacity:.75;">
    Kopyalar serverdə <code>storage/app/{{ \App\Support\Backup::FOLDER }}</code> qovluğunda saxlanılır —
    bu qovluğa heç bir internet ünvanı ilə çatmaq olmur. Ən vacibi isə öz kompüterinizdəki kopyadır:
    «Yüklə» düyməsi onun üçündür.
  </div>

  <form wire:submit="save" style="display:flex; flex-direction:column; gap:1rem;">
    {{ $this->form }}
    <div>
      <x-filament::button type="submit">Yadda saxla</x-filament::button>
    </div>
  </form>

  @php $rows = $this->rows(); @endphp

  @if($rows === [])
    <div style="padding:2rem; text-align:center; opacity:.7;">
      Hələ kopya yoxdur. «İndi kopya al» düyməsini basın.
    </div>
  @else
    <div style="overflow-x:auto;">
      <table style="width:100%; border-collapse:collapse; font-size:.875rem;">
        <thead>
          <tr style="text-align:left; border-bottom:1px solid rgba(128,128,128,.3);">
            <th style="padding:.6rem .5rem;">Tarix</th>
            <th style="padding:.6rem .5rem;">Fayl</th>
            <th style="padding:.6rem .5rem;">Ölçü</th>
            <th style="padding:.6rem .5rem;"></th>
          </tr>
        </thead>
        <tbody>
          @foreach($rows as $row)
            <tr style="border-bottom:1px solid rgba(128,128,128,.15);">
              <td style="padding:.6rem .5rem; white-space:nowrap;">{{ $row['at']->format('d.m.Y H:i') }}</td>
              <td style="padding:.6rem .5rem; font-family:ui-monospace,monospace; font-size:.8125rem;">{{ $row['name'] }}</td>
              <td style="padding:.6rem .5rem; white-space:nowrap;">{{ $row['pretty'] }}</td>
              <td style="padding:.6rem .5rem; white-space:nowrap; text-align:right;">
                <x-filament::button size="xs" wire:click="download(@js($row['name']))">Yüklə</x-filament::button>
                <x-filament::button size="xs" color="danger" wire:click="forget(@js($row['name']))"
                                    wire:confirm="Bu kopya silinsin?">Sil</x-filament::button>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</x-filament-panels::page>
