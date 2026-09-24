@extends('layouts.app')

@section('title', __('Ödəniş') . ' — ' . __('Sifariş') . ' #' . $order->id . ' — Nefis Şokolad Evi')
@section('robots', 'noindex, nofollow')

@section('page_style')
  .pay-grid{ display:grid; gap:1.5rem; grid-template-columns:minmax(0,1fr); max-width:44rem; margin-inline:auto; }
  .pay-card{ border:1px solid var(--line); border-radius:1rem; background:var(--paper); padding:1.25rem 1.35rem; }
  .pay-total{ display:flex; justify-content:space-between; align-items:baseline; gap:1rem; }
  .pay-total b{ font-size:1.6rem; color:var(--gold-deep); font-variant-numeric:tabular-nums; }
  .pay-methods{ display:grid; grid-template-columns:repeat(auto-fit, minmax(10rem, 1fr)); gap:.75rem; margin:.75rem 0 0; }
  .pay-method{
    position:relative; display:flex; flex-direction:column; align-items:center; gap:.4rem; padding:1rem .75rem;
    border:1.5px solid var(--line); border-radius:.9rem; background:var(--paper); color:var(--cocoa); text-align:center;
    transition:border-color .2s, box-shadow .2s, background .2s;
  }
  .pay-method:hover{ border-color:var(--gold); }
  .pay-method .ico{ width:2.75rem; height:2.75rem; border-radius:.8rem; display:grid; place-items:center; background:var(--cream-2); font-size:1.15rem; font-weight:800; }
  .pay-method .name{ font-weight:700; font-size:.9375rem; }
  .pay-method .kind{ font-size:.75rem; color:var(--cocoa-soft); }
  .pay-method.on{ border-color:var(--gold); background:linear-gradient(160deg, rgba(214,163,90,.14), transparent 70%); box-shadow:0 0 0 3px var(--ring); }
  .pay-method.on .ico{ background:var(--gold); color:#fff; }
  .pay-method.on .name{ color:var(--gold-deep); }
  .pay-method .tick{ position:absolute; top:.5rem; right:.55rem; width:1.35rem; height:1.35rem; border-radius:50%;
    background:#16a34a; color:#fff; font-size:.8rem; display:grid; place-items:center; }
  .pay-account{ display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-top:.75rem;
    border:1px solid var(--line); border-radius:.75rem; padding:.8rem 1rem; background:var(--cream-2); }
  .pay-account .who{ font-size:.8125rem; color:var(--cocoa-soft); margin-bottom:.25rem; }
  /* breaks only between the groups of digits, so the number reads and copies by eye */
  .pay-account .num{ font-weight:700; font-size:clamp(.95rem, 4.4vw, 1.05rem); letter-spacing:.04em; font-variant-numeric:tabular-nums; overflow-wrap:break-word; }
  .pay-copy{ flex:none; width:2.4rem; height:2.4rem; border-radius:.6rem; border:1px solid var(--line); background:var(--paper); color:var(--cocoa); font-size:1rem; }
  .pay-copy:hover{ border-color:var(--gold); color:var(--gold-deep); }
  .pay-copy.done{ border-color:#16a34a; color:#16a34a; }
  .pay-note{ font-size:.875rem; color:var(--cocoa-soft); line-height:1.6; margin-top:.75rem; }
  .pay-steps{ margin:.5rem 0 0; padding-left:1.15rem; font-size:.9rem; color:var(--cocoa-soft); line-height:1.8; }
  .pay-file{ display:block; border:1.5px dashed var(--ring); border-radius:.9rem; padding:1.1rem; text-align:center; cursor:pointer; }
  .pay-file:hover{ border-color:var(--gold); }
  .pay-file input{ display:none; }
  .pay-file .name{ font-weight:600; margin-top:.35rem; word-break:break-all; }
  .pay-sent{ border:1px solid #16a34a; border-radius:.75rem; padding:.8rem 1rem; color:#16a34a; font-weight:600; font-size:.9rem; }
@endsection

@section('content')
<section class="page-hero" style="padding-bottom:0;">
  <div class="wrap">
    <span class="eyebrow" style="justify-content:center;">{{ __('Ödəniş') }}</span>
    <h1>{{ __('Sifariş') }} #{{ $order->id }}</h1>
  </div>
</section>

<section>
  <div class="wrap">
    @if($errors->any())
      <div class="alert alert-error" style="max-width:44rem; margin-inline:auto;">
        <ul style="margin:0; padding-left:1.1rem;">
          @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
      </div>
    @endif

    <div class="pay-grid">
      <div class="pay-card">
        <div class="pay-total">
          <span>{{ __('Ödəniləcək məbləğ') }}</span>
          <b>{{ \App\Support\Price::format($order->total()) }}</b>
        </div>
        <ol class="pay-steps">
          <li>{{ __('Aşağıdakı hesablardan birini seçin və məbləği köçürün.') }}</li>
          <li>{{ __('Çeki (qəbzi) bu səhifədə yükləyin.') }}</li>
          <li>{{ __('Ödənişi yoxlayıb sifarişinizi təsdiqləyirik.') }}</li>
        </ol>
      </div>

      @if($offered)
        <div class="pay-card">
          <label>{{ __('Ödəniş üsulu') }}</label>
          <div class="pay-methods">
            @foreach($offered as $type => $account)
              <form method="POST" action="{{ lroute('orders.pay.method', $order) }}">
                @csrf
                <input type="hidden" name="type" value="{{ $type }}">
                <button type="submit" class="pay-method{{ $order->payment_account_id === $account->id ? ' on' : '' }}">
                  @if($order->payment_account_id === $account->id)<span class="tick">✓</span>@endif
                  <span class="ico">{{ $type === 'm10' ? 'M10' : ($type === 'iban' ? 'AZ' : '💳') }}</span>
                  <span class="name">{{ $account->typeLabel() }}</span>
                  <span class="kind">{{ $account->typeNote() }}</span>
                </button>
              </form>
            @endforeach
          </div>

          @if($order->paymentAccount)
            <div class="pay-account">
              <div style="min-width:0;">
                <div class="who">{{ $order->paymentAccount->label }}</div>
                <div class="num" id="pay-number">{{ $order->paymentAccount->formatted() }}</div>
                @if($order->paymentAccount->note)<div class="who" style="margin:.35rem 0 0;">{{ $order->paymentAccount->note }}</div>@endif
              </div>
              <button type="button" class="pay-copy" id="pay-copy" aria-label="{{ __('Nömrəni kopyala') }}" title="{{ __('Kopyala') }}">⧉</button>
            </div>
          @endif

          @if($note)<p class="pay-note">{{ $note }}</p>@endif
        </div>
      @else
        <div class="pay-card">
          <p class="pay-note">{{ __('Ödəniş hesabları hazırda əlçatan deyil. Sifarişiniz qeydə alınıb — sizinlə əlaqə saxlayacağıq.') }}</p>
        </div>
      @endif

      <div class="pay-card">
        <label>{{ __('Çek (qəbz)') }}</label>
        @if($order->payment_receipt)
          <p class="pay-sent" style="margin:.6rem 0 .9rem;">{{ __('Çek göndərilib') }}{{ $order->receipt_at ? ' — ' . $order->receipt_at->format('d.m.Y H:i') : '' }}. Yoxlanılır.</p>
        @endif
        <form method="POST" action="{{ lroute('orders.pay.receipt', $order) }}" enctype="multipart/form-data">
          @csrf
          <label class="pay-file">
            <input type="file" name="receipt" id="receipt-input" accept="image/*,application/pdf" required>
            <span>📄 {{ __('Çeki seçmək üçün klikləyin') }}</span>
            <span class="name" id="receipt-name"></span>
          </label>
          <button type="submit" class="btn btn-primary btn-block" style="margin-top:1rem;">
            {{ $order->payment_receipt ? __('Çeki yenilə') : __('Çeki göndər') }}
          </button>
        </form>
        <p class="pay-note">{{ __('Şəkil (PNG, JPG, WEBP) və ya PDF, 8 MB-a qədər.') }}</p>
      </div>

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
