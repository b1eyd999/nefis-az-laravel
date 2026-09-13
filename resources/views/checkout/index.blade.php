@extends('layouts.app')

@section('title', 'Sifarişi Tamamla — Nefis Şokolad Evi')

@section('content')
<section class="page-hero" style="padding-bottom:0;">
  <div class="wrap">
    <span class="eyebrow" style="justify-content:center;">Son Addım</span>
    <h1>Sifarişi Tamamlayın</h1>
    <p class="lede" style="margin-inline:auto;">Sifarişiniz göndəriləcək, biz tezliklə sizinlə əlaqə saxlayıb təsdiqləyəcəyik.</p>
  </div>
</section>

<section>
  <div class="wrap" style="max-width:44rem;">
    @if($errors->any())
      <div class="alert alert-error">
        <ul style="margin:0; padding-left:1.1rem;">
          @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <div class="cart-list">
      @foreach($items as $item)
        <div class="cart-row">
          <div class="thumb"><img src="{{ asset('storage/' . $item['photo_path']) }}" alt="Yüklənmiş şəkil"></div>
          <div class="info">
            <h3>{{ $item['product']->name }}</h3>
            <p>
              @if($item['custom_text']) "{{ $item['custom_text'] }}" &middot; @endif
              {{ $item['quantity'] }} ədəd
            </p>
          </div>
        </div>
      @endforeach
    </div>

    <div class="auth-card">
      <form method="POST" action="{{ route('checkout.store') }}">
        @csrf
        <div class="field">
          <label for="contact_phone">Əlaqə Nömrəsi</label>
          <input type="tel" id="contact_phone" name="contact_phone" value="{{ old('contact_phone', auth()->user()->phone) }}" required placeholder="+994 XX XXX XX XX">
        </div>
        <div class="field">
          <label for="delivery_address">Çatdırılma Ünvanı</label>
          <input type="text" id="delivery_address" name="delivery_address" value="{{ old('delivery_address') }}" required placeholder="Şəhər, rayon, ünvan">
        </div>
        <div class="field">
          <label for="note">Əlavə Qeyd (istəyə bağlı)</label>
          <textarea id="note" name="note" rows="3">{{ old('note') }}</textarea>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Sifarişi Göndər</button>
      </form>
    </div>
  </div>
</section>
@endsection
