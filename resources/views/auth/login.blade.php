@extends('layouts.app')

@section('title', __('Giriş') . ', Nefis Şokolad Evi')
@section('robots', 'noindex, nofollow')

@section('content')
<section class="page-hero">
  <div class="wrap-narrow">
    <span class="eyebrow">{{ __('Xoş Gəlmisiniz') }}</span>
    <h1>{{ __('Hesabınıza Daxil Olun') }}</h1>
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

      <form method="POST" action="{{ lroute('login') }}">
        @csrf
        <div class="field">
          <label for="login">{{ __('E-poçt və ya telefon') }}</label>
          {{-- The number is found by its digits, so there is nothing to get
               right; saying so saves the ones who try twice and give up. --}}
          <small class="hint">{{ __('Nömrəni istədiyiniz kimi yazın: 0551234567 və ya +994 55 123 45 67.') }}</small>
          {{-- No example inside the field: the label above already says what goes here. --}}
          <input type="text" id="login" name="login" value="{{ old('login') }}" required autofocus
                 autocomplete="username" inputmode="email">
        </div>
        <div class="field">
          <label for="password">{{ __('Şifrə') }}</label>
          <input type="password" id="password" name="password" required>
        </div>
        <div class="checkbox-row">
          <input type="checkbox" id="remember" name="remember">
          <label for="remember">{{ __('Məni xatırla') }}</label>
          <a href="{{ lroute('password.request') }}" style="margin-left:auto; font-size:.9rem;">{{ __('Şifrəni unutmusunuz?') }}</a>
        </div>
        <button type="submit" class="btn btn-primary btn-block">{{ __('Daxil Ol') }}</button>
      </form>

      <p class="foot-link">{{ __('Hesabınız yoxdur?') }} <a href="{{ lroute('register') }}">{{ __('Qeydiyyatdan keçin') }}</a></p>
    </div>
  </div>
</section>
@endsection
