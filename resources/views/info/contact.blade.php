@extends('layouts.app')

@section('title', __('Əlaqə') . ' | Nefis')
@section('meta_description', __('Nefis.az ilə əlaqə: telefon, WhatsApp, Instagram və e-poçt. Sifarişlə bağlı suallara iş saatları ərzində cavab veririk.'))

@section('content')
@php $d = \App\Http\Controllers\InfoController::details(); @endphp

<section class="page-hero">
  <div class="wrap-narrow">
    <span class="eyebrow">{{ __($page['contact_eyebrow']) }}</span>
    <h1>{{ __($page['contact_title']) }}</h1>
    <p class="lede">{{ __($page['contact_lede']) }}</p>
  </div>
</section>

<section style="padding-top:0;">
  <div class="wrap-narrow">
    {{-- The ways themselves, biggest first: on a telephone the top one is a
         tap away from a call. --}}
    <div class="ways">
      @if($d['phone'])
        <a class="way" href="tel:{{ $d['dial'] }}">
          <span class="way-ico">📞</span>
          <span class="way-text"><b>{{ $d['phone'] }}</b><small>{{ $d['hours'] ?: __('Zəng edin') }}</small></span>
        </a>
        <a class="way" href="{{ $d['whatsapp'] }}" target="_blank" rel="noopener">
          <span class="way-ico">@include('partials.whatsapp-icon')</span>
          <span class="way-text"><b>WhatsApp</b><small>{{ __('Şəkil və səs mesajı da göndərə bilərsiniz') }}</small></span>
        </a>
      @endif
      <a class="way" href="https://www.instagram.com/nefis.az/" target="_blank" rel="noopener">
        <span class="way-ico">@include('partials.instagram-icon')</span>
        <span class="way-text"><b>Instagram</b><small>@nefis.az</small></span>
      </a>
      <a class="way" href="mailto:{{ $d['email'] }}">
        <span class="way-ico">✉️</span>
        <span class="way-text"><b>{{ $d['email'] }}</b><small>{{ __('Sifariş və hesab-faktura üçün') }}</small></span>
      </a>
    </div>

    <h2 class="ct-h2">{{ __($page['contact_where_title']) }}</h2>
    <p class="ct-p">{{ __($page['contact_where_note']) }}</p>

    {{-- A message, for somebody who would rather write than ring. It is
         written down here and sent to the shop's Telegram at once. --}}
    <div class="auth-card ct-form" id="yazin">
      <h2>{{ __($page['contact_form_title']) }}</h2>
      <p class="ct-note">{{ __($page['contact_form_note']) }}</p>

      @if(session('status'))
        <p class="ct-thanks">{{ session('status') }}</p>
      @endif

      <form method="POST" action="{{ lroute('info.contact.write') }}">
        @csrf
        <div class="field">
          <label for="ct-name">{{ __('Adınız') }}</label>
          <input type="text" id="ct-name" name="name" value="{{ old('name', auth()->user()?->name) }}"
                 required maxlength="150" autocomplete="name">
          @error('name')<p class="guest-bad">{{ $message }}</p>@enderror
        </div>
        <div class="field">
          <label for="ct-phone">{{ __('Telefon nömrəsi') }}</label>
          <input type="tel" id="ct-phone" name="phone" value="{{ old('phone', auth()->user()?->phone) }}"
                 required maxlength="40" autocomplete="tel" placeholder="{{ __('+994 XX XXX XX XX') }}">
          @error('phone')<p class="guest-bad">{{ $message }}</p>@enderror
        </div>
        <div class="field">
          <label for="ct-email">{{ __('E-poçt (istəyə bağlı)') }}</label>
          <input type="email" id="ct-email" name="email" value="{{ old('email', auth()->user()?->email) }}"
                 maxlength="150" autocomplete="email">
          @error('email')<p class="guest-bad">{{ $message }}</p>@enderror
        </div>
        <div class="field">
          <label for="ct-about">{{ __('Hansı sifariş barədə? (istəyə bağlı)') }}</label>
          <input type="text" id="ct-about" name="about" value="{{ old('about') }}" maxlength="150"
                 placeholder="{{ __('Məs. sifariş #124, ya da «hələ sifariş verməmişəm»') }}">
        </div>
        <div class="field">
          <label for="ct-message">{{ __('Sualınız') }}</label>
          <textarea id="ct-message" name="message" rows="5" required maxlength="2000">{{ old('message') }}</textarea>
          @error('message')<p class="guest-bad">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="btn btn-primary btn-block">{{ __($page['contact_form_button']) }}</button>
      </form>
    </div>

    @if($d['legal'])
      {{-- Who sells, on paper: a company looking for an invoice, and a bank
           checking the shop is real, both look for exactly this. --}}
      <div class="ct-legal">
        @if(\App\Models\Setting::get(\App\Models\Setting::LEGAL_NAME))
          <p><b>{{ \App\Models\Setting::get(\App\Models\Setting::LEGAL_NAME) }}</b></p>
        @endif
        @if(\App\Models\Setting::get(\App\Models\Setting::LEGAL_VOEN))
          <p>{{ __('VÖEN') }} {{ \App\Models\Setting::get(\App\Models\Setting::LEGAL_VOEN) }}</p>
        @endif
        @if(\App\Models\Setting::get(\App\Models\Setting::LEGAL_ADDRESS))
          <p>{{ \App\Models\Setting::get(\App\Models\Setting::LEGAL_ADDRESS) }}</p>
        @endif
      </div>
    @endif

    <div class="how-cta">
      <a href="{{ lroute('info.faq') }}" class="btn btn-ghost">{{ __('Tez-tez soruşulan suallar') }}</a>
      <a href="{{ lroute('info.how') }}" class="btn btn-ghost">{{ __('Necə işləyir') }}</a>
    </div>
  </div>
</section>
@endsection
