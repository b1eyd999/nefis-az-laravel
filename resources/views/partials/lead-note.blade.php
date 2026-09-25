{{-- How long a box takes, said before the customer starts, and what it costs
     to be made before the others. The fee is the owner's, and a fee of 0
     means the shop is not offering the queue jump at all. --}}
@php
  $leadDays = \App\Support\DeliveryTime::leadDays();
  $rushFee = \App\Support\DeliveryTime::rushFee();
@endphp
@if($leadDays > 0 || $rushFee > 0)
  <div class="lead-note">
    <span class="ln-ico" aria-hidden="true">⏱</span>
    <p>
      @if($leadDays > 0)
        <b>{{ __('Sifariş :days gün ərzində hazırlanır.', ['days' => $leadDays]) }}</b>
      @endif
      @if($rushFee > 0)
        {{ __('Tezləşdirmək istəsəniz, əlavə :fee ödəniş göndərməlisiniz.', ['fee' => \App\Support\Price::format($rushFee)]) }}
        @if(\App\Support\Contact::has())
          <a href="{{ \App\Support\Contact::whatsapp() }}" target="_blank" rel="noopener">{{ __('Sifarişdən sonra bizə yazın.') }}</a>
        @else
          {{ __('Sifarişdən sonra bizə yazın.') }}
        @endif
      @endif
    </p>
  </div>
@endif
