{{-- "Put the shop on your home screen."

     Shown by hand rather than at the first glance: the bar appears on the
     second page a visitor opens, once, and a tap on × means it does not come
     back for a month. Hidden by default and never shown at all to somebody
     who already opened the app from his home screen.

     Android and Chrome have a real install dialog, so there the button opens
     that. iPhone has none — Apple keeps it behind the share sheet — so there
     the bar says where it is, with the share mark drawn in. --}}
<div class="pwa-bar" id="pwa-bar" hidden
     data-install="{{ __('Quraşdır') }}"
     data-seen-key="nefis-install-hint">
  <img class="pwa-mark" src="{{ \App\Support\Assets::url('images/icon-192.png') }}" alt="" width="40" height="40">
  <div class="pwa-words">
    <b>{{ __('Nefis-i telefonunuza qurun') }}</b>
    <span class="pwa-ios">{{ __('Paylaş düyməsinə, sonra «Ana ekrana əlavə et»') }}</span>
    <span class="pwa-and">{{ __('App Store olmadan, bir toxunuşla') }}</span>
  </div>
  <button type="button" class="pwa-go" hidden>{{ __('Quraşdır') }}</button>
  <button type="button" class="pwa-x" aria-label="{{ __('Bağla') }}">&times;</button>
</div>
