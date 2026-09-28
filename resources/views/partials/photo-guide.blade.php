{{--
  What a photo for a box has to look like, drawn rather than described: a
  sketch of the right shot next to the two that come in most often, a face
  turned away, and a person standing too far off.

  Used by the box customizer under every photo slot ($small = the inline
  sketch) and once per page as the window the "Nümunəyə bax" link opens.
--}}

@php
    // The frontal portrait: head in the middle, shoulders in, plain wall.
    $sketchGood = <<<'SVG'
      <g fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M13 150c3-24 18-39 35-45h24c17 6 32 21 35 45" />
        <path d="M51 88v13c0 4-2 6-5 8" /><path d="M69 88v13c0 4 2 6 5 8" />
        <path d="M60 32c12 0 19 9 19 23 0 6 0 11-1 16-1 7-4 13-8 17-3 3-6 5-10 5s-7-2-10-5c-4-4-7-10-8-17-1-5-1-10-1-16 0-14 7-23 19-23z" />
        <path d="M41 58c-3-17 6-29 19-29s22 12 19 29c-1-7-3-12-5-15-3 4-9 7-17 7-6 0-11-1-14-3-1 2-2 6-2 11z" />
        <path d="M41 61c-4 0-6 3-5 7s4 6 6 5" /><path d="M79 61c4 0 6 3 5 7s-4 6-6 5" />
        <path d="M48 58c3-2 7-2 9 0" /><path d="M63 58c2-2 6-2 9 0" />
        <path d="M48 65c3-3 6-3 9 0" /><path d="M63 65c3-3 6-3 9 0" />
        <circle cx="52.5" cy="66" r="1.7" fill="currentColor" stroke="none" />
        <circle cx="67.5" cy="66" r="1.7" fill="currentColor" stroke="none" />
        <path d="M60 68v7c0 2-1 3-3 3" />
        <path d="M54 83c4 3 8 3 12 0" />
      </g>
SVG;

    // Turned away: the same person, seen from the side.
    $sketchSide = <<<'SVG'
      <g fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M13 150c3-24 18-39 35-45h24c17 6 32 21 35 45" />
        <path d="M64 96v9c0 3 2 5 5 7" />
        <path d="M71 30C57 30 49 39 48 51c-1 6-1 9-3 13-2 4-7 7-7 9 0 2 3 2 5 3 2 1 1 3 0 4-2 2 0 4 3 5-2 2-1 6 2 8 4 3 10 4 16 3" />
        <path d="M71 30c15 1 22 14 22 27 0 16-5 29-13 34" />
        <path d="M48 48c7-12 26-15 37-6" />
        <path d="M57 37c8-2 18 0 24 6" opacity=".55" />
        <path d="M45 57c2-2 6-2 8 0" />
        <path d="M45 63c2-2 5-2 6 1" />
        <circle cx="48" cy="66" r="1.7" fill="currentColor" stroke="none" />
        <path d="M69 66c5-2 9 2 8 7s-5 7-8 6" />
      </g>
SVG;

    // Too far off: a small figure lost in the frame, a busy wall behind.
    $sketchFar = <<<'SVG'
      <g fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" opacity=".38">
        <path d="M10 114h100" /><path d="M14 34h26v20H14z" /><path d="M27 34v20" /><path d="M14 44h26" />
        <path d="M86 40h22v16H86z" /><path d="M92 114v-14c0-6 4-10 8-10s8 4 8 10v14" />
        <path d="M100 90c-6-4-8-10-6-16 6 2 8 8 6 16z" />
      </g>
      <g fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="60" cy="70" r="7" />
        <circle cx="57.5" cy="69" r="1" fill="currentColor" stroke="none" />
        <circle cx="62.5" cy="69" r="1" fill="currentColor" stroke="none" />
        <path d="M53 98c0-9 3-15 7-15s7 6 7 15" />
        <path d="M53 92l-6 11" /><path d="M67 92l6 11" />
        <path d="M56 98v16" /><path d="M64 98v16" />
      </g>
      <rect x="48" y="57" width="24" height="24" rx="4" fill="none" stroke="currentColor" stroke-width="1.5" stroke-dasharray="3 4" opacity=".6" />
