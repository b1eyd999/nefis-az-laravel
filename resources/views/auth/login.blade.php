@extends('layouts.app')

@section('title', 'Giriş — Nefis Şokolad Evi')

@section('content')
<section class="page-hero">
  <div class="wrap-narrow">
    <span class="eyebrow">Xoş Gəlmisiniz</span>
    <h1>Hesabınıza Daxil Olun</h1>
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

      <form method="POST" action="{{ route('login') }}">
        @csrf
        <div class="field">
          <label for="email">E-poçt</label>
          <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus>
        </div>
        <div class="field">
          <label for="password">Şifrə</label>
          <input type="password" id="password" name="password" required>
        </div>
        <div class="checkbox-row">
          <input type="checkbox" id="remember" name="remember">
          <label for="remember">Məni xatırla</label>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Daxil Ol</button>
      </form>

      <p class="foot-link">Hesabınız yoxdur? <a href="{{ route('register') }}">Qeydiyyatdan keçin</a></p>
    </div>
  </div>
</section>
@endsection
