{{-- An order the customer left without paying.

     Placing the order empties the basket, so someone who opened the payment
     page and wandered off found an empty basket everywhere and thought the
     order was gone. This carries him back to it from any page — except the
     payment page itself, where it would only be in the way. --}}
@auth
  @php $unpaidOrder = \App\Models\Order::unpaidFor(auth()->user()); @endphp
  @if($unpaidOrder && ! request()->routeIs('*orders.pay'))
    <a class="unpaid-bar" href="{{ lroute('orders.pay', $unpaidOrder) }}">
      <span class="unpaid-dot" aria-hidden="true"></span>
      <span class="unpaid-text">
        <b>{{ __('Sifariş') }} #{{ $unpaidOrder->id }}</b>
        {{ __('ödəniş gözləyir') }} — {{ \App\Support\Price::format($unpaidOrder->total()) }}
      </span>
      <span class="unpaid-go">{{ __('Ödənişə keç') }} <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
    </a>
  @endif
@endauth