SVG;
@endphp

@once
  <div class="pg-modal" id="photo-guide-modal" hidden>
    <div class="pg-sheet" role="dialog" aria-modal="true" aria-labelledby="pg-title">
      <button type="button" class="pg-close" id="photo-guide-close" aria-label="{{ __('Bağla') }}">×</button>
      <h2 id="pg-title">{{ __('Şəkil necə olmalıdır?') }}</h2>
      <p class="pg-lead">{{ __('Şəkil qutunun üzərinə çap olunur, ona görə üz aydın görünməlidir.') }}</p>

      <div class="pg-grid">
        <figure class="pg-case is-good">
          <div class="pg-art">
            <svg viewBox="0 0 120 150" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <line x1="60" y1="16" x2="60" y2="134" stroke="currentColor" stroke-width="1" stroke-dasharray="3 5" opacity=".45"/>
              <line x1="22" y1="66" x2="98" y2="66" stroke="currentColor" stroke-width="1" stroke-dasharray="3 5" opacity=".45"/>
              {!! $sketchGood !!}
            </svg>
            <span class="pg-mark">✓</span>
          </div>
          <figcaption><b>{{ __('Belə olsun') }}</b><br>{{ __('Düz kameraya baxın: üz şəklin mərkəzində, çiyinlər görünsün, arxa fon sadə olsun.') }}</figcaption>
        </figure>

        <figure class="pg-case is-bad">
          <div class="pg-art">
            <svg viewBox="0 0 120 150" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">{!! $sketchSide !!}</svg>
            <span class="pg-mark">✕</span>
          </div>
          <figcaption><b>{{ __('Yandan') }}</b><br>{{ __('Profildən və ya aşağıdan çəkilmiş şəkildə üz tanınmır.') }}</figcaption>
        </figure>

        <figure class="pg-case is-bad">
          <div class="pg-art">
            <svg viewBox="0 0 120 150" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">{!! $sketchFar !!}</svg>
            <span class="pg-mark">✕</span>
          </div>
          <figcaption><b>{{ __('Çox uzaqdan') }}</b><br>{{ __('Tam boy və ya qarışıq fonda çəkilmiş şəkildə üz kiçik çıxır.') }}</figcaption>
        </figure>
      </div>

      <ul class="pg-tips">
        <li>{{ __('Gündüz işığında və ya pəncərəyə tərəf durub çəkin, kölgə üzə düşməsin.') }}</li>
        <li>{{ __('Telefonu göz səviyyəsində tutun.') }}</li>
        <li>{{ __('Şəkil aydın olsun: bulanıq və ya ekrandan çəkilmiş şəkil çap olunanda daha pis görünür.') }}</li>
        <li>{{ __('Bir neçə nəfər varsa, hamısı kameraya baxsın.') }}</li>
      </ul>
    </div>
  </div>
@endonce

@if (! empty($small))
  <div class="photo-guide">
    <svg viewBox="0 0 120 150" width="58" height="72" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
      <line x1="60" y1="16" x2="60" y2="134" stroke="currentColor" stroke-width="1" stroke-dasharray="3 5" opacity=".45"/>
      <line x1="22" y1="66" x2="98" y2="66" stroke="currentColor" stroke-width="1" stroke-dasharray="3 5" opacity=".45"/>
      {!! $sketchGood !!}
    </svg>
    <div>
      <p>{{ __('Üz şəklin mərkəzində, düz kameraya baxaraq çəkilmiş olsun.') }}</p>
      <button type="button" class="pg-open" data-photo-guide>{{ __('Nümunəyə bax') }}</button>
    </div>
  </div>
@endif
