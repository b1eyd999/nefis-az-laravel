@extends('layouts.app')

@section('title', __('Səbət') . ', Nefis Şokolad Evi')
@section('robots', 'noindex, nofollow')

@section('page_style')
  /* The shop's own corner of the basket page, and the address it hands out. */
  .handoff-make{ margin-top:1rem; padding:.9rem 1rem; border:1px dashed var(--line); border-radius:var(--radius);
    background:var(--cream); }
  .handoff-make b{ display:block; font-size:.9375rem; }
  .handoff-make p{ font-size:.8125rem; line-height:1.55; color:var(--cocoa-soft); margin:.3rem 0 .7rem; }
  .handoff-make input{ width:100%; margin-bottom:.6rem; }
  .handoff-made{ margin-bottom:1.2rem; padding:1rem 1.1rem; border:1px solid var(--line);
    border-radius:var(--radius); background:var(--paper); box-shadow:var(--shadow-sm); }
  .handoff-made b{ display:block; }
  .handoff-made .who{ font-size:.8125rem; color:var(--cocoa-soft); margin:.2rem 0 0; }
  .handoff-made input{ width:100%; margin:.7rem 0; font-size:.8125rem; }
  .handoff-made .row{ display:flex; gap:.6rem; flex-wrap:wrap; }
  .handoff-made small{ display:block; margin-top:.6rem; font-size:.78rem; color:var(--cocoa-soft); }
@endsection

@section('content')
<section class="page-hero" style="padding-bottom:0;">
  <div class="wrap">
    <span class="eyebrow" style="justify-content:center;">{{ __('Səbətiniz') }}</span>
    <h1>{{ __('Sifariş Səbəti') }}</h1>
  </div>
</section>

