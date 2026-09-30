@extends('layouts.app')

@section('title', __('Yeni şifrə') . ', Nefis Şokolad Evi')
@section('robots', 'noindex, nofollow')

@section('content')
<section class="page-hero">
  <div class="wrap-narrow">
    <span class="eyebrow">{{ __('Giriş') }}</span>
    <h1>{{ __('Yeni şifrə təyin edin') }}</h1>
  </div>
</section>

<section style="padding-top:0;">
  <div class="wrap-narrow">
    <div class="auth-card reveal is-visible">
      @if($errors->any())
        <div class="alert alert-error">
          <ul style="margin:0; padding-left:1.1rem;">
            @foreach($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <form method="POST" action="{{ lroute('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div class="field">
          <label for="email">{{ __('E-poçt') }}</label>
          <input type="email" id="email" name="email" value="{{ old('email', $email) }}" required
                 autocomplete="email" inputmode="email">
        </div>
        <div class="field">
          <label for="password">{{ __('Yeni şifrə') }}</label>
          <input type="password" id="password" name="password" required autocomplete="new-password" minlength="8">
        </div>
        <div class="field">
          <label for="password_confirmation">{{ __('Yeni şifrə, bir daha') }}</label>
          <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password" minlength="8">
        </div>
        <button type="submit" class="btn btn-primary btn-block">{{ __('Şifrəni yenilə') }}</button>
      </form>

      <p class="foot-link"><a href="{{ lroute('login') }}">{{ __('Girişə qayıt') }}</a></p>
    </div>
  </div>
</section>
@endsection
