<!DOCTYPE html>
<html lang="az">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
{{-- Scene images come from Yandex Disk, which refuses foreign referers. --}}
<meta name="referrer" content="no-referrer">
<title>{{ $product->name }} — Qutu redaktoru</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=Inter:wght@400;500;600;700&family=Great+Vibes&family=Poppins:wght@600&family=Titan+One&family=Bungee&family=Fredoka:wght@600&family=Sacramento&family=Creepster&family=Source+Sans+3:wght@400;600&family=Orbitron:wght@800&family=Anton&family=Cinzel:wght@400;700&family=Bangers&family=Luckiest+Guy&family=Oswald:wght@500;700&family=Bevan&family=Archivo+Black&family=Caveat:wght@600&family=Pacifico&family=Montserrat:wght@300;500&display=swap" rel="stylesheet">
<style>
  :root{
    --ink:#1d2433; --ink-2:#4b5566; --muted:#8a93a3; --line:#e3e6ec; --panel:#ffffff;
    --bg:#eef0f4; --accent:#7c3aed; --accent-2:#a78bfa; --guide:#ff2d8f; --ok:#16a34a; --danger:#dc2626;
  }
  *{ box-sizing:border-box; }
  html,body{ margin:0; height:100%; font-family:Inter, system-ui, sans-serif; color:var(--ink); background:var(--bg); font-size:14px; }
  button, input, select, textarea{ font:inherit; color:inherit; }
  button{ cursor:pointer; }
  [hidden]{ display:none !important; }

  /* ---------- top bar ---------- */
  .topbar{ height:56px; display:flex; align-items:center; gap:.75rem; padding:0 1rem; background:var(--panel); border-bottom:1px solid var(--line); }
  .topbar .back{ color:var(--ink-2); text-decoration:none; padding:.4rem .6rem; border-radius:.5rem; }
  .topbar .back:hover{ background:var(--bg); }
  .topbar .title{ font-weight:600; font-size:15px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .topbar .dim{ color:var(--muted); font-weight:400; margin-left:.35rem; font-size:12px; }
  .topbar .spacer{ flex:1; }
  .tb-group{ display:flex; align-items:center; gap:.25rem; padding:0 .5rem; border-left:1px solid var(--line); }
  .icon-btn{ width:34px; height:34px; border:1px solid transparent; background:none; border-radius:.5rem; display:inline-grid; place-items:center; font-size:16px; }
  .icon-btn:hover{ background:var(--bg); }
  .icon-btn:disabled{ opacity:.35; cursor:default; background:none; }
  .zoom-val{ min-width:3.2rem; text-align:center; color:var(--ink-2); font-variant-numeric:tabular-nums; }
  .btn{ border:1px solid var(--line); background:var(--panel); border-radius:.55rem; padding:.45rem .8rem; font-weight:500; text-decoration:none; color:var(--ink); display:inline-flex; align-items:center; gap:.35rem; }
  .btn:hover{ border-color:#c9ced8; }
  .btn.primary{ background:var(--accent); color:#fff; border-color:var(--accent); }
  .btn.primary:hover{ background:#6d28d9; }
  .btn.danger{ color:var(--danger); }
  .btn.small{ padding:.3rem .55rem; font-size:12.5px; }
  .save-state{ font-size:12px; color:var(--muted); min-width:6.5rem; text-align:right; }
  .save-state.dirty{ color:#b45309; }
  .save-state.ok{ color:var(--ok); }

  /* ---------- layout ---------- */
  .app{ display:grid; grid-template-columns:84px 1fr 330px; height:calc(100% - 56px); }
  .toolbar{ background:var(--panel); border-right:1px solid var(--line); display:flex; flex-direction:column; align-items:stretch; padding:.6rem .4rem; gap:.25rem; }
  .tool{ border:none; background:none; border-radius:.6rem; padding:.55rem .2rem; display:flex; flex-direction:column; align-items:center; gap:.3rem; font-size:11.5px; color:var(--ink-2); }
  .tool .ico{ font-size:20px; line-height:1; }
  .tool:hover{ background:var(--bg); color:var(--ink); }
  .toolbar hr{ border:none; border-top:1px solid var(--line); margin:.35rem .3rem; }

  .workspace{ position:relative; overflow:auto; }
  .workspace-inner{ min-width:100%; min-height:100%; display:grid; place-items:center; padding:40px; }
  .stage{ position:relative; box-shadow:0 10px 40px -12px rgba(20,24,40,.35); background:#fff; }
  .stage canvas{ display:block; width:100%; height:100%; }
  .overlay{ position:absolute; inset:0; }

  /* selection */
  .sel{ position:absolute; outline:1.5px solid var(--accent); pointer-events:none; }
  .sel.locked{ outline-style:dashed; outline-color:var(--muted); }
  .hover-box{ position:absolute; outline:1px solid var(--accent-2); pointer-events:none; }
  .handle{ position:absolute; width:12px; height:12px; background:#fff; border:1.5px solid var(--accent); border-radius:3px; pointer-events:auto; margin:-6px 0 0 -6px; }
  .handle.nw{ left:0; top:0; cursor:nwse-resize; } .handle.ne{ left:100%; top:0; cursor:nesw-resize; }
  .handle.se{ left:100%; top:100%; cursor:nwse-resize; } .handle.sw{ left:0; top:100%; cursor:nesw-resize; }
  .handle.n{ left:50%; top:0; cursor:ns-resize; border-radius:6px; width:18px; height:8px; margin:-4px 0 0 -9px; }
  .handle.s{ left:50%; top:100%; cursor:ns-resize; border-radius:6px; width:18px; height:8px; margin:-4px 0 0 -9px; }
  .handle.e{ left:100%; top:50%; cursor:ew-resize; border-radius:6px; width:8px; height:18px; margin:-9px 0 0 -4px; }
  .handle.w{ left:0; top:50%; cursor:ew-resize; border-radius:6px; width:8px; height:18px; margin:-9px 0 0 -4px; }
  .handle.rot{ left:50%; top:-34px; border-radius:50%; width:18px; height:18px; margin:0 0 0 -9px; cursor:grab; display:grid; place-items:center; font-size:11px; color:var(--accent); }
  .rot-stem{ position:absolute; left:50%; top:-16px; width:1.5px; height:16px; background:var(--accent); }
  .guide-line{ position:absolute; background:var(--guide); pointer-events:none; }
  .guide-line.v{ width:1px; top:0; bottom:0; } .guide-line.h{ height:1px; left:0; right:0; }
  .badge{ position:fixed; z-index:50; background:var(--ink); color:#fff; font-size:11.5px; padding:.25rem .45rem; border-radius:.35rem; pointer-events:none; font-variant-numeric:tabular-nums; white-space:nowrap; }
  .inline-edit{ position:absolute; resize:none; border:none; outline:2px solid var(--accent); background:rgba(255,255,255,.12); padding:0; margin:0; overflow:hidden; line-height:1.2; }

  /* ---------- right panel ---------- */
  .side{ background:var(--panel); border-left:1px solid var(--line); display:flex; flex-direction:column; min-height:0; }
  .props{ flex:1 1 auto; overflow:auto; padding:1rem; border-bottom:1px solid var(--line); min-height:0; }
  .layers{ flex:0 0 38%; overflow:auto; padding:.75rem 1rem 1rem; }
  .side h3{ margin:0 0 .75rem; font-size:13px; text-transform:uppercase; letter-spacing:.06em; color:var(--muted); font-weight:600; }
  .side h4{ margin:1rem 0 .5rem; font-size:12.5px; color:var(--ink-2); font-weight:600; }
  .row{ display:grid; grid-template-columns:repeat(2, 1fr); gap:.5rem; margin-bottom:.5rem; }
  .row.three{ grid-template-columns:repeat(3, 1fr); }
  .row.four{ grid-template-columns:repeat(4, 1fr); }
  .row.one{ grid-template-columns:1fr; }
  .field label{ display:block; font-size:11.5px; color:var(--muted); margin-bottom:.2rem; }
  .field input[type=text], .field input[type=number], .field select, .field textarea{
    width:100%; border:1px solid var(--line); border-radius:.45rem; padding:.4rem .5rem; background:#fff; }
  .field textarea{ min-height:4.2rem; resize:vertical; }
  .field input[type=color]{ width:100%; height:32px; border:1px solid var(--line); border-radius:.45rem; padding:2px; background:#fff; }
  .field input[type=range]{ width:100%; }
  .seg{ display:flex; border:1px solid var(--line); border-radius:.5rem; overflow:hidden; }
  .seg button{ flex:1; border:none; background:#fff; padding:.4rem .3rem; font-size:12.5px; }
  .seg button + button{ border-left:1px solid var(--line); }
  .seg button.on{ background:var(--accent); color:#fff; }
  .actions{ display:flex; flex-wrap:wrap; gap:.4rem; margin-top:.75rem; }
  .hint{ font-size:12px; color:var(--muted); line-height:1.5; }
  .hint kbd{ font-family:inherit; font-size:11px; border:1px solid var(--line); border-bottom-width:2px; border-radius:.3rem; padding:0 .3rem; background:#fafbfc; color:var(--ink-2); }
  .visual-thumb{ width:100%; border:1px solid var(--line); border-radius:.5rem; display:block; margin:.25rem 0 .5rem; }
  .check{ display:flex; align-items:center; gap:.45rem; font-size:13px; }
  .font-list{ max-height:11rem; overflow:auto; border:1px solid var(--line); border-radius:.5rem; }
  .font-list div{ padding:.35rem .6rem; font-size:15px; border-bottom:1px solid var(--line); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .font-list div:last-child{ border-bottom:none; }
  .warn{ background:#fff7ed; border:1px solid #fed7aa; color:#9a3412; border-radius:.5rem; padding:.5rem .6rem; font-size:12.5px; margin:.5rem 0; }

  /* layer list */
  .list{ list-style:none; margin:0; padding:0; }
  .list li{ display:flex; align-items:center; gap:.5rem; padding:.35rem .45rem; border-radius:.5rem; border:1px solid transparent; font-size:13px; cursor:pointer; user-select:none; }
  .list li:hover{ background:var(--bg); }
  .list li.on{ background:#f3eefe; border-color:#ddd0fb; }
  .list li.drop-before{ box-shadow:inset 0 2px 0 var(--accent); }
  .list li.drop-after{ box-shadow:inset 0 -2px 0 var(--accent); }
  .list .thumb{ width:34px; height:34px; flex:none; border-radius:.35rem; border:1px solid var(--line); display:grid; place-items:center; overflow:hidden; font-size:15px;
    background:repeating-conic-gradient(#eef0f3 0 25%, #fff 0 50%) 0 0/10px 10px; }
  .list .thumb img{ max-width:100%; max-height:100%; }
  .list .name{ flex:1; min-width:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .list .kind{ font-size:11px; color:var(--muted); }
  .list .mini{ border:none; background:none; padding:.15rem .2rem; border-radius:.3rem; font-size:13px; color:var(--muted); }
  .list .mini:hover{ background:#e6e8ee; color:var(--ink); }
  .list li.sep{ cursor:default; font-size:11.5px; color:var(--muted); text-transform:uppercase; letter-spacing:.06em; padding:.5rem .45rem .2rem; }
  .list li.sep:hover{ background:none; }
  .list li.hidden-layer .name{ opacity:.45; }

  .toast{ position:fixed; left:50%; bottom:24px; transform:translateX(-50%); background:var(--ink); color:#fff; padding:.6rem 1rem; border-radius:.6rem; font-size:13px; opacity:0; transition:opacity .2s; pointer-events:none; z-index:60; max-width:80vw; }
  .toast.show{ opacity:1; }
  .toast.error{ background:var(--danger); }
  .drop-zone{ position:absolute; inset:0; border:3px dashed var(--accent); background:rgba(124,58,237,.06); display:grid; place-items:center; font-weight:600; color:var(--accent); font-size:16px; z-index:5; pointer-events:none; }
</style>
</head>
<body>

<header class="topbar">
  <a class="back" href="{{ url('/admin/products') }}">← Admin</a>
  <div class="title">{{ $product->name }}<span class="dim">{{ $design['canvas']['width'] }} × {{ $design['canvas']['height'] }}</span></div>
  <div class="spacer"></div>
  <div class="tb-group">
    <button class="icon-btn" id="undo" title="Geri al (Ctrl+Z)">↶</button>
    <button class="icon-btn" id="redo" title="Təkrar et (Ctrl+Y)">↷</button>
  </div>
  <div class="tb-group">
    <button class="icon-btn" id="zoom-out" title="Kiçilt">−</button>
    <span class="zoom-val" id="zoom-val">100%</span>
    <button class="icon-btn" id="zoom-in" title="Böyüt">+</button>
    <button class="btn small" id="zoom-fit" title="Ekrana sığdır (Ctrl+0)">Sığdır</button>
  </div>
  <div class="tb-group">
    <label class="check" title="Vizualı bələdçi kimi üstə göstər (V)"><input type="checkbox" id="guide-on"> Vizual</label>
    <input type="range" id="guide-op" min="10" max="100" value="45" style="width:80px" title="Vizualın şəffaflığı">
  </div>
  <div class="tb-group">
    <span class="save-state" id="save-state">Saxlanılıb</span>
    <a class="btn" href="{{ route('products.customize', $product->slug) }}" target="_blank">Saytda bax ↗</a>
    <button class="btn primary" id="save">Saxla</button>
  </div>
</header>

<div class="app">
  <nav class="toolbar">
    <button class="tool" id="tool-asset" title="Şəffaf PNG qatları yüklə"><span class="ico">🖼️</span>Qat yüklə</button>
    <button class="tool" id="tool-photo" title="Müştərinin şəkli üçün sahə"><span class="ico">👤</span>Foto sahəsi</button>
    <button class="tool" id="tool-text" title="Mətn əlavə et (T)"><span class="ico">T</span>Mətn</button>
    <button class="tool" id="tool-time" title="4 rəqəmli vaxt sahəsi (dəq:san), məs. mahnının 00:34 / 04:39"><span class="ico">⏱</span>Vaxt</button>
    <hr>
    <button class="tool" id="tool-visual" title="Hazır görünüşü (vizual) yüklə"><span class="ico">🎯</span>Vizual</button>
    <button class="tool" id="tool-font" title="Şrift faylı yüklə (TTF, OTF, WOFF)"><span class="ico">Aa</span>Şrift</button>
    <button class="tool" id="tool-test" title="Sınaq üçün şəkil — yalnız burada görünür, saxlanılmır"><span class="ico">🧪</span>Sınaq şəkli</button>
    <input type="file" id="file-asset" accept="image/png,image/webp,image/jpeg" multiple hidden>
    <input type="file" id="file-visual" accept="image/png,image/webp,image/jpeg" hidden>
    <input type="file" id="file-font" accept=".ttf,.otf,.woff,.woff2" hidden>
    <input type="file" id="file-test" accept="image/*" hidden>
  </nav>

  <section class="workspace" id="workspace">
    <div class="workspace-inner" id="workspace-inner">
      <div class="stage" id="stage">
        <canvas id="canvas"></canvas>
        <div class="overlay" id="overlay"></div>
      </div>
    </div>
  </section>

  <aside class="side">
    <div class="props" id="props"></div>
    <div class="layers">
      <h3>Qatlar</h3>
      <ul class="list" id="layer-list"></ul>
    </div>
  </aside>
</div>

<div class="toast" id="toast"></div>

<script src="{{ asset('js/box-render.js') }}"></script>
<script>
(function(){
  'use strict';

  /* ================================================================
     Data
     ================================================================ */
  var CANVAS = @json($design['canvas']);
  var W = CANVAS.width, H = CANVAS.height;
  var ROUTES = {
    save: @json(route('box.save', $product->slug)),
    asset: @json(route('box.asset', $product->slug)),
    visual: @json(route('box.visual', $product->slug)),
    font: @json(route('box.font', $product->slug))
  };
  var CSRF = document.querySelector('meta[name="csrf-token"]').content;

  var FONTS = @json($fonts);
  var doc = {
    layers: @json($design['layers']),
    photos: @json($design['photos']),
    texts: @json($design['texts']),
    box_color: @json($design['box_color'])
  };
  var visualUrl = @json($design['visual']);

  /* Editor-only state, never saved. */
  var images = {};        // url -> {img, alpha, aw, ah}
  var hiddenLayers = {};  // layer index -> true (eye toggle)
  var testPhoto = null;
  var visualImg = null;
  var selection = null;   // {kind:'layer'|'photo'|'text', index}
  var zoom = 1;
  var lastFont = null;

  /* ================================================================
     Elements
     ================================================================ */
  var canvas = document.getElementById('canvas');
  var ctx = canvas.getContext('2d');
  var stage = document.getElementById('stage');
  var overlay = document.getElementById('overlay');
  var workspace = document.getElementById('workspace');
  var props = document.getElementById('props');
  var layerList = document.getElementById('layer-list');
  var saveState = document.getElementById('save-state');
  var guideOn = document.getElementById('guide-on');
  var guideOp = document.getElementById('guide-op');
  canvas.width = W;
  canvas.height = H;

  /* ================================================================
     Helpers
     ================================================================ */
  function clone(o){ return JSON.parse(JSON.stringify(o)); }
  function clamp(v, a, b){ return Math.max(a, Math.min(b, v)); }
  function round(v){ return Math.round(v); }
  function rad(d){ return d * Math.PI / 180; }
  function rotatePoint(px, py, cx, cy, deg){
    var r = rad(deg), c = Math.cos(r), s = Math.sin(r), dx = px - cx, dy = py - cy;
    return { x: cx + dx * c - dy * s, y: cy + dx * s + dy * c };
  }
  function esc(s){ return String(s == null ? '' : s).replace(/[&<>"]/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }

  var toastTimer;
  function toast(msg, isError){
    var t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'toast show' + (isError ? ' error' : '');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function(){ t.className = 'toast'; }, isError ? 5000 : 2200);
  }

  var TIME_RE = /^\d{2}:[0-5]\d$/;
  function formatTime(v){
    var d = String(v || '').replace(/\D/g, '').slice(0, 4);
    return d.length > 2 ? d.slice(0, 2) + ':' + d.slice(2) : d;
  }

  function renderText(t){
    return {
      x: t.x, y: t.y, maxWidth: t.max_width, fontSize: t.font_size, color: t.color,
      align: t.align, rotation: t.rotation, maxLines: t.max_lines,
      fontFamily: t.font_family, fontWeight: t.font_weight || 400,
      strokeColor: t.stroke_color, strokeWidth: t.stroke_width == null ? 0 : t.stroke_width,
      shadowColor: t.shadow_color, shadowBlur: t.shadow_blur, shadowX: t.shadow_x, shadowY: t.shadow_y
    };
  }

  /* ================================================================
     Images & fonts
     ================================================================ */
  /* One pass of a max filter along one axis; run it twice for a square. */
  function dilate(src, w, h, r, dx, dy){
    var out = new Uint8Array(src.length);
    for (var y = 0; y < h; y++) {
      for (var x = 0; x < w; x++) {
        var m = 0;
        for (var k = -r; k <= r && m < 255; k++) {
          var xx = x + k * dx, yy = y + k * dy;
          if (xx < 0 || yy < 0 || xx >= w || yy >= h) continue;
          var v = src[yy * w + xx];
          if (v > m) m = v;
        }
        out[y * w + x] = m;
      }
    }
    return out;
  }

  function loadImage(url){
    if (!url) return null;
    if (images[url]) return images[url];
    var entry = { img: new Image(), alpha: null, aw: 0, ah: 0 };
    images[url] = entry;
    entry.img.onload = function(){
      /* A small alpha map lets clicks pass through transparent parts of a
         layer, so the photo under a full-size fade stays clickable. */
      try {
        var aw = Math.max(1, Math.round(entry.img.naturalWidth / 4));
        var ah = Math.max(1, Math.round(entry.img.naturalHeight / 4));
        var c = document.createElement('canvas'); c.width = aw; c.height = ah;
        var x = c.getContext('2d'); x.drawImage(entry.img, 0, 0, aw, ah);
        var data = x.getImageData(0, 0, aw, ah).data;
        var alpha = new Uint8Array(aw * ah);
        for (var i = 0; i < aw * ah; i++) alpha[i] = data[i * 4 + 3];
        /* Grow the opaque parts by ~32px, so a click on a small hole — the
           triangle cut out of a play button — still picks the layer, while a
           big empty area like the top of a fade lets it through. */
        entry.alpha = dilate(dilate(alpha, aw, ah, 8, 1, 0), aw, ah, 8, 0, 1);
        entry.aw = aw; entry.ah = ah;
      } catch (e) {}
      render();
      renderList();
    };
    entry.img.src = url;
    return entry;
  }

  var loadedFonts = {};
  function loadFont(f){
    if (!f || !f.url || loadedFonts[f.family]) return;
    loadedFonts[f.family] = true;
    try {
      new FontFace(f.family, 'url(' + f.url + ')').load().then(function(face){
        document.fonts.add(face);
        render();
      }).catch(function(){});
    } catch (e) {}
  }
  FONTS.forEach(loadFont);
  document.fonts && document.fonts.ready.then(render);

  function fontFor(t){
    for (var i = 0; i < FONTS.length; i++) {
      var f = FONTS[i];
      if (f.family === t.font_family && (f.weight || 400) === (t.font_weight || 400)) return f;
    }
    return null;
  }

  /* ================================================================
     History
     ================================================================ */
  var history = [], future = [], savedSnapshot = JSON.stringify(doc), commitTimer = null;
  function snapshot(){ return JSON.stringify(doc); }
  function commit(){
    clearTimeout(commitTimer);
    var s = snapshot();
    if (history.length && history[history.length - 1] === s) return;
    history.push(s);
    if (history.length > 150) history.shift();
    future = [];
    updateDirty();
  }
  function commitSoon(){ clearTimeout(commitTimer); commitTimer = setTimeout(commit, 350); }
  function restore(s){
    doc = JSON.parse(s);
    doc.layers.forEach(function(l){ loadImage(l.url); });
    if (selection && !itemOf(selection)) selection = null;
    refresh();
  }
  function undo(){
    commit();
    if (history.length < 2) return;
    future.push(history.pop());
    restore(history[history.length - 1]);
    updateDirty();
  }
  function redo(){
    if (!future.length) return;
    var s = future.pop();
    history.push(s);
    restore(s);
    updateDirty();
  }
  function updateDirty(){
    var dirty = snapshot() !== savedSnapshot;
    saveState.textContent = dirty ? 'Saxlanılmayıb' : 'Saxlanılıb';
    saveState.className = 'save-state' + (dirty ? ' dirty' : '');
    document.getElementById('undo').disabled = history.length < 2;
    document.getElementById('redo').disabled = !future.length;
  }
  history.push(snapshot());

  /* ================================================================
     Geometry
     ================================================================ */
  function itemOf(sel){
    if (!sel) return null;
    var list = sel.kind === 'layer' ? doc.layers : sel.kind === 'photo' ? doc.photos : doc.texts;
    return list[sel.index] || null;
  }

  var measureCtx = document.createElement('canvas').getContext('2d');
  /* Unrotated rectangle + the point it turns about, in canvas pixels. */
  function boxOf(kind, it){
    if (kind === 'text') {
      var lay = NefisBox.measureText(measureCtx, it.default_value || it.placeholder || ' ', renderText(it));
      var w = it.max_width, h = Math.max(lay.height, it.font_size * 1.2);
      var left = it.align === 'left' ? it.x : it.align === 'right' ? it.x - w : it.x - w / 2;
      return { left: left, top: it.y - h / 2, w: w, h: h, rot: it.rotation || 0, px: it.x, py: it.y };
    }
    return { left: it.x, top: it.y, w: it.width, h: it.height, rot: it.rotation || 0,
             px: it.x + it.width / 2, py: it.y + it.height / 2 };
  }

  /* Axis-aligned bounds of the rotated box, for snapping. */
  function aabb(b){
    var pts = [[b.left, b.top], [b.left + b.w, b.top], [b.left + b.w, b.top + b.h], [b.left, b.top + b.h]]
      .map(function(p){ return rotatePoint(p[0], p[1], b.px, b.py, b.rot); });
    var xs = pts.map(function(p){ return p.x; }), ys = pts.map(function(p){ return p.y; });
    var x1 = Math.min.apply(null, xs), x2 = Math.max.apply(null, xs);
    var y1 = Math.min.apply(null, ys), y2 = Math.max.apply(null, ys);
    return { x1: x1, x2: x2, y1: y1, y2: y2, cx: (x1 + x2) / 2, cy: (y1 + y2) / 2 };
  }

  function localPoint(b, x, y){
    return rotatePoint(x, y, b.px, b.py, -b.rot);
  }

  function hitItem(kind, index, x, y){
    var it = (kind === 'layer' ? doc.layers : kind === 'photo' ? doc.photos : doc.texts)[index];
    var b = boxOf(kind, it);
    var p = localPoint(b, x, y);
    if (p.x < b.left || p.x > b.left + b.w || p.y < b.top || p.y > b.top + b.h) return false;
    if (kind === 'photo' && it.shape === 'ellipse') {
      var dx = (p.x - (b.left + b.w / 2)) / (b.w / 2), dy = (p.y - (b.top + b.h / 2)) / (b.h / 2);
      return dx * dx + dy * dy <= 1;
    }
    if (kind === 'layer') {
      var e = images[it.url];
      if (e && e.alpha) {
        var ax = Math.floor((p.x - b.left) / b.w * e.aw), ay = Math.floor((p.y - b.top) / b.h * e.ah);
        /* A faint shadow (like the soft smudge on the Spotify fade) is not
           something anyone means to click. */
        return e.alpha[clamp(ay, 0, e.ah - 1) * e.aw + clamp(ax, 0, e.aw - 1)] > 64;
      }
    }
    return true;
  }

  /* Topmost first: captions, layers over the photo, photo areas, layers under it. */
  function stackTopDown(){
    var out = [], i;
    for (i = doc.texts.length - 1; i >= 0; i--) out.push({ kind: 'text', index: i });
    for (i = doc.layers.length - 1; i >= 0; i--) if (doc.layers[i].placement === 'above') out.push({ kind: 'layer', index: i });
    for (i = doc.photos.length - 1; i >= 0; i--) out.push({ kind: 'photo', index: i });
    for (i = doc.layers.length - 1; i >= 0; i--) if (doc.layers[i].placement === 'below') out.push({ kind: 'layer', index: i });
    return out;
  }

  function hitTest(x, y){
    var stack = stackTopDown();
    for (var i = 0; i < stack.length; i++) {
      var s = stack[i];
      if (s.kind === 'layer' && (doc.layers[s.index].locked || hiddenLayers[s.index])) continue;
      if (hitItem(s.kind, s.index, x, y)) return s;
    }
    return null;
  }

  /* ================================================================
     Rendering
     ================================================================ */
  var editingText = -1;

  function drawPhotoArea(p){
    ctx.save();
    ctx.translate(p.x + p.width / 2, p.y + p.height / 2);
    ctx.rotate(rad(p.rotation || 0));
    ctx.beginPath();
    if (p.shape === 'ellipse') ctx.ellipse(0, 0, p.width / 2, p.height / 2, 0, 0, Math.PI * 2);
    else ctx.rect(-p.width / 2, -p.height / 2, p.width, p.height);
    ctx.clip();
    if (testPhoto) {
      var s = Math.max(p.width / testPhoto.width, p.height / testPhoto.height);
      ctx.rotate(-rad(p.rotation || 0));
      ctx.drawImage(testPhoto, -testPhoto.width * s / 2, -testPhoto.height * s / 2, testPhoto.width * s, testPhoto.height * s);
    } else {
      ctx.fillStyle = '#c9ced8';
      ctx.fillRect(-p.width / 2, -p.height / 2, p.width, p.height);
      ctx.strokeStyle = 'rgba(255,255,255,.55)';
      ctx.lineWidth = 3;
      for (var d = -p.width - p.height; d < p.width + p.height; d += 36) {
        ctx.beginPath(); ctx.moveTo(d, -p.height); ctx.lineTo(d + p.height * 2, p.height); ctx.stroke();
      }
      ctx.fillStyle = 'rgba(29,36,51,.55)';
      ctx.font = '600 ' + Math.max(18, Math.min(p.width, p.height) / 8) + 'px Inter, sans-serif';
      ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
      ctx.fillText(p.label || 'Foto', 0, 0);
    }
    ctx.restore();
  }

  function render(){
    ctx.clearRect(0, 0, W, H);
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, W, H);

    doc.layers.forEach(function(l, i){
      if (l.placement === 'below' && !hiddenLayers[i]) { var e = loadImage(l.url); NefisBox.drawLayer(ctx, e && e.img, l); }
    });
    doc.photos.forEach(drawPhotoArea);
    doc.layers.forEach(function(l, i){
      if (l.placement === 'above' && !hiddenLayers[i]) { var e = loadImage(l.url); NefisBox.drawLayer(ctx, e && e.img, l); }
    });
    doc.texts.forEach(function(t, i){
      if (i === editingText) return;
      NefisBox.drawText(ctx, t.default_value || t.placeholder || '', renderText(t));
    });

    if (guideOn.checked && visualImg && visualImg.complete) {
      ctx.save();
      ctx.globalAlpha = guideOp.value / 100;
      ctx.drawImage(visualImg, 0, 0, W, H);
      ctx.restore();
    }
    renderSelection();
  }

  /* ---------- overlay ---------- */
  var hoverSel = null;
  function placeBox(el, b){
    el.style.left = (b.left * zoom) + 'px';
    el.style.top = (b.top * zoom) + 'px';
    el.style.width = (b.w * zoom) + 'px';
    el.style.height = (b.h * zoom) + 'px';
    el.style.transformOrigin = ((b.px - b.left) * zoom) + 'px ' + ((b.py - b.top) * zoom) + 'px';
    el.style.transform = b.rot ? 'rotate(' + b.rot + 'deg)' : '';
  }

  function renderSelection(){
    overlay.querySelectorAll('.sel, .hover-box').forEach(function(n){ n.remove(); });
    if (hoverSel && !(selection && hoverSel.kind === selection.kind && hoverSel.index === selection.index) && itemOf(hoverSel)) {
      var hb = document.createElement('div');
      hb.className = 'hover-box';
      placeBox(hb, boxOf(hoverSel.kind, itemOf(hoverSel)));
      overlay.appendChild(hb);
    }
    var it = itemOf(selection);
    if (!it) return;
    var b = boxOf(selection.kind, it);
    var el = document.createElement('div');
    var locked = selection.kind === 'layer' && it.locked;
    el.className = 'sel' + (locked ? ' locked' : '');
    placeBox(el, b);
    if (!locked && editingText < 0) {
      var handles = selection.kind === 'text' ? ['nw', 'ne', 'se', 'sw', 'e', 'w'] : ['nw', 'n', 'ne', 'e', 'se', 's', 'sw', 'w'];
      handles.forEach(function(h){
        var d = document.createElement('div');
        d.className = 'handle ' + h;
        d.dataset.handle = h;
        el.appendChild(d);
      });
      var stem = document.createElement('div'); stem.className = 'rot-stem'; el.appendChild(stem);
      var r = document.createElement('div'); r.className = 'handle rot'; r.dataset.handle = 'rot'; r.textContent = '⟳'; el.appendChild(r);
    }
    overlay.appendChild(el);
  }

  function clearGuides(){ overlay.querySelectorAll('.guide-line').forEach(function(n){ n.remove(); }); }
  function showGuides(xs, ys){
    clearGuides();
    xs.forEach(function(x){ var g = document.createElement('div'); g.className = 'guide-line v'; g.style.left = (x * zoom) + 'px'; overlay.appendChild(g); });
    ys.forEach(function(y){ var g = document.createElement('div'); g.className = 'guide-line h'; g.style.top = (y * zoom) + 'px'; overlay.appendChild(g); });
  }

  var badge = null;
  function showBadge(text, cx, cy){
    if (!badge) { badge = document.createElement('div'); badge.className = 'badge'; document.body.appendChild(badge); }
    badge.textContent = text;
    badge.style.left = (cx + 14) + 'px';
    badge.style.top = (cy + 14) + 'px';
    badge.hidden = false;
  }
  function hideBadge(){ if (badge) badge.hidden = true; }

  /* ================================================================
     Zoom
     ================================================================ */
  function setZoom(z){
    zoom = clamp(z, 0.1, 4);
    stage.style.width = (W * zoom) + 'px';
    stage.style.height = (H * zoom) + 'px';
    document.getElementById('zoom-val').textContent = Math.round(zoom * 100) + '%';
    renderSelection();
  }
  function fitZoom(){
    var r = workspace.getBoundingClientRect();
    setZoom(Math.min((r.width - 80) / W, (r.height - 80) / H));
  }
  document.getElementById('zoom-in').onclick = function(){ setZoom(zoom * 1.2); };
  document.getElementById('zoom-out').onclick = function(){ setZoom(zoom / 1.2); };
  document.getElementById('zoom-fit').onclick = fitZoom;
  workspace.addEventListener('wheel', function(e){
    if (!e.ctrlKey) return;
    e.preventDefault();
    setZoom(zoom * (e.deltaY < 0 ? 1.1 : 1 / 1.1));
  }, { passive: false });

  function toCanvas(clientX, clientY){
    var r = stage.getBoundingClientRect();
    return { x: (clientX - r.left) / zoom, y: (clientY - r.top) / zoom };
  }

  /* ================================================================
     Snapping
     ================================================================ */
  function snapTargets(except){
    var xs = [0, W / 2, W], ys = [0, H / 2, H];
    [['layer', doc.layers], ['photo', doc.photos], ['text', doc.texts]].forEach(function(pair){
      pair[1].forEach(function(it, i){
        if (except && except.kind === pair[0] && except.index === i) return;
        if (pair[0] === 'layer' && hiddenLayers[i]) return;
        var a = aabb(boxOf(pair[0], it));
        xs.push(a.x1, a.cx, a.x2); ys.push(a.y1, a.cy, a.y2);
      });
    });
    return { xs: xs, ys: ys };
  }

  function snapDelta(values, targets, threshold){
    var best = null;
    values.forEach(function(v){
      targets.forEach(function(t){
        var d = t - v;
        if (Math.abs(d) <= threshold && (best === null || Math.abs(d) < Math.abs(best.d))) best = { d: d, at: t };
      });
    });
    return best;
  }

  /* ================================================================
     Pointer interaction
     ================================================================ */
  var drag = null;

  stage.addEventListener('pointerdown', function(e){
    if (e.button !== 0) return;
    if (editingText >= 0) { finishInlineEdit(); }
    /* Hand the keyboard back to the canvas, so arrows nudge the selection
       even right after a field or checkbox was used. */
    if (document.activeElement && document.activeElement !== document.body) document.activeElement.blur();
    var p = toCanvas(e.clientX, e.clientY);
    var handle = e.target.dataset && e.target.dataset.handle;

    if (handle && selection) {
      startDrag(handle === 'rot' ? 'rotate' : 'resize', e, p, handle);
      return;
    }

    var hit = hitTest(p.x, p.y);
    /* A click on the current selection keeps it even where it is transparent. */
    if (selection && !hit) {
      var cur = itemOf(selection);
      if (cur && !(selection.kind === 'layer' && cur.locked)) {
        var b = boxOf(selection.kind, cur), lp = localPoint(b, p.x, p.y);
        if (lp.x >= b.left && lp.x <= b.left + b.w && lp.y >= b.top && lp.y <= b.top + b.h) hit = selection;
      }
    }
    select(hit);
    if (hit && !(hit.kind === 'layer' && itemOf(hit).locked)) startDrag('move', e, p);
  });

  stage.addEventListener('dblclick', function(e){
    var p = toCanvas(e.clientX, e.clientY);
    var hit = hitTest(p.x, p.y);
    if (hit && hit.kind === 'text') { select(hit); startInlineEdit(hit.index); }
  });

  stage.addEventListener('pointermove', function(e){
    if (drag) return;
    var p = toCanvas(e.clientX, e.clientY);
    var h = hitTest(p.x, p.y);
    if ((h && hoverSel && h.kind === hoverSel.kind && h.index === hoverSel.index) || (!h && !hoverSel)) return;
    hoverSel = h;
    renderSelection();
  });
  stage.addEventListener('pointerleave', function(){ if (!drag && hoverSel) { hoverSel = null; renderSelection(); } });

  function startDrag(mode, e, p, handle){
    var it = itemOf(selection);
    if (!it) return;
    drag = { mode: mode, handle: handle, start: p, orig: clone(it), box: boxOf(selection.kind, it),
             targets: snapTargets(selection), moved: false };
    stage.setPointerCapture(e.pointerId);
    e.preventDefault();
  }

  stage.addEventListener('pointermove', function(e){
    if (!drag) return;
    var p = toCanvas(e.clientX, e.clientY);
    var it = itemOf(selection);
    var dx = p.x - drag.start.x, dy = p.y - drag.start.y;
    if (!drag.moved && Math.abs(dx) * zoom < 2 && Math.abs(dy) * zoom < 2) return;
    drag.moved = true;
    var threshold = 6 / zoom;

    if (drag.mode === 'move') {
      var a0 = aabb(drag.box);
      var gx = [], gy = [];
      if (!e.altKey) {
        var sx = snapDelta([a0.x1 + dx, a0.cx + dx, a0.x2 + dx], drag.targets.xs, threshold);
        var sy = snapDelta([a0.y1 + dy, a0.cy + dy, a0.y2 + dy], drag.targets.ys, threshold);
        if (sx) { dx += sx.d; gx.push(sx.at); }
        if (sy) { dy += sy.d; gy.push(sy.at); }
      }
      if (e.shiftKey) { if (Math.abs(dx) > Math.abs(dy)) dy = 0; else dx = 0; }
      it.x = drag.orig.x + dx;
      it.y = drag.orig.y + dy;
      showGuides(gx, gy);
      var nb = aabb(boxOf(selection.kind, it));
      showBadge('X ' + round(nb.x1) + '   Y ' + round(nb.y1), e.clientX, e.clientY);
    } else if (drag.mode === 'rotate') {
      var ang = Math.atan2(p.y - drag.box.py, p.x - drag.box.px) * 180 / Math.PI + 90;
      if (e.shiftKey) ang = Math.round(ang / 15) * 15;
      else { var near = Math.round(ang / 45) * 45; if (Math.abs(ang - near) < 4) ang = near; }
      ang = ((ang + 540) % 360) - 180;
      it.rotation = round(ang);
      showBadge(it.rotation + '°', e.clientX, e.clientY);
    } else if (drag.mode === 'resize') {
      resizeFromHandle(it, drag, p, e.shiftKey);
      var bb = boxOf(selection.kind, it);
      showBadge(selection.kind === 'text' ? ('Ölçü ' + round(it.font_size) + '   En ' + round(it.max_width))
                                          : (round(bb.w) + ' × ' + round(bb.h)), e.clientX, e.clientY);
    }
    render();
    renderProps(true);
  });

  stage.addEventListener('pointerup', function(){
    if (!drag) return;
    var it = itemOf(selection);
    if (drag.moved && it) {
      ['x', 'y', 'width', 'height', 'max_width', 'font_size'].forEach(function(k){ if (typeof it[k] === 'number') it[k] = round(it[k]); });
      commit();
    }
    drag = null;
    clearGuides();
    hideBadge();
    render();
    renderProps();
  });

  function resizeFromHandle(it, d, p, shift){
    var b = d.box;
    var dirs = { nw: [-1, -1], n: [0, -1], ne: [1, -1], e: [1, 0], se: [1, 1], s: [0, 1], sw: [-1, 1], w: [-1, 0] }[d.handle];
    var sx = dirs[0], sy = dirs[1];
    var local = rotatePoint(p.x, p.y, b.px, b.py, -b.rot);
    var start = rotatePoint(d.start.x, d.start.y, b.px, b.py, -b.rot);
    var dlx = local.x - start.x, dly = local.y - start.y;

    var w = Math.max(8, b.w + sx * dlx);
    var h = Math.max(8, b.h + sy * dly);
    var corner = sx !== 0 && sy !== 0;

    if (selection.kind === 'text') {
      if (corner) {
        var k = Math.max(0.1, w / b.w);
        it.font_size = Math.max(6, d.orig.font_size * k);
        it.max_width = Math.max(20, d.orig.max_width * k);
      } else {
        it.max_width = Math.max(20, w);
      }
      /* Keep the opposite edge where it was. */
      var nw2 = it.max_width;
      var fixedLeft = sx < 0 ? b.left + b.w : b.left;
      var left = sx < 0 ? fixedLeft - nw2 : fixedLeft;
      var cxLocal = left + nw2 / 2;
      var centre = rotatePoint(cxLocal, b.py, b.px, b.py, b.rot);
      it.x = it.align === 'left' ? centre.x - nw2 / 2 : it.align === 'right' ? centre.x + nw2 / 2 : centre.x;
      it.y = centre.y;
      return;
    }

    var keepRatio = selection.kind === 'layer' ? (corner && !shift) : (corner && shift);
    if (keepRatio) {
      var kk = Math.max(w / b.w, h / b.h);
      w = b.w * kk; h = b.h * kk;
    }
    /* The opposite corner/edge stays fixed in canvas space. */
    var fx = -sx * b.w / 2, fy = -sy * b.h / 2;
    var ncx = fx + sx * w / 2, ncy = fy + sy * h / 2;
    if (sx === 0) ncx = 0;
    if (sy === 0) ncy = 0;
    var c = rotatePoint(b.px + ncx, b.py + ncy, b.px, b.py, b.rot);
    it.width = w; it.height = h;
    it.x = c.x - w / 2; it.y = c.y - h / 2;
  }

  /* ================================================================
     Inline text editing
     ================================================================ */
  var inlineEl = null;
  function startInlineEdit(index){
    var t = doc.texts[index];
    editingText = index;
    render();
    var b = boxOf('text', t);
    inlineEl = document.createElement('textarea');
    inlineEl.className = 'inline-edit';
    placeBox(inlineEl, b);
    inlineEl.style.font = (t.font_weight || 400) + ' ' + (t.font_size * zoom) + 'px ' + NefisBox.fontStack(renderText(t));
    inlineEl.style.color = t.color;
    inlineEl.style.textAlign = t.align;
    if (t.stroke_width > 0 && t.stroke_color) inlineEl.style.webkitTextStroke = (t.stroke_width * zoom) + 'px ' + t.stroke_color;
    inlineEl.value = t.default_value || '';
    overlay.appendChild(inlineEl);
    inlineEl.focus();
    inlineEl.select();
    inlineEl.addEventListener('input', function(){
      if (t.kind === 'time') inlineEl.value = formatTime(inlineEl.value);
      t.default_value = inlineEl.value.replace(/\n/g, ' ');
      renderProps(true);
    });
    inlineEl.addEventListener('keydown', function(e){
      if (e.key === 'Escape' || (e.key === 'Enter' && !e.shiftKey)) { e.preventDefault(); finishInlineEdit(); }
      e.stopPropagation();
    });
    inlineEl.addEventListener('blur', finishInlineEdit);
  }
  function finishInlineEdit(){
    if (editingText < 0) return;
    editingText = -1;
    if (inlineEl) { inlineEl.remove(); inlineEl = null; }
    commit();
    refresh();
  }

  /* ================================================================
     Selection & structure
     ================================================================ */
  function select(sel){
    selection = sel;
    hoverSel = null;
    render();
    renderProps();
    renderList();
  }

  function refresh(){ render(); renderProps(); renderList(); updateDirty(); }

  function addLayer(entry){
    var w = entry.width, h = entry.height;
    /* A full-size asset lands exactly on the canvas; anything else is centred
       and, if too big, shrunk to fit. */
    var k = Math.min(1, W / w, H / h);
    w *= k; h *= k;
    doc.layers.push({
      name: entry.name, image: entry.image, url: entry.url,
      x: round((W - w) / 2), y: round((H - h) / 2), width: round(w), height: round(h),
      rotation: 0, opacity: 100, placement: 'above', locked: false
    });
    loadImage(entry.url);
    select({ kind: 'layer', index: doc.layers.length - 1 });
    commit();
  }

  function addPhoto(){
    var s = 560;
    doc.photos.push({ label: doc.photos.length ? 'Şəkil ' + (doc.photos.length + 1) : 'Şəkil',
      x: round((W - s) / 2), y: round((H - s) / 2), width: s, height: s, rotation: 0, shape: 'rectangle' });
    select({ kind: 'photo', index: doc.photos.length - 1 });
    commit();
  }

  function addText(kind){
    var time = kind === 'time';
    var f = lastFont || fontByName('SF Regular') || FONTS[0] || { family: 'Inter', file: null, weight: 600 };
    doc.texts.push({
      label: time ? 'Vaxt' : 'Mətn ' + (doc.texts.length + 1), kind: time ? 'time' : 'text', fixed: false,
      default_value: time ? '00:00' : 'Mətn', placeholder: '',
      x: round(W / 2), y: round(H / 2), max_width: 600, font_size: 72, color: '#000000', align: 'center', rotation: 0,
      font_family: f.family, font_file: f.file, font_weight: f.weight || 400,
      stroke_color: '#000000', stroke_width: 0, shadow_color: null, shadow_blur: 0, shadow_x: 0, shadow_y: 0,
      max_lines: 1, max_length: time ? 5 : 60, link_key: null
    });
    select({ kind: 'text', index: doc.texts.length - 1 });
    commit();
  }

  function fontByName(n){ for (var i = 0; i < FONTS.length; i++) if (FONTS[i].name === n) return FONTS[i]; return null; }

  function listOf(kind){ return kind === 'layer' ? doc.layers : kind === 'photo' ? doc.photos : doc.texts; }

  function removeSelected(){
    if (!selection) return;
    var list = listOf(selection.kind);
    list.splice(selection.index, 1);
    if (selection.kind === 'layer') hiddenLayers = {};
    selection = null;
    commit();
    refresh();
  }

  function duplicateSelected(){
    if (!selection) return;
    var list = listOf(selection.kind);
    var copy = clone(list[selection.index]);
    copy.x += 20; copy.y += 20;
    if (copy.locked) copy.locked = false;
    list.splice(selection.index + 1, 0, copy);
    select({ kind: selection.kind, index: selection.index + 1 });
    commit();
  }

  /* Moves a layer within its group (under/over the photo), or across it. */
  function moveLayer(index, where){
    var l = doc.layers[index];
    var same = doc.layers.map(function(x, i){ return i; }).filter(function(i){ return doc.layers[i].placement === l.placement; });
    var pos = same.indexOf(index);
    var target;
    if (where === 'front') target = same[same.length - 1];
    else if (where === 'back') target = same[0];
    else if (where === 'up') target = same[Math.min(same.length - 1, pos + 1)];
    else target = same[Math.max(0, pos - 1)];
    if (target === undefined || target === index) return;
    doc.layers.splice(index, 1);
    doc.layers.splice(target, 0, l);
    select({ kind: 'layer', index: target });
    commit();
  }

  /* ================================================================
     Properties panel
     ================================================================ */
  function field(label, html){ return '<div class="field"><label>' + label + '</label>' + html + '</div>'; }
  function num(key, val, step){ return '<input type="number" data-k="' + key + '" value="' + (val == null ? '' : +(+val).toFixed(2)) + '"' + (step ? ' step="' + step + '"' : '') + '>'; }
  function txt(key, val, ph){ return '<input type="text" data-k="' + key + '" value="' + esc(val) + '"' + (ph ? ' placeholder="' + esc(ph) + '"' : '') + '>'; }
  function color(key, val){ return '<input type="color" data-k="' + key + '" value="' + esc((val || '#000000').slice(0, 7)) + '">'; }
  function seg(key, val, opts){
    return '<div class="seg">' + opts.map(function(o){
      return '<button type="button" data-seg="' + key + '" data-v="' + o[0] + '"' + (String(val) === String(o[0]) ? ' class="on"' : '') + '>' + o[1] + '</button>';
    }).join('') + '</div>';
  }

  function renderProps(live){
    /* While dragging, only refresh the numbers so inputs keep their focus. */
    if (live) {
      var it0 = itemOf(selection);
      if (!it0) return;
      props.querySelectorAll('input[data-k]').forEach(function(inp){
        if (document.activeElement === inp || inp.type !== 'number') return;
        var v = it0[inp.dataset.k];
        if (v != null) inp.value = +(+v).toFixed(2);
      });
      if (selection.kind === 'text') {
        var ta = props.querySelector('textarea[data-k="default_value"]');
        if (ta && document.activeElement !== ta) ta.value = it0.default_value || '';
      }
      return;
    }

    var it = itemOf(selection);
    var h = '';

    if (!it) {
      h += '<h3>Dizayn</h3>';
      h += '<h4>Vizual (bələdçi)</h4>';
      if (visualUrl) h += '<img class="visual-thumb" src="' + esc(visualUrl) + '" alt="">';
      else h += '<p class="hint">Hazır görünüşü yükləyin — kataloqda göstəriləcək və burada qatları düzmək üçün üstə qoyula bilər.</p>';
      h += '<div class="actions"><button class="btn small" data-act="visual">' + (visualUrl ? 'Vizualı dəyiş' : 'Vizual yüklə') + '</button>'
         + (visualUrl ? '<button class="btn small" data-act="guide">' + (guideOn.checked ? 'Bələdçini gizlət' : 'Bələdçini göstər') + '</button>' : '') + '</div>';
      h += '<h4>Qutunun rəngi (mokaplarda)</h4><p class="hint" style="margin-top:0">Səhnələrdə "məhsulun rəngini götür" işarəli ağ qutu renderi bu rəngə boyanır. Rəngsiz — ağ qalır.</p>'
         + '<div class="row"><div class="field"><label>Rəng</label><input type="color" data-box-color value="' + esc(doc.box_color || '#ffffff') + '"></div>'
         + '<div class="field"><label>&nbsp;</label><button class="btn small" data-act="box-color-clear"' + (doc.box_color ? '' : ' disabled') + '>' + (doc.box_color ? 'Rəngsiz et' : 'Rəngsiz') + '</button></div></div>';
      h += '<h4>Sınaq şəkli</h4><p class="hint">Foto sahələrində necə görünəcəyini yoxlamaq üçün. Yalnız burada görünür, saxlanılmır.</p>'
         + '<div class="actions"><button class="btn small" data-act="test">' + (testPhoto ? 'Başqa şəkil' : 'Şəkil seç') + '</button>'
         + (testPhoto ? '<button class="btn small" data-act="test-clear">Təmizlə</button>' : '') + '</div>';
      h += '<h4>Şriftlər (' + FONTS.length + ')</h4><div class="font-list">' + FONTS.map(function(f){
            return '<div style="font-family:&quot;' + esc(f.family) + '&quot;, Inter; font-weight:' + (f.weight || 400) + '">' + esc(f.name) + '</div>';
          }).join('') + '</div>';
      h += '<div class="actions"><button class="btn small" data-act="font">Şrift yüklə</button></div>';
      h += '<h4>Qısayollar</h4><p class="hint">'
         + '<kbd>←</kbd><kbd>→</kbd><kbd>↑</kbd><kbd>↓</kbd> 1px sürüşdür, <kbd>Shift</kbd> ilə 10px<br>'
         + '<kbd>Delete</kbd> sil · <kbd>Ctrl</kbd>+<kbd>D</kbd> təkrarla<br>'
         + '<kbd>Ctrl</kbd>+<kbd>Z</kbd> geri · <kbd>Ctrl</kbd>+<kbd>S</kbd> saxla<br>'
         + '<kbd>T</kbd> mətn · <kbd>V</kbd> vizual bələdçi · <kbd>Ctrl</kbd>+təkər zoom<br>'
         + 'Sürüşdürəndə <kbd>Alt</kbd> — yapışmadan, <kbd>Shift</kbd> — düz xətt üzrə<br>'
         + 'Mətnə iki dəfə klik — birbaşa yaz</p>';
      props.innerHTML = h;
      return;
    }

    if (selection.kind === 'layer') {
      h += '<h3>Qat</h3>';
      h += '<div class="row one">' + field('Ad', txt('name', it.name)) + '</div>';
      h += '<div class="row one">' + field('Yeri', seg('placement', it.placement, [['below', 'Fotonun altında'], ['above', 'Fotonun üstündə']])) + '</div>';
      h += '<div class="row four">' + field('X', num('x', it.x)) + field('Y', num('y', it.y)) + field('En', num('width', it.width)) + field('Hünd.', num('height', it.height)) + '</div>';
      h += '<div class="row">' + field('Bucaq °', num('rotation', it.rotation)) + field('Şəffaflıq ' + it.opacity + '%', '<input type="range" min="0" max="100" data-k="opacity" value="' + it.opacity + '">') + '</div>';
      h += '<label class="check"><input type="checkbox" data-k="locked"' + (it.locked ? ' checked' : '') + '> Kilidlə (kətanda seçilmir, yerindən oynamır)</label>';
      h += '<h4>Sıra</h4><div class="actions">'
         + '<button class="btn small" data-act="front">Ən önə</button><button class="btn small" data-act="up">Bir irəli</button>'
         + '<button class="btn small" data-act="down">Bir geri</button><button class="btn small" data-act="back">Ən arxaya</button></div>';
      h += '<h4>Ölçü</h4><div class="actions"><button class="btn small" data-act="natural">Orijinal ölçü</button><button class="btn small" data-act="fill">Bütün kətan</button>'
         + '<button class="btn small" data-act="center-h">Üfüqi mərkəz</button><button class="btn small" data-act="center-v">Şaquli mərkəz</button></div>';
      h += '<div class="actions"><button class="btn small" data-act="dup">Təkrarla</button><button class="btn small danger" data-act="del">Sil</button></div>';
    } else if (selection.kind === 'photo') {
      h += '<h3>Foto sahəsi</h3>';
      h += '<p class="hint">Müştərinin yüklədiyi şəkil bura düşür. "Fotonun üstündə" olan qatlar onu örtür.</p>';
      h += '<div class="row one">' + field('Müştəriyə görünən ad', txt('label', it.label, 'Şəkil')) + '</div>';
      h += '<div class="row one">' + field('Forma', seg('shape', it.shape, [['rectangle', 'Düzbucaqlı'], ['ellipse', 'Oval']])) + '</div>';
      h += '<div class="row four">' + field('X', num('x', it.x)) + field('Y', num('y', it.y)) + field('En', num('width', it.width)) + field('Hünd.', num('height', it.height)) + '</div>';
      h += '<div class="row">' + field('Bucaq °', num('rotation', it.rotation)) + '</div>';
      h += '<div class="actions"><button class="btn small" data-act="fill">Bütün kətan</button><button class="btn small" data-act="center-h">Üfüqi mərkəz</button><button class="btn small" data-act="center-v">Şaquli mərkəz</button></div>';
      h += '<div class="actions"><button class="btn small" data-act="dup">Təkrarla</button><button class="btn small danger" data-act="del">Sil</button></div>';
    } else {
      var f = fontFor(it);
      h += '<h3>Mətn</h3>';
      var isTime = it.kind === 'time';
      h += '<div class="row one">' + field('Növ', seg('kind', it.kind || 'text', [['text', 'Mətn'], ['time', 'Vaxt (dəq:san)']])) + '</div>';
      h += '<label class="check" style="margin-bottom:.6rem"><input type="checkbox" data-k="fixed"' + (it.fixed ? ' checked' : '') + '> Müştəri dəyişə bilməz (sabit)</label>';
      if (it.fixed) h += '<p class="hint" style="margin-top:0">Qutuda olduğu kimi çap olunur; müştəriyə sahə göstərilmir.</p>';
      else h += '<div class="row one">' + field('Müştəriyə görünən sahə adı', txt('label', it.label, isTime ? 'Məs. Başlanğıc vaxtı' : 'Məs. Mahnının adı')) + '</div>';
      h += '<div class="row one">' + (isTime
        ? field('Vaxt (ilkin dəyər)', '<input type="text" data-k="default_value" inputmode="numeric" maxlength="5" placeholder="00:00" value="' + esc(it.default_value) + '">')
        : field('Mətn (ilkin dəyər)', '<textarea data-k="default_value">' + esc(it.default_value) + '</textarea>')) + '</div>';
      h += '<div class="row one">' + field('Şrift', '<select data-k="font">' + FONTS.map(function(fo, i){
            return '<option value="' + i + '"' + (f === fo ? ' selected' : '') + '>' + esc(fo.name) + '</option>';
          }).join('') + (f ? '' : '<option selected>' + esc(it.font_family || 'Inter') + '</option>') + '</select>') + '</div>';
      h += '<div class="row">' + field('Ölçü', num('font_size', it.font_size)) + field('Rəng', color('color', it.color)) + '</div>';
      h += '<div class="row one">' + field('Düzülüş', seg('align', it.align, [['left', 'Sol'], ['center', 'Mərkəz'], ['right', 'Sağ']])) + '</div>';
      h += '<div class="row four">' + field('X', num('x', it.x)) + field('Y', num('y', it.y)) + field('Maks en', num('max_width', it.max_width)) + field('Bucaq °', num('rotation', it.rotation)) + '</div>';
      if (!isTime) h += '<div class="row">' + field('Maks sətir', num('max_lines', it.max_lines)) + field('Maks simvol', num('max_length', it.max_length)) + '</div>';
      h += '<h4>Kontur</h4><div class="row">' + field('Rəng', color('stroke_color', it.stroke_color)) + field('Qalınlıq', num('stroke_width', it.stroke_width, 0.5)) + '</div>';
      var sh = it.shadow_color || '';
      var shAlpha = sh.length === 9 ? Math.round(parseInt(sh.slice(7, 9), 16) / 2.55) : (sh ? 100 : 0);
      h += '<h4>Kölgə</h4><div class="row">' + field('Rəng', color('shadow_rgb', sh || '#000000')) + field('Görünmə ' + shAlpha + '%', '<input type="range" min="0" max="100" data-k="shadow_alpha" value="' + shAlpha + '">') + '</div>';
      h += '<div class="row three">' + field('Bulanıq', num('shadow_blur', it.shadow_blur)) + field('X', num('shadow_x', it.shadow_x)) + field('Y', num('shadow_y', it.shadow_y)) + '</div>';
      h += '<h4>Təkrarlanan ad</h4><div class="row one">' + field('Qrup', txt('link_key', it.link_key, 'məs. ad — eyni qrupdakılar bir sahədən dolur')) + '</div>';
      h += '<div class="actions"><button class="btn small" data-act="center-h">Üfüqi mərkəz</button><button class="btn small" data-act="dup">Təkrarla</button><button class="btn small danger" data-act="del">Sil</button></div>';
    }
    props.innerHTML = h;
  }

  function hexAlpha(rgb, pct){
    if (!pct) return null;
    return pct >= 100 ? rgb : rgb + ('0' + Math.round(pct * 2.55).toString(16)).slice(-2);
  }

  props.addEventListener('input', function(e){
    var el = e.target, k = el.dataset.k;
    if (el.dataset.boxColor !== undefined) { doc.box_color = el.value; commitSoon(); return; }
    if (!k) return;
    var it = itemOf(selection);
    if (!it) return;
    if (k === 'locked') it.locked = el.checked;
    else if (k === 'fixed') { it.fixed = el.checked; renderProps(); renderList(); }
    else if (k === 'font') {
      var f = FONTS[+el.value];
      if (f) { it.font_family = f.family; it.font_file = f.file; it.font_weight = f.weight || 400; lastFont = f; loadFont(f); }
    } else if (k === 'shadow_rgb' || k === 'shadow_alpha') {
      var rgb = props.querySelector('[data-k="shadow_rgb"]').value;
      var pct = +props.querySelector('[data-k="shadow_alpha"]').value;
      it.shadow_color = hexAlpha(rgb, pct);
      el.closest('.field').querySelector('label').textContent = k === 'shadow_alpha' ? 'Görünmə ' + pct + '%' : 'Rəng';
    } else if (el.type === 'number' || el.type === 'range') {
      if (el.value === '') return;
      it[k] = +el.value;
      if (k === 'opacity') el.closest('.field').querySelector('label').textContent = 'Şəffaflıq ' + it.opacity + '%';
    } else if (k === 'default_value') {
      if (it.kind === 'time') el.value = formatTime(el.value);
      it.default_value = el.value.replace(/\n/g, ' ');
    }
    else it[k] = el.value === '' && k === 'link_key' ? null : el.value;
    render();
    if (k === 'name' || k === 'label' || k === 'default_value') renderList();
    commitSoon();
  });
  props.addEventListener('change', function(){ commit(); });

  props.addEventListener('click', function(e){
    var b = e.target.closest('button');
    if (!b) return;
    var it = itemOf(selection);
    if (b.dataset.seg === 'kind' && it) {
      it.kind = b.dataset.v;
      if (it.kind === 'time') {
        it.default_value = TIME_RE.test(it.default_value || '') ? it.default_value : '00:00';
        it.max_lines = 1;
        it.max_length = 5;
        if (!it.label || /^Mətn \d+$/.test(it.label)) it.label = 'Vaxt';
      } else if (it.max_length === 5) {
        it.max_length = 60;
      }
      commit(); refresh(); return;
    }
    if (b.dataset.seg && it) {
      it[b.dataset.seg] = b.dataset.v;
      if (b.dataset.seg === 'align' && selection.kind === 'text') {
        /* Keep the text box where it is; only the anchor moves. */
        var box = boxOf('text', it);
        var mid = box.left + box.w / 2;
        it.x = b.dataset.v === 'left' ? mid - it.max_width / 2 : b.dataset.v === 'right' ? mid + it.max_width / 2 : mid;
      }
      commit(); refresh(); return;
    }
    var act = b.dataset.act;
    if (!act) return;
    if (act === 'visual') return document.getElementById('file-visual').click();
    if (act === 'guide') { guideOn.checked = !guideOn.checked; render(); renderProps(); return; }
    if (act === 'test') return document.getElementById('file-test').click();
    if (act === 'test-clear') { testPhoto = null; render(); renderProps(); return; }
    if (act === 'font') return document.getElementById('file-font').click();
    if (act === 'box-color-clear') { doc.box_color = null; commit(); renderProps(); return; }
    if (act === 'del') return removeSelected();
    if (act === 'dup') return duplicateSelected();
    if (['front', 'back', 'up', 'down'].indexOf(act) >= 0) return moveLayer(selection.index, act);
    if (!it) return;
    var bx = boxOf(selection.kind, it);
    if (act === 'natural') {
      var e2 = images[it.url];
      if (e2 && e2.img.naturalWidth) { var cx = it.x + it.width / 2, cy = it.y + it.height / 2; it.width = e2.img.naturalWidth; it.height = e2.img.naturalHeight; it.x = round(cx - it.width / 2); it.y = round(cy - it.height / 2); }
    } else if (act === 'fill') { it.x = 0; it.y = 0; it.width = W; it.height = H; it.rotation = 0; }
    else if (act === 'center-h') { var a = aabb(bx); if (selection.kind === 'text') it.x += W / 2 - a.cx; else it.x = round(it.x + W / 2 - a.cx); }
    else if (act === 'center-v') { var a2 = aabb(bx); it.y = round(it.y + H / 2 - a2.cy); }
    commit(); refresh();
  });

  /* ================================================================
     Layer list
     ================================================================ */
  function renderList(){
    var h = '';
    var rows = [];
    for (var i = doc.texts.length - 1; i >= 0; i--) rows.push({ kind: 'text', index: i });
    var above = [], below = [];
    doc.layers.forEach(function(l, j){ (l.placement === 'above' ? above : below).push(j); });
    above.reverse().forEach(function(j){ rows.push({ kind: 'layer', index: j }); });
    rows.push({ sep: 'Müştərinin şəkli' });
    for (var p = doc.photos.length - 1; p >= 0; p--) rows.push({ kind: 'photo', index: p });
    below.reverse().forEach(function(j){ rows.push({ kind: 'layer', index: j }); });

    rows.forEach(function(r){
      if (r.sep) { h += '<li class="sep" data-sep="1">' + r.sep + ' ↓ altında / ↑ üstündə</li>'; return; }
      var it = listOf(r.kind)[r.index];
      var on = selection && selection.kind === r.kind && selection.index === r.index;
      var thumb, name, kind;
      if (r.kind === 'layer') { thumb = '<img src="' + esc(it.url) + '" alt="">'; name = it.name || 'Qat'; kind = it.placement === 'above' ? 'fotonun üstündə' : 'fotonun altında'; }
      else if (r.kind === 'photo') { thumb = '👤'; name = it.label || 'Foto'; kind = it.shape === 'ellipse' ? 'oval' : 'düzbucaqlı'; }
      else {
        thumb = it.kind === 'time' ? '⏱' : '<span style="font-family:&quot;' + esc(it.font_family) + '&quot;,Inter;font-weight:700">T</span>';
        name = it.default_value || it.label || 'Mətn';
        kind = it.fixed ? '🔒 sabit — müştəri dəyişmir' : (it.kind === 'time' ? 'vaxt · ' : '') + (it.label || 'mətn');
      }
      h += '<li draggable="true" data-kind="' + r.kind + '" data-index="' + r.index + '" class="' + (on ? 'on' : '') + (r.kind === 'layer' && hiddenLayers[r.index] ? ' hidden-layer' : '') + '">'
         + '<span class="thumb">' + thumb + '</span>'
         + '<span class="name">' + esc(name) + '<br><span class="kind">' + esc(kind) + '</span></span>';
      if (r.kind === 'layer') {
        h += '<button class="mini" data-eye="' + r.index + '" title="Redaktorda gizlət/göstər">' + (hiddenLayers[r.index] ? '🙈' : '👁') + '</button>'
           + '<button class="mini" data-lock="' + r.index + '" title="Kilid">' + (it.locked ? '🔒' : '🔓') + '</button>';
      }
      h += '</li>';
    });
    layerList.innerHTML = h || '<li class="sep">Hələ heç nə yoxdur</li>';
  }

  layerList.addEventListener('click', function(e){
    var eye = e.target.dataset.eye, lock = e.target.dataset.lock;
    if (eye !== undefined) { hiddenLayers[eye] = !hiddenLayers[eye]; render(); renderList(); return; }
    if (lock !== undefined) { doc.layers[lock].locked = !doc.layers[lock].locked; commit(); refresh(); return; }
    var li = e.target.closest('li[data-kind]');
    if (li) select({ kind: li.dataset.kind, index: +li.dataset.index });
  });

  /* Drag to reorder. A layer dropped across the photo row switches sides. */
  var dragRow = null;
  layerList.addEventListener('dragstart', function(e){
    var li = e.target.closest('li[data-kind]');
    if (!li) return;
    dragRow = { kind: li.dataset.kind, index: +li.dataset.index };
    e.dataTransfer.effectAllowed = 'move';
  });
  layerList.addEventListener('dragover', function(e){
    if (!dragRow) return;
    var li = e.target.closest('li');
    layerList.querySelectorAll('.drop-before, .drop-after').forEach(function(n){ n.classList.remove('drop-before', 'drop-after'); });
    if (!li) return;
    e.preventDefault();
    var r = li.getBoundingClientRect();
    li.classList.add(e.clientY < r.top + r.height / 2 ? 'drop-before' : 'drop-after');
  });
  layerList.addEventListener('dragend', function(){ dragRow = null; layerList.querySelectorAll('.drop-before, .drop-after').forEach(function(n){ n.classList.remove('drop-before', 'drop-after'); }); });
  layerList.addEventListener('drop', function(e){
    if (!dragRow) return;
    e.preventDefault();
    var li = e.target.closest('li');
    if (!li) return;
    var before = e.clientY < li.getBoundingClientRect().top + li.getBoundingClientRect().height / 2;
    var src = dragRow; dragRow = null;

    if (src.kind === 'layer') {
      var moving = doc.layers[src.index];
      var placement, anchorIndex = null;
      if (li.dataset.sep || li.dataset.kind === 'photo') {
        placement = before ? 'above' : 'below';
      } else if (li.dataset.kind === 'layer') {
        var target = doc.layers[+li.dataset.index];
        placement = target.placement;
        anchorIndex = +li.dataset.index;
      } else { placement = 'above'; }
      doc.layers.splice(src.index, 1);
      var insertAt;
      if (anchorIndex !== null) {
        if (anchorIndex > src.index) anchorIndex--;
        /* The list shows the top first, so "before" means higher in the stack. */
        insertAt = before ? anchorIndex + 1 : anchorIndex;
      } else if (placement === 'above') {
        var firstAbove = doc.layers.findIndex(function(l){ return l.placement === 'above'; });
        insertAt = firstAbove < 0 ? doc.layers.length : firstAbove;
      } else {
        var lastBelow = -1;
        doc.layers.forEach(function(l, i){ if (l.placement === 'below') lastBelow = i; });
        insertAt = lastBelow + 1;
      }
      moving.placement = placement;
      doc.layers.splice(insertAt, 0, moving);
      hiddenLayers = {};
      select({ kind: 'layer', index: insertAt });
      commit();
      return;
    }

    if (li.dataset.kind !== src.kind) return;
    var list = listOf(src.kind);
    var to = +li.dataset.index;
    var item = list.splice(src.index, 1)[0];
    if (to > src.index) to--;
    var at = before ? to + 1 : to;
    list.splice(at, 0, item);
    select({ kind: src.kind, index: at });
    commit();
  });

  /* ================================================================
     Uploads
     ================================================================ */
  function post(url, formData){
    return fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }, body: formData, credentials: 'same-origin' })
      .then(function(r){
        return r.json().catch(function(){ return {}; }).then(function(body){
          if (!r.ok) throw new Error(body.message || ('Xəta ' + r.status));
          return body;
        });
      });
  }

  function uploadAssets(files){
    var list = Array.prototype.slice.call(files).filter(function(f){ return /^image\//.test(f.type); });
    if (!list.length) return;
    toast('Yüklənir…');
    list.reduce(function(chain, file){
      return chain.then(function(){
        var fd = new FormData(); fd.append('file', file);
        return post(ROUTES.asset, fd).then(addLayer);
      });
    }, Promise.resolve()).then(function(){ toast(list.length > 1 ? list.length + ' qat əlavə olundu' : 'Qat əlavə olundu'); })
      .catch(function(err){ toast(err.message, true); });
  }

  document.getElementById('tool-asset').onclick = function(){ document.getElementById('file-asset').click(); };
  document.getElementById('file-asset').onchange = function(){ uploadAssets(this.files); this.value = ''; };
  document.getElementById('tool-photo').onclick = addPhoto;
  document.getElementById('tool-text').onclick = function(){ addText('text'); };
  document.getElementById('tool-time').onclick = function(){ addText('time'); };
  document.getElementById('tool-visual').onclick = function(){ document.getElementById('file-visual').click(); };
  document.getElementById('tool-font').onclick = function(){ document.getElementById('file-font').click(); };
  document.getElementById('tool-test').onclick = function(){ document.getElementById('file-test').click(); };

  document.getElementById('file-visual').onchange = function(){
    var file = this.files[0]; this.value = '';
    if (!file) return;
    var fd = new FormData(); fd.append('file', file);
    toast('Vizual yüklənir…');
    post(ROUTES.visual, fd).then(function(res){
      visualUrl = res.url;
      visualImg = new Image(); visualImg.onload = render; visualImg.src = visualUrl;
      guideOn.checked = true;
      toast(res.warning || 'Vizual yükləndi — kataloqda da bu şəkil görünəcək', !!res.warning);
      renderProps();
    }).catch(function(err){ toast(err.message, true); });
  };

  document.getElementById('file-font').onchange = function(){
    var file = this.files[0]; this.value = '';
    if (!file) return;
    var name = prompt('Şriftin adı (siyahıda belə görünəcək):', file.name.replace(/\.[^.]+$/, '').replace(/[-_]+/g, ' '));
    if (!name) return;
    var fd = new FormData(); fd.append('file', file); fd.append('name', name.trim());
    toast('Şrift yüklənir…');
    post(ROUTES.font, fd).then(function(f){
      FONTS.push(f);
      FONTS.sort(function(a, b){ return a.name.localeCompare(b.name); });
      loadFont(f);
      var it = itemOf(selection);
      if (selection && selection.kind === 'text') { it.font_family = f.family; it.font_file = f.file; it.font_weight = f.weight; lastFont = f; commit(); }
      toast('"' + f.name + '" əlavə olundu');
      refresh();
    }).catch(function(err){ toast(err.message, true); });
  };

  document.getElementById('file-test').onchange = function(){
    var file = this.files[0]; this.value = '';
    if (!file) return;
    var r = new FileReader();
    r.onload = function(ev){ var img = new Image(); img.onload = function(){ testPhoto = img; render(); renderProps(); }; img.src = ev.target.result; };
    r.readAsDataURL(file);
  };

  /* Drop PNGs straight onto the canvas, as in Canva. */
  var dropZone = null;
  workspace.addEventListener('dragover', function(e){
    if (dragRow || !e.dataTransfer || Array.prototype.indexOf.call(e.dataTransfer.types, 'Files') < 0) return;
    e.preventDefault();
    if (!dropZone) { dropZone = document.createElement('div'); dropZone.className = 'drop-zone'; dropZone.textContent = 'Qat kimi əlavə etmək üçün buraxın'; workspace.appendChild(dropZone); }
  });
  workspace.addEventListener('dragleave', function(e){ if (dropZone && !workspace.contains(e.relatedTarget)) { dropZone.remove(); dropZone = null; } });
  workspace.addEventListener('drop', function(e){
    if (dragRow) return;
    e.preventDefault();
    if (dropZone) { dropZone.remove(); dropZone = null; }
    if (e.dataTransfer.files.length) uploadAssets(e.dataTransfer.files);
  });

  /* ================================================================
     Save
     ================================================================ */
  function save(){
    if (editingText >= 0) finishInlineEdit();
    var badTime = doc.texts.findIndex(function(t){ return t.kind === 'time' && !TIME_RE.test(t.default_value || ''); });
    if (badTime >= 0) {
      select({ kind: 'text', index: badTime });
      toast('Vaxt dəq:san şəklində olmalıdır, məs. 03:45', true);
      return;
    }
    commit();
    var payload = {
      layers: doc.layers.map(function(l){ return { name: l.name, image: l.image, x: l.x, y: l.y, width: l.width, height: l.height,
        rotation: l.rotation || 0, opacity: l.opacity == null ? 100 : l.opacity, placement: l.placement, locked: !!l.locked }; }),
      photos: doc.photos.map(function(p){ return { label: p.label, x: p.x, y: p.y, width: p.width, height: p.height, rotation: p.rotation || 0, shape: p.shape }; }),
      texts: doc.texts.map(function(t){ var o = clone(t); o.rotation = o.rotation || 0; o.max_lines = Math.max(1, +o.max_lines || 1); o.max_length = Math.max(1, +o.max_length || 60); return o; }),
      box_color: doc.box_color || null
    };
    var btn = document.getElementById('save');
    btn.disabled = true;
    saveState.textContent = 'Saxlanılır…';
    fetch(ROUTES.save, { method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
      body: JSON.stringify(payload) })
      .then(function(r){ return r.json().then(function(b){ if (!r.ok) throw new Error(b.message || ('Xəta ' + r.status)); return b; }); })
      .then(function(res){
        savedSnapshot = snapshot();
        updateDirty();
        saveState.textContent = 'Saxlanıldı ' + res.saved_at;
        saveState.className = 'save-state ok';
        toast('Saxlanıldı — saytda yenilənib');
      })
      .catch(function(err){ toast('Saxlanılmadı: ' + err.message, true); updateDirty(); })
      .then(function(){ btn.disabled = false; });
  }
  document.getElementById('save').onclick = save;
  document.getElementById('undo').onclick = undo;
  document.getElementById('redo').onclick = redo;
  guideOn.onchange = function(){ render(); renderProps(); };
  guideOp.oninput = render;
  window.addEventListener('beforeunload', function(e){ if (snapshot() !== savedSnapshot) { e.preventDefault(); e.returnValue = ''; } });

  /* ================================================================
     Keyboard
     ================================================================ */
  document.addEventListener('keydown', function(e){
    var el = document.activeElement;
    var tag = (el && el.tagName) || '';
    /* Only fields that take text keep the keys; a checkbox or slider does not. */
    var typing = tag === 'TEXTAREA' || tag === 'SELECT' ||
      (tag === 'INPUT' && /^(text|number|search|email|url)$/.test(el.type));
    var mod = e.ctrlKey || e.metaKey;

    if (mod && e.key.toLowerCase() === 's') { e.preventDefault(); save(); return; }
    if (typing) return;
    if (mod && e.key.toLowerCase() === 'z' && !e.shiftKey) { e.preventDefault(); undo(); return; }
    if (mod && (e.key.toLowerCase() === 'y' || (e.key.toLowerCase() === 'z' && e.shiftKey))) { e.preventDefault(); redo(); return; }
    if (mod && e.key.toLowerCase() === 'd') { e.preventDefault(); duplicateSelected(); return; }
    if (mod && e.key === '0') { e.preventDefault(); fitZoom(); return; }
    if (!mod && e.key.toLowerCase() === 't') { e.preventDefault(); addText('text'); return; }
    if (!mod && e.key.toLowerCase() === 'v') { guideOn.checked = !guideOn.checked; render(); renderProps(); return; }
    if (e.key === 'Escape') { select(null); return; }

    var it = itemOf(selection);
    if (!it) return;
    if (e.key === 'Delete' || e.key === 'Backspace') { e.preventDefault(); removeSelected(); return; }
    if (e.key === 'Enter' && selection.kind === 'text') { e.preventDefault(); startInlineEdit(selection.index); return; }
    var step = e.shiftKey ? 10 : 1;
    var moves = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] }[e.key];
    if (moves && !(selection.kind === 'layer' && it.locked)) {
      e.preventDefault();
      it.x += moves[0]; it.y += moves[1];
      render(); renderProps(true); commitSoon();
    }
  });

  /* ================================================================
     Start
     ================================================================ */
  doc.layers.forEach(function(l){ loadImage(l.url); });
  if (visualUrl) { visualImg = new Image(); visualImg.onload = render; visualImg.src = visualUrl; }
  if (!doc.layers.length && visualUrl) guideOn.checked = true;
  window.addEventListener('resize', function(){ renderSelection(); });
  fitZoom();
  refresh();
})();
</script>
</body>
</html>
