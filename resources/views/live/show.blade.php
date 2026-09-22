@php
  // The picture's shape, for the little pictures on the screens: tall pictures by height, wide ones by width.
  $aspect = $live->aspect();
  [$picW, $picH] = $aspect >= 1 ? [round(9.5 / $aspect, 2), 9.5] : [11, round(11 * $aspect, 2)];
  // The frame the camera view shows while looking for the picture.
  $frameW = 'min(72vw, ' . round(58 / $aspect, 2) . 'vh)';
@endphp
<!DOCTYPE html>
<html lang="az">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
<meta name="theme-color" content="#17110D">
<meta name="robots" content="noindex">
{{-- Yandex Disk refuses requests that carry a referer from another site. --}}
<meta name="referrer" content="no-referrer">
<title>{{ $live->title }} — Canlı şəkil · Nefis</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  :root{ --cream:#F3E6D6; --gold:#D6A35A; --gold2:#F0C987; --ink:#17110D; --ease:cubic-bezier(.34,1.56,.64,1); }
  *{ box-sizing:border-box; }
  html, body{ margin:0; height:100%; background:var(--ink); color:var(--cream); font-family:Inter, system-ui, sans-serif; overflow:hidden;
    -webkit-tap-highlight-color:transparent; }
  #ar{ position:fixed; inset:0; }
  #ar > video{ object-fit:cover; }

  /* ---------- the video over the picture (1000 px = the picture's width in MindAR's space) ---------- */
  #clip-wrap{ display:flex; align-items:center; justify-content:center; pointer-events:none; }
  #clip-wrap:not(.in-ar){ position:fixed; left:-10000px; top:0; }
  #clip{ display:block; object-fit:contain; background:transparent; border-radius:14px;
    box-shadow:0 30px 90px rgba(0,0,0,.45); transform:scale(.55); opacity:0; transition:transform .7s var(--ease), opacity .35s; }
  #clip-wrap.show #clip{ transform:scale(1); opacity:1; }

  /* ---------- screens ---------- */
  .screen{ position:fixed; inset:0; z-index:10; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:1rem;
    padding:max(1.2rem, env(safe-area-inset-top)) 1.4rem max(1.4rem, env(safe-area-inset-bottom)); text-align:center; overflow-y:auto;
    background:radial-gradient(ellipse at 50% 25%, #4a2f1c 0%, #2a1b12 45%, var(--ink) 80%); transition:opacity .4s; }
  .screen[hidden]{ display:none; }
  .logo{ height:2.4rem; }
  h1{ font-family:'Playfair Display', Georgia, serif; font-size:1.75rem; margin:0; line-height:1.2; }
  h1 .spark{ display:inline-block; animation:twinkle 1.8s ease-in-out infinite; }
  p{ margin:0; opacity:.85; line-height:1.55; max-width:22rem; }
  .err{ color:#f2a39a; opacity:1; font-weight:600; }

  /* the demonstration: a phone comes over the picture, looks, and it plays */
  .demo{ position:relative; width:17rem; height:15rem; flex:none; }
  .demo-pic{ position:absolute; left:50%; top:50%; width:{{ $picW }}rem; height:{{ $picH }}rem; transform:translate(-50%,-50%) rotate(-5deg);
    border:4px solid #fff; border-radius:4px; box-shadow:0 18px 40px rgba(0,0,0,.55); background:#fff; }
  .demo-pic img{ width:100%; height:100%; object-fit:cover; display:block; }
  .demo-phone{ position:absolute; left:50%; top:50%; width:7.4rem; height:14rem; border-radius:1.4rem; padding:.4rem; background:#0c0806;
    border:2px solid #4a3a2e; box-shadow:0 22px 50px rgba(0,0,0,.6); animation:phone 6s ease-in-out infinite; }
  .demo-phone::before{ content:''; position:absolute; top:.55rem; left:50%; width:1.8rem; height:.3rem; border-radius:1rem; background:#2a211b; transform:translateX(-50%); z-index:2; }
  .demo-screen{ position:relative; width:100%; height:100%; border-radius:1rem; overflow:hidden; display:flex; align-items:center; justify-content:center;
    background:linear-gradient(160deg, #6b6158, #3e3731); }
  .demo-screen img{ width:62%; box-shadow:0 6px 14px rgba(0,0,0,.5); border:2px solid #fff; transform:rotate(-5deg); animation:alive 6s ease-in-out infinite; }
  .demo-beam{ position:absolute; left:8%; right:8%; height:3px; border-radius:3px; background:var(--gold2); box-shadow:0 0 14px 3px rgba(240,201,135,.8);
    opacity:0; animation:beam 6s ease-in-out infinite; }
  .demo-corners{ position:absolute; inset:18% 14%; opacity:0; animation:corners 6s ease-in-out infinite; }
  .demo-corners i{ position:absolute; width:1rem; height:1rem; border:3px solid var(--gold2); }
  .demo-corners i:nth-child(1){ top:0; left:0; border-right:0; border-bottom:0; border-radius:6px 0 0 0; }
  .demo-corners i:nth-child(2){ top:0; right:0; border-left:0; border-bottom:0; border-radius:0 6px 0 0; }
  .demo-corners i:nth-child(3){ bottom:0; left:0; border-right:0; border-top:0; border-radius:0 0 0 6px; }
  .demo-corners i:nth-child(4){ bottom:0; right:0; border-left:0; border-top:0; border-radius:0 0 6px 0; }
  .demo-play{ position:absolute; left:50%; top:50%; width:2.6rem; height:2.6rem; margin:-1.3rem 0 0 -1.3rem; border-radius:50%; display:grid; place-items:center;
    background:var(--gold); color:var(--ink); font-size:1rem; padding-left:.2rem; transform:scale(0); animation:play 6s var(--ease) infinite; }
  .demo-play::after{ content:''; position:absolute; inset:-4px; border-radius:50%; border:3px solid var(--gold2); opacity:0; animation:ripple 6s ease-out infinite; }
  .demo-spark{ position:absolute; left:50%; top:50%; font-size:1.2rem; opacity:0; animation:fly 6s ease-out infinite; }

  /* the three steps, lit one after another in time with the demonstration */
  .steps{ display:flex; gap:.5rem; margin:0; padding:0; list-style:none; width:100%; max-width:22rem; }
  .steps li{ flex:1; display:flex; flex-direction:column; align-items:center; gap:.4rem; font-size:.78rem; font-weight:600; line-height:1.25; opacity:.9; }
  .steps .ico{ width:3rem; height:3rem; border-radius:1rem; display:grid; place-items:center; font-size:1.5rem; background:rgba(255,255,255,.07);
    border:1px solid rgba(255,255,255,.12); }
  .steps li:nth-child(1) .ico{ animation:step1 6s ease-in-out infinite; }
  .steps li:nth-child(2) .ico{ animation:step2 6s ease-in-out infinite; }
  .steps li:nth-child(3) .ico{ animation:step3 6s ease-in-out infinite; }

  .btn{ appearance:none; border:0; border-radius:999px; padding:1.05rem 2.2rem; font:700 1.05rem Inter, sans-serif; background:var(--cream); color:var(--ink);
    display:inline-flex; align-items:center; gap:.55rem; animation:pulse 2s ease-out infinite; cursor:pointer; }
  .btn:active{ transform:scale(.97); }
  .note{ font-size:.78rem; opacity:.7; max-width:20rem; }

  /* ---------- opening the camera ---------- */
  .spinner{ width:4.2rem; height:4.2rem; border-radius:50%; border:4px solid rgba(255,255,255,.15); border-top-color:var(--gold); animation:spin .9s linear infinite;
    display:grid; place-items:center; }
  .spinner span{ animation:spin .9s linear infinite reverse; font-size:1.5rem; }

  /* ---------- looking for the picture ---------- */
  .scan{ position:fixed; inset:0; z-index:5; pointer-events:none; display:flex; flex-direction:column; align-items:center; justify-content:center; transition:opacity .35s; }
  .scan.off{ opacity:0; }
  .frame{ position:relative; width:{{ $frameW }}; aspect-ratio:1 / {{ $aspect }}; max-height:62vh; animation:breathe 2.4s ease-in-out infinite; }
  .frame i{ position:absolute; width:2.4rem; height:2.4rem; border:4px solid #fff; filter:drop-shadow(0 0 6px rgba(0,0,0,.5)); }
  .frame i:nth-child(1){ top:0; left:0; border-right:0; border-bottom:0; border-radius:14px 0 0 0; }
  .frame i:nth-child(2){ top:0; right:0; border-left:0; border-bottom:0; border-radius:0 14px 0 0; }
  .frame i:nth-child(3){ bottom:0; left:0; border-right:0; border-top:0; border-radius:0 0 0 14px; }
  .frame i:nth-child(4){ bottom:0; right:0; border-left:0; border-top:0; border-radius:0 0 14px 0; }
  .frame .line{ position:absolute; left:6%; right:6%; top:8%; height:3px; border-radius:3px; background:var(--gold2); box-shadow:0 0 18px 4px rgba(240,201,135,.75);
    animation:sweep 2.2s ease-in-out infinite; }
  .scan-card{ position:fixed; left:50%; bottom:max(1.2rem, env(safe-area-inset-bottom)); transform:translateX(-50%); width:min(22rem, calc(100vw - 2rem));
    display:flex; align-items:center; gap:.8rem; padding:.65rem .8rem; border-radius:1.1rem; background:rgba(23,17,13,.78); backdrop-filter:blur(8px);
    -webkit-backdrop-filter:blur(8px); border:1px solid rgba(255,255,255,.12); text-align:left; }
  .scan-card img{ width:3rem; height:3rem; object-fit:cover; border-radius:.5rem; border:2px solid #fff; flex:none; animation:bob 1.6s ease-in-out infinite; }
  .scan-card b{ display:block; font-size:.92rem; }
  .scan-card small{ display:block; font-size:.78rem; opacity:.8; margin-top:.15rem; min-height:1.1em; transition:opacity .3s; }
  .scan-card small.fade{ opacity:0; }

  /* ---------- found ---------- */
  .burst{ position:fixed; left:50%; top:50%; z-index:6; pointer-events:none; }
  .burst span{ position:absolute; left:0; top:0; opacity:0; }
  .burst .ring{ width:10rem; height:10rem; margin:-5rem 0 0 -5rem; border-radius:50%; border:4px solid var(--gold2); }
  .burst .sp{ font-size:1.6rem; margin:-.8rem 0 0 -.8rem; }
  .burst.go .ring{ animation:ring .9s ease-out forwards; }
  .burst.go .ring.r2{ animation-delay:.15s; }
  .burst.go .sp{ animation:burstfly .9s ease-out forwards; }
  .sound{ position:fixed; z-index:7; appearance:none; border:0; cursor:pointer; font:700 .95rem Inter, sans-serif; transition:all .3s var(--ease); }
  .sound[hidden]{ display:none; }
  .sound.muted{ left:50%; bottom:max(1.4rem, env(safe-area-inset-bottom)); transform:translateX(-50%); padding:.85rem 1.4rem; border-radius:999px;
    background:var(--cream); color:var(--ink); animation:pulse 1.6s ease-out infinite; }
  .sound.on{ top:max(1rem, env(safe-area-inset-top)); right:1rem; width:2.8rem; height:2.8rem; border-radius:50%; background:rgba(23,17,13,.6);
    color:#fff; border:1px solid rgba(255,255,255,.3); font-size:1.15rem; }
  .toast{ position:fixed; left:50%; top:max(1.2rem, env(safe-area-inset-top)); z-index:7; transform:translate(-50%, -150%); padding:.6rem 1.1rem; border-radius:999px;
    background:rgba(23,17,13,.8); backdrop-filter:blur(6px); font-size:.88rem; font-weight:600; white-space:nowrap; transition:transform .5s var(--ease); }
  .toast.show{ transform:translate(-50%, 0); }

  /* ---------- motion ---------- */
  @keyframes phone{
    0%{ transform:translate(-5%, 14%) rotate(16deg) scale(.9); opacity:0; }
    8%{ opacity:1; }
    26%, 84%{ transform:translate(-50%, -50%) rotate(0) scale(1); opacity:1; }
    96%, 100%{ transform:translate(-5%, 14%) rotate(16deg) scale(.9); opacity:0; }
  }
  @keyframes beam{ 0%, 27%{ top:20%; opacity:0; } 30%{ opacity:1; } 40%{ top:78%; } 50%{ top:22%; opacity:1; } 54%, 100%{ opacity:0; } }
  @keyframes corners{ 0%, 27%{ opacity:0; transform:scale(1.15); } 32%, 52%{ opacity:1; transform:scale(1); } 56%, 100%{ opacity:0; } }
  @keyframes play{ 0%, 54%{ transform:scale(0); } 60%{ transform:scale(1.2); } 64%, 84%{ transform:scale(1); } 90%, 100%{ transform:scale(0); } }
  @keyframes ripple{ 0%, 60%{ opacity:0; transform:scale(1); } 62%{ opacity:1; } 80%, 100%{ opacity:0; transform:scale(2.2); } }
  @keyframes alive{ 0%, 56%{ filter:none; } 62%, 84%{ filter:brightness(1.15) saturate(1.3); box-shadow:0 0 0 3px var(--gold2), 0 0 24px rgba(240,201,135,.7); } 90%, 100%{ filter:none; } }
  @keyframes fly{ 0%, 58%{ opacity:0; transform:translate(-50%, -50%) scale(.3); } 64%{ opacity:1; } 82%, 100%{ opacity:0; transform:translate(var(--x), var(--y)) scale(1.1); } }
  @keyframes step1{ 0%, 24%{ transform:scale(1.18); background:var(--gold); border-color:var(--gold2); } 30%, 100%{ transform:none; } }
  @keyframes step2{ 0%, 24%{ transform:none; } 30%, 52%{ transform:scale(1.18) rotate(-8deg); background:var(--gold); border-color:var(--gold2); } 58%, 100%{ transform:none; } }
  @keyframes step3{ 0%, 56%{ transform:none; } 62%, 88%{ transform:scale(1.18); background:var(--gold); border-color:var(--gold2); } 94%, 100%{ transform:none; } }
  @keyframes pulse{ 0%{ box-shadow:0 0 0 0 rgba(243,230,214,.55), 0 10px 30px rgba(0,0,0,.35); } 100%{ box-shadow:0 0 0 18px rgba(243,230,214,0), 0 10px 30px rgba(0,0,0,.35); } }
  @keyframes twinkle{ 0%, 100%{ transform:scale(1) rotate(0); opacity:1; } 50%{ transform:scale(1.3) rotate(15deg); opacity:.7; } }
  @keyframes spin{ to{ transform:rotate(360deg); } }
  @keyframes breathe{ 0%, 100%{ transform:scale(1); } 50%{ transform:scale(1.035); } }
  @keyframes sweep{ 0%{ top:8%; } 50%{ top:calc(92% - 3px); } 100%{ top:8%; } }
  @keyframes bob{ 0%, 100%{ transform:translateY(0) rotate(-4deg); } 50%{ transform:translateY(-4px) rotate(4deg); } }
  @keyframes ring{ 0%{ opacity:1; transform:scale(.3); } 100%{ opacity:0; transform:scale(2.4); } }
  @keyframes burstfly{ 0%{ opacity:0; transform:translate(0, 0) scale(.4); } 25%{ opacity:1; } 100%{ opacity:0; transform:translate(var(--x), var(--y)) scale(1.2) rotate(40deg); } }
  @media (prefers-reduced-motion: reduce){ .btn, .sound.muted, .frame, .frame .line, .scan-card img{ animation:none; } }
  @media (max-height:640px){ .demo{ transform:scale(.82); margin:-1.3rem 0; } h1{ font-size:1.5rem; } }
</style>
</head>
<body data-state="start">
  <div id="ar"></div>

  @if($live->isReady())
    {{-- Streamed from Yandex Disk; shown as an HTML video, so no cross-origin permission is needed.
         It keeps its own shape (a 9:16 film stays 9:16), fitted inside the picture. --}}
    <div id="clip-wrap"><video id="clip" src="{{ $live->videoUrl() }}" poster="{{ $live->imageUrl() }}" playsinline webkit-playsinline loop muted preload="metadata" referrerpolicy="no-referrer"></video></div>

    <div class="screen" id="start">
      <img src="/images/logo.svg" alt="Nefis" class="logo">
      <h1>Bu şəkil canlanır <span class="spark">✨</span></h1>
      <div class="demo" aria-hidden="true">
        <div class="demo-pic"><img src="{{ $live->imageUrl() }}" alt=""></div>
        <span class="demo-spark" style="--x:-7rem; --y:-5rem;">✨</span>
        <span class="demo-spark" style="--x:6rem; --y:-6rem;">💫</span>
        <span class="demo-spark" style="--x:6.5rem; --y:4rem;">✨</span>
        <span class="demo-spark" style="--x:-6.5rem; --y:4.5rem;">⭐</span>
        <div class="demo-phone">
          <div class="demo-screen">
            <img src="{{ $live->imageUrl() }}" alt="">
            <span class="demo-corners"><i></i><i></i><i></i><i></i></span>
            <span class="demo-beam"></span>
            <span class="demo-play">▶</span>
          </div>
        </div>
      </div>
      <ol class="steps">
        <li><span class="ico">📷</span>Kameranı aç</li>
        <li><span class="ico">📱</span>Telefonu şəklə tut</li>
        <li><span class="ico">🎬</span>Video canlanır</li>
      </ol>
      <button type="button" class="btn" id="go">📷 Kameranı aç</button>
      <p class="note">🔒 Brauzer kameraya icazə soruşacaq — <b>«İcazə ver»</b> basın. Heç nə yazılmır və saxlanmır.</p>
      <p class="err" id="err" hidden></p>
    </div>

    <div class="screen" id="loading" hidden>
      <div class="spinner"><span>📷</span></div>
      <h1 style="font-size:1.4rem;">Kamera açılır…</h1>
      <p>İcazə pəncərəsi çıxsa, <b>«İcazə ver»</b> basın.</p>
    </div>

    <div class="scan off" id="scan" hidden>
      <div class="frame"><i></i><i></i><i></i><i></i><span class="line"></span></div>
      <div class="scan-card">
        <img src="{{ $live->imageUrl() }}" alt="">
        <div><b id="scan-title">Bu şəkli kameraya göstərin</b><small id="tip">Şəkli çərçivəyə sığışdırın</small></div>
      </div>
    </div>

    <div class="burst" id="burst" aria-hidden="true">
      <span class="ring"></span><span class="ring r2"></span>
      <span class="sp" style="--x:-7rem; --y:-6rem;">✨</span><span class="sp" style="--x:7rem; --y:-5rem;">💫</span>
      <span class="sp" style="--x:-6rem; --y:6rem;">⭐</span><span class="sp" style="--x:6.5rem; --y:6.5rem;">✨</span>
      <span class="sp" style="--x:0; --y:-8.5rem;">🎉</span>
    </div>
    <div class="toast" id="toast">✨ Şəkil tapıldı!</div>
    <button type="button" class="sound muted" id="sound" hidden>🔇 Səsi aç</button>

    <script type="importmap">
    {
      "imports": {
        "three": "https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js",
        "three/addons/": "https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/",
        "mindar-image-three": "https://cdn.jsdelivr.net/npm/mind-ar@1.2.5/dist/mindar-image-three.prod.js"
      }
    }
    </script>
    <script type="module">
      import { MindARThree } from 'mindar-image-three';
      import { CSS3DObject } from 'three/addons/renderers/CSS3DRenderer.js';

      const PIC = {{ $aspect }};          // the picture's height over its width
      const $ = id => document.getElementById(id);
      const video = $('clip'), wrap = $('clip-wrap'), scan = $('scan'), sound = $('sound');
      const state = s => { document.body.dataset.state = s; };

      /* The film in its own shape, as large as fits inside the picture. */
      function fit(){
        const va = video.videoWidth ? video.videoHeight / video.videoWidth : PIC;
        let w = 1000, h = 1000 * va;
        if (h > 1000 * PIC) { h = 1000 * PIC; w = h / va; }
        for (const el of [wrap, video]) { el.style.width = w + 'px'; el.style.height = h + 'px'; }
      }
      fit();
      video.addEventListener('loadedmetadata', fit);

      /* Tips while looking, one after another. */
      const TIPS = ['Şəkli çərçivəyə sığışdırın', 'Telefonu sabit saxlayın', 'Bir az uzaqlaşdırın — şəkil tam görünsün', 'İşıqlı yerdə daha yaxşı işləyir'];
      let tipAt = 0, tipTimer = null;
      function tips(on){
        clearInterval(tipTimer);
        if (!on) return;
        tipTimer = setInterval(() => {
          const t = $('tip');
          t.classList.add('fade');
          setTimeout(() => { tipAt = (tipAt + 1) % TIPS.length; t.textContent = TIPS[tipAt]; t.classList.remove('fade'); }, 300);
        }, 3500);
      }
      let lostTimer = null;
      function looking(again){
        $('scan-title').textContent = again ? 'Şəkli yenidən kameraya göstərin' : 'Bu şəkli kameraya göstərin';
        scan.hidden = false;
        requestAnimationFrame(() => scan.classList.remove('off'));
        tips(true);
        state('scan');
      }

      function soundButton(){
        sound.hidden = broken;
        sound.className = 'sound ' + (video.muted ? 'muted' : 'on');
        sound.textContent = video.muted ? '🔇 Səsi aç' : '🔊';
        sound.setAttribute('aria-label', video.muted ? 'Səsi aç' : 'Səsi bağla');
      }
      sound.addEventListener('click', () => { video.muted = !video.muted; if (!video.muted) video.play().catch(() => {}); soundButton(); });

      let broken = false;
      const failed = () => {
        broken = true;
        sound.hidden = true;
        $('scan-title').textContent = 'Video hazırda açılmır';
        $('tip').textContent = 'Bir az sonra yenidən cəhd edin.';
        tips(false);
      };
      video.addEventListener('error', failed);
      if (video.error) failed();

      $('go').addEventListener('click', async () => {
        $('err').hidden = true;
        // Started once on the tap itself, so the phone lets it play later — without
        // waiting for it: the camera should not wait on the film's first bytes.
        const unlock = video.play();
        if (unlock) unlock.then(() => { if (document.body.dataset.state !== 'found') video.pause(); }).catch(() => {});
        $('start').hidden = true;
        $('loading').hidden = false;
        state('loading');

        const mindar = new MindARThree({
          container: $('ar'),
          imageTargetSrc: @json($live->mindUrl()),
          uiLoading: 'no', uiScanning: 'no', uiError: 'no',
          filterMinCF: 0.0001, filterBeta: 0.001,
        });
        const { renderer, cssRenderer, scene, cssScene, camera } = mindar;
        const anchor = mindar.addCSSAnchor(0);
        wrap.classList.add('in-ar');
        anchor.group.add(new CSS3DObject(wrap));

        let seen = false;
        anchor.onTargetFound = () => {
          clearTimeout(lostTimer);
          scan.classList.add('off');
          tips(false);
          state('found');
          if (broken) return;
          requestAnimationFrame(() => wrap.classList.add('show'));
          if (!seen) {
            seen = true;
            const b = $('burst');
            b.classList.remove('go'); void b.offsetWidth; b.classList.add('go');
            $('toast').classList.add('show');
            setTimeout(() => $('toast').classList.remove('show'), 1800);
            if (navigator.vibrate) navigator.vibrate(40);
          }
          video.muted = false;
          video.play().catch(() => { video.muted = true; video.play().catch(() => {}); }).finally(soundButton);
          soundButton();
        };
        anchor.onTargetLost = () => {
          video.pause();
          wrap.classList.remove('show');
          // A glance away is not worth the whole overlay; a real loss is.
          lostTimer = setTimeout(() => looking(true), 700);
        };

        try {
          await mindar.start();
          $('loading').hidden = true;
          looking(false);
          renderer.setAnimationLoop(() => {
            renderer.render(scene, camera);
            cssRenderer.render(cssScene, camera);
          });
        } catch (e) {
          $('loading').hidden = true;
          $('start').hidden = false;
          state('start');
          $('err').textContent = 'Kamera açılmadı. Brauzerə kameradan istifadəyə icazə verin və yenidən cəhd edin.';
          $('err').hidden = false;
        }
      });
    </script>
  @else
    <div class="screen">
      <img src="/images/logo.svg" alt="Nefis" class="logo">
      <div class="spinner"><span>⏳</span></div>
      <h1>Canlı şəkil hazırlanır</h1>
      <p>Bu şəkil tezliklə canlanacaq. Bir az sonra QR kodu yenidən oxudun.</p>
    </div>
  @endif
</body>
</html>
