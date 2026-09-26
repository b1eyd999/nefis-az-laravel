{{-- Touching up the cut-out by hand.
     The machine gets the head out of the photo on its own, but a corner of a
     room or a bit of a shoulder sometimes stays. Rather than send the
     customer to Photoshop, he wipes it away here — and sees exactly what the
     brush will take, because the circle follows his hand. --}}
<div class="brush" id="brush" hidden aria-hidden="true">
  <div class="brush-back" data-close></div>
  <div class="brush-sheet" role="dialog" aria-modal="true" aria-label="{{ __('Fonu düzəlt') }}">
    <div class="brush-head">
      <span class="brush-title">
        <b>{{ __('Fonu düzəlt') }}</b>
        <small>{{ __('Qutuya yalnız baş düşsün: artıq qalan fonu silin') }}</small>
      </span>
      <button type="button" class="brush-x" data-close aria-label="{{ __('Bağla') }}">✕</button>
    </div>

    <div class="brush-stage" id="brush-stage">
      <canvas id="brush-canvas"></canvas>
      {{-- The circle is the brush: what it covers is what goes. --}}
      <span class="brush-ring" id="brush-ring" hidden aria-hidden="true"></span>
    </div>

    <div class="brush-tools">
      <div class="brush-seg" role="group" aria-label="{{ __('Alət') }}">
        <button type="button" class="on" data-tool="erase">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M8.5 19.5 3.8 14.8a2 2 0 0 1 0-2.8l8-8a2 2 0 0 1 2.8 0l5.4 5.4a2 2 0 0 1 0 2.8l-7.3 7.3H8.5Z"/><path d="M20.2 19.5h-8"/><path d="m8.9 7.6 5.4 5.4"/>
          </svg>
          {{ __('Sil') }}
        </button>
        <button type="button" data-tool="restore">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M3 12a9 9 0 1 0 2.6-6.4"/><path d="M3 4.5V10h5.5"/>
          </svg>
          {{ __('Qaytar') }}
        </button>
      </div>

      <label class="brush-size">
        <span class="brush-size-ico" aria-hidden="true">●</span>
        <input type="range" id="brush-size" min="12" max="170" value="56" aria-label="{{ __('Fırçanın ölçüsü') }}">
        <output id="brush-size-out">56</output>
      </label>

      <div class="brush-acts">
        <button type="button" id="brush-undo" class="brush-act" disabled>↶ {{ __('Geri al') }}</button>
        <button type="button" id="brush-reset" class="brush-act">{{ __('Hamısını qaytar') }}</button>
      </div>
    </div>

    <p class="brush-hint">{{ __('Barmağınızla və ya siçanla artıq fonu silin. Çox silsəniz "Qaytar" ilə şəkli geri gətirin.') }}</p>

    <div class="brush-foot">
      <button type="button" class="btn btn-ghost" data-close>{{ __('İmtina') }}</button>
      <button type="button" class="btn btn-primary" id="brush-save">{{ __('Hazırdır') }}</button>
    </div>
  </div>
</div>