<section>
  <div class="wrap" style="max-width:52rem;">
    @if(session('status'))
      <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if($errors->has('handoff'))
      <div class="alert alert-error">{{ $errors->first('handoff') }}</div>
    @endif

    {{-- The basket has just been turned into an address to send. It is shown
         here rather than in the panel because this is where he was standing
         when he made it, and because his own basket is empty again now. --}}
    @php $made = session('handoff.made') ? \App\Models\CartHandoff::find(session('handoff.made')) : null; @endphp
    @if($made)
      <div class="handoff-made">
        <b>{{ __('Səbət hazırdır. Linki müştəriyə göndərin.') }}</b>
        @if($made->note)<p class="who">{{ $made->note }}</p>@endif
        <input type="text" readonly value="{{ $made->url() }}" id="handoff-url"
               onclick="this.select()" aria-label="{{ __('Səbətin linki') }}">
        <div class="row">
          <button type="button" class="btn btn-primary" id="handoff-copy">{{ __('Linki kopyala') }}</button>
          <a class="btn btn-ghost" href="https://wa.me/?text={{ urlencode($made->url()) }}"
             target="_blank" rel="noopener">WhatsApp</a>
        </div>
        <small>{{ __('Link :days gün işləyir. Müştəri onu açanda səbət onun qarşısına çıxır.', ['days' => \App\Models\CartHandoff::DAYS]) }}</small>
      </div>
    @endif

    @if($items->isEmpty())
      <div class="cart-empty">
        <div class="ico">@include('partials.cart-icon', ['id' => 'empty'])</div>
        <p>{{ __('Səbətiniz hələ boşdur.') }}</p>
        <div style="margin-top:1.5rem;">
          <a href="{{ lroute('designs.index') }}" class="btn btn-primary">{{ __('Dizaynlara Bax') }}</a>
        </div>
      </div>
    @else
      <div class="cart-list">
        @foreach($items as $item)
          @php $texts = array_filter($item['custom_texts'] ?? []); $isLetter = \App\Support\Cart::isLetter($item); $isLive = \App\Support\Cart::isLive($item); @endphp
          <div class="cart-row">
            <div class="thumb">
              @if($isLive)
                <img src="{{ \App\Support\Media::url($item['ar']['image'] ?? null) }}" alt="{{ __('Canlı şəkil') }}">
              @elseif($isLetter)
                <div class="thumb-polaroid">@include('partials.polaroid', ['photo' => \App\Support\Media::url($item['letter']['photo'] ?? null), 'text' => $item['letter']['text'] ?? null])</div>
              @elseif(! empty($item['photo_paths']))
                <img src="{{ \App\Support\Media::url($item['photo_paths'][0]) }}" alt="{{ __('Yüklənmiş şəkil') }}">
                @if(count($item['photo_paths']) > 1)
                  <span class="thumb-more">+{{ count($item['photo_paths']) - 1 }}</span>
                @endif
              @else
                <img src="{{ \App\Support\Media::url($item['product']->catalogImage()) }}" alt="{{ $item['product']->name }}">
              @endif
            </div>
            <div class="info">
              <h3>{{ $isLive ? __('Canlı şəkil') : ($isLetter ? __('Polaroid məktub') : $item['product']->name) }}</h3>
              <p>
                @if($texts) "{{ implode('" · "', $texts) }}" &middot; @endif
                {{ __(':count ədəd', ['count' => $item['quantity']]) }}
                @php $unit = \App\Support\Cart::unitPrice($item, $item['product']); @endphp
                @if($unit > 0)
                  &middot; {{ \App\Support\Price::format($unit * $item['quantity']) }}
                @endif
              </p>
              @if(! empty($item['chocolate']))
                <p style="margin-top:.2rem;">🍫 {{ $item['chocolate']['name'] }} &middot; {{ \App\Support\Price::format($item['chocolate']['price']) }}</p>
              @endif
              @if(! empty($item['wrapping']))
                <p style="margin-top:.2rem;">🎁 {{ __('Qablaşdırma') }}: {{ $item['wrapping']['name'] }} &middot; {{ \App\Support\Price::format($item['wrapping']['price']) }}</p>
              @endif
              @if($isLive)
                <p style="margin-top:.2rem;">🎬 {{ __('Şəkil və video, QR kodla çap olunur') }}</p>
              @elseif(! empty($item['ar']))
                <p style="margin-top:.2rem;">🎬 {{ __('Canlı şəkil (AR)') }} &middot; {{ \App\Support\Price::format($item['ar']['price']) }}</p>
              @endif
              @if(! empty($item['spotify']))
                {{-- Free, so no price: the song is part of the box, not an extra. --}}
                <p style="margin-top:.2rem;">🎧 {{ \App\Support\SpotifyCode::kindLabel($item['spotify']) }} &middot;
                  <a href="{{ \App\Support\SpotifyCode::link($item['spotify']) }}" target="_blank" rel="noopener"
                     style="text-decoration:underline;">{{ __('Spotify-da yoxla') }}</a></p>
              @endif
              @if(! empty($item['letter']))
                <p style="margin-top:.2rem;">💌 {{ $isLetter ? '' : __('Polaroid məktub') . ' · ' }}{{ \Illuminate\Support\Str::limit(str_replace("\n", ' ', $item['letter']['text'] ?? ''), 60) ?: __('şəkilli') }}
                  @unless($isLetter) &middot; {{ \App\Support\Price::format($item['letter']['price']) }} @endunless</p>
              @endif
            </div>
            <form method="POST" action="{{ lroute('cart.remove', $item['id']) }}">
              @csrf
              @method('DELETE')
              <button type="submit" class="remove-btn">{{ __('Sil') }}</button>
            </form>
          </div>
        @endforeach
      </div>

      <div class="cart-summary">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
          <span style="font-weight:700; font-size:1.125rem;">{{ __('Cəmi') }}</span>
          <span style="font-weight:700; font-size:1.125rem; color:var(--gold-deep);">
            @php
              $total = $items->sum(fn($i) => \App\Support\Cart::unitPrice($i, $i['product']) * $i['quantity']);
              // Asked for on the design page, paid once for the whole order.
              $rush = \App\Support\Cart::rush() ? \App\Support\DeliveryTime::rushFee() : 0;
            @endphp
            {{ $total > 0 ? \App\Support\Price::format($total + $rush) : __('Qiymət sorğu ilə') }}
          </span>
        </div>
        @if($rush > 0)
          <p style="display:flex; justify-content:space-between; gap:1rem; font-size:.875rem; color:var(--flame-2); font-weight:600; margin:.35rem 0 .75rem;">
            <span>⚡ {{ __('Təcili hazırlansın') }}</span><span>+{{ \App\Support\Price::format($rush) }}</span>
          </p>
        @endif
        <a href="{{ lroute('checkout.index') }}" class="btn btn-primary btn-block">{{ __('Sifarişi Tamamla') }}</a>
        {{-- The cart keeps what is in it: one tap back to the designs. --}}
        <a href="{{ lroute('designs.index') }}" class="btn btn-ghost btn-block" style="margin-top:.6rem;">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>
          {{ __('Alış-verişə davam et') }}
        </a>

        {{-- Only the shop sees this. Most conversations start in Instagram,
             and some customers never get through the design page; the owner
             builds the box here himself and sends one address. --}}
        @if(auth()->user()?->isAdmin())
          <form method="POST" action="{{ lroute('cart.handoff.store') }}" class="handoff-make">
            @csrf
            <b>{{ __('Müştəri üçün hazır səbət') }}</b>
            <p>{{ __('Bu səbəti linkə çevirin. Müştəri linki açır, səbət onun qarşısına çıxır — ünvanı və ödənişi özü edir.') }}</p>
            <input type="text" name="note" maxlength="120" value="{{ old('note') }}"
                   placeholder="{{ __('Kimin üçün? Məs. Aygün, Instagram') }}">
            <button type="submit" class="btn btn-ghost btn-block">{{ __('Linki yarat') }}</button>
          </form>
        @endif
      </div>
    @endif
  </div>
</section>
@endsection

@section('page_script')
<script>
  (function () {
    var button = document.getElementById('handoff-copy');
    var field = document.getElementById('handoff-url');
    if (!button || !field) return;
    button.addEventListener('click', function () {
      field.select();
      var done = function () { button.textContent = @json(__('Kopyalandı ✓')); };
      /* The clipboard is refused on an insecure page and in some browsers;
         the old command still works there, and the field is selected
         either way so it can be copied by hand. */
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(field.value).then(done, function () {
          try { document.execCommand('copy'); done(); } catch (e) {}
        });
      } else {
        try { document.execCommand('copy'); done(); } catch (e) {}
      }
    });
  })();
</script>
@endsection
