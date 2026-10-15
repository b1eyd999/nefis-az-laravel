{{-- Asks whether Google Analytics may run. Shown only when there is a
     measurement id saved, because that is the only optional thing this site
     starts; the session, the form token and the remembered theme are what
     make the shop work, and refusing them would mean no basket. --}}
@if(\App\Support\CookieNotice::enabled())
<div class="cookie-bar" id="cookie-bar" hidden
     data-key="{{ \App\Support\CookieNotice::KEY }}"
     role="region" aria-label="{{ __('Çərəzlər') }}">
  <p class="cookie-text">
    {{ __('Saytın işləməsi üçün lazım olan çərəzləri (səbətiniz, forma qorunması) hər halda istifadə edirik. Bundan əlavə, səhifələrin necə baxıldığını Google Analytics ilə saymaq istəyirik — bu, sizin seçiminizdir.') }}
    <a href="{{ lroute('legal.privacy') }}">{{ __('Ətraflı') }}</a>
  </p>
  <div class="cookie-acts">
    <button type="button" class="btn btn-ghost cookie-no" data-cookie="essential">{{ __('Yalnız zəruri') }}</button>
    <button type="button" class="btn btn-primary cookie-yes" data-cookie="all">{{ __('Qəbul edirəm') }}</button>
  </div>
</div>
<script defer src="{{ asset('js/cookie-notice.js') }}?v={{ \App\Support\Assets::version('js/cookie-notice.js') }}"></script>
@endif
