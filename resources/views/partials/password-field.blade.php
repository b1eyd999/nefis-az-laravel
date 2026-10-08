{{-- A password box with an eye beside it.
     The sign-up no longer asks for the password twice, so this is what
     catches a typo: whoever is unsure looks at what he typed. --}}
@php
  $pwId = $id ?? 'password';
  $pwName = $name ?? 'password';
  $pwAuto = $autocomplete ?? 'current-password';
  $pwShow = __('Şifrəni göstər');
  $pwHide = __('Şifrəni gizlət');
@endphp
<div class="pw">
  <input type="password" id="{{ $pwId }}" name="{{ $pwName }}" required
         autocomplete="{{ $pwAuto }}" minlength="8">
  <button type="button" class="pw-eye" data-for="{{ $pwId }}" aria-pressed="false"
          data-show="{{ $pwShow }}" data-hide="{{ $pwHide }}" aria-label="{{ $pwShow }}">
    <svg class="open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>
    </svg>
    <svg class="shut" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M3 3l18 18M10.6 10.7a3 3 0 004.2 4.2"/>
      <path d="M9.4 5.2A9.9 9.9 0 0112 5c6.5 0 10 7 10 7a18 18 0 01-3.1 4M6.2 6.6A17.8 17.8 0 002 12s3.5 7 10 7a9.8 9.8 0 004.3-.96"/>
    </svg>
  </button>
</div>
