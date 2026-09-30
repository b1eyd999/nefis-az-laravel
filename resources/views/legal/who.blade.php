{{-- Who is speaking on these pages: what the owner wrote in the admin. --}}
@php
  $name = \App\Models\Setting::get(\App\Models\Setting::LEGAL_NAME);
  $voen = \App\Models\Setting::get(\App\Models\Setting::LEGAL_VOEN);
  $address = \App\Models\Setting::get(\App\Models\Setting::LEGAL_ADDRESS);
@endphp
@if($name || $voen || $address)
  <p class="legal-who">
    <b>{{ $name ?: 'Nefis Şokolad Evi' }}</b>@if($voen) · {{ __('VÖEN') }} {{ $voen }}@endif@if($address) · {{ $address }}@endif
  </p>
@endif
