{{-- The shop's own bag: drawn in the logo's orange, with a square of
     chocolate inside, because that is what goes in it. The gradient needs a
     name of its own, so a page may carry more than one. --}}
@php($gid = 'nf-bag-' . ($id ?? 'h'))
<svg class="cart-ico" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">
  <defs>
    <linearGradient id="{{ $gid }}" x1="5" y1="5" x2="19" y2="21" gradientUnits="userSpaceOnUse">
      <stop stop-color="#FF8401"/><stop offset="1" stop-color="#F05420"/>
    </linearGradient>
  </defs>
  <path d="M6.3 8.6h11.4a1 1 0 0 1 1 1.1l-.82 8.5a2.7 2.7 0 0 1-2.69 2.4H8.81a2.7 2.7 0 0 1-2.69-2.4L5.3 9.7a1 1 0 0 1 1-1.1Z"
        stroke="url(#{{ $gid }})" stroke-width="1.6"/>
  <path d="M9.2 10.1V7.3a2.8 2.8 0 0 1 5.6 0v2.8" stroke="url(#{{ $gid }})" stroke-width="1.6" stroke-linecap="round"/>
  <rect x="9.9" y="13.1" width="4.2" height="4.2" rx=".9" fill="url(#{{ $gid }})"/>
  <path d="M12 13.1v4.2M9.9 15.2h4.2" stroke="var(--paper)" stroke-width=".8" opacity=".9"/>
</svg>
