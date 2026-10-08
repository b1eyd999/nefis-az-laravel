<!DOCTYPE html>
<html lang="{{ \App\Support\Locale::tag() }}">
<head>
<meta charset="UTF-8">
{{-- Runs before any styles paint, so a visitor who chose a theme never sees
     a flash of the other one. With no choice stored the shop is light. --}}
<script>
  try {
    var t = localStorage.getItem('nefis-theme');
    if (t === 'dark' || t === 'light') document.documentElement.setAttribute('data-theme', t);
  } catch (e) {}
</script>
{{-- The artwork is served from a Yandex Disk share, which rejects any request
     that carries a referer from another site. --}}
<meta name="referrer" content="@yield('referrer', 'no-referrer')">
<meta name="viewport" content="width=device-width, initial-scale=1">
{{-- Search engines and link previews: each page names itself (canonical), and
     pages set their own title, description, picture and robots rule.
     yieldContent() hands these back already escaped, hence {!! !!}. --}}
@php
  $seoTitle = trim($__env->yieldContent('title', __('Nefis, Şəkilli Şokolad Qutuları və Fərdi Hədiyyələr')));
  $seoDescription = trim($__env->yieldContent('meta_description', __('Öz şəkliniz və sözlərinizlə fərdi şokolad qutusu, ad günü, sevgiliyə, 8 Mart və hər münasibətə unudulmaz hədiyyə. Bakıda və bütün Azərbaycanda çatdırılma.')));
  $seoUrl = e(\App\Support\Seo::canonical());
  $seoImage = trim($__env->yieldContent('og_image'));
@endphp
<title>{!! $seoTitle !!}</title>
<meta name="description" content="{!! $seoDescription !!}">
@hasSection('robots')
<meta name="robots" content="@yield('robots')">
@elseif(! \App\Support\Locale::isPublished())
{{-- This language is still being written: readable by address, but not kept. --}}
<meta name="robots" content="noindex, follow">
@endif
<link rel="canonical" href="{!! $seoUrl !!}">
<meta name="theme-color" content="#FBF4EA">
<meta name="theme-color" content="#17110D" media="(prefers-color-scheme: dark)">
<meta property="og:type" content="@yield('og_type', 'website')">
<meta property="og:locale" content="{{ ['az' => 'az_AZ', 'ru' => 'ru_RU', 'en' => 'en_US'][\App\Support\Locale::current()] }}">
{{-- The same page in the other two languages, for search engines. --}}
@unless(trim($__env->yieldContent('own_hreflang')))
@foreach(\App\Support\Locale::published() as $__loc)
<link rel="alternate" hreflang="{{ $__loc }}" href="{{ \App\Support\Locale::switchUrl($__loc) }}">
@endforeach
<link rel="alternate" hreflang="x-default" href="{{ \App\Support\Locale::switchUrl('az') }}">
@endunless
<meta property="og:site_name" content="Nefis Şokolad Evi">
<meta property="og:title" content="{!! $seoTitle !!}">
<meta property="og:description" content="{!! $seoDescription !!}">
<meta property="og:url" content="{!! $seoUrl !!}">
@if($seoImage)
<meta property="og:image" content="{!! $seoImage !!}">
@else
<meta property="og:image" content="{{ asset('images/og-nefis.jpg') }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
@endif
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{!! $seoTitle !!}">
<meta name="twitter:description" content="{!! $seoDescription !!}">
<meta name="twitter:image" content="{!! $seoImage ?: e(asset('images/og-nefis.jpg')) !!}">
{{ \App\Support\Seo::verificationTags() }}
{{ \App\Support\Seo::analyticsTag() }}
{{ \App\Support\Analytics::script() }}
@stack('head')
@stack('jsonld')
{{-- The shop's own mark. A data: icon is never fetched by search engines,
     so Google drew the blank globe next to the site in its results. --}}
<link rel="icon" href="/favicon.ico" sizes="32x32">
<link rel="icon" type="image/png" sizes="96x96" href="{{ \App\Support\Assets::url('images/icon-96.png') }}">
<link rel="icon" type="image/png" sizes="192x192" href="{{ \App\Support\Assets::url('images/icon-192.png') }}">
<link rel="apple-touch-icon" href="{{ \App\Support\Assets::url('images/apple-touch-icon.png') }}">
{{-- What makes the shop an app on a phone: its own icon on the home screen,
     its own window with no browser bar, its own opening screen. --}}
