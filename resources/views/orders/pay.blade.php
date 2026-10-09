@extends('layouts.app')

@section('title', __('Ödəniş') . ', ' . __('Sifariş') . ' #' . $order->id . ', Nefis Şokolad Evi')
@section('robots', 'noindex, nofollow')

@section('page_style')
@include('orders._pay-style')
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
    @if(session('error'))
      <div class="alert alert-error" style="max-width:44rem; margin-inline:auto;">{{ session('error') }}</div>
    @endif

    @if($errors->any())
      <div class="alert alert-error" style="max-width:44rem; margin-inline:auto;">
        <ul style="margin:0; padding-left:1.1rem;">
          @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
      </div>
    @endif

    <div class="pay-grid">
      <div class="pay-card">
        @if($order->hasDiscount())
          <div style="display:flex; justify-content:space-between; gap:1rem; font-size:.9375rem; color:#15803d; margin-bottom:.4rem;">
            <span>{{ __('Promokod') }} {{ $order->promo_code }}</span>
            <b>−{{ \App\Support\Price::format($order->discount) }}</b>
          </div>
        @endif
        <div class="pay-total">
          <span>{{ __('Ödəniləcək məbləğ') }}</span>
          <b>{{ \App\Support\Price::format($order->total()) }}</b>
        </div>
        <ol class="pay-steps">
          @if($card)
            <li>{{ __('Kartla, Google Pay və ya Apple Pay ilə ödəyin — ödəniş dərhal təsdiqlənir.') }}</li>
            <li>{{ __('Yaxud hesablardan birinə köçürüb çeki bu səhifədə yükləyin.') }}</li>
          @else
            <li>{{ __('Aşağıdakı hesablardan birini seçin və məbləği köçürün.') }}</li>
            <li>{{ __('Çeki (qəbzi) bu səhifədə yükləyin.') }}</li>
          @endif
          <li>{{ __('Ödənişi yoxlayıb sifarişinizi təsdiqləyirik.') }}</li>
        </ol>
      </div>

      @if($order->paymentInFlight())
        {{-- He is at the bank, or has just come back and the bank's own word
             to us is still on its way. Offering the button now is offering a
             second charge on the same card. --}}
        <div class="pay-card pay-now">
          <label>{{ __('Ödəniş yoxlanılır') }}</label>
          <p class="pay-note" style="margin:.4rem 0 0;">{{ __('Bankdan cavab gözləyirik. Bu bir neçə dəqiqə çəkə bilər — səhifəni yeniləyin.') }}</p>
          <a class="btn btn-block" style="margin-top:.85rem;" href="{{ lroute('orders.pay', $order) }}">{{ __('Yenilə') }}</a>
          <p class="pay-note">{{ __('Kartınızdan pul çıxıbsa, ikinci dəfə ödəməyin — sifariş özü təsdiqlənəcək.') }}</p>
        </div>
      @elseif($card)
        <div class="pay-card pay-now">
          <label>{{ __('Kartla onlayn ödəniş') }}</label>
          <p class="pay-note" style="margin:.4rem 0 0;">{{ __('Visa, Mastercard, Google Pay və ya Apple Pay ilə indi ödəyin — çek göndərmək lazım deyil.') }}</p>
          <form method="POST" action="{{ lroute('orders.pay.card', $order) }}">
            @csrf
            <button type="submit" class="btn btn-primary btn-block pay-card-btn">
              <span class="cards" aria-hidden="true">VISA · MC · G PAY</span>
              {{ __('Kartla ödə') }} — {{ \App\Support\Price::format($order->total()) }}
            </button>
          </form>
          @if(\App\Support\Epoint::walletOffered())
          {{-- The same money, without leaving the shop: epoint's wallet widget
               opens in a window on this page, and on a telephone that has
               Google Pay or Apple Pay set up the button appears inside it.
               Nothing here confirms the payment — the signed callback does,
               exactly as it does for the card page. --}}
          <button type="button" class="btn btn-ghost btn-block pay-wallet-btn" id="pay-wallet"
                  data-url="{{ lroute('orders.pay.wallet', $order) }}"
                  data-wait="{{ __('Açılır…') }}"
                  data-label="{{ __('Google Pay / Apple Pay') }}">
            <span class="wallet-marks" aria-hidden="true">G Pay &nbsp;·&nbsp;  Pay</span>
            {{ __('Google Pay / Apple Pay') }}
          </button>
          <p class="pay-note pay-wallet-bad" id="pay-wallet-bad" hidden></p>
          @endif
          <p class="pay-note">{{ __('Ödəniş epoint.az-ın qorunan səhifəsində aparılır, kart məlumatları bizdə saxlanmır.') }}</p>
        </div>

        {{-- The window the widget lives in. Empty until it is asked for. --}}
        @if(\App\Support\Epoint::walletOffered())
        <div class="wallet-sheet" id="wallet-sheet" hidden>
          <div class="wallet-box">
            <div class="wallet-top">
              <span>{{ __('Ödəniş') }} — {{ \App\Support\Price::format($order->total()) }}</span>
              <button type="button" id="wallet-close" aria-label="{{ __('Bağla') }}">×</button>
            </div>
            <iframe id="wallet-frame" title="{{ __('Ödəniş') }}" allow="payment *"
                    referrerpolicy="origin"></iframe>
          </div>
        </div>
        @endif

        @if($offered)
          <div class="pay-or"><span>{{ __('və ya köçürmə ilə') }}</span></div>
        @endif
      @endif

      @if($offered && ! $order->paymentInFlight())
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
      @elseif(! $card)
        <div class="pay-card">
          <p class="pay-note">{{ __('Ödəniş hesabları hazırda əlçatan deyil. Sifarişiniz qeydə alınıb, sizinlə əlaqə saxlayacağıq.') }}</p>
        </div>
      @endif

      @if($offered)
      <div class="pay-card">
        <label>{{ __('Çek (qəbz)') }}</label>
        @if($order->payment_receipt)
          <p class="pay-sent" style="margin:.6rem 0 .9rem;">{{ __('Çek göndərilib') }}{{ $order->receipt_at ? ', ' . $order->receipt_at->format('d.m.Y H:i') : '' }}. Yoxlanılır.</p>
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
      @endif

      <div style="text-align:center;">
        <a href="{{ lroute('orders.index') }}" style="font-size:.9rem; text-decoration:underline; color:var(--cocoa-soft);">{{ __('Sifarişlərim') }}</a>
        @if($order->mayBeDroppedBy(auth()->user()))
          {{-- The bar at the top of every page brought him here; if he has
               changed his mind, this is where he says so. --}}
          <form method="POST" action="{{ lroute('orders.cancel', $order) }}" class="order-drop"
                onsubmit="return confirm(@js(__('Sifariş #:id ləğv edilsin?', ['id' => $order->id])))">
            @csrf
            <button type="submit">{{ __('Sifarişi ləğv et') }}</button>
          </form>
        @endif
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

  /* The wallet window.
     The address is asked for only when the button is pressed: every call
     opens a payment at the gateway, and a page that asked for one on load
     would open one for everybody who merely looked at it. */
  var walletBtn = document.getElementById('pay-wallet');
  var sheet = document.getElementById('wallet-sheet');
  var frame = document.getElementById('wallet-frame');
  var walletBad = document.getElementById('pay-wallet-bad');
  if (walletBtn && sheet && frame) {
    var shut = function(){
      sheet.hidden = true;
      frame.src = 'about:blank';
      document.body.style.overflow = '';
    };
    document.getElementById('wallet-close').addEventListener('click', function(){
      shut();
      /* He opened a payment and closed it. The shop has already stamped the
         order as in flight, so send him back to this page, where it says so
         and offers to wait rather than to pay again. */
      window.location.reload();
    });

    walletBtn.addEventListener('click', function(){
      walletBtn.disabled = true;
      walletBtn.textContent = walletBtn.dataset.wait;
      if (walletBad) walletBad.hidden = true;

      fetch(walletBtn.dataset.url, {
        method: 'POST',
        headers: {
          /* Every form on this page carries one; the layout has no meta tag. */
          'X-CSRF-TOKEN': (document.querySelector('input[name="_token"]') || {}).value || '',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
      }).then(function(r){ return r.json().then(function(b){ return { ok: r.ok, body: b }; }); })
        .then(function(a){
          if (!a.ok || !a.body.url) throw new Error(a.body.error || 'wallet');
          frame.src = a.body.url;
          sheet.hidden = false;
          document.body.style.overflow = 'hidden';
          walletBtn.textContent = walletBtn.dataset.label;
          walletBtn.disabled = false;
        })
        .catch(function(e){
          walletBtn.textContent = walletBtn.dataset.label;
          walletBtn.disabled = false;
          if (walletBad) {
            walletBad.textContent = (e && e.message && e.message !== 'wallet')
              ? e.message
              : @json(__('Google Pay indi işləmir. Kartla ödəyin və ya bir az sonra yoxlayın.'));
            walletBad.hidden = false;
          }
        });
    });

    /* What the widget says when it is finished. It is a hint, not a receipt:
       the order moves when the signed callback arrives, so all this does is
       stop the customer staring at a finished window. Messages from anywhere
       but the gateway are ignored. */
    window.addEventListener('message', function(event){
      if (!/(^|\.)epoint\.az$/.test((function(){ try { return new URL(event.origin).hostname; } catch (e) { return ''; } })())) return;
      var said = event.data;
      if (said && (said.status === 'success' || said.status === 'error')) {
        shut();
        window.location.reload();
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
