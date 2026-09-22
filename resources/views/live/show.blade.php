<!DOCTYPE html>
<html lang="az">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
<meta name="theme-color" content="#17110D">
<meta name="robots" content="noindex">
{{-- Yandex Disk refuses requests that carry a referer from another site. --}}
<meta name="referrer" content="no-referrer">
<title>{{ $live->title }} — Canlı şəkil · Nefis</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=Inter:wght@400;600&display=swap" rel="stylesheet">
<style>
  :root{ --cream:#F3E6D6; --gold:#D6A35A; --ink:#17110D; }
  *{ box-sizing:border-box; }
  html, body{ margin:0; height:100%; background:var(--ink); color:var(--cream); font-family:Inter, system-ui, sans-serif; overflow:hidden; }
  #ar{ position:fixed; inset:0; }
  #ar > video{ object-fit:cover; }
  /* the video laid over the picture: 1000 px is the picture's width in MindAR's CSS space */
  #clip{ width:1000px; height:{{ round(1000 * $live->aspect()) }}px; object-fit:cover; display:block; background:transparent; }
  #clip:not(.in-ar){ position:fixed; left:-10000px; top:0; }
  .screen{ position:fixed; inset:0; z-index:10; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:1.1rem;
    padding:2rem 1.5rem; text-align:center; background:radial-gradient(ellipse at 50% 30%, #3a2618, var(--ink) 70%); }
  .screen[hidden]{ display:none; }
  .logo{ height:3.2rem; }
  h1{ font-family:'Playfair Display', Georgia, serif; font-size:1.7rem; margin:0; }
  p{ margin:0; opacity:.8; line-height:1.55; max-width:22rem; }
  .thumb{ width:9rem; aspect-ratio:1/{{ $live->aspect() }}; object-fit:cover; border-radius:.6rem; box-shadow:0 12px 30px rgba(0,0,0,.5); border:3px solid rgba(255,255,255,.85); }
  .btn{ appearance:none; border:0; border-radius:999px; padding:1rem 2rem; font:600 1rem Inter, sans-serif; background:var(--cream); color:var(--ink);
    box-shadow:0 10px 30px rgba(0,0,0,.35); }
  .hint{ position:fixed; left:50%; bottom:1.5rem; transform:translateX(-50%); z-index:5; padding:.6rem 1rem; border-radius:999px;
    background:rgba(23,17,13,.72); backdrop-filter:blur(6px); font-size:.85rem; width:max-content; max-width:calc(100vw - 2rem); text-align:center; transition:opacity .3s; }
  .hint.found{ opacity:0; }
  .sound{ position:fixed; top:1rem; right:1rem; z-index:5; width:2.8rem; height:2.8rem; border-radius:50%; border:1px solid rgba(255,255,255,.35);
    background:rgba(23,17,13,.6); color:#fff; font-size:1.2rem; }
  .sound[hidden], .hint[hidden]{ display:none; }
  .err{ color:#f2a39a; }
</style>
</head>
<body>
  <div id="ar"></div>

  @if($live->isReady())
    {{-- Streamed from Yandex Disk; shown as an HTML video, so no cross-origin permission is needed. --}}
    <video id="clip" src="{{ $live->videoUrl() }}" poster="{{ $live->imageUrl() }}" playsinline webkit-playsinline loop muted preload="metadata" referrerpolicy="no-referrer"></video>
    <div class="screen" id="start">
      <img src="/images/logo.svg" alt="Nefis" class="logo">
      <img src="{{ $live->imageUrl() }}" alt="" class="thumb">
      <h1>Şəkil canlanır</h1>
      <p>Kameranı açın və telefonu qutunun üzərindəki bu şəklə tutun — video onun üstündə oynayacaq.</p>
      <button type="button" class="btn" id="go">📷 Kameranı aç</button>
      <p class="err" id="err" hidden></p>
    </div>
    <div class="hint" id="hint" hidden>Telefonu şəklə tutun…</div>
    <button type="button" class="sound" id="sound" hidden aria-label="Səs">🔊</button>

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

      const video = document.getElementById('clip');
      const start = document.getElementById('start');
      const hint = document.getElementById('hint');
      const sound = document.getElementById('sound');
      const err = document.getElementById('err');

      document.getElementById('go').addEventListener('click', async () => {
        err.hidden = true;
        // Started once on the tap itself, so the phone lets it play — with sound — later.
        try { await video.play(); video.pause(); } catch (e) {}

        const mindar = new MindARThree({
          container: document.getElementById('ar'),
          imageTargetSrc: @json($live->mindUrl()),
          uiLoading: 'yes', uiScanning: 'no', uiError: 'yes',
          filterMinCF: 0.0001, filterBeta: 0.001,
        });
        const { renderer, cssRenderer, scene, cssScene, camera } = mindar;

        const anchor = mindar.addCSSAnchor(0);
        video.classList.add('in-ar');
        anchor.group.add(new CSS3DObject(video));

        let broken = false;
        anchor.onTargetFound = () => {
          if (! broken) hint.classList.add('found');
          video.muted = false;
          video.play().catch(() => { video.muted = true; video.play().catch(() => {}); });
          sound.hidden = broken;
          sound.textContent = video.muted ? '🔇' : '🔊';
        };
        anchor.onTargetLost = () => { video.pause(); hint.classList.remove('found'); };
        // Until it loads, the poster (the picture itself) sits in its place; if it cannot load, say so.
        const failed = () => {
          broken = true;
          hint.textContent = 'Video hazırda açılmır — bir az sonra yenidən cəhd edin.';
          hint.classList.remove('found');
          sound.hidden = true;
        };
        video.addEventListener('error', failed);
        if (video.error) failed();
        sound.addEventListener('click', () => { video.muted = !video.muted; sound.textContent = video.muted ? '🔇' : '🔊'; });

        try {
          await mindar.start();
          start.hidden = true;
          hint.hidden = false;
          renderer.setAnimationLoop(() => {
            renderer.render(scene, camera);
            cssRenderer.render(cssScene, camera);
          });
        } catch (e) {
          err.textContent = 'Kamera açılmadı. Brauzerə kameradan istifadəyə icazə verin və yenidən cəhd edin.';
          err.hidden = false;
        }
      });
    </script>
  @else
    <div class="screen">
      <img src="/images/logo.svg" alt="Nefis" class="logo">
      <h1>Canlı şəkil hazırlanır</h1>
      <p>Bu şəkil tezliklə canlanacaq. Bir az sonra QR kodu yenidən oxudun.</p>
    </div>
  @endif
</body>
</html>