@include('partials.pwa-head')
{{-- The type is the phone's own — San Francisco on Apple — and Inter stands
     in for it everywhere else, so that one family is all a page loads. The
     twenty display faces belong to the box designs and the polaroid letter;
     the pages that draw those ask for them themselves, and every other page
     stops waiting on 50 KB it never paints. --}}
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/site.css') }}?v={{ \App\Support\Assets::version('css/site.css') }}">
<style>
  @yield('page_style')
</style>
</head>
<body>
{{-- The "Məhsullar" menu as the owner arranged it in admin → Menyu: which
     lines show, what each carries beside it, in what order, and which of them
     he has pulled out of the list to stand on the bar itself. --}}
@php $navMenu = \App\Support\Menu::shown(); @endphp
@php ['drop' => $navDrop, 'top' => $navTop] = \App\Support\Menu::split($navMenu); @endphp
@php $navGifts = \App\Models\GiftPage::shown()->inLocale(\App\Support\Locale::current())->get(); @endphp


<a href="#main" class="skip-link">{{ __('Əsas məzmuna keç') }}</a>

{{-- The corner the eye goes to when it wants to ask something: the shop's
     own chat, and its Instagram under it. --}}
<div class="float-stack">
  @if(\App\Support\ChatBot::enabled())
    @include('partials.chat')
  @endif
  <a href="https://www.instagram.com/nefis.az/" target="_blank" rel="noopener" class="float-cta" id="float-cta" aria-label="{{ __('Instagramda yazın') }}">
    <span class="ico">@include('partials.instagram-icon')</span><span class="txt">{{ __('Instagramda Yaz') }}</span>
  </a>
</div>

{{-- What other people are ordering, in the same corner but above the buttons.
     Not while someone is paying: the basket and the checkout are left alone. --}}
@if(\App\Models\Setting::get(\App\Models\Setting::SALE_TOASTS) === '1' && ! request()->routeIs('*cart.*', '*checkout*', '*orders.*'))
  @include('partials.sale-toasts')
@endif

<div class="blobs" aria-hidden="true">
  <div class="blob" style="width:26rem;height:26rem;background:var(--gold);top:-8rem;right:-6rem;"></div>
  <div class="blob b2" style="width:20rem;height:20rem;background:var(--terracotta);top:20rem;left:-8rem;opacity:.28;"></div>
  <div class="blob b3" style="width:24rem;height:24rem;background:var(--flame);top:34rem;right:-10rem;opacity:.16;"></div>
</div>

