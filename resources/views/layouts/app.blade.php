<!DOCTYPE html>
<html lang="@yield('lang', 'az')">
<head>
<meta charset="UTF-8">
{{-- Runs before any styles paint, so a visitor who chose a theme never sees
     a flash of the other one. With no choice stored the device decides. --}}
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
  $seoTitle = trim($__env->yieldContent('title', 'Nefis — Şəkilli Şokolad Qutuları və Fərdi Hədiyyələr'));
  $seoDescription = trim($__env->yieldContent('meta_description', 'Öz şəkliniz və sözlərinizlə fərdi şokolad qutusu — ad günü, sevgiliyə, 8 Mart və hər münasibətə unudulmaz hədiyyə. Bakıda və bütün Azərbaycanda çatdırılma.'));
  $seoUrl = e(\App\Support\Seo::canonical());
  $seoImage = trim($__env->yieldContent('og_image'));
@endphp
<title>{!! $seoTitle !!}</title>
<meta name="description" content="{!! $seoDescription !!}">
@hasSection('robots')
<meta name="robots" content="@yield('robots')">
@endif
<link rel="canonical" href="{!! $seoUrl !!}">
<meta name="theme-color" content="#3A2617">
<meta property="og:type" content="@yield('og_type', 'website')">
<meta property="og:locale" content="az_AZ">
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
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%F0%9F%8D%AB%3C/text%3E%3C/svg%3E">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
{{-- The families after Poppins stand in for the designs' own display faces,
     which are not licensed for the web. --}}
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,500&family=Inter:wght@400;500;600;700&family=Great+Vibes&family=Poppins:wght@600&family=Titan+One&family=Bungee&family=Fredoka:wght@500;600&family=Sacramento&family=Creepster&family=Source+Sans+3:wght@400;600&family=Orbitron:wght@600;800&family=Anton&family=Cinzel:wght@400;700&family=Bangers&family=Luckiest+Guy&family=Oswald:wght@500;700&family=Bevan&family=Archivo+Black&family=Caveat:wght@600&family=Pacifico&family=Montserrat:wght@300;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/polaroid.css') }}">
<link rel="stylesheet" href="{{ asset('css/site.css') }}?v={{ \App\Support\Assets::version('css/site.css') }}">
<style>
  @yield('page_style')
</style>
</head>
<body>
@php $navWraps = \App\Models\Wrapping::where('is_active', true)->exists(); @endphp
@php $navLetters = \App\Support\Letter::enabled(); @endphp
@php $navLive = \App\Support\LiveMaterials::enabled(); @endphp
@php $navGifts = \App\Models\GiftPage::shown()->inLocale('az')->get(); @endphp
@php $navRu = \App\Models\GiftPage::shown()->inLocale('ru')->exists(); @endphp

<a href="#main" class="skip-link">Əsas məzmuna keç</a>

<a href="https://www.instagram.com/nefis.az/" target="_blank" rel="noopener" class="float-cta" id="float-cta" aria-label="Instagramda yazın">
  <span class="ico">📷</span><span class="txt">Instagramda Yaz</span>
</a>

<div class="blobs" aria-hidden="true">
  <div class="blob" style="width:26rem;height:26rem;background:var(--gold);top:-8rem;right:-6rem;"></div>
  <div class="blob b2" style="width:20rem;height:20rem;background:var(--terracotta);top:20rem;left:-8rem;opacity:.28;"></div>
</div>

