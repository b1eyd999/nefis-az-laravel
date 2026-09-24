{{--
  What a photo for a box has to look like, drawn rather than described: a
  sketch of the right shot next to the two that come in most often — a face
  turned away, and a person standing too far off.

  Used by the box customizer under every photo slot ($small = the inline
  sketch) and once per page as the window the "Nümunəyə bax" link opens.
--}}

@php
    // The frontal portrait: head in the middle, shoulders in, plain wall.
    $sketchGood = <<<'SVG'
      <g fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M16 150c2-24 16-38 32-44h24c16 6 30 20 32 44" />
        <path d="M53 106l7 10 7-10" />
        <path d="M53 84c0 10-1 14-3 17" /><path d="M67 84c0 10 1 14 3 17" />
        <path d="M34 106c-4-22-4-46 2-60 6-16 16-22 24-22s18 6 24 22c6 14 6 38 2 60" />
        <ellipse cx="60" cy="64" rx="18" ry="23" />
        <path d="M42 56c2-16 8-22 18-22s16 6 18 22" />
        <path d="M44 62c-2 16-4 28-5 40" opacity=".5" />
        <path d="M76 62c2 16 4 28 5 40" opacity=".5" />
        <path d="M50 57c2-2 6-2 8 0" /><path d="M62 57c2-2 6-2 8 0" />
        <path d="M50 64c2-3 6-3 8 0-2 3-6 3-8 0z" /><path d="M62 64c2-3 6-3 8 0-2 3-6 3-8 0z" />
        <circle cx="54" cy="64" r="1.6" fill="currentColor" stroke="none" />
        <circle cx="66" cy="64" r="1.6" fill="currentColor" stroke="none" />
        <path d="M60 66v6c0 2-1 3-3 3" />
        <path d="M55 80c3 3 7 3 10 0" />
      </g>
SVG;

    // Turned away: the same person, seen from the side.
    $sketchSide = <<<'SVG'
      <g fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M18 150c2-24 16-38 32-44h24c16 6 30 20 32 44" />
        <path d="M55 106l7 10 7-10" opacity=".6" />
        <path d="M57 84c0 10-1 14-3 17" /><path d="M71 84c0 10 1 14 3 17" />
        <path d="M62 30c-10 2-17 10-18 22 0 4-1 6-3 8-2 2-6 3-6 5s4 3 6 4c-1 3-2 5-1 7 1 3 4 6 8 8 6 2 13 1 17-1" />
        <path d="M62 30c14 0 22 12 22 26 0 16-6 28-14 32" />
        <path d="M48 44c8-10 22-12 32-4" />
        <path d="M46 58c2-1 4-1 5 0" />
        <circle cx="48" cy="61" r="1.6" fill="currentColor" stroke="none" />
        <path d="M64 60c4-1 6 2 5 6-1 3-4 3-5 2" opacity=".7" />
      </g>
SVG;

    // Too far off: a small figure lost in the frame, a busy wall behind.
    $sketchFar = <<<'SVG'
      <g fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="60" cy="72" r="6" />
        <path d="M51 100c0-7 4-12 9-12s9 5 9 12" />
        <path d="M54 100v16" /><path d="M66 100v16" />
        <g opacity=".4" stroke-width="1.6">
          <path d="M14 30h22v16H14z" /><path d="M88 26l12 12-12 12" />
          <path d="M16 128c8-6 14-6 20 0" /><path d="M94 116v22" />
          <path d="M98 96h10" /><path d="M12 88h12" /><path d="M78 134h16" />
        </g>
      </g>
SVG;
@endphp

@once
  <div class="pg-modal" id="photo-guide-modal" hidden>
    <div class="pg-sheet" role="dialog" aria-modal="true" aria-labelledby="pg-title">
      <button type="button" class="pg-close" id="photo-guide-close" aria-label="{{ __('Bağla') }}">×</button>
      <h3 id="pg-title">{{ __('Şəkil necə olmalıdır?') }}</h3>
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
        <li>{{ __('Gündüz işığında və ya pəncərəyə tərəf durub çəkin — kölgə üzə düşməsin.') }}</li>
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
