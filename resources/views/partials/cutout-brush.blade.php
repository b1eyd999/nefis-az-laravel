{{-- Touching up the cut-out by hand.
     The machine gets the head out of the photo on its own, but a corner of a
     room or a bit of a shoulder sometimes stays. Rather than send the
     customer to Photoshop, he wipes it away here with a finger or a mouse,
     and can paint it back if he wipes too much. --}}
<div class="brush" id="brush" hidden aria-hidden="true">
  <div class="brush-back" data-close></div>
  <div class="brush-sheet" role="dialog" aria-modal="true" aria-label="{{ __('Fonu düzəlt') }}">
    <div class="brush-head">
      <b>{{ __('Fonu düzəlt') }}</b>
      <button type="button" class="brush-x" data-close aria-label="{{ __('Bağla') }}">✕</button>
    </div>

    <div class="brush-stage" id="brush-stage">
      <canvas id="brush-canvas"></canvas>
    </div>

    <div class="brush-tools">
      <div class="brush-seg" role="group" aria-label="{{ __('Alət') }}">
        <button type="button" class="on" data-tool="erase">🧽 {{ __('Sil') }}</button>
        <button type="button" data-tool="restore">↩ {{ __('Qaytar') }}</button>
      </div>
      <label class="brush-size">
        <span>{{ __('Fırça') }}</span>
        <input type="range" id="brush-size" min="10" max="140" value="48">
      </label>
      <button type="button" class="btn btn-ghost brush-undo" id="brush-undo">{{ __('Geri al') }}</button>
    </div>

    <p class="brush-hint">{{ __('Barmağınızla və ya siçanla artıq qalan fonu silin. Çox silsəniz, "Qaytar" ilə geri gətirin.') }}</p>

    <div class="brush-foot">
      <button type="button" class="btn btn-ghost" data-close>{{ __('İmtina') }}</button>
      <button type="button" class="btn btn-primary" id="brush-save">{{ __('Hazırdır') }}</button>
    </div>
  </div>
</div>
