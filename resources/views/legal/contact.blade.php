{{-- The ways a customer can actually reach the shop, as the admin has them. --}}
<ul>
  @if(\App\Support\Contact::has())
    <li>{{ __('Telefon və WhatsApp') }}: <a href="{{ \App\Support\Contact::whatsapp() }}">{{ \App\Support\Contact::display() }}</a>@if(\App\Support\Contact::hours()) — {{ __(\App\Support\Contact::hours()) }}@endif</li>
  @endif
  <li>{{ __('Instagram') }}: <a href="{{ \App\Support\Seo::INSTAGRAM }}" target="_blank" rel="noopener">nefis.az</a></li>
  <li>{{ __('E-poçt') }}: <a href="mailto:{{ \App\Support\CustomerNotice::FROM }}">{{ \App\Support\CustomerNotice::FROM }}</a></li>
  <li>{{ __('Saytın küncündəki söhbət — açıq olanda ora yazdığınız birbaşa bizə düşür.') }}</li>
</ul>
