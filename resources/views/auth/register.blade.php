@extends('layouts.app')

@section('title', __('Qeydiyyat') . ', Nefis Şokolad Evi')
@section('robots', 'noindex, nofollow')

@section('content')
<section class="page-hero">
  <div class="wrap-narrow">
    <span class="eyebrow">{{ __('Xoş Gəlmisiniz') }}</span>
    <h1>{{ __('Qeydiyyatdan Keçin') }}</h1>
    <p class="lede" style="margin-inline:auto;">{{ __('Fərdi şokolad qutunuzu sifariş etmək üçün hesab yaradın.') }}</p>
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

      <form method="POST" action="{{ lroute('register') }}">
        @csrf
        <div class="field">
          <label for="name">{{ __('Ad Soyad') }}</label>
          <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus>
        </div>
        <div class="field">
          <label for="email">{{ __('E-poçt') }}</label>
          <input type="email" id="email" name="email" value="{{ old('email') }}" required>
        </div>
        <div class="field">
          <label for="phone">{{ __('Telefon') }}</label>
          <input type="tel" id="phone" name="phone" value="{{ old('phone', '+994 ') }}" required
                 inputmode="tel" autocomplete="tel" placeholder="+994 55 555 55 55">
        </div>
        <div class="field">
          <label for="password">{{ __('Şifrə') }}</label>
          <input type="password" id="password" name="password" required>
        </div>
        <div class="field">
          <label for="password_confirmation">{{ __('Şifrəni Təkrarlayın') }}</label>
          <input type="password" id="password_confirmation" name="password_confirmation" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">{{ __('Qeydiyyatdan Keç') }}</button>
      </form>

      <p class="foot-link">{{ __('Artıq hesabınız var?') }} <a href="{{ lroute('login') }}">{{ __('Daxil olun') }}</a></p>
    </div>
  </div>
</section>
@endsection

@section('page_script')
<script>
/* The number writes itself as it is typed: the country code stays where it
   is and the rest falls into +994 55 555 55 55, however it was pasted in. */
(function(){
  var el = document.getElementById('phone');
  if (!el) return;
  function shape(value){
    var d = value.replace(/\D/g, '');
    if (d.indexOf('994') === 0) d = d.slice(3);
    d = d.replace(/^0+/, '').slice(0, 9);
    var out = '+994';
    if (d.length) out += ' ' + d.slice(0, 2);
    if (d.length > 2) out += ' ' + d.slice(2, 5);
    if (d.length > 5) out += ' ' + d.slice(5, 7);
    if (d.length > 7) out += ' ' + d.slice(7, 9);
    return out;
  }
  el.value = shape(el.value);
  el.addEventListener('input', function(){ el.value = shape(el.value); });
  el.addEventListener('focus', function(){ if (el.value.length < 5) el.value = '+994 '; });
})();
</script>
@endsection
