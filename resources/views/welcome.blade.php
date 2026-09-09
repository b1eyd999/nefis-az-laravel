<!DOCTYPE html>
<html lang="az">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Nefis Şokolad Evi — Fərdi Şokolad Qutuları</title>
<meta name="description" content="Öz şəklinizlə fərdi şokolad qutusu. Premium keyfiyyət, özəl günləriniz üçün unudulmaz hədiyyə.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --surface:#000000;
    --lattice:#0d0d0d;
    --content:#ffffff;
    --content-muted:rgb(255 255 255 / .4);
    --content-faint:rgb(255 255 255 / .25);
    --rule:rgb(255 255 255 / .25);
    --accent:#d98a2b;

    --caption: max(.75rem, 12px);
    --body: max(1.125rem, 12px);
    --lede: 1.25rem;
    --fine: max(.8125rem, 12px);
    --chip: max(.875rem, 12px);
    --title: 1.625rem;
    --display: 4.375rem;

    --pitch: .625rem;
    --gap: .125rem;
    --offset: calc(var(--gap) / -2);
    --reach: 12rem;

    --dur-fast: 150ms;
    --dur-normal: 250ms;
    --ease-entrance: cubic-bezier(.2,0,0,1);
  }
  @supports (width: round(1px,1px)){
    :root{
      --pitch: round(.625rem,1px);
      --gap: round(.125rem,1px);
      --offset: round(calc(var(--gap) / -2),1px);
    }
  }
  @media (min-width:1024px) and (min-aspect-ratio:1/1){
    html{ font-size:min(1.111111vw,2lvh); }
  }
  @media (max-width:1023.98px), (max-aspect-ratio:0.9999/1){
    html{ font-size:16px; }
    :root{ --display:2.5rem; }
  }
  @media (min-width:640px) and (max-width:1023.98px){
    :root{ --display:3.125rem; }
  }

  *,*::before,*::after{ box-sizing:border-box; }
  html{ background:#000; scroll-behavior:auto; }
  body{
    margin:0; position:relative; min-height:100lvh; overflow-x:clip;
    background:var(--surface); color:var(--content);
    font-family:"IBM Plex Mono", ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size:var(--body); line-height:1.1; -webkit-font-smoothing:antialiased;
  }
  a{ color:inherit; text-decoration:none; }
  button{ font:inherit; color:inherit; background:none; border:0; cursor:pointer; }
  input{ font:inherit; color:inherit; background:none; }
  ul,ol,dl,dd,dt,h1,h2,h3,p{ margin:0; padding:0; list-style:none; }
  img{ max-width:100%; display:block; }

  /* ---------- lattice ---------- */
  .lattice-shell{ position:relative; background-color:var(--lattice); }
  .lattice-beam{
    position:fixed; inset:0; pointer-events:none; z-index:1;
    --px:-100vw; --py:-100vh;
    background-image:
      radial-gradient(circle var(--reach) at var(--px) var(--py),
        rgb(255 255 255 / .07) 0%, rgb(255 255 255 / .055) 26%,
        rgb(255 255 255 / .034) 52%, rgb(255 255 255 / .016) 74%,
        rgb(255 255 255 / .005) 89%, transparent 100%),
      radial-gradient(circle calc(var(--reach) * 1.75) at var(--px) var(--py),
        rgb(255 255 255 / .017) 0%, rgb(255 255 255 / .009) 48%, transparent 100%);
  }
  @media (hover:none) and (pointer:coarse){ .lattice-beam{ display:none; } }
  .lattice-bars{
    position:absolute; inset:0; pointer-events:none; z-index:2;
    background-image:
      repeating-linear-gradient(to right, #000 0 var(--gap), transparent var(--gap) var(--pitch)),
      repeating-linear-gradient(to bottom, #000 0 var(--gap), transparent var(--gap) var(--pitch));
    background-position: var(--offset) var(--offset);
  }
  .lattice-panel{
    position:relative; background-color:var(--lattice);
    background-image:
      repeating-linear-gradient(to right, #000 0 var(--gap), transparent var(--gap) var(--pitch)),
      repeating-linear-gradient(to bottom, #000 0 var(--gap), transparent var(--gap) var(--pitch));
    background-position: var(--offset) var(--offset);
    border:1px solid var(--rule);
  }

  main{ position:relative; z-index:3; }

  /* ---------- scramble ---------- */
  .scramble .visually-hidden{
    position:absolute; width:1px; height:1px; overflow:hidden; clip:rect(0 0 0 0); white-space:nowrap;
  }

  /* ---------- reveal ---------- */
  .reveal{ opacity:0; transform:translateY(14px); transition:opacity .6s var(--ease-entrance), transform .6s var(--ease-entrance); }
  .reveal.is-visible{ opacity:1; transform:none; }
  @media (prefers-reduced-motion:reduce){
    .reveal{ opacity:1; transform:none; transition:none; }
  }

  /* ---------- header ---------- */
  header.site{
    position:sticky; top:0; z-index:50;
  }
  .header-inner{
    display:flex; align-items:center; justify-content:space-between;
    padding:1.25rem 1.5rem; gap:1rem;
  }
  .logo{ font-weight:700; font-size:1.25rem; letter-spacing:.02em; display:flex; align-items:center; gap:.5rem; }
  .logo span.mark{ color:var(--accent); }
  nav.primary{ display:none; }
  .cart-badge{ font-size:var(--body); color:var(--content-muted); }
  @media (min-width:1024px) and (min-aspect-ratio:1/1){
    header.site{ height:0; overflow:visible; }
    .header-inner{ position:absolute; inset-inline:0; top:1.5rem; padding:0 2.5rem; }
    nav.primary{ display:flex; gap:4rem; position:absolute; left:50%; transform:translateX(-50%); }
    .logo{ position:absolute; left:0; }
    .cart-badge{ position:absolute; right:0; }
  }
  nav.primary a{ opacity:.6; transition:opacity var(--dur-fast); }
  nav.primary a:hover, nav.primary a:focus-visible{ opacity:1; }
  .submenu-wrap{ position:relative; padding:.75rem 0; }
  .submenu{
    position:absolute; top:100%; left:0; margin-top:0; min-width:14rem; padding:1rem;
    visibility:hidden; opacity:0; transform:translateY(-4px);
    transition:opacity var(--dur-normal), transform var(--dur-normal), visibility var(--dur-normal);
  }
  .submenu-wrap:hover .submenu, .submenu-wrap:focus-within .submenu{
    visibility:visible; opacity:1; transform:none;
  }
  .submenu a{ display:block; padding:.375rem 0; font-size:var(--chip); }

  .hamburger{ display:block; }
  @media (min-width:1024px) and (min-aspect-ratio:1/1){ .hamburger{ display:none; } }
  .mobile-panel{
    position:fixed; inset:0; z-index:60; background:#000;
    display:flex; flex-direction:column; align-items:center; justify-content:center; gap:2rem;
    transform:translateY(-100%); transition:transform var(--dur-normal) var(--ease-entrance);
  }
  .mobile-panel.open{ transform:translateY(0); }
  .mobile-panel a{ font-size:1.5rem; }
  .mobile-panel .close-x{ position:absolute; top:1.5rem; right:1.5rem; font-size:1.5rem; }

  /* ---------- buttons ---------- */
  .btn{
    position:relative; display:inline-flex; align-items:center; gap:2rem;
    height:2.875rem; padding-inline:1.25rem; font-size:var(--lede);
    border:1px solid var(--content); background-color:var(--lattice);
    background-image:
      repeating-linear-gradient(to right, #000 0 var(--gap), transparent var(--gap) var(--pitch)),
      repeating-linear-gradient(to bottom, #000 0 var(--gap), transparent var(--gap) var(--pitch));
    background-position: var(--offset) var(--offset);
  }
  .btn .arrow{ transition:transform var(--dur-normal); }
  .btn:hover .arrow, .btn:focus-visible .arrow{ transform:translateX(4px); }
  .btn::before{
    content:""; position:absolute; inset:-.75rem; pointer-events:none;
    background:
      linear-gradient(to right, var(--content) 0 2px, transparent 0) top left / .625rem .625rem no-repeat,
      linear-gradient(to bottom, var(--content) 0 2px, transparent 0) top left / .625rem .625rem no-repeat,
      linear-gradient(to left, var(--content) 0 2px, transparent 0) top right / .625rem .625rem no-repeat,
      linear-gradient(to bottom, var(--content) 0 2px, transparent 0) top right / .625rem .625rem no-repeat,
      linear-gradient(to right, var(--content) 0 2px, transparent 0) bottom left / .625rem .625rem no-repeat,
      linear-gradient(to top, var(--content) 0 2px, transparent 0) bottom left / .625rem .625rem no-repeat,
      linear-gradient(to left, var(--content) 0 2px, transparent 0) bottom right / .625rem .625rem no-repeat,
      linear-gradient(to top, var(--content) 0 2px, transparent 0) bottom right / .625rem .625rem no-repeat;
    opacity:0; transform:translate(0,0) scale(.92); transition:opacity var(--dur-normal), transform var(--dur-normal);
  }
  .btn:hover::before, .btn:focus-visible::before{ opacity:1; transform:none; outline:none; }
  .btn:focus-visible{ outline:2px solid #fff; outline-offset:2px; }

  /* ---------- sections shell ---------- */
  section{ position:relative; padding:5rem 1.5rem; }
  @media (min-width:1024px) and (min-aspect-ratio:1/1){
    section{ min-height:50rem; padding:0; }
    .frame-box{ position:relative; max-width:90rem; margin:0 auto; padding:0 2.5rem; }
  }
  .headline{
    font-size:var(--display); line-height:.9; letter-spacing:-.02em; font-weight:700;
  }
  .standfirst{ color:var(--content-muted); font-size:var(--lede); text-transform:uppercase; max-width:32rem; margin-top:1.25rem; }

  /* ---------- hero ---------- */
  .hero{ display:flex; flex-direction:column; min-height:100lvh; }
  @media (min-width:1024px) and (min-aspect-ratio:1/1){
    .hero{ display:block; height:100lvh; min-height:50rem; }
  }
  .hero-product{
    position:relative; margin:2rem auto; width:min(92vw, 26rem); aspect-ratio:4/3;
    display:flex; align-items:center; justify-content:center; text-align:center;
    color:var(--content-faint); font-size:var(--caption); letter-spacing:.05em;
    border:1px dashed var(--rule);
  }
  @media (min-width:1024px) and (min-aspect-ratio:1/1){
    .hero-product{ position:absolute; inset:0; margin:0; width:auto; aspect-ratio:auto; border:0; }
  }
  .hero-markers{ display:flex; justify-content:space-between; gap:1rem; font-size:var(--body); line-height:1.1; color:var(--content-muted); text-transform:uppercase; }
  @media (min-width:1024px) and (min-aspect-ratio:1/1){
    .hero-markers{ position:absolute; top:50%; transform:translateY(-50%); display:contents; }
    .marker{ position:absolute; top:0; transform:translateY(-50%); width:11.5rem; }
    .marker.left{ left:2.5rem; text-align:left; }
    .marker.right{ right:2.5rem; text-align:right; }
  }
  .hero-claim{ text-align:center; font-size:var(--lede); line-height:.9; margin-top:2.5rem; font-weight:700; }
  @media (min-width:1024px) and (min-aspect-ratio:1/1){
    .hero-claim{ position:absolute; bottom:7.375rem; left:0; right:0; margin:0; }
  }
  .hero-cta{ display:flex; justify-content:center; margin-top:2rem; }
  @media (min-width:1024px) and (min-aspect-ratio:1/1){
    .hero-cta{ position:absolute; bottom:2.5rem; left:0; right:0; margin:0; }
  }
  .corner-cards{ display:grid; gap:1rem; margin-top:2.5rem; }
  @media (min-width:1024px) and (min-aspect-ratio:1/1){
    .corner-cards{ display:contents; }
    .corner-card{ position:absolute; bottom:2.5rem; display:flex; align-items:center; gap:1rem; height:max(4.25rem,68px); padding:0 1rem; }
    .corner-card.left{ left:2.5rem; }
    .corner-card.right{ right:2.5rem; }
  }
  .corner-card{ display:flex; align-items:center; gap:1rem; padding:1rem; }
  .corner-card .icon{ width:1.5rem; height:1.5rem; flex:none; color:var(--accent); }
  .corner-card .cap{ font-size:var(--caption); color:var(--content-faint); }
  .corner-card .txt{ font-size:var(--fine); color:var(--content-muted); line-height:1.3; }
  .rule-v{ width:1px; align-self:stretch; background:var(--rule); }

  /* ---------- details ---------- */
  .details-grid{ display:grid; gap:2.5rem; }
  @media (min-width:1024px) and (min-aspect-ratio:1/1){
    .details-grid{ grid-template-columns:1fr 1fr; align-items:start; padding-top:7.625rem; }
  }
  .spec-rows{ display:flex; flex-direction:column; gap:.75rem; margin-top:2rem; }
  .spec-row{ display:flex; gap:1rem; padding:1rem; }
  .spec-row .num{ font-size:var(--body); color:var(--content-faint); flex:none; width:2rem; }
  .spec-row h3{ font-size:var(--body); font-weight:700; margin-bottom:.375rem; }
  .spec-row p{ font-size:var(--fine); color:var(--content-muted); line-height:1.4; }

  /* ---------- collections ---------- */
  .cards-row{ display:grid; gap:1rem; grid-template-columns:1fr; margin-top:2.5rem; }
  @media (min-width:640px){ .cards-row{ grid-template-columns:1fr 1fr; } }
  @media (min-width:1024px) and (min-aspect-ratio:1/1){ .cards-row{ grid-template-columns:repeat(4,1fr); } }
  .p-card{ perspective:60rem; }
  .p-card-surface{
    padding:1rem; display:flex; flex-direction:column; justify-content:space-between; height:100%; min-height:24rem;
    transition:transform .12s ease-out;
    transform-style:preserve-3d;
  }
  .p-card-photo{
    flex:1; margin:1rem 0; display:flex; align-items:center; justify-content:center;
    color:var(--content-faint); font-size:var(--caption); text-align:center; letter-spacing:.05em;
    border:1px dashed var(--rule);
  }
  .p-card-title{ font-size:var(--title); font-weight:700; }
  .p-card-price{ font-size:var(--fine); color:var(--content-muted); margin-top:.25rem; }
  .p-card-foot{ position:relative; height:2.3125rem; margin-top:.5rem; }
  .p-card-chips{ display:flex; gap:.5rem; flex-wrap:wrap; position:absolute; inset:0; align-items:center; transition:opacity var(--dur-normal); }
  .chip{ border:1px solid var(--content-muted); padding:.25rem .875rem; font-size:var(--chip); white-space:nowrap; }
  .p-card-link{ position:absolute; inset:0; display:flex; align-items:center; gap:.5rem; opacity:0; pointer-events:none; transition:opacity var(--dur-normal); border:1px solid #fff; padding:0 .875rem; width:fit-content; }
  .p-card:hover .p-card-chips, .p-card:focus-within .p-card-chips{ opacity:0; }
  .p-card:hover .p-card-link, .p-card:focus-within .p-card-link{ opacity:1; pointer-events:auto; }
  @media (hover:none){
    .p-card-chips{ display:none; }
    .p-card-link{ opacity:1; pointer-events:auto; }
  }

  /* ---------- process ---------- */
  .process-list{ display:flex; flex-direction:column; gap:.75rem; margin-top:2.5rem; }
  .process-row{ display:flex; gap:1.5rem; align-items:flex-start; padding:1.25rem 1rem; }
  .process-row .num{ font-size:var(--display-compact,2rem); font-size:2rem; color:var(--content-faint); flex:none; width:3rem; }
  .process-row h3{ font-size:var(--body); font-weight:700; margin-bottom:.375rem; }
  .process-row p{ font-size:var(--fine); color:var(--content-muted); line-height:1.4; max-width:34rem; }

  /* ---------- faq ---------- */
  dl.faq{ display:flex; flex-direction:column; gap:.75rem; margin-top:2.5rem; }
  .faq-row{ padding:1.25rem 1rem; }
  .faq-row dt{ font-weight:700; font-size:var(--body); display:flex; justify-content:space-between; gap:1rem; }
  .faq-row dt .idx{ color:var(--content-faint); font-weight:400; }
  .faq-row dd{ margin-top:.75rem; padding-top:.75rem; border-top:1px solid var(--rule); font-size:var(--fine); color:var(--content-muted); text-transform:uppercase; line-height:1.5; }

  /* ---------- footer ---------- */
  footer{ padding:3rem 1.5rem 2rem; }
  .footer-top{ display:flex; flex-direction:column; gap:2.5rem; }
  @media (min-width:768px){ .footer-top{ flex-direction:row; justify-content:space-between; } }
  .footer-cols{ display:flex; flex-wrap:wrap; gap:2.5rem; }
  .footer-col h4{ font-size:var(--chip); margin-bottom:1rem; }
  .footer-col a{ display:block; padding:.25rem 0; font-size:var(--chip); color:var(--content-muted); transition:color var(--dur-fast); }
  .footer-col a:hover{ color:var(--content); }
  .newsletter{ max-width:20rem; }
  .newsletter label{ display:block; font-size:var(--chip); margin-bottom:.75rem; }
  .newsletter-field{ display:flex; border:1px solid #fff; }
  .newsletter-field input{ flex:1; padding:.875rem 1rem; }
  .newsletter-field button{ padding:0 1rem; }
  .consent{ display:flex; align-items:center; gap:.5rem; margin-top:.75rem; }
  .consent input[type=checkbox]{ appearance:none; -webkit-appearance:none; width:.625rem; height:.625rem; border:1px solid var(--rule); }
  .consent input[type=checkbox]:checked{ background:#fff; }
  .consent span{ font-size:var(--fine); color:var(--content-faint); text-transform:uppercase; }
  .footer-rule{ height:1px; background:var(--rule); margin:2.5rem 0 1.5rem; }
  .footer-bottom{ display:flex; flex-direction:column; gap:.5rem; font-size:var(--fine); color:var(--content-faint); }
  @media (min-width:768px){ .footer-bottom{ flex-direction:row; justify-content:space-between; } }

  /* ---------- preloader ---------- */
  #preloader{
    position:fixed; inset:0; z-index:100; background:var(--lattice); display:grid; place-items:center;
    transition:opacity .5s;
  }
  #preloader .box{ width:16rem; text-align:center; }
  #preloader .mark{ font-weight:700; font-size:1.5rem; margin-bottom:1.5rem; }
  #preloader .track{ height:1px; background:var(--rule); position:relative; margin-bottom:.75rem; }
  #preloader .fill{ position:absolute; inset:0; background:#fff; transform-origin:left; transform:scaleX(0); }
  #preloader .count{ font-size:var(--caption); color:var(--content-faint); }
  #preloader.done{ opacity:0; pointer-events:none; }
</style>
</head>
<body>

<noscript><style>#preloader{display:none!important}</style></noscript>
<div id="preloader">
  <div class="box">
    <div class="mark">NEFİS</div>
    <div class="track"><div class="fill" id="preloader-fill"></div></div>
    <div class="count" id="preloader-count">YÜKLƏNİR 000</div>
  </div>
</div>

<div class="lattice-shell">
  <div class="lattice-beam" id="beam"></div>
  <div class="lattice-bars"></div>

  <a href="#main" class="visually-hidden" style="position:fixed;top:-40px;left:1rem;z-index:200;background:#fff;color:#000;padding:.5rem 1rem;">Əsas məzmuna keç</a>

  <header class="site">
    <div class="header-inner lattice-panel" style="border:0;">
      <a href="#" class="logo"><span class="mark">◆</span> NEFİS</a>
      <nav class="primary">
        <a href="#collections" class="scramble" data-text="KOLLEKSİYA">KOLLEKSİYA</a>
        <div class="submenu-wrap">
          <button aria-expanded="false" class="scramble" data-text="ŞOKOLADLAR ▾">ŞOKOLADLAR ▾</button>
          <div class="submenu lattice-panel">
            <a href="#collections">AZERBAIJAN STYLE</a>
            <a href="#collections">COUPLE BOX</a>
            <a href="#collections">KİNDER STYLE</a>
            <a href="#collections">MİLKA STYLE</a>
          </div>
        </div>
        <a href="#process" class="scramble" data-text="NECƏ HAZIRLANIR">NECƏ HAZIRLANIR</a>
        <a href="#faq" class="scramble" data-text="SUALLAR">SUALLAR</a>
      </nav>
      <a href="https://www.instagram.com/nefis.az/" target="_blank" rel="noopener" class="cart-badge scramble" data-text="INSTAGRAM">INSTAGRAM</a>
      <button class="hamburger" id="hamburger-open" aria-label="Menyu">☰</button>
    </div>
  </header>

  <div class="mobile-panel" id="mobile-panel">
    <button class="close-x" id="hamburger-close" aria-label="Bağla">✕</button>
    <a href="#collections">KOLLEKSİYA</a>
    <a href="#process">NECƏ HAZIRLANIR</a>
    <a href="#faq">SUALLAR</a>
    <a href="https://www.instagram.com/nefis.az/" target="_blank" rel="noopener">INSTAGRAM</a>
  </div>

  <main id="main">

    <!-- HERO -->
    <section class="hero">
      <div class="hero-markers">
        <div class="marker left">
          <p class="scramble" data-text="PREMİUM KEYFİYYƏT.">PREMİUM KEYFİYYƏT.</p>
          <p class="scramble" data-text="FƏRDİ DİZAYN.">FƏRDİ DİZAYN.</p>
          <p class="scramble" data-text="HƏR AN ÜÇÜN HAZIR.">HƏR AN ÜÇÜN HAZIR.</p>
        </div>
        <div class="marker right">
          <p class="scramble" data-text="ÖZƏL GÜNLƏRİNİZ ÜÇÜN.">ÖZƏL GÜNLƏRİNİZ ÜÇÜN.</p>
          <p class="scramble" data-text="UNUDULMAZ TƏƏSSÜRAT.">UNUDULMAZ TƏƏSSÜRAT.</p>
          <p class="scramble" data-text="SÜRƏTLİ ÇATDIRILMA.">SÜRƏTLİ ÇATDIRILMA.</p>
        </div>
      </div>

      <div class="hero-product" aria-hidden="true">
        [ MƏHSUL VİZUALI TEZLİKLƏ ]
      </div>

      <h1 class="hero-claim scramble" data-text="ÖZ ŞƏKLİNİZLƏ ŞOKOLAD QUTUSU.&#10;FƏRDİ DİZAYN. NƏFİS DADIM.">ÖZ ŞƏKLİNİZLƏ ŞOKOLAD QUTUSU.<br>FƏRDİ DİZAYN. NƏFİS DADIM.</h1>

      <div class="hero-cta">
        <a href="https://www.instagram.com/nefis.az/" target="_blank" rel="noopener" class="btn">
          <span class="scramble" data-text="SİFARİŞ VER">SİFARİŞ VER</span>
          <span class="arrow">→</span>
        </a>
      </div>

      <div class="corner-cards">
        <div class="corner-card left lattice-panel">
          <span class="icon">🌍</span>
          <div>
            <div class="cap">2024-DƏN BƏRİ</div>
          </div>
          <div class="rule-v"></div>
          <div class="txt">KEYFİYYƏTLƏ QURULUB. /<br>İSTƏYİNİZLƏ HAZIRLANIR. /<br>SİZƏ ODAQLANIB.</div>
        </div>
        <div class="corner-card right lattice-panel">
          <span class="icon">◎</span>
          <div class="rule-v"></div>
          <div class="txt">SÜRƏTLİ ÇATDIRILMA /<br>TƏHLÜKƏSİZ QABLAŞDIRMA</div>
        </div>
      </div>
    </section>

    <!-- DETAILS -->
    <section id="details">
      <div class="frame-box">
        <div class="details-grid">
          <div class="reveal">
            <h2 class="headline scramble" data-text="HƏR DETAL VACİBDİR.">HƏR DETAL<br>VACİBDİR.</h2>
            <p class="standfirst">Hər qutu sizin xatirənizə görə fərdi hazırlanır — şəkildən tutmuş yazıya qədər hər şey sizin seçiminizdir.</p>
            <div style="margin-top:2.5rem;">
              <a href="#collections" class="btn"><span class="scramble" data-text="DİZAYNI KƏŞF ET">DİZAYNI KƏŞF ET</span><span class="arrow">→</span></a>
            </div>
          </div>
          <ol class="spec-rows">
            <li class="spec-row lattice-panel reveal"><span class="num">01</span><div><h3 class="scramble" data-text="FƏRDİ ŞƏKİL ÇAPI">FƏRDİ ŞƏKİL ÇAPI</h3><p>Öz şəklinizi (üz və ya tam boy) yüksək keyfiyyətdə qutunun üzərinə çap edirik.</p></div></li>
            <li class="spec-row lattice-panel reveal"><span class="num">02</span><div><h3 class="scramble" data-text="PREMİUM ŞOKOLAD">PREMİUM ŞOKOLAD</h3><p>Yalnız keyfiyyətli, təzə şokolad məhsullarından istifadə olunur.</p></div></li>
            <li class="spec-row lattice-panel reveal"><span class="num">03</span><div><h3 class="scramble" data-text="FƏRDİ YAZI">FƏRDİ YAZI</h3><p>İstədiyiniz mətni, adı və ya tarixi qutuya əlavə edə bilərsiniz.</p></div></li>
            <li class="spec-row lattice-panel reveal"><span class="num">04</span><div><h3 class="scramble" data-text="MÜXTƏLİF DİZAYNLAR">MÜXTƏLİF DİZAYNLAR</h3><p>Kinder, Milka, Azerbaijan Style və digər hazır şablonlardan seçim edin.</p></div></li>
            <li class="spec-row lattice-panel reveal"><span class="num">05</span><div><h3 class="scramble" data-text="SÜRƏTLİ HAZIRLANMA">SÜRƏTLİ HAZIRLANMA</h3><p>Sifarişiniz qısa müddətdə hazırlanıb çatdırılır.</p></div></li>
          </ol>
        </div>
      </div>
    </section>

    <!-- COLLECTIONS -->
    <section id="collections">
      <div class="frame-box">
        <div class="reveal">
          <h2 class="headline scramble" data-text="KOLLEKSİYA.">KOLLEKSİYA.</h2>
          <p class="standfirst">Hər zövqə uyğun hazır dizaynlardan seçin və ya özününüzü yaradın.</p>
        </div>
        <ol class="cards-row">
          <li class="p-card reveal"><div class="p-card-surface lattice-panel">
            <div><div class="p-card-title scramble" data-text="AZERBAIJAN STYLE">AZERBAIJAN STYLE</div><div class="p-card-price">QİYMƏT SORĞU İLƏ</div></div>
            <div class="p-card-photo">[ FOTO ]</div>
            <div class="p-card-foot">
              <div class="p-card-chips"><span class="chip">MİLLİ ORNAMENT</span></div>
              <a href="https://www.instagram.com/nefis.az/" target="_blank" rel="noopener" class="p-card-link">SİFARİŞ VER <span class="arrow">→</span></a>
            </div>
          </div></li>
          <li class="p-card reveal"><div class="p-card-surface lattice-panel">
            <div><div class="p-card-title scramble" data-text="COUPLE BOX">COUPLE BOX</div><div class="p-card-price">QİYMƏT SORĞU İLƏ</div></div>
            <div class="p-card-photo">[ FOTO ]</div>
            <div class="p-card-foot">
              <div class="p-card-chips"><span class="chip">CÜTLÜK ÜÇÜN</span></div>
              <a href="https://www.instagram.com/nefis.az/" target="_blank" rel="noopener" class="p-card-link">SİFARİŞ VER <span class="arrow">→</span></a>
            </div>
          </div></li>
          <li class="p-card reveal"><div class="p-card-surface lattice-panel">
            <div><div class="p-card-title scramble" data-text="KİNDER STYLE">KİNDER STYLE</div><div class="p-card-price">QİYMƏT SORĞU İLƏ</div></div>
            <div class="p-card-photo">[ FOTO ]</div>
            <div class="p-card-foot">
              <div class="p-card-chips"><span class="chip">KLASSİK</span></div>
              <a href="https://www.instagram.com/nefis.az/" target="_blank" rel="noopener" class="p-card-link">SİFARİŞ VER <span class="arrow">→</span></a>
            </div>
          </div></li>
          <li class="p-card reveal"><div class="p-card-surface lattice-panel">
            <div><div class="p-card-title scramble" data-text="MİLKA STYLE">MİLKA STYLE</div><div class="p-card-price">QİYMƏT SORĞU İLƏ</div></div>
            <div class="p-card-photo">[ FOTO ]</div>
            <div class="p-card-foot">
              <div class="p-card-chips"><span class="chip">POPULYAR</span></div>
              <a href="https://www.instagram.com/nefis.az/" target="_blank" rel="noopener" class="p-card-link">SİFARİŞ VER <span class="arrow">→</span></a>
            </div>
          </div></li>
        </ol>
      </div>
    </section>

    <!-- PROCESS -->
    <section id="process">
      <div class="frame-box">
        <div class="reveal">
          <h2 class="headline scramble" data-text="NECƏ HAZIRLANIR">NECƏ<br>HAZIRLANIR</h2>
          <p class="standfirst">Hər addım diqqətlə, sizin xatirənizi ən nəfis formada təqdim etmək üçün planlaşdırılıb.</p>
        </div>
        <ol class="process-list">
          <li class="process-row lattice-panel reveal"><span class="num">01</span><div><h3 class="scramble" data-text="ŞƏKLİNİZİ SEÇİN">ŞƏKLİNİZİ SEÇİN</h3><p>Üz və ya tam boy şəklinizi bizə göndərin.</p></div></li>
          <li class="process-row lattice-panel reveal"><span class="num">02</span><div><h3 class="scramble" data-text="DİZAYNI SEÇİN">DİZAYNI SEÇİN</h3><p>Hazır şablonlardan birini seçin və ya öz ideyanızı bildirin.</p></div></li>
          <li class="process-row lattice-panel reveal"><span class="num">03</span><div><h3 class="scramble" data-text="ÇAP HAZIRLANIR">ÇAP HAZIRLANIR</h3><p>Şəkliniz yüksək keyfiyyətdə qutu üzərinə çap olunur.</p></div></li>
          <li class="process-row lattice-panel reveal"><span class="num">04</span><div><h3 class="scramble" data-text="ŞOKOLAD YERLƏŞDİRİLİR">ŞOKOLAD YERLƏŞDİRİLİR</h3><p>Premium şokolad diqqətlə qutuya yerləşdirilir.</p></div></li>
          <li class="process-row lattice-panel reveal"><span class="num">05</span><div><h3 class="scramble" data-text="QABLAŞDIRILIR VƏ GÖNDƏRİLİR">QABLAŞDIRILIR VƏ GÖNDƏRİLİR</h3><p>Hədiyyəniz təhlükəsiz qablaşdırılıb sizə çatdırılır.</p></div></li>
        </ol>
      </div>
    </section>

    <!-- FAQ -->
    <section id="faq">
      <div class="frame-box">
        <div class="reveal">
          <h2 class="headline scramble" data-text="TEZ-TEZ SORUŞULAN SUALLAR">TEZ-TEZ<br>SORUŞULAN<br>SUALLAR.</h2>
        </div>
        <dl class="faq">
          <div class="faq-row lattice-panel reveal">
            <dt><span class="scramble" data-text="NECƏ SİFARİŞ VERƏ BİLƏRƏM?">NECƏ SİFARİŞ VERƏ BİLƏRƏM?</span><span class="idx">01</span></dt>
            <dd>İnstagram səhifəmiz üzərindən şəklinizi göndərib istədiyiniz dizaynı seçməniz kifayətdir.</dd>
          </div>
          <div class="faq-row lattice-panel reveal">
            <dt><span class="scramble" data-text="HANSI ŞOKOLAD NÖVLƏRİ MÖVCUDDUR?">HANSI ŞOKOLAD NÖVLƏRİ MÖVCUDDUR?</span><span class="idx">02</span></dt>
            <dd>Kinder, Milka, Alionka və digər premium brendlərin dizaynında qutular təklif edirik.</dd>
          </div>
          <div class="faq-row lattice-panel reveal">
            <dt><span class="scramble" data-text="ÇATDIRILMA NƏ QƏDƏR VAXT APARIR?">ÇATDIRILMA NƏ QƏDƏR VAXT APARIR?</span><span class="idx">03</span></dt>
            <dd>Sifariş adətən 1-3 iş günü ərzində hazırlanıb çatdırılır.</dd>
          </div>
          <div class="faq-row lattice-panel reveal">
            <dt><span class="scramble" data-text="BAKI XARİCİNƏ ÇATDIRILMA VARMI?">BAKI XARİCİNƏ ÇATDIRILMA VARMI?</span><span class="idx">04</span></dt>
            <dd>Bəli, Azərbaycan daxilində bütün bölgələrə çatdırılma mövcuddur.</dd>
          </div>
          <div class="faq-row lattice-panel reveal">
            <dt><span class="scramble" data-text="FƏRDİ SİFARİŞİ GERİ QAYTARA BİLƏRƏMMİ?">FƏRDİ SİFARİŞİ GERİ QAYTARA BİLƏRƏMMİ?</span><span class="idx">05</span></dt>
            <dd>Fərdi hazırlanan məhsullar üçün geri qaytarma tətbiq olunmur, lakin çatdırılma zamanı zədə aşkar olarsa əvəz edilir.</dd>
          </div>
        </dl>
      </div>
    </section>

  </main>

  <footer class="lattice-panel" style="border-inline:0; border-bottom:0;">
    <div class="frame-box">
      <div class="footer-top">
        <div class="footer-cols">
          <div class="footer-col">
            <h4>MƏHSULLAR</h4>
            <a href="#collections">Kolleksiya</a>
            <a href="#process">Fərdi Sifariş</a>
            <a href="#">Hədiyyə Kartları</a>
          </div>
          <div class="footer-col">
            <h4>KÖMƏK</h4>
            <a href="#faq">Suallar</a>
            <a href="#faq">Çatdırılma</a>
            <a href="#faq">Sifariş İzləmə</a>
          </div>
          <div class="footer-col">
            <h4>ƏLAQƏ</h4>
            <a href="https://www.instagram.com/nefis.az/" target="_blank" rel="noopener">Instagram</a>
            <a href="#">WhatsApp</a>
            <a href="#">Email</a>
          </div>
        </div>
        <form class="newsletter" onsubmit="event.preventDefault()">
          <label for="nl-email">YENİLİKLƏRDƏN XƏBƏRDAR OLUN.</label>
          <div class="newsletter-field">
            <input id="nl-email" type="email" autocomplete="email" placeholder="E-poçtunuz">
            <button type="submit" aria-label="Abunə ol">→</button>
          </div>
          <div class="consent">
            <input type="checkbox" id="nl-consent">
            <label for="nl-consent"><span>MARKETİNQ E-POÇTLARI ALMAĞA RAZIYAM.</span></label>
          </div>
        </form>
      </div>
      <div class="footer-rule"></div>
      <div class="footer-bottom">
        <span>© 2026 NEFİS. BÜTÜN HÜQUQLAR QORUNUR.</span>
        <span>INSTAGRAM</span>
      </div>
    </div>
  </footer>

</div>

<script>
(function(){
  "use strict";
  var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var coarse = window.matchMedia("(hover: none) and (pointer: coarse)").matches;

  /* shared ticker */
  var subs = new Set();
  var rafId = null;
  var lastT = performance.now();
  function loop(t){
    var dt = t - lastT; lastT = t;
    subs.forEach(function(fn){ try{ fn(dt, t); }catch(e){} });
    if (subs.size) rafId = requestAnimationFrame(loop); else rafId = null;
  }
  function subscribe(fn){ subs.add(fn); if(!rafId){ lastT = performance.now(); rafId = requestAnimationFrame(loop); } return function(){ subs.delete(fn); }; }

  /* pointer field */
  if (!coarse && !reduced){
    var beam = document.getElementById("beam");
    var px = -1000, py = -1000, tx = -1000, ty = -1000;
    window.addEventListener("pointermove", function(e){ tx = e.clientX; ty = e.clientY; }, { passive:true });
    subscribe(function(dt){
      var k = 1 - Math.pow(1 - 0.12, dt/(1000/60));
      px += (tx-px)*k; py += (ty-py)*k;
      beam.style.setProperty("--px", px+"px");
      beam.style.setProperty("--py", py+"px");
    });
  }

  /* scramble decoder */
  var CHARS = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789@#$%&*<>";
  function buildScramble(el){
    if (el.dataset.scrambleReady) return;
    el.dataset.scrambleReady = "1";
    var raw = el.getAttribute("data-text") || el.textContent;
    var visible = document.createElement("span");
    visible.setAttribute("aria-hidden","true");
    var hidden = document.createElement("span");
    hidden.className = "visually-hidden";
    hidden.textContent = raw.replace(/&#10;/g,"\n");
    el.textContent = "";
    el.appendChild(visible);
    el.appendChild(hidden);
    el.__scrambleRaw = raw.replace(/&#10;/g,"\n");
    el.__scrambleVisible = visible;
    el.__scrambleRunning = false;
    function run(){
      if (reduced){ visible.textContent = el.__scrambleRaw; return; }
      if (el.__scrambleRunning) return;
      el.__scrambleRunning = true;
      var text = el.__scrambleRaw;
      var len = text.length;
      var maxSteps = 22;
      var charsPerStep = Math.max(1, Math.ceil(len/maxSteps));
      var resolved = 0;
      var stop = subscribe(throttle(40, function(){
        var out = "";
        for (var i=0;i<len;i++){
          var ch = text[i];
          if (ch === "\n"){ out += "\n"; continue; }
          if (ch === " "){ out += " "; continue; }
          if (i < resolved){ out += ch; }
          else { out += CHARS[(Math.random()*CHARS.length)|0]; }
        }
        visible.textContent = out;
        resolved += charsPerStep;
        if (resolved >= len){ visible.textContent = text; stop(); el.__scrambleRunning = false; }
      }));
    }
    el.__scrambleRun = run;
    el.addEventListener("mouseenter", run);
    el.addEventListener("focus", run);
  }
  function throttle(ms, fn){
    var acc = 0;
    return function(dt){ acc += dt; if (acc >= ms){ acc = 0; fn(); } };
  }
  var scrambleEls = document.querySelectorAll(".scramble");
  scrambleEls.forEach(buildScramble);

  var scrambleObserver = new IntersectionObserver(function(entries){
    entries.forEach(function(entry){
      if (entry.isIntersecting){ entry.target.__scrambleRun && entry.target.__scrambleRun(); scrambleObserver.unobserve(entry.target); }
    });
  }, { rootMargin: "0px 0px -10% 0px" });
  scrambleEls.forEach(function(el){ scrambleObserver.observe(el); });

  /* reveal on scroll */
  var revealEls = document.querySelectorAll(".reveal");
  function revealNow(el){ el.classList.add("is-visible"); }
  if (reduced){
    revealEls.forEach(revealNow);
  } else {
    var seen = new WeakSet();
    function onReveal(entries, obs){
      entries.forEach(function(entry){
        if (entry.isIntersecting && !seen.has(entry.target)){
          seen.add(entry.target);
          revealNow(entry.target);
          obs.unobserve(entry.target);
        }
      });
    }
    var ro1 = new IntersectionObserver(onReveal, { rootMargin:"0px 0px -64px 0px" });
    var ro2 = new IntersectionObserver(onReveal, { threshold:1 });
    revealEls.forEach(function(el){ ro1.observe(el); ro2.observe(el); });
  }

  /* card tilt */
  if (!coarse && !reduced){
    document.querySelectorAll(".p-card").forEach(function(card){
      var surface = card.querySelector(".p-card-surface");
      card.addEventListener("pointermove", function(e){
        var r = card.getBoundingClientRect();
        var nx = ((e.clientX - r.left) / r.width) * 2 - 1;
        var ny = ((e.clientY - r.top) / r.height) * 2 - 1;
        surface.style.transform = "rotateX(" + (-ny*6.5) + "deg) rotateY(" + (nx*6.5) + "deg)";
      });
      card.addEventListener("pointerleave", function(){ surface.style.transform = ""; });
    });
  }

  /* mobile nav */
  var panel = document.getElementById("mobile-panel");
  document.getElementById("hamburger-open").addEventListener("click", function(){ panel.classList.add("open"); });
  document.getElementById("hamburger-close").addEventListener("click", function(){ panel.classList.remove("open"); });
  panel.querySelectorAll("a").forEach(function(a){ a.addEventListener("click", function(){ panel.classList.remove("open"); }); });

  /* preloader */
  var pre = document.getElementById("preloader");
  var fill = document.getElementById("preloader-fill");
  var count = document.getElementById("preloader-count");
  var startedAt = performance.now();
  var target = 0, shown = 0;
  var fontsReady = false, windowLoaded = false;
  function ceiling(){ return (fontsReady && windowLoaded) ? 1 : Math.min(0.97, target + 0.12); }
  var stopPreload = subscribe(function(dt){
    target = Math.min(ceiling(), target + dt/4000);
    shown += (target - shown) * 0.08;
    if (fontsReady && windowLoaded) shown = Math.max(shown, 0.999*Math.min(1,shown+0.02));
    var pct = Math.min(1, shown);
    fill.style.transform = "scaleX(" + pct + ")";
    count.textContent = "YÜKLƏNİR " + String(Math.round(pct*100)).padStart(3,"0");
    if (fontsReady && windowLoaded && pct >= 0.999 && (performance.now()-startedAt) > 700){
      pre.classList.add("done");
      setTimeout(function(){ pre.remove(); }, 700);
      stopPreload();
    }
  });
  if (document.fonts && document.fonts.ready){ document.fonts.ready.then(function(){ fontsReady = true; }); } else { fontsReady = true; }
  if (document.readyState === "complete"){ windowLoaded = true; } else { window.addEventListener("load", function(){ windowLoaded = true; }); }
  setTimeout(function(){ fontsReady = true; windowLoaded = true; }, 8000);
})();
</script>
</body>
</html>