<header id="site-header">
  <div class="wrap">
    <a href="{{ lroute('home') }}" class="brand"><img src="/images/logo.svg" alt="Nefis"></a>
    <nav class="primary" aria-label="{{ __('Əsas menyu') }}">
      {{-- Everything for sale under one word, so the bar stays short however
           many there are — all but the lines the owner has pulled out, which
           stand beside it in their own right. --}}
      @if(count($navDrop) > 1)
        <div class="nav-drop">
          <button type="button" aria-expanded="false" aria-haspopup="true">
            {{ __('Məhsullar') }}
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
          </button>
          <div class="nav-panel">
            @foreach($navDrop as $item)
              <a class="nav-item" href="{{ lroute($item['route']) }}">
                <span class="ni-ico">{{ $item['icon'] }}</span>
                <span>
                  <b>{{ \App\Support\Menu::title($item) }}@if($item['badge'])<i class="ni-badge">{{ __($item['badge']) }}</i>@endif</b>
                  <small>{{ \App\Support\Menu::note($item, $navGifts) }}</small>
                </span>
              </a>
            @endforeach
          </div>
        </div>
      @elseif(count($navDrop) === 1)
        {{-- One line left in the list is not a list; it is a link. --}}
        <a href="{{ lroute($navDrop[0]['route']) }}">{{ \App\Support\Menu::title($navDrop[0]) }}@if($navDrop[0]['badge'])<i class="ni-badge">{{ __($navDrop[0]['badge']) }}</i>@endif</a>
      @endif
      @foreach($navTop as $item)
        <a class="nav-top" href="{{ lroute($item['route']) }}">{{ \App\Support\Menu::title($item) }}@if($item['badge'])<i class="ni-badge">{{ __($item['badge']) }}</i>@endif</a>
      @endforeach
      <a href="{{ lroute('home') }}#how">{{ __('Necə İşləyir') }}</a>
      <a href="{{ lroute('home') }}#faq">{{ __('Suallar') }}</a>
    </nav>
    <div class="header-actions">
      <button type="button" class="icon-btn theme-btn" id="theme-toggle" aria-label="{{ __('Qaranlıq rejim') }}" title="{{ __('Qaranlıq / işıqlı rejim') }}">
        <svg class="moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
        <svg class="sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4.5"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
      </button>
      {{-- The same page in another language: one tap, nothing else changes. --}}
      <div class="nav-drop lang-drop" @if(count(\App\Support\Locale::published()) < 2) hidden @endif>
        <button type="button" class="icon-btn lang-btn" aria-expanded="false" aria-haspopup="true" aria-label="{{ __('Dil') }}">
          @include('partials.flag', ['code' => \App\Support\Locale::current()])
        </button>
        <div class="nav-panel right lang-panel">
          @foreach(\App\Support\Locale::published() as $__code)
            @php $__name = \App\Support\Locale::NAMES[$__code]; @endphp
            <a class="nav-item{{ \App\Support\Locale::is($__code) ? ' is-on' : '' }}" hreflang="{{ $__code }}"
               href="{{ \App\Support\Locale::switchUrl($__code) }}"><span class="ni-ico">@include('partials.flag', ['code' => $__code])</span><span><b>{{ $__name }}</b></span></a>
          @endforeach
        </div>
      </div>
      <a href="{{ lroute('cart.index') }}" class="icon-btn cart-btn" aria-label="{{ __('Səbət') }}">
        @include('partials.cart-icon')
        @if(($cartCount ?? 0) > 0)
          <span class="badge">{{ $cartCount }}</span>
        @endif
      </a>
      @auth
        {{-- The customer's own things, and the panel for the owner and managers only. --}}
        <div class="nav-drop account-drop header-cta">
          <button type="button" class="icon-btn" aria-expanded="false" aria-haspopup="true" aria-label="{{ __('Hesabım') }}" title="{{ auth()->user()->name }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/></svg>
          </button>
          <div class="nav-panel right">
            <div class="nav-who">{{ auth()->user()->name }}</div>
            <a class="nav-item" href="{{ lroute('orders.index') }}"><span class="ni-ico">📦</span><span><b>{{ __('Sifarişlərim') }}</b></span></a>
            @if(auth()->user()->isStaff())
              <a class="nav-item" href="{{ url('/admin') }}"><span class="ni-ico">⚙️</span><span><b>{{ __('Admin panel') }}</b></span></a>
            @endif
            {{-- A courier signs in on the same shop as everybody else, so this
                 is the only place he can be told where his own screen is. --}}
            @if(auth()->user()->isCourier())
              <a class="nav-item" href="{{ route('courier.index') }}"><span class="ni-ico">🚴</span><span><b>{{ __('Kuryer səhifəsi') }}</b></span></a>
            @endif
            <form method="POST" action="{{ lroute('logout') }}">
              @csrf
              <button type="submit" class="nav-item"><span class="ni-ico">↩</span><span><b>{{ __('Çıxış') }}</b></span></button>
            </form>
          </div>
        </div>
      @else
        <a href="{{ lroute('login') }}" class="btn btn-ghost header-cta">{{ __('Giriş') }}</a>
        <a href="{{ lroute('register') }}" class="btn btn-primary header-cta">{{ __('Qeydiyyat') }}</a>
      @endauth
      <button class="icon-btn menu-btn" id="menu-open" aria-label="{{ __('Menyu') }}"><span></span></button>
    </div>
  </div>
</header>

<div class="mobile-nav" id="mobile-nav">
  <button class="close-btn" id="menu-close" aria-label="{{ __('Bağla') }}">✕</button>
  @if($navDrop)
    <div class="mn-group">
      <span class="mn-head">{{ __('Məhsullar') }}</span>
      @foreach($navDrop as $item)
        <a href="{{ lroute($item['route']) }}">{{ $item['icon'] }} {{ \App\Support\Menu::title($item) }}@if($item['badge'])<i class="ni-badge">{{ __($item['badge']) }}</i>@endif</a>
      @endforeach
    </div>
  @endif
  {{-- A line the owner pulled out of «Məhsullar» does not belong under that
       heading here either; it stands on its own, as it does on the bar. --}}
  @if($navTop)
    <div class="mn-group">
      @foreach($navTop as $item)
        <a href="{{ lroute($item['route']) }}">{{ $item['icon'] }} {{ \App\Support\Menu::title($item) }}@if($item['badge'])<i class="ni-badge">{{ __($item['badge']) }}</i>@endif</a>
      @endforeach
    </div>
  @endif
  @if(\App\Support\Contact::has())
    <div class="mn-group">
      <span class="mn-head">{{ __('Əlaqə') }}</span>
      <a href="tel:{{ \App\Support\Contact::dial() }}">📞 {{ \App\Support\Contact::display() }}</a>
      <a href="{{ \App\Support\Contact::whatsapp() }}" target="_blank" rel="noopener">💬 WhatsApp</a>
    </div>
  @endif
  <div class="mn-group">
    <span class="mn-head">{{ __('Məlumat') }}</span>
    <a href="{{ lroute('home') }}#how">{{ __('Necə İşləyir') }}</a>
    <a href="{{ lroute('home') }}#faq">{{ __('Suallar') }}</a>
  </div>
  <div class="mn-group">
    <span class="mn-head">{{ __('Hesab') }}</span>
    <a href="{{ lroute('cart.index') }}">{{ __('Səbət') }}</a>
    @auth
      <a href="{{ lroute('orders.index') }}">{{ __('Sifarişlərim') }}</a>
      @if(auth()->user()->isStaff())
        <a href="{{ url('/admin') }}">{{ __('Admin panel') }}</a>
      @endif
      @if(auth()->user()->isCourier())
        <a href="{{ route('courier.index') }}">{{ __('Kuryer səhifəsi') }}</a>
      @endif
      {{-- The token is not decoration: without it every sign-out from the
           phone menu — which is the one most people use — came back as
           "419 Page Expired" instead of signing anybody out. --}}
      <form method="POST" action="{{ lroute('logout') }}">@csrf<button type="submit">{{ __('Çıxış') }}</button></form>
    @else
      <a href="{{ lroute('login') }}">{{ __('Giriş') }}</a>
      <a href="{{ lroute('register') }}">{{ __('Qeydiyyat') }}</a>
    @endauth
  </div>
</div>

<main id="main">
@include('partials.unpaid-order')
{{-- The offer to keep the shop on the home screen; it shows itself. --}}
@include('partials.install-app')
@yield('content')
</main>

<footer>
  <div class="wrap">
    <div class="footer-top">
      <div class="footer-brand">
        <span class="brand"><img src="/images/logo.svg" alt="Nefis"></span>
        <p>{{ __('Şokoladın ən nəfis halı, hər qutu sizin xatirəniz üçün fərdi hazırlanır.') }}</p>
        <div class="footer-social">
          <a href="https://www.instagram.com/nefis.az/" target="_blank" rel="noopener" aria-label="Instagram">@include('partials.instagram-icon')</a>
          @if(\App\Support\Contact::has())
            <a href="{{ \App\Support\Contact::whatsapp() }}" target="_blank" rel="noopener" aria-label="WhatsApp">@include('partials.whatsapp-icon')</a>
          @endif
        </div>
      </div>
      <div class="footer-col">
        <h3>{{ __('Naviqasiya') }}</h3>
        @foreach($navMenu as $item)<a href="{{ lroute($item['route']) }}">{{ \App\Support\Menu::title($item) }}</a>@endforeach
        <a href="{{ lroute('home') }}#how">{{ __('Necə İşləyir') }}</a>
        <a href="{{ lroute('home') }}#faq">{{ __('Suallar') }}</a>
      </div>
      @if($navGifts->isNotEmpty())
        <div class="footer-col">
          <h3>{{ __('Hədiyyə fikirləri') }}</h3>
          @foreach($navGifts->take(7) as $gift)
            <a href="{{ lroute('gifts.show', $gift->slug) }}">{{ $gift->linkText() }}</a>
          @endforeach
          <a href="{{ lroute('gifts.index') }}">{{ __('Hamısı') }} →</a>
        </div>
      @endif
      <div class="footer-col">
        <h3>{{ __('Əlaqə') }}</h3>
        @if(\App\Support\Contact::has())
          <a href="tel:{{ \App\Support\Contact::dial() }}" class="f-phone">{{ \App\Support\Contact::display() }}</a>
          <a href="{{ \App\Support\Contact::whatsapp() }}" target="_blank" rel="noopener">WhatsApp</a>
        @endif
        <a href="https://www.instagram.com/nefis.az/" target="_blank" rel="noopener">Instagram</a>
        @if(\App\Support\Contact::hours())
          <span class="f-hours">{{ __(\App\Support\Contact::hours()) }}</span>
        @endif
      </div>
    </div>
    <div class="footer-rules">
      <a href="{{ lroute('legal.terms') }}">{{ __('İstifadə şərtləri') }}</a>
      <a href="{{ lroute('legal.privacy') }}">{{ __('Məxfilik siyasəti') }}</a>
      <a href="{{ lroute('legal.refund') }}">{{ __('Ödəniş və qaytarma') }}</a>
    </div>
    @php
      $legal = array_filter([
          \App\Models\Setting::get(\App\Models\Setting::LEGAL_NAME),
          \App\Models\Setting::get(\App\Models\Setting::LEGAL_VOEN)
              ? __('VÖEN') . ' ' . \App\Models\Setting::get(\App\Models\Setting::LEGAL_VOEN)
              : null,
          \App\Models\Setting::get(\App\Models\Setting::LEGAL_ADDRESS),
      ]);
    @endphp
    @if($legal)
      {{-- Who sells, on paper. Written once in the admin; a bank, a payment
           gateway and an ordinary customer all look for it here. --}}
      <div class="footer-legal">{{ implode(' · ', $legal) }}</div>
    @endif
    {{-- The cards the shop takes. People look for these at the very bottom of
         a shop before they get out their wallet, so that is where they are:
         the acceptance marks themselves, on white, the way both schemes ask
         for them. The payment page itself is ePoint's — no card number ever
         reaches this site. --}}
    <div class="footer-pay">
      <span>{{ __('Təhlükəsiz ödəniş') }}</span>
      <img src="{{ \App\Support\Assets::url('images/pay/visa.svg') }}" alt="Visa" width="56" height="18" loading="lazy" decoding="async">
      <img src="{{ \App\Support\Assets::url('images/pay/mastercard.svg') }}" alt="Mastercard" width="30" height="23" loading="lazy" decoding="async">
    </div>
    <div class="footer-bottom">
      <span>© {{ date('Y') }} Nefis Şokolad Evi. {{ __('Bütün hüquqlar qorunur.') }}
        {{ __('Dizaynlar müəllif hüququ ilə qorunur.') }}</span>
      <span>{{ __('Sevgi ilə hazırlanıb') }} 🤎</span>
    </div>
  </div>
</footer>

<script>
(function(){
  "use strict";

  /* header shrink on scroll */
  var header = document.getElementById("site-header");

  var floatCta = document.getElementById("float-cta");
  var onScroll = function(){
    if (window.scrollY > 24) header.classList.add("scrolled");
    else header.classList.remove("scrolled");
    if (window.scrollY > window.innerHeight * 0.6) floatCta.classList.add("show");
    else floatCta.classList.remove("show");
  };
  document.addEventListener("scroll", onScroll, { passive:true });
  onScroll();

  /* dark / light switch: the shop is light until this visitor asks for dark */
  var root = document.documentElement;
  var themeBtn = document.getElementById("theme-toggle");
  function currentTheme(){
    return root.getAttribute("data-theme") === "dark" ? "dark" : "light";
  }
  function labelThemeBtn(){
    var dark = currentTheme() === "dark";
    themeBtn.setAttribute("aria-label", dark ? @json(__("İşıqlı rejim")) : @json(__("Qaranlıq rejim")));
    themeBtn.setAttribute("aria-pressed", dark ? "true" : "false");
  }
  themeBtn.addEventListener("click", function(){
    var next = currentTheme() === "dark" ? "light" : "dark";
    root.setAttribute("data-theme", next);
    try { localStorage.setItem("nefis-theme", next); } catch (e) {}
    labelThemeBtn();
  });
  labelThemeBtn();

  /* mobile nav */
  document.querySelectorAll('.nav-drop > button').forEach(function(b){
    b.addEventListener('click', function(e){
      var drop = b.parentNode, open = !drop.classList.contains('open');
      document.querySelectorAll('.nav-drop.open').forEach(function(d){ d.classList.remove('open'); d.firstElementChild.setAttribute('aria-expanded', 'false'); });
      drop.classList.toggle('open', open);
      b.setAttribute('aria-expanded', open ? 'true' : 'false');
      e.stopPropagation();
    });
  });
  document.addEventListener('click', function(e){
    if (e.target.closest && e.target.closest('.nav-drop')) return;
    document.querySelectorAll('.nav-drop.open').forEach(function(d){ d.classList.remove('open'); d.firstElementChild.setAttribute('aria-expanded', 'false'); });
  });
  document.addEventListener('keydown', function(e){
    if (e.key !== 'Escape') return;
    document.querySelectorAll('.nav-drop.open').forEach(function(d){ d.classList.remove('open'); d.firstElementChild.setAttribute('aria-expanded', 'false'); d.firstElementChild.focus(); });
  });
  var nav = document.getElementById("mobile-nav");
  document.getElementById("menu-open").addEventListener("click", function(){ nav.classList.add("open"); });
  document.getElementById("menu-close").addEventListener("click", function(){ nav.classList.remove("open"); });
  nav.querySelectorAll("a").forEach(function(a){ a.addEventListener("click", function(){ nav.classList.remove("open"); }); });

  /* reveal on scroll — CSS transition driven, no rAF dependency */
  var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  var els = document.querySelectorAll(".reveal");
  if (reduced || !("IntersectionObserver" in window)){
    els.forEach(function(el){ el.classList.add("is-visible"); });
  } else {
    var io = new IntersectionObserver(function(entries, obs){
      entries.forEach(function(entry){
        if (entry.isIntersecting){
          entry.target.classList.add("is-visible");
          obs.unobserve(entry.target);
        }
      });
    }, { threshold:0.12, rootMargin:"0px 0px -40px 0px" });
    els.forEach(function(el){ io.observe(el); });
  }

  /* The three steps: the dashes run to the next number when the section is
     reached. Without an observer they are simply there from the start. */
  var steps = document.querySelector(".steps");
  if (steps){
    var drawLines = function(){ steps.classList.add("lines-in"); };
    if (reduced || !("IntersectionObserver" in window)){
      drawLines();
    } else {
      var lineIo = new IntersectionObserver(function(entries, obs){
        if (entries[0].isIntersecting){ drawLines(); obs.disconnect(); }
      }, { threshold:0.2 });
      lineIo.observe(steps);
    }
  }

  /* only one FAQ item open at a time */
  document.querySelectorAll(".faq-item").forEach(function(item){
    item.addEventListener("toggle", function(){
      if (item.open){
        document.querySelectorAll(".faq-item[open]").forEach(function(other){
          if (other !== item) other.removeAttribute("open");
        });
      }
    });
  });
})();

/* The hosting takes at most 30 MB a sending: say so here, before the page's
   own work (and the customer's wait) rather than show a server error after. */
document.addEventListener('submit', function(e){
  var form = e.target;
  if (!form || form.enctype !== 'multipart/form-data') return;
  var total = 0;
  Array.prototype.forEach.call(form.querySelectorAll('input[type=file]'), function(i){
    Array.prototype.forEach.call(i.files || [], function(f){ total += f.size; });
  });
  if (total <= 27 * 1048576) return;
  e.preventDefault();
  e.stopImmediatePropagation();
  alert(@json(__('Yüklədiyiniz fayllar birlikdə ')) + (total / 1048576).toFixed(1) + @json(__(' MB-dır, 27 MB-dan çox ola bilməz. Videonu qısaldın və ya şəkilləri kiçildin.')));
}, true);
</script>
@yield('page_script')
@stack('chat_script')
{{-- The shop's own calendar: the browser's date panel cannot be styled, so
     every date field on the site opens this one instead. --}}
<script defer src="{{ asset('js/date-picker.js') }}?v={{ \App\Support\Assets::version('js/date-picker.js') }}"></script>
<script defer src="{{ asset('js/time-picker.js') }}?v={{ \App\Support\Assets::version('js/time-picker.js') }}"></script>
<script defer src="{{ asset('js/select-picker.js') }}?v={{ \App\Support\Assets::version('js/select-picker.js') }}"></script>
{{-- Hands the worker to the browser, and runs the bar above. --}}
<script defer src="{{ asset('js/pwa.js') }}?v={{ \App\Support\Assets::version('js/pwa.js') }}"></script>
</body>
</html>
