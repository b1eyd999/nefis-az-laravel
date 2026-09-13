<!DOCTYPE html>
<html lang="az">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', 'Nefis Şokolad Evi — Fərdi Şokolad Qutuları')</title>
<meta name="description" content="@yield('meta_description', 'Öz şəklinizlə, öz sözünüzlə fərdi şokolad qutusu. Premium keyfiyyət, sevdiklərinizə unudulmaz hədiyyə.')">
<meta name="theme-color" content="#3A2617">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Nefis Şokolad Evi">
<meta property="og:title" content="@yield('title', 'Nefis Şokolad Evi — Fərdi Şokolad Qutuları')">
<meta property="og:description" content="@yield('meta_description', 'Öz şəklinizlə, öz sözünüzlə fərdi şokolad qutusu. Premium keyfiyyət, sevdiklərinizə unudulmaz hədiyyə.')">
<meta property="og:url" content="https://nefis.az">
<link rel="canonical" href="https://nefis.az">
<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="@yield('title', 'Nefis Şokolad Evi — Fərdi Şokolad Qutuları')">
<meta name="twitter:description" content="@yield('meta_description', 'Öz şəklinizlə, öz sözünüzlə fərdi şokolad qutusu.')">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%F0%9F%8D%AB%3C/text%3E%3C/svg%3E">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,500&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --cream:#FBF4EA;
    --cream-2:#F3E4D0;
    --paper:#FFFDF9;
    --cocoa:#3A2617;
    --cocoa-2:#6B4630;
    --cocoa-soft:rgba(58,38,23,.72);
    --cocoa-faint:rgba(58,38,23,.38);
    --gold:#C08A3E;
    --gold-deep:#9C6C2A;
    --terracotta:#B5533F;
    --red:#B5473F;
    --line:rgba(58,38,23,.14);
    --shadow: 0 20px 50px -20px rgba(58,38,23,.25);
    --shadow-sm: 0 8px 24px -12px rgba(58,38,23,.25);
    --radius: 1.5rem;
    --radius-sm: .9rem;
    --serif: "Playfair Display", Georgia, serif;
    --sans: "Inter", ui-sans-serif, system-ui, -apple-system, sans-serif;
    --dur: 500ms;
    --ease: cubic-bezier(.16,1,.3,1);
  }
  *,*::before,*::after{ box-sizing:border-box; }
  html{ scroll-behavior:smooth; }
  body{
    margin:0; background:var(--cream); color:var(--cocoa);
    font-family:var(--sans); font-size:1rem; line-height:1.6; -webkit-font-smoothing:antialiased;
    overflow-x:hidden;
  }
  img{ max-width:100%; display:block; }
  a{ color:inherit; text-decoration:none; }
  ul,ol{ margin:0; padding:0; list-style:none; }
  h1,h2,h3,p{ margin:0; }
  button{ font:inherit; cursor:pointer; }
  label{ display:block; font-size:.875rem; font-weight:600; margin-bottom:.4rem; }
  input,textarea,select{
    font:inherit; width:100%; padding:.8rem 1rem; border:1px solid var(--line); border-radius:.7rem;
    background:var(--paper); color:var(--cocoa); transition:border-color .2s;
  }
  input:focus,textarea:focus,select:focus{ outline:none; border-color:var(--gold); }
  .field{ margin-bottom:1.25rem; }
  .error-text{ color:var(--red); font-size:.8125rem; margin-top:.35rem; }
  .alert{ padding:1rem 1.25rem; border-radius:.9rem; font-size:.9375rem; margin-bottom:1.5rem; }
  .alert-success{ background:#EAF3E7; color:#3C6B32; border:1px solid #C6E0BE; }
  .alert-error{ background:#FBEAE8; color:#8C3229; border:1px solid #F1C6C0; }

  .wrap{ max-width:76rem; margin:0 auto; padding-inline:1.5rem; }
  @media (min-width:768px){ .wrap{ padding-inline:2.5rem; } }
  .wrap-narrow{ max-width:34rem; margin:0 auto; padding-inline:1.5rem; }

  .eyebrow{
    display:inline-flex; align-items:center; gap:.5rem;
    font-family:var(--sans); font-weight:600; font-size:.75rem; letter-spacing:.14em; text-transform:uppercase;
    color:var(--gold-deep);
  }
  .eyebrow::before{ content:""; width:1.5rem; height:1px; background:var(--gold); }

  h1,h2,h3{ font-family:var(--serif); font-weight:600; letter-spacing:-.01em; color:var(--cocoa); }
  h1{ font-size:clamp(2.25rem, 5vw, 3.75rem); line-height:1.08; }
  h2{ font-size:clamp(1.875rem, 3.4vw, 2.75rem); line-height:1.15; }
  h3{ font-size:1.375rem; line-height:1.3; }
  .lede{ font-size:1.125rem; color:var(--cocoa-soft); max-width:34rem; }

  /* ---------- decorative blobs ---------- */
  .blob{ position:absolute; border-radius:50%; filter:blur(60px); opacity:.5; pointer-events:none; z-index:0; animation:drift 16s ease-in-out infinite; }
  .blob.b2{ animation-duration:20s; animation-direction:reverse; }
  @keyframes drift{
    0%,100%{ transform:translate(0,0) scale(1); }
    50%{ transform:translate(1.5rem,-1.25rem) scale(1.06); }
  }
  @keyframes float-y{
    0%,100%{ transform:translateY(0); }
    50%{ transform:translateY(-.6rem); }
  }
  @media (prefers-reduced-motion:reduce){
    .blob{ animation:none; }
  }

  /* ---------- reveal ---------- */
  .reveal{ opacity:0; transform:translateY(24px); transition:opacity .9s var(--ease), transform .9s var(--ease); }
  .reveal.is-visible{ opacity:1; transform:none; }
  @media (prefers-reduced-motion:reduce){ .reveal{ opacity:1; transform:none; transition:none; } }

  /* ---------- buttons ---------- */
  .btn{
    display:inline-flex; align-items:center; justify-content:center; gap:.6rem;
    padding:1rem 1.75rem; border-radius:999px; font-weight:600; font-size:.9375rem;
    border:1px solid transparent; transition:transform .35s var(--ease), box-shadow .35s var(--ease), background .35s;
    white-space:nowrap; cursor:pointer;
  }
  .btn-primary{ background:var(--cocoa); color:var(--cream); box-shadow:var(--shadow-sm); }
  .btn-primary:hover{ transform:translateY(-3px); box-shadow:0 16px 32px -12px rgba(58,38,23,.45); }
  .btn-ghost{ background:transparent; color:var(--cocoa); border-color:var(--line); }
  .btn-ghost:hover{ background:var(--paper); border-color:var(--cocoa-2); transform:translateY(-2px); }
  .btn-block{ width:100%; }
  .btn:disabled{ opacity:.5; cursor:not-allowed; transform:none !important; }
  .btn svg{ width:1rem; height:1rem; transition:transform .3s var(--ease); }
  .btn:hover svg{ transform:translate(3px,-3px); }

  /* ---------- header ---------- */
  header{
    position:sticky; top:0; z-index:50; padding:1.25rem 0;
    transition:background .4s, box-shadow .4s, padding .4s, backdrop-filter .4s;
    background:rgba(251,244,234,.85); backdrop-filter:blur(12px); box-shadow:0 1px 0 var(--line);
  }
  header.scrolled{ padding:.85rem 0; }
  header .wrap{ display:flex; align-items:center; justify-content:space-between; gap:1rem; }
  .brand{ display:flex; align-items:center; gap:.6rem; }
  .brand img{ height:4.5rem; width:auto; display:block; }
  .footer-brand .brand img{ height:5.1rem; }
  nav.primary{ display:none; align-items:center; gap:2rem; font-weight:500; font-size:.9375rem; color:var(--cocoa-soft); }
  nav.primary a{ transition:color .25s; position:relative; }
  nav.primary a:hover{ color:var(--cocoa); }
  @media (min-width:900px){ nav.primary{ display:flex; } }
  .header-actions{ display:flex; align-items:center; gap:.75rem; }
  .header-cta{ display:none; }
  @media (min-width:900px){ .header-cta{ display:inline-flex; } }
  .icon-btn{
    position:relative; display:flex; width:2.75rem; height:2.75rem; align-items:center; justify-content:center;
    border-radius:50%; border:1px solid var(--line); background:var(--paper); font-size:1.15rem; flex:none;
  }
  .icon-btn .badge{
    position:absolute; top:-.25rem; right:-.25rem; min-width:1.25rem; height:1.25rem; padding:0 .25rem; border-radius:999px;
    background:var(--terracotta); color:#fff; font-size:.6875rem; font-weight:700; display:flex; align-items:center; justify-content:center;
  }
  .menu-btn span{ display:block; width:1.1rem; height:2px; background:var(--cocoa); position:relative; }
  .menu-btn span::before,.menu-btn span::after{ content:""; position:absolute; left:0; width:100%; height:2px; background:var(--cocoa); }
  .menu-btn span::before{ top:-6px; } .menu-btn span::after{ top:6px; }
  @media (min-width:900px){ .menu-btn{ display:none; } }

  .mobile-nav{
    position:fixed; inset:0; z-index:80; background:var(--cream);
    display:flex; flex-direction:column; align-items:center; justify-content:center; gap:1.75rem;
    opacity:0; visibility:hidden; transform:translateY(-12px); transition:opacity .35s var(--ease), transform .35s var(--ease), visibility .35s;
  }
  .mobile-nav.open{ opacity:1; visibility:visible; transform:none; }
  .mobile-nav a{ font-family:var(--serif); font-size:1.5rem; }
  .mobile-nav .close-btn{ position:absolute; top:1.5rem; right:1.5rem; width:2.75rem; height:2.75rem; border-radius:50%; border:1px solid var(--line); display:flex; align-items:center; justify-content:center; }

  /* ---------- page header (non-home pages) ---------- */
  .page-hero{ padding:3.5rem 0 2.5rem; text-align:center; }
  .page-hero .eyebrow{ justify-content:center; }
  .page-hero h1{ margin-top:1rem; font-size:clamp(2rem,4vw,2.75rem); }
  .page-hero p{ margin:1rem auto 0; }

  /* ---------- auth cards ---------- */
  .auth-card{
    background:var(--paper); border:1px solid var(--line); border-radius:var(--radius); padding:2.5rem;
    box-shadow:var(--shadow-sm); margin:2rem auto 5rem;
  }
  .auth-card .foot-link{ text-align:center; margin-top:1.5rem; font-size:.9375rem; color:var(--cocoa-soft); }
  .auth-card .foot-link a{ color:var(--gold-deep); font-weight:600; }
  .checkbox-row{ display:flex; align-items:center; gap:.6rem; margin-bottom:1.25rem; }
  .checkbox-row input{ width:1.1rem; height:1.1rem; }
  .checkbox-row label{ margin:0; font-weight:400; }

  /* ---------- hero ---------- */
  .hero{ position:relative; padding:3rem 0 5rem; overflow:hidden; }
  @media (min-width:900px){ .hero{ padding:4rem 0 7rem; } }
  .hero .wrap{ position:relative; z-index:1; display:grid; gap:3rem; align-items:center; }
  @media (min-width:960px){ .hero .wrap{ grid-template-columns:1.05fr .95fr; gap:2rem; } }
  .hero h1{ margin-top:1.25rem; }
  .hero .lede{ margin-top:1.5rem; }
  .hero-ctas{ display:flex; flex-wrap:wrap; gap:1rem; margin-top:2.25rem; }
  .hero-badges{ display:flex; flex-wrap:wrap; gap:1.5rem; margin-top:2.75rem; }

  @keyframes fade-up{ from{ opacity:0; transform:translateY(18px); } to{ opacity:1; transform:none; } }
  .hero-in{ opacity:0; animation:fade-up .8s var(--ease) forwards; }
  .hero-in.d1{ animation-delay:.05s; } .hero-in.d2{ animation-delay:.15s; }
  .hero-in.d3{ animation-delay:.28s; } .hero-in.d4{ animation-delay:.4s; } .hero-in.d5{ animation-delay:.52s; }
  @media (prefers-reduced-motion:reduce){ .hero-in{ opacity:1; animation:none; } }
  .hero-badge{ display:flex; align-items:center; gap:.6rem; font-size:.875rem; color:var(--cocoa-soft); font-weight:500; }
  .hero-badge .dot{ width:.5rem; height:.5rem; border-radius:50%; background:var(--gold); flex:none; }

  .hero-visual{
    position:relative; aspect-ratio:1/1.05; border-radius:2rem;
    background:
      radial-gradient(120% 100% at 30% 20%, rgba(255,255,255,.55), transparent 60%),
      linear-gradient(155deg, var(--cream-2), #E7C9A0 60%, var(--gold) 130%);
    box-shadow: var(--shadow);
    display:flex; align-items:center; justify-content:center; text-align:center; overflow:hidden;
  }
  .hero-visual::before{
    content:""; position:absolute; inset:0;
    background-image: radial-gradient(rgba(58,38,23,.10) 1.5px, transparent 1.5px);
    background-size:18px 18px; opacity:.5;
  }
  .hero-visual .ph{ position:relative; z-index:1; padding:2rem; }
  .hero-visual .ph .ring{
    width:5.5rem; height:5.5rem; margin:0 auto 1.25rem; border-radius:50%;
    border:1.5px dashed rgba(58,38,23,.35); display:flex; align-items:center; justify-content:center; font-size:1.75rem;
    animation:float-y 4.5s ease-in-out infinite;
  }
  @media (prefers-reduced-motion:reduce){ .hero-visual .ph .ring{ animation:none; } }
  .hero-visual .ph p{ font-family:var(--serif); font-style:italic; color:var(--cocoa-2); font-size:1.0625rem; }
  .hero-ribbon{
    position:absolute; top:1.5rem; right:-2.75rem; background:var(--terracotta); color:#fff;
    padding:.5rem 3rem; font-size:.75rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase;
    transform:rotate(35deg); box-shadow:0 6px 16px rgba(0,0,0,.18);
  }

  /* ---------- features strip ---------- */
  .features{ padding:1rem 0 4rem; position:relative; z-index:1; }
  .features-grid{ display:grid; gap:1rem; grid-template-columns:repeat(2,1fr); }
  @media (min-width:800px){ .features-grid{ grid-template-columns:repeat(4,1fr); } }
  .feature-card{
    background:var(--paper); border:1px solid var(--line); border-radius:var(--radius-sm);
    padding:1.5rem 1.25rem; transition:transform .4s var(--ease), box-shadow .4s var(--ease);
  }
  .feature-card:hover{ transform:translateY(-6px); box-shadow:var(--shadow-sm); }
  .feature-card .ico{ font-size:1.5rem; margin-bottom:.9rem; }
  .feature-card h3{ font-size:1rem; font-family:var(--sans); font-weight:700; }
  .feature-card p{ font-size:.875rem; color:var(--cocoa-soft); margin-top:.4rem; }

  /* ---------- section shell ---------- */
  section{ position:relative; padding:5rem 0; scroll-margin-top:5.5rem; }
  .section-head{ max-width:38rem; margin-bottom:3rem; }
  .section-head.center{ margin-inline:auto; text-align:center; }
  .section-head h2{ margin-top:1rem; }
  .section-head .lede{ margin-top:1rem; }
  .tinted{ background:var(--paper); }

  /* ---------- how it works ---------- */
  .steps{ display:grid; gap:2rem; counter-reset:step; position:relative; }
  @media (min-width:800px){ .steps{ grid-template-columns:repeat(3,1fr); } }
  .step{ position:relative; text-align:center; padding:0 1rem; }
  .step .num{
    width:3.5rem; height:3.5rem; border-radius:50%; background:var(--cocoa); color:var(--cream);
    font-family:var(--serif); font-size:1.375rem; display:flex; align-items:center; justify-content:center; margin:0 auto 1.25rem;
  }
  .step h3{ font-size:1.1875rem; }
  .step p{ font-size:.9375rem; color:var(--cocoa-soft); margin-top:.5rem; max-width:18rem; margin-inline:auto; }
  .step-line{ position:absolute; top:1.75rem; left:calc(50% + 2.5rem); right:calc(-50% + 2.5rem); height:1px; background:repeating-linear-gradient(to right, var(--line) 0 6px, transparent 6px 12px); }
  .step:last-child .step-line{ display:none; }
  @media (max-width:799px){ .step-line{ display:none; } }

  /* ---------- collections / product cards ---------- */
  .cards-grid{ display:grid; gap:1.5rem; grid-template-columns:1fr; }
  @media (min-width:640px){ .cards-grid{ grid-template-columns:repeat(2,1fr); } }
  @media (min-width:1100px){ .cards-grid{ grid-template-columns:repeat(4,1fr); } }
  .p-card{
    background:var(--paper); border:1px solid var(--line); border-radius:var(--radius); overflow:hidden;
    transition:transform .45s var(--ease), box-shadow .45s var(--ease);
    display:flex; flex-direction:column;
  }
  .p-card:hover{ transform:translateY(-8px); box-shadow:var(--shadow); }
  .p-card-media{
    aspect-ratio:4/3; position:relative; display:flex; align-items:center; justify-content:center;
    background:
      radial-gradient(circle at 30% 25%, rgba(255,255,255,.5), transparent 55%),
      linear-gradient(155deg,var(--cream-2), #EAD2AE);
    color:var(--cocoa-faint); font-size:.8125rem; letter-spacing:.04em; overflow:hidden;
  }
  .p-card-media img{ width:100%; height:100%; object-fit:cover; }
  .p-card-media .ph-ico{ font-size:2.5rem; opacity:.55; filter:grayscale(.15); }
  .p-card-media span.tag{
    position:absolute; top:.9rem; left:.9rem; background:rgba(255,253,249,.9); color:var(--cocoa);
    font-size:.6875rem; font-weight:700; letter-spacing:.05em; text-transform:uppercase; padding:.3rem .7rem; border-radius:999px;
  }
  .p-card-body{ padding:1.25rem 1.25rem 1.5rem; display:flex; flex-direction:column; gap:.6rem; flex:1; }
  .p-card-body h3{ font-size:1.1875rem; }
  .p-card-body p{ font-size:.875rem; color:var(--cocoa-soft); flex:1; }
  .p-card-foot{ display:flex; align-items:center; justify-content:space-between; margin-top:.25rem; }
  .p-card-price{ font-weight:700; font-size:.9375rem; color:var(--gold-deep); }
  .p-card-link{ font-size:.8125rem; font-weight:600; display:inline-flex; align-items:center; gap:.3rem; }
  .p-card-link svg{ width:.875rem; height:.875rem; transition:transform .3s var(--ease); }
  .p-card:hover .p-card-link svg{ transform:translateX(3px); }

  /* ---------- instagram cta ---------- */
  .insta-band{
    border-radius:2rem; background:linear-gradient(145deg,var(--cocoa),#241408);
    color:var(--cream); padding:3.5rem 2rem; text-align:center; position:relative; overflow:hidden;
  }
  .insta-band h2{ color:var(--cream); }
  .insta-band p{ color:rgba(251,244,234,.7); max-width:30rem; margin:1rem auto 2rem; }
  .insta-band .btn-primary{ background:var(--cream); color:var(--cocoa); }
  .insta-band .btn-primary:hover{ box-shadow:0 16px 32px -12px rgba(0,0,0,.5); }

  /* ---------- faq ---------- */
  .faq-list{ display:flex; flex-direction:column; gap:.75rem; max-width:44rem; margin-inline:auto; }
  .faq-item{ background:var(--paper); border:1px solid var(--line); border-radius:var(--radius-sm); overflow:hidden; }
  .faq-item summary{
    list-style:none; cursor:pointer; padding:1.25rem 1.5rem; display:flex; align-items:center; justify-content:space-between; gap:1rem;
    font-weight:600; font-size:1rem;
  }
  .faq-item summary::-webkit-details-marker{ display:none; }
  .faq-item summary .plus{ flex:none; width:1.5rem; height:1.5rem; position:relative; }
  .faq-item summary .plus::before,.faq-item summary .plus::after{
    content:""; position:absolute; background:var(--cocoa); border-radius:2px;
  }
  .faq-item summary .plus::before{ left:0; top:50%; width:100%; height:2px; transform:translateY(-50%); }
  .faq-item summary .plus::after{ top:0; left:50%; width:2px; height:100%; transform:translateX(-50%); transition:transform .3s var(--ease); }
  .faq-item[open] summary .plus::after{ transform:translateX(-50%) rotate(90deg); opacity:0; }
  .faq-item .faq-a{ padding:0 1.5rem 1.5rem; font-size:.9375rem; color:var(--cocoa-soft); }

  /* ---------- cart / checkout ---------- */
  .cart-list{ display:flex; flex-direction:column; gap:1rem; margin-bottom:2rem; }
  .cart-row{
    display:flex; gap:1.25rem; background:var(--paper); border:1px solid var(--line); border-radius:var(--radius-sm); padding:1rem;
    align-items:center;
  }
  .cart-row .thumb{ width:5.5rem; height:5.5rem; border-radius:.7rem; overflow:hidden; flex:none; position:relative; background:var(--cream-2); }
  .cart-row .thumb img{ width:100%; height:100%; object-fit:cover; }
  .cart-row .thumb .photo-overlay{ position:absolute; }
  .cart-row .info{ flex:1; min-width:0; }
  .cart-row .info h3{ font-size:1.0625rem; }
  .cart-row .info p{ font-size:.8125rem; color:var(--cocoa-soft); margin-top:.2rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .cart-row .remove-btn{ font-size:.8125rem; color:var(--terracotta); font-weight:600; flex:none; }
  .cart-summary{ background:var(--paper); border:1px solid var(--line); border-radius:var(--radius-sm); padding:1.5rem; }
  .cart-empty{ text-align:center; padding:4rem 1rem; color:var(--cocoa-soft); }
  .cart-empty .ico{ font-size:3rem; margin-bottom:1rem; }

  /* ---------- customizer ---------- */
  .customizer{ display:grid; gap:2.5rem; }
  @media (min-width:960px){ .customizer{ grid-template-columns:1.1fr .9fr; align-items:start; } }
  .stage{
    position:relative; border-radius:var(--radius); overflow:hidden; background:var(--paper); border:1px solid var(--line);
    box-shadow:var(--shadow-sm); aspect-ratio:1/1; display:flex; align-items:center; justify-content:center;
  }
  .stage canvas{ max-width:100%; max-height:100%; }
  .stage .drop-hint{
    position:absolute; inset:1.5rem; border:2px dashed var(--line); border-radius:1rem; display:flex; align-items:center;
    justify-content:center; text-align:center; color:var(--cocoa-faint); font-size:.9375rem; pointer-events:none; padding:1rem;
  }
  .customize-panel{ display:flex; flex-direction:column; gap:1.5rem; }
  .upload-box{
    border:2px dashed var(--line); border-radius:var(--radius-sm); padding:2rem 1.5rem; text-align:center; cursor:pointer;
    transition:border-color .25s, background .25s;
  }
  .upload-box:hover{ border-color:var(--gold); background:var(--paper); }
  .upload-box .ico{ font-size:2rem; margin-bottom:.5rem; }
  .range-row{ display:flex; align-items:center; gap:.75rem; }
  .range-row input[type=range]{ flex:1; padding:0; }
  .range-row .lbl{ font-size:.8125rem; color:var(--cocoa-soft); width:5rem; flex:none; }

  /* ---------- footer ---------- */
  footer{ background:var(--cocoa); color:rgba(251,244,234,.7); padding:4rem 0 2rem; }
  .footer-top{ display:grid; gap:2.5rem; padding-bottom:2.5rem; border-bottom:1px solid rgba(251,244,234,.12); }
  @media (min-width:800px){ .footer-top{ grid-template-columns:1.4fr repeat(2,1fr); } }
  .footer-brand p{ margin-top:1rem; max-width:22rem; font-size:.9375rem; }
  .footer-social{ display:flex; gap:.75rem; margin-top:1.5rem; }
  .footer-social a{ width:2.5rem; height:2.5rem; border-radius:50%; border:1px solid rgba(251,244,234,.25); display:flex; align-items:center; justify-content:center; transition:background .25s, transform .25s; }
  .footer-social a:hover{ background:rgba(251,244,234,.12); transform:translateY(-2px); }
  .footer-col h4{ color:var(--cream); font-size:.8125rem; letter-spacing:.06em; text-transform:uppercase; margin-bottom:1rem; font-weight:700; }
  .footer-col a{ display:block; padding:.3rem 0; font-size:.9375rem; transition:color .25s; }
  .footer-col a:hover{ color:var(--cream); }
  .footer-bottom{ display:flex; flex-direction:column; gap:.75rem; padding-top:1.75rem; font-size:.8125rem; }
  @media (min-width:600px){ .footer-bottom{ flex-direction:row; justify-content:space-between; } }

  /* ---------- floating contact ---------- */
  .float-cta{
    position:fixed; right:1.25rem; bottom:1.25rem; z-index:70;
    display:flex; align-items:center; gap:.6rem; padding:.9rem 1.1rem; border-radius:999px;
    background:var(--cocoa); color:var(--cream); font-weight:600; font-size:.875rem;
    box-shadow:0 12px 28px -10px rgba(58,38,23,.55);
    transition:transform .3s var(--ease), box-shadow .3s var(--ease);
    opacity:0; transform:translateY(12px) scale(.96); pointer-events:none;
  }
  .float-cta.show{ opacity:1; transform:none; pointer-events:auto; }
  .float-cta:hover{ transform:translateY(-3px); box-shadow:0 16px 34px -10px rgba(58,38,23,.65); }
  .float-cta .ico{ font-size:1.125rem; line-height:1; }
  @media (min-width:900px){ .float-cta span.txt{ display:inline; } }

  .sr-only{ position:absolute; width:1px; height:1px; overflow:hidden; clip:rect(0 0 0 0); white-space:nowrap; }
  .skip-link{ position:fixed; top:-3rem; left:1rem; background:var(--cocoa); color:var(--cream); padding:.6rem 1.1rem; border-radius:.5rem; z-index:200; transition:top .25s; }
  .skip-link:focus{ top:1rem; }

  @yield('page_style')
</style>
</head>
<body>

<a href="#main" class="skip-link">Əsas məzmuna keç</a>

<a href="https://www.instagram.com/nefis.az/" target="_blank" rel="noopener" class="float-cta" id="float-cta">
  <span class="ico">📷</span><span class="txt">Instagramda Yaz</span>
</a>

<div class="blob" style="width:26rem;height:26rem;background:var(--gold);top:-8rem;right:-6rem;"></div>
<div class="blob b2" style="width:20rem;height:20rem;background:var(--terracotta);top:20rem;left:-8rem;opacity:.28;"></div>

<header id="site-header">
  <div class="wrap">
    <a href="{{ route('home') }}" class="brand"><img src="/images/logo.svg" alt="Nefis"></a>
    <nav class="primary">
      <a href="{{ route('home') }}#collections">Kolleksiya</a>
      <a href="{{ route('home') }}#how">Necə İşləyir</a>
      <a href="{{ route('home') }}#faq">Suallar</a>
      @auth
        <a href="{{ route('orders.index') }}">Sifarişlərim</a>
      @endauth
    </nav>
    <div class="header-actions">
      <a href="{{ route('cart.index') }}" class="icon-btn" aria-label="Səbət">
        🛍️
        @if(($cartCount ?? 0) > 0)
          <span class="badge">{{ $cartCount }}</span>
        @endif
      </a>
      @auth
        <form method="POST" action="{{ route('logout') }}">
          @csrf
          <button type="submit" class="btn btn-ghost header-cta">Çıxış</button>
        </form>
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
  <a href="{{ route('home') }}#collections">Kolleksiya</a>
  <a href="{{ route('home') }}#how">Necə İşləyir</a>
  <a href="{{ route('home') }}#faq">Suallar</a>
  <a href="{{ route('cart.index') }}">Səbət</a>
  @auth
    <a href="{{ route('orders.index') }}">Sifarişlərim</a>
    <form method="POST" action="{{ route('logout') }}"><button type="submit">Çıxış</button></form>
  @else
    <a href="{{ route('login') }}">Giriş</a>
    <a href="{{ route('register') }}">Qeydiyyat</a>
  @endauth
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
        <a href="{{ route('home') }}#collections">Kolleksiya</a>
        <a href="{{ route('home') }}#how">Necə İşləyir</a>
        <a href="{{ route('home') }}#faq">Suallar</a>
      </div>
      <div class="footer-col">
        <h4>Əlaqə</h4>
        <a href="https://www.instagram.com/nefis.az/" target="_blank" rel="noopener">Instagram</a>
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

  /* mobile nav */
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
</script>
@yield('page_script')
</body>
</html>
