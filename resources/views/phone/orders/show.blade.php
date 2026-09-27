@extends('phone.layout')

@section('title', 'Sifariş #' . $order->id . ' — Nefis admin')
@section('heading', 'Sifariş #' . $order->id)
@section('sub', $order->created_at?->format('d.m.Y H:i'))
@section('tab', 'orders')
@section('back', route('phone.orders.index'))

@section('content')
  @php
    $pills = [
      'awaiting_payment' => 'pill-gray', 'payment_check' => 'pill-warn', 'pending' => 'pill-warn',
      'confirmed' => 'pill-info', 'ready' => 'pill-flame', 'completed' => 'pill-ok', 'cancelled' => 'pill-bad',
    ];
    $whatsapp = \App\Support\CustomerNotice::whatsapp($order);
  @endphp

  {{-- One step forward. Whatever else this order needs, this is the button he
       came to press; the rest of the statuses live behind "Statusu dəyiş". --}}
  <div class="ph-block">
    <div style="display:flex; align-items:center; gap:.5rem; margin-bottom:.7rem;">
      <span class="pill {{ $pills[$order->status] ?? 'pill-gray' }}">{{ $order->statusLabel() }}</span>
      @if($order->isRush())<span class="rush">Təcili · {{ \App\Support\Price::format($order->rush_fee ?? 0) }}</span>@endif
    </div>

    @if($order->awaitsPayment())
      <form method="POST" action="{{ route('phone.orders.pay', $order) }}" data-once>
        @csrf
        <button class="ph-btn ph-btn-primary" data-busy="Göndərilir…">Ödənişi təsdiqlə</button>
      </form>
    @elseif($order->status === 'pending')
      {{-- Paid and waiting for the shop to say yes: the next step is one tap. --}}
      <form method="POST" action="{{ route('phone.orders.status', $order) }}" data-once>
        @csrf
        <input type="hidden" name="status" value="confirmed">
        <button class="ph-btn ph-btn-primary" data-busy="Göndərilir…">Təsdiqlə</button>
      </form>
    @elseif($order->status === 'confirmed')
      <form method="POST" action="{{ route('phone.orders.status', $order) }}" data-once>
        @csrf
        <input type="hidden" name="status" value="ready">
        <button class="ph-btn ph-btn-primary" data-busy="Göndərilir…">Hazırdır</button>
      </form>
      @unless(\App\Support\Telegram::courierOn())
        <p class="ph-note">Kuryer qrupu qoşulmayıb — heç kimə bildiriş getməyəcək.</p>
      @endunless
    @endif

    <button class="ph-btn" style="margin-top:.5rem;" data-sheet="status-sheet">Statusu dəyiş</button>
  </div>

  {{-- The one thing the whole shop works to. --}}
  <div class="ph-block">
    <h2>Nə vaxta</h2>
    <div class="ph-big">{{ \App\Support\DeliveryTime::day($order->delivery_date) }}</div>
    @if($order->delivery_slot)<div>{{ $order->delivery_slot }}</div>@endif
  </div>

  <div class="ph-block">
    <h2>Müştəri</h2>
    <div style="font-weight:700;">{{ $order->user?->name ?? $order->recipient_name ?? '—' }}</div>
    @if($order->contact_phone)
      <div style="margin-top:.35rem;"><a class="ph-link" href="tel:{{ preg_replace('~[^0-9+]~', '', $order->contact_phone) }}">{{ $order->contact_phone }}</a></div>
    @endif
    @if($whatsapp)
      <a class="ph-btn ph-btn-sm" style="margin-top:.5rem;" href="{{ $whatsapp }}" target="_blank" rel="noopener">WhatsApp-a yaz ↗</a>
    @endif
  </div>

  <div class="ph-block">
    <h2>Çatdırılma</h2>
    <div style="font-weight:700;">{{ $order->delivery_name ?? 'Seçilməyib' }}</div>
    @if($summary = $order->deliverySummary())<div class="ph-note" style="margin:.2rem 0 0;">{{ $summary }}</div>@endif
    @if($map = $order->mapUrl())
      <div style="margin-top:.4rem;"><a class="ph-link" href="{{ $map }}" target="_blank" rel="noopener">Xəritədə aç ↗</a></div>
    @endif
    @if($order->courier_chat_id)
      <div class="ph-note" style="margin:.4rem 0 0;">
        {{ $order->courier_name ? $order->courier_name . ' · ' . $order->courier_taken_at?->format('d.m H:i') : 'Hələ kimsə götürməyib' }}
      </div>
    @endif
  </div>

  @if($order->note)
    <div class="ph-block">
      <h2>Müştərinin qeydi</h2>
      <div style="white-space:pre-wrap;">{{ $order->note }}</div>
    </div>
  @endif

  {{-- What was ordered, and what the customer sent for it. The same block the
       desktop panel shows, so the two never say different things. --}}
  @foreach($order->items as $item)
    <div class="ph-block">
      <h2>{{ $item->product?->name ?? $item->product_name ?? 'Silinmiş məhsul' }}</h2>
      <div class="ph-line">
        <span>{{ $item->quantity }} ədəd</span>
        <b>{{ \App\Support\Price::format($item->unitPrice() * $item->quantity) }}</b>
      </div>
      @if($item->chocolate_name)<div class="ph-note" style="margin:0;">🍫 {{ $item->chocolate_name }}</div>@endif
      @if($item->wrapping_name)<div class="ph-note" style="margin:0;">🎁 {{ $item->wrapping_name }}</div>@endif
      @include('filament.order-item-fields', ['getRecord' => fn () => $item])
    </div>
  @endforeach

  <div class="ph-block">
    <h2>Məbləğ</h2>
    <div class="ph-line"><span>Məhsullar</span><b>{{ \App\Support\Price::format($order->itemsTotal()) }}</b></div>
    <div class="ph-line"><span>Çatdırılma</span><b>{{ \App\Support\Price::format($order->delivery_price ?? 0) }}</b></div>
    @if($order->isRush())
      <div class="ph-line"><span>Təcili</span><b>{{ \App\Support\Price::format($order->rush_fee ?? 0) }}</b></div>
    @endif
    <div class="ph-line ph-sum"><span>Cəmi</span><b>{{ \App\Support\Price::format($order->total()) }}</b></div>
  </div>

  @if($order->payment_account_id || $order->payment_receipt)
    <div class="ph-block">
      <h2>Ödəniş</h2>
      @if($order->paymentAccount)
        <div>{{ $order->paymentAccount->typeLabel() }} · {{ $order->paymentAccount->label }}</div>
        <div class="ph-note" style="margin:.1rem 0 0;">{{ $order->paymentAccount->masked() }}</div>
      @endif
      <div class="ph-note" style="margin:.35rem 0 0;">
        Çek: {{ $order->receipt_at?->format('d.m.Y H:i') ?? ($order->payment_confirmed_at ? 'çeksiz təsdiqlənib' : 'hələ yox') }}
      </div>
      @if($receipt = $order->receiptUrl())
        <a class="ph-btn ph-btn-sm" style="margin-top:.5rem;" href="{{ $receipt }}" target="_blank" rel="noopener">Çeki aç ↗</a>
      @endif
    </div>
  @endif

  {{-- Every status, one tap each. Cancelling asks again, because going in and
       out of it writes real stock movements and writes to the customer. --}}
  <dialog class="sheet" id="status-sheet">
    <div class="sheet-in">
      <div class="sheet-grip"></div>
      <h2>Sifariş #{{ $order->id }} — status</h2>
      <div class="sheet-rows">
        @foreach(\App\Models\Order::STATUSES as $key => $label)
          <form method="POST" action="{{ route('phone.orders.status', $order) }}" data-once
                @if($key === 'cancelled') onsubmit="return confirm('Sifariş #{{ $order->id }} ləğv edilsin? Materiallar anbara qaytarılacaq və müştəriyə bildiriş gedəcək.')" @endif>
            @csrf
            <input type="hidden" name="status" value="{{ $key }}">
            <button class="sheet-row {{ $order->status === $key ? 'on' : '' }} {{ $key === 'cancelled' ? 'danger' : '' }}"
                    @if($order->status === $key) disabled @endif>
              <span>{{ $label }}</span>
              @if($order->status === $key)<span class="ph-note" style="margin:0;">indiki</span>@endif
            </button>
          </form>
        @endforeach
      </div>
      <button class="ph-btn" style="margin-top:.8rem;" data-close-sheet>Bağla</button>
    </div>
  </dialog>
@endsection
