@extends('layouts.app')

@section('title', __('Hesabım') . ', Nefis Şokolad Evi')
@section('robots', 'noindex, nofollow')

@section('page_style')
  .profile-card + .profile-card{ margin-top:1.25rem; }
  .profile-card h2{ font-size:1.15rem; margin:0 0 .25rem; }
  .profile-card .lead{ font-size:.875rem; line-height:1.6; color:var(--cocoa-soft); margin:0 0 1.1rem; }
  .profile-card .hint{ display:block; margin-top:.35rem; font-size:.78rem; color:var(--cocoa-soft); }
  .profile-foot{ display:flex; flex-wrap:wrap; gap:.6rem; margin-top:1.25rem; }
  .profile-foot .btn{ flex:1 1 12rem; }
@endsection

@section('content')
<section class="page-hero">
  <div class="wrap-narrow">
    <span class="eyebrow">{{ __('Hesab') }}</span>
    <h1>{{ __('Hesabım') }}</h1>
    <p class="lede" style="margin-inline:auto;">{{ __('Adınız, əlaqə nömrəniz və şifrəniz — hamısı burada dəyişdirilir.') }}</p>
  </div>
</section>

<section style="padding-top:0;">
  <div class="wrap-narrow">
    @if(session('status'))
      <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if($errors->any())
      <div class="alert alert-error">
        <ul style="margin:0; padding-left:1.1rem;">
          @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
      </div>
    @endif

    <div class="auth-card profile-card">
      <h2>{{ __('Məlumatlarım') }}</h2>
      <p class="lead">{{ __('Sifarişi bu nömrə ilə axtarırıq və bu nömrəyə zəng edirik.') }}</p>

      <form method="POST" action="{{ lroute('profile.update') }}">
        @csrf
        <div class="field">
          <label for="name">{{ __('Ad Soyad') }}</label>
          <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required>
        </div>
        <div class="field">
          <label for="phone">{{ __('Telefon') }}</label>
          <input type="tel" id="phone" name="phone" value="{{ old('phone', $user->phone) }}" required
                 inputmode="tel" autocomplete="tel" placeholder="+994 55 555 55 55">
        </div>
        <div class="field">
          <label for="email">{{ __('E-poçt') }}</label>
          <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">{{ __('Yadda saxla') }}</button>
      </form>
    </div>

    <div class="auth-card profile-card">
      <h2>{{ __('Şifrəni dəyişin') }}</h2>
      <p class="lead">{{ __('Əvvəlcə hazırkı şifrəni yazın, sonra yenisini.') }}</p>

      <form method="POST" action="{{ lroute('profile.password') }}">
        @csrf
        <div class="field">
          <label for="current_password">{{ __('Hazırkı şifrə') }}</label>
          @include('partials.password-field', [
            'id' => 'current_password', 'name' => 'current_password', 'autocomplete' => 'current-password',
          ])
        </div>
        <div class="field">
          <label for="new_password">{{ __('Yeni şifrə') }}</label>
          @include('partials.password-field', [
            'id' => 'new_password', 'name' => 'password', 'autocomplete' => 'new-password',
          ])
          <small class="hint">{{ __('Ən azı 8 simvol.') }}</small>
        </div>
        <button type="submit" class="btn btn-ghost btn-block">{{ __('Şifrəni dəyiş') }}</button>
      </form>
    </div>

    <div class="profile-foot">
      <a href="{{ lroute('orders.index') }}" class="btn btn-ghost">📦 {{ __('Sifarişlərim') }}</a>
      <form method="POST" action="{{ lroute('logout') }}" style="flex:1 1 12rem;">
        @csrf
        <button type="submit" class="btn btn-ghost btn-block">↩ {{ __('Çıxış') }}</button>
      </form>
    </div>
  </div>
</section>
@endsection

@section('page_script')
<script defer src="{{ asset('js/password-eye.js') }}?v={{ \App\Support\Assets::version('js/password-eye.js') }}"></script>
<script>
/* The same shaping the sign-up does, so a number saved here comes out in
   the one form the shop stores and dials. */
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
