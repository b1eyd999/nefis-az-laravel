@extends('layouts.app')

@section('title', __('Əlavə ödəniş') . ', ' . __('Sifariş') . ' #' . $order->id . ', Nefis Şokolad Evi')
@section('robots', 'noindex, nofollow')

@section('page_style')
@include('orders._pay-style')
  .pay-ledger{ display:grid; gap:.45rem; margin:.25rem 0 .9rem; font-size:.9375rem; color:var(--cocoa-soft); }
  .pay-ledger div{ display:flex; justify-content:space-between; gap:1rem; }
  .pay-ledger .what{ color:var(--cocoa); }
  .pay-ledger b{ font-variant-numeric:tabular-nums; }
  .pay-why{ border-left:3px solid var(--gold); padding:.15rem 0 .15rem .8rem; margin:.1rem 0 .9rem; color:var(--cocoa); }
@endsection

@section('content')
<section class="page-hero" style="padding-bottom:0;">
  <div class="wrap">
    <span class="eyebrow" style="justify-content:center;">{{ __('Əlavə ödəniş') }}</span>
    <h1>{{ __('Sifariş') }} #{{ $order->id }}</h1>
  </div>
</section>

<section>
  <div class="wrap">
    @if(session('error'))
      <div class="alert alert-error" style="max-width:44rem; margin-inline:auto;">{{ session('error') }}</div>
    @endif
    @if(session('status'))
      <div class="alert" style="max-width:44rem; margin-inline:auto;">{{ session('status') }}</div>
    @endif

    @if($errors->any())
      <div class="alert alert-error" style="max-width:44rem; margin-inline:auto;">
        <ul style="margin:0; padding-left:1.1rem;">
          @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
      </div>
    @endif

    <div class="pay-grid">
      {{-- What changed and what it comes to. The customer has already paid
           once, so the page says plainly what that was and what is left. --}}
      <div class="pay-card">
        @if($adjustment->reason)
          <div class="pay-why">{{ $adjustment->reason }}</div>
        @endif
        <div class="pay-ledger">
          <div><span class="what">{{ __('Sifarişin yeni məbləği') }}</span><b>{{ \App\Support\Price::format($order->total()) }}</b></div>
          <div><span class="what">{{ __('Artıq ödənilib') }}</span><b>−{{ \App\Support\Price::format($order->paidSoFar()) }}</b></div>
        </div>
        <div class="pay-total" style="border-top:1px solid var(--line); padding-top:.7rem;">
          <span>{{ __('Əlavə ödəniləcək') }}</span>
          <b>{{ \App\Support\Price::format((float) $adjustment->amount) }}</b>
        </div>
      </div>

      @if($adjustment->paymentInFlight())
        <div class="pay-card pay-now">
          <label>{{ __('Ödəniş yoxlanılır') }}</label>
          <p class="pay-note" style="margin:.4rem 0 0;">{{ __('Bankdan cavab gözləyirik. Bu bir neçə dəqiqə çəkə bilər — səhifəni yeniləyin.') }}</p>
          <a class="btn btn-block" style="margin-top:.85rem;" href="{{ lroute('orders.extra.show', [$order, $adjustment]) }}">{{ __('Yenilə') }}</a>
          <p class="pay-note">{{ __('Kartınızdan pul çıxıbsa, ikinci dəfə ödəməyin — sifariş özü təsdiqlənəcək.') }}</p>
        </div>
      @elseif($card)
        <div class="pay-card pay-now">
          <label>{{ __('Kartla onlayn ödəniş') }}</label>
          <p class="pay-note" style="margin:.4rem 0 0;">{{ __('Visa, Mastercard, Google Pay və ya Apple Pay ilə indi ödəyin — çek göndərmək lazım deyil.') }}</p>
          <form method="POST" action="{{ lroute('orders.extra.card', [$order, $adjustment]) }}">
            @csrf
            <button type="submit" class="btn btn-primary btn-block pay-card-btn">
              <span class="cards" aria-hidden="true">VISA · MC · G PAY</span>
              {{ __('Kartla ödə') }} — {{ \App\Support\Price::format((float) $adjustment->amount) }}
            </button>
          </form>
          <p class="pay-note">{{ __('Ödəniş epoint.az-ın qorunan səhifəsində aparılır, kart məlumatları bizdə saxlanmır.') }}</p>
        </div>

        @if($account)
          <div class="pay-or"><span>{{ __('və ya köçürmə ilə') }}</span></div>
        @endif
      @endif

      @if($account && ! $adjustment->paymentInFlight())
        <div class="pay-card">
          <label>{{ __('Köçürmə üçün hesab') }}</label>
          <div class="pay-account">
            <div style="min-width:0;">
              <div class="who">{{ $account->label }}</div>
              <div class="num" id="pay-number">{{ $account->formatted() }}</div>
              @if($account->note)<div class="who" style="margin:.35rem 0 0;">{{ $account->note }}</div>@endif
            </div>
            <button type="button" class="pay-copy" id="pay-copy" aria-label="{{ __('Nömrəni kopyala') }}" title="{{ __('Kopyala') }}">⧉</button>
          </div>
          @if($note)<p class="pay-note">{{ $note }}</p>@endif
        </div>

        <div class="pay-card">
          <label>{{ __('Çek (qəbz)') }}</label>
          @if($adjustment->payment_receipt)
            <p class="pay-sent" style="margin:.6rem 0 .9rem;">{{ __('Çek göndərilib') }}{{ $adjustment->receipt_at ? ', ' . $adjustment->receipt_at->format('d.m.Y H:i') : '' }}. {{ __('Yoxlanılır.') }}</p>
          @endif
          <form method="POST" action="{{ lroute('orders.extra.receipt', [$order, $adjustment]) }}" enctype="multipart/form-data">
            @csrf
            <label class="pay-file">
              <input type="file" name="receipt" id="receipt-input" accept="image/*,application/pdf" required>
              <span>📄 {{ __('Çeki seçmək üçün klikləyin') }}</span>
              <span class="name" id="receipt-name"></span>
            </label>
            <button type="submit" class="btn btn-primary btn-block" style="margin-top:1rem;">
              {{ $adjustment->payment_receipt ? __('Çeki yenilə') : __('Çeki göndər') }}
            </button>
          </form>
          <p class="pay-note">{{ __('Şəkil (PNG, JPG, WEBP) və ya PDF, 8 MB-a qədər.') }}</p>
        </div>
      @endif

      <div style="text-align:center;">
        <a href="{{ lroute('orders.index') }}" style="font-size:.9rem; text-decoration:underline; color:var(--cocoa-soft);">{{ __('Sifarişlərim') }}</a>
      </div>
    </div>
  </div>
</section>
@endsection

@section('page_script')
<script>
(function(){
  var copy = document.getElementById('pay-copy');
  var number = document.getElementById('pay-number');
  if (copy && number) {
    copy.addEventListener('click', function(){
      var text = number.textContent.trim();
      var done = function(){ copy.textContent = '✓'; copy.classList.add('done'); setTimeout(function(){ copy.textContent = '⧉'; copy.classList.remove('done'); }, 1600); };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(done).catch(function(){});
      } else {
        var t = document.createElement('textarea');
        t.value = text; document.body.appendChild(t); t.select();
        try { document.execCommand('copy'); done(); } catch (e) {}
        t.remove();
      }
    });
  }

  var input = document.getElementById('receipt-input');
  var name = document.getElementById('receipt-name');
  if (input && name) {
    input.addEventListener('change', function(){
      name.textContent = input.files && input.files[0] ? input.files[0].name : '';
    });
  }
})();
</script>
@endsection