<header id="site-header">
  <div class="wrap">
    <a href="{{ route('home') }}" class="brand"><img src="/images/logo.svg" alt="Nefis"></a>
    <nav class="primary" aria-label="Əsas menyu">
      {{-- Everything for sale under one word, so the bar stays short however many there are. --}}
      @if($navWraps || $navLetters || $navLive || $navGifts->isNotEmpty())
        <div class="nav-drop">
          <button type="button" aria-expanded="false" aria-haspopup="true">
            Məhsullar
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
          </button>
          <div class="nav-panel">
            <a class="nav-item" href="{{ route('designs.index') }}"><span class="ni-ico">🍫</span><span><b>Dizaynlar</b><small>Fərdi şokolad qutuları</small></span></a>
            @if($navGifts->isNotEmpty())
              <a class="nav-item" href="{{ route('gifts.index') }}"><span class="ni-ico">🎉</span><span><b>Hədiyyə fikirləri</b><small>{{ $navGifts->take(3)->pluck('menu_label')->implode(', ') }}…</small></span></a>
            @endif
            @if($navWraps)
              <a class="nav-item" href="{{ route('wrappings.index') }}"><span class="ni-ico">🎁</span><span><b>Qablaşdırma</b><small>Hədiyyə kağızı və lent</small></span></a>
            @endif
            @if($navLetters)
              <a class="nav-item" href="{{ route('letters.create') }}"><span class="ni-ico">💌</span><span><b>{{ \App\Support\Letter::text('menu') }}</b><small>Şəkil və sözlərlə polaroid</small></span></a>
            @endif
            @if($navLive)
              <a class="nav-item" href="{{ route('live.create') }}"><span class="ni-ico">🎬</span><span><b>Canlı şəkil</b><small>Telefonda canlanan şəkil (AR)</small></span></a>
            @endif
          </div>
        </div>
      @else
        <a href="{{ route('designs.index') }}">Dizaynlar</a>
      @endif
      <a href="{{ route('home') }}#how">Necə İşləyir</a>
      <a href="{{ route('home') }}#faq">Suallar</a>
    </nav>
    <div class="header-actions">
      <button type="button" class="icon-btn theme-btn" id="theme-toggle" aria-label="Qaranlıq rejim" title="Qaranlıq / işıqlı rejim">
        <svg class="moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
        <svg class="sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4.5"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
      </button>
      <a href="{{ route('cart.index') }}" class="icon-btn" aria-label="Səbət">
        🛍️
        @if(($cartCount ?? 0) > 0)
          <span class="badge">{{ $cartCount }}</span>
        @endif
      </a>
      @auth
        {{-- The customer's own things, and the panel for the owner and managers only. --}}
        <div class="nav-drop account-drop header-cta">
          <button type="button" class="icon-btn" aria-expanded="false" aria-haspopup="true" aria-label="Hesabım" title="{{ auth()->user()->name }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/></svg>
          </button>
          <div class="nav-panel right">
            <div class="nav-who">{{ auth()->user()->name }}</div>
            <a class="nav-item" href="{{ route('orders.index') }}"><span class="ni-ico">📦</span><span><b>Sifarişlərim</b></span></a>
            @if(auth()->user()->isStaff())
              <a class="nav-item" href="{{ url('/admin') }}"><span class="ni-ico">⚙️</span><span><b>Admin panel</b></span></a>
            @endif
            <form method="POST" action="{{ route('logout') }}">
              @csrf
              <button type="submit" class="nav-item"><span class="ni-ico">↩</span><span><b>Çıxış</b></span></button>
            </form>
          </div>
        </div>
      @else
        <a href="{{ route('login') }}" class="btn btn-ghost header-cta">Giriş</a>
        <a href="{{ route('register') }}" class="btn btn-primary header-cta">Qeydiyyat</a>
      @endauth
      <button class="icon-btn menu-btn" id="menu-open" aria-label="Menyu"><span></span></button>
    </div>
  </div>
</header>

<div class="mobile-nav" id="mobile-nav">
  <button class="close-btn" id="menu-close" aria-label="Bağla">✕</button>
  <div class="mn-group">
    <span class="mn-head">Məhsullar</span>
    <a href="{{ route('designs.index') }}">🍫 Dizaynlar</a>
    @if($navGifts->isNotEmpty())<a href="{{ route('gifts.index') }}">🎉 Hədiyyə fikirləri</a>@endif
    @if($navWraps)<a href="{{ route('wrappings.index') }}">🎁 Qablaşdırma</a>@endif
    @if($navLetters)<a href="{{ route('letters.create') }}">💌 {{ \App\Support\Letter::text('menu') }}</a>@endif
    @if($navLive)<a href="{{ route('live.create') }}">🎬 Canlı şəkil</a>@endif
  </div>
  @if(\App\Support\Contact::has())
    <div class="mn-group">
      <span class="mn-head">Əlaqə</span>
      <a href="tel:{{ \App\Support\Contact::dial() }}">📞 {{ \App\Support\Contact::display() }}</a>
      <a href="{{ \App\Support\Contact::whatsapp() }}" target="_blank" rel="noopener">💬 WhatsApp</a>
    </div>
  @endif
  <div class="mn-group">
    <span class="mn-head">Məlumat</span>
    <a href="{{ route('home') }}#how">Necə İşləyir</a>
    <a href="{{ route('home') }}#faq">Suallar</a>
  </div>
  <div class="mn-group">
    <span class="mn-head">Hesab</span>
    <a href="{{ route('cart.index') }}">Səbət</a>
    @auth
      <a href="{{ route('orders.index') }}">Sifarişlərim</a>
      @if(auth()->user()->isStaff())
        <a href="{{ url('/admin') }}">Admin panel</a>
      @endif
      <form method="POST" action="{{ route('logout') }}"><button type="submit">Çıxış</button></form>
    @else
      <a href="{{ route('login') }}">Giriş</a>
      <a href="{{ route('register') }}">Qeydiyyat</a>
    @endauth
  </div>
</div>

<main id="main">
@yield('content')
</main>

<footer>
  <div class="wrap">
    <div class="footer-top">
      <div class="footer-brand">
        <span class="brand"><img src="/images/logo.svg" alt="Nefis"></span>
        <p>Şokoladın ən nəfis halı — hər qutu sizin xatirəniz üçün fərdi hazırlanır.</p>
        <div class="footer-social">
          <a href="https://www.instagram.com/nefis.az/" target="_blank" rel="noopener" aria-label="Instagram">📷</a>
        </div>
      </div>
      <div class="footer-col">
        <h4>Naviqasiya</h4>
        <a href="{{ route('designs.index') }}">Dizaynlar</a>@if($navWraps)<a href="{{ route('wrappings.index') }}">Qablaşdırma</a>@endif @if($navLetters)<a href="{{ route('letters.create') }}">{{ \App\Support\Letter::text('menu') }}</a>@endif @if($navLive)<a href="{{ route('live.create') }}">Canlı şəkil</a>@endif
        <a href="{{ route('home') }}#how">Necə İşləyir</a>
        <a href="{{ route('home') }}#faq">Suallar</a>
      </div>
      @if($navGifts->isNotEmpty())
        <div class="footer-col">
          <h4>Hədiyyə fikirləri</h4>
          @foreach($navGifts->take(7) as $gift)
            <a href="{{ route('gifts.show', $gift->slug) }}">{{ $gift->linkText() }}</a>
          @endforeach
          <a href="{{ route('gifts.index') }}">Hamısı →</a>
          @if($navRu)<a href="{{ route('gifts.index.ru') }}" lang="ru">На русском</a>@endif
        </div>
      @endif
      <div class="footer-col">
        <h4>Əlaqə</h4>
        @if(\App\Support\Contact::has())
          <a href="tel:{{ \App\Support\Contact::dial() }}" class="f-phone">{{ \App\Support\Contact::display() }}</a>
          <a href="{{ \App\Support\Contact::whatsapp() }}" target="_blank" rel="noopener">WhatsApp</a>
        @endif
        <a href="https://www.instagram.com/nefis.az/" target="_blank" rel="noopener">Instagram</a>
        @if(\App\Support\Contact::hours())
          <span class="f-hours">{{ \App\Support\Contact::hours() }}</span>
        @endif
      </div>
    </div>
    <div class="footer-bottom">
      <span>© {{ date('Y') }} Nefis Şokolad Evi. Bütün hüquqlar qorunur.</span>
      <span>Sevgi ilə hazırlanıb 🤎</span>
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

  /* dark / light switch — remembered per visitor, otherwise the device decides */
  var root = document.documentElement;
  var prefersDark = window.matchMedia ? window.matchMedia("(prefers-color-scheme: dark)") : null;
  var themeBtn = document.getElementById("theme-toggle");
  function currentTheme(){
    return root.getAttribute("data-theme") || (prefersDark && prefersDark.matches ? "dark" : "light");
  }
  function labelThemeBtn(){
    var dark = currentTheme() === "dark";
    themeBtn.setAttribute("aria-label", dark ? "İşıqlı rejim" : "Qaranlıq rejim");
    themeBtn.setAttribute("aria-pressed", dark ? "true" : "false");
  }
  themeBtn.addEventListener("click", function(){
    var next = currentTheme() === "dark" ? "light" : "dark";
    root.setAttribute("data-theme", next);
    try { localStorage.setItem("nefis-theme", next); } catch (e) {}
    labelThemeBtn();
  });
  if (prefersDark && prefersDark.addEventListener) prefersDark.addEventListener("change", labelThemeBtn);
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
  alert('Yüklədiyiniz fayllar birlikdə ' + (total / 1048576).toFixed(1) + ' MB-dır — 27 MB-dan çox ola bilməz. Videonu qısaldın və ya şəkilləri kiçildin.');
}, true);
</script>
@yield('page_script')
</body>
</html>
