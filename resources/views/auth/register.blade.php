@extends('layouts.app')

@section('title', 'Qeydiyyat — Nefis Şokolad Evi')

@section('content')
<section class="page-hero">
  <div class="wrap-narrow">
    <span class="eyebrow">Xoş Gəlmisiniz</span>
    <h1>Qeydiyyatdan Keçin</h1>
    <p class="lede" style="margin-inline:auto;">Fərdi şokolad qutunuzu sifariş etmək üçün hesab yaradın.</p>
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

      <form method="POST" action="{{ route('register') }}">
        @csrf
        <div class="field">
          <label for="name">Ad Soyad</label>
          <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus>
        </div>
        <div class="field">
          <label for="email">E-poçt</label>
          <input type="email" id="email" name="email" value="{{ old('email') }}" required>
        </div>
        <div class="field">
          <label for="phone">Telefon (istəyə bağlı)</label>
          <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" placeholder="+994 XX XXX XX XX">
        </div>
        <div class="field">
          <label for="password">Şifrə</label>
          <input type="password" id="password" name="password" required>
        </div>
        <div class="field">
          <label for="password_confirmation">Şifrəni Təkrarlayın</label>
          <input type="password" id="password_confirmation" name="password_confirmation" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Qeydiyyatdan Keç</button>
      </form>

      <p class="foot-link">Artıq hesabınız var? <a href="{{ route('login') }}">Daxil olun</a></p>
    </div>
  </div>
</section>
@endsection
