{{-- The mark that stands on the customer's place, in the shop's orange. --}}
@if($mark === 'heart')
  <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">
    <path d="M12 21s-7.5-4.7-9.3-9A5.2 5.2 0 0 1 12 6.6 5.2 5.2 0 0 1 21.3 12c-1.8 4.3-9.3 9-9.3 9Z"/>
  </svg>
@elseif($mark === 'star')
  <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">
    <path d="m12 2.6 2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.5 6.1 20.6l1.2-6.5L2.5 9.5l6.6-.9 2.9-6Z"/>
  </svg>
@else
  <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">
    <path d="M12 2.5a7 7 0 0 0-7 7c0 5 7 12 7 12s7-7 7-12a7 7 0 0 0-7-7Zm0 9.4a2.6 2.6 0 1 1 0-5.2 2.6 2.6 0 0 1 0 5.2Z"/>
  </svg>
@endif
