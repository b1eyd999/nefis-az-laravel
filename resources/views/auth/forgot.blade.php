@extends('layouts.app')

@section('title', __('Şifrəni unutmusunuz?') . ', Nefis Şokolad Evi')
@section('robots', 'noindex, nofollow')

@section('content')
<section class="page-hero">
  <div class="wrap-narrow">
    <span class="eyebrow">{{ __('Giriş') }}</span>
    <h1>{{ __('Şifrəni unutmusunuz?') }}</h1>
  </div>
</section>

<section style="padding-top:0;">
  <div class="wrap-narrow">
    <div class="auth-card reveal is-visible">
      @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
      @endif
      @if($errors->any())
        <div class="alert alert-error">
          <ul style="margin:0; padding-left:1.1rem;">
            @foreach($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <p class="foot-link" style="text-align:left; margin:0 0 1rem;">
        {{ __('E-poçt ünvanınızı yazın — şifrəni yeniləmək üçün keçid göndərək.') }}
      </p>

      <form method="POST" action="{{ lroute('password.email') }}">
        @csrf
        <div class="field">
          <label for="email">{{ __('E-poçt') }}</label>
          <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus
                 autocomplete="email" inputmode="email">
        </div>
        <button type="submit" class="btn btn-primary btn-block">{{ __('Keçid göndər') }}</button>
      </form>

      {{-- Someone who signed up with a telephone has no address to write to,
           and should be told where to knock instead of waiting for a letter. --}}
      <p class="foot-link">{{ __('Telefonla qeydiyyatdan keçmisiniz?') }}
        <a href="{{ \App\Support\Contact::whatsapp() }}" rel="nofollow">{{ __('Bizə yazın') }}</a></p>
      <p class="foot-link"><a href="{{ lroute('login') }}">{{ __('Girişə qayıt') }}</a></p>
    </div>
  </div>
</section>
@endsection
