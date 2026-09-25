{{-- The flag beside a language. Drawn here instead of an emoji: Windows has
     no flag glyphs and would print two letters in a box. --}}
@php($code = $code ?? 'az')
<svg class="flag" viewBox="0 0 60 40" aria-hidden="true" focusable="false">
  @switch($code)
    @case('ru')
      <rect width="60" height="40" fill="#fff"/>
      <rect y="13.34" width="60" height="13.33" fill="#0039A6"/>
      <rect y="26.67" width="60" height="13.33" fill="#D52B1E"/>
      @break
    @case('en')
      <rect width="60" height="40" fill="#012169"/>
      <path d="M0 0 60 40M60 0 0 40" stroke="#fff" stroke-width="8"/>
      <path d="M0 0 60 40M60 0 0 40" stroke="#C8102E" stroke-width="4"/>
      <path d="M30 0v40M0 20h60" stroke="#fff" stroke-width="13"/>
      <path d="M30 0v40M0 20h60" stroke="#C8102E" stroke-width="7"/>
      @break
    @default
      <rect width="60" height="13.34" fill="#0092BC"/>
      <rect y="13.34" width="60" height="13.33" fill="#E8112D"/>
      <rect y="26.67" width="60" height="13.33" fill="#00AE65"/>
      <circle cx="27.2" cy="20" r="5.6" fill="#fff"/>
      <circle cx="29.3" cy="20" r="4.5" fill="#E8112D"/>
      <path d="M36.60 15.60L37.35 18.20L39.71 16.89L38.40 19.25L41.00 20.00L38.40 20.75L39.71 23.11L37.35 21.80L36.60 24.40L35.85 21.80L33.49 23.11L34.80 20.75L32.20 20.00L34.80 19.25L33.49 16.89L35.85 18.20Z" fill="#fff"/>
  @endswitch
</svg>
