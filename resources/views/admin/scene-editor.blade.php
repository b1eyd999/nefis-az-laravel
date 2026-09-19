<!DOCTYPE html>
<html lang="az">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="referrer" content="no-referrer">
<title>{{ $scene->name }} — Səhnə redaktoru</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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
  .topbar .back{ color:var(--ink-2); text-decoration:none; padding:.4rem .6rem; border-radius:.5rem; white-space:nowrap; }
  .topbar .back:hover{ background:var(--bg); }
  .topbar .title{ font-weight:600; font-size:15px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; min-width:0; }
  .topbar .dim{ color:var(--muted); font-weight:400; margin-left:.35rem; font-size:12px; }
  .topbar .spacer{ flex:1; }
  .tb-group{ display:flex; align-items:center; gap:.35rem; padding:0 .5rem; border-left:1px solid var(--line); }
  .tb-group select{ border:1px solid var(--line); border-radius:.45rem; padding:.3rem .4rem; background:#fff; max-width:11rem; }
  .icon-btn{ width:34px; height:34px; border:1px solid transparent; background:none; border-radius:.5rem; display:inline-grid; place-items:center; font-size:16px; }
  .icon-btn:hover{ background:var(--bg); }
  .icon-btn:disabled{ opacity:.35; cursor:default; background:none; }
  .zoom-val{ min-width:3.2rem; text-align:center; color:var(--ink-2); font-variant-numeric:tabular-nums; }
  .btn{ border:1px solid var(--line); background:var(--panel); border-radius:.55rem; padding:.45rem .8rem; font-weight:500; text-decoration:none; color:var(--ink); display:inline-flex; align-items:center; justify-content:center; gap:.35rem; }
  .btn:hover{ border-color:#c9ced8; }
  .btn.primary{ background:var(--accent); color:#fff; border-color:var(--accent); }
  .btn.primary:hover{ background:#6d28d9; }
  .btn.danger{ color:var(--danger); }
  .btn.small{ padding:.3rem .55rem; font-size:12.5px; }
  .btn.block{ width:100%; }
  .save-state{ font-size:12px; color:var(--muted); min-width:6.5rem; text-align:right; }
  .save-state.dirty{ color:#b45309; }
  .save-state.ok{ color:var(--ok); }
  .check{ display:flex; align-items:center; gap:.45rem; font-size:13px; white-space:nowrap; }

  /* ---------- layout ---------- */
  .app{ display:grid; grid-template-columns:260px 1fr 340px; height:calc(100% - 56px); }

  /* library */
  .library{ background:var(--panel); border-right:1px solid var(--line); display:flex; flex-direction:column; min-height:0; padding:.75rem; gap:.6rem; position:relative; }
  .library h3{ margin:.25rem 0 0; font-size:12px; text-transform:uppercase; letter-spacing:.06em; color:var(--muted); font-weight:600; }
  .lib-grid{ flex:1 1 auto; overflow:auto; display:grid; grid-template-columns:1fr 1fr; gap:.5rem; align-content:start; min-height:0; padding-bottom:.5rem; }
  .tile{ position:relative; border:1px solid var(--line); border-radius:.55rem; overflow:hidden; cursor:pointer; background:#fff; padding:0; text-align:left; }
  .tile:hover{ border-color:var(--accent-2); box-shadow:0 0 0 2px #ede5fe; }
  .tile .pic{ aspect-ratio:4/5; display:grid; place-items:center; background:repeating-conic-gradient(#eef0f3 0 25%, #fff 0 50%) 0 0/12px 12px; }
  .tile img{ max-width:100%; max-height:100%; display:block; }
  .tile .nm{ font-size:11.5px; padding:.25rem .4rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; color:var(--ink-2); }
  .tile .x{ position:absolute; top:4px; right:4px; width:22px; height:22px; border-radius:50%; border:none; background:rgba(29,36,51,.75); color:#fff; font-size:12px; line-height:1; display:none; }
  .tile:hover .x{ display:grid; place-items:center; }
  .tile.in-use{ border-color:var(--accent); }
  .tile.in-use::after{ content:'✓'; position:absolute; left:5px; top:4px; background:var(--accent); color:#fff; font-size:11px; border-radius:.3rem; padding:0 .3rem; }
  .lib-empty{ grid-column:1/-1; color:var(--muted); font-size:12.5px; line-height:1.5; padding:.5rem .25rem; }
  .lib-drop{ position:absolute; inset:0; border:3px dashed var(--accent); background:rgba(124,58,237,.07); display:grid; place-items:center; font-weight:600; color:var(--accent); z-index:5; pointer-events:none; text-align:center; padding:1rem; }

  .workspace{ position:relative; overflow:auto; }
  .workspace-inner{ min-width:100%; min-height:100%; display:grid; place-items:center; padding:48px; }
  .stage{ position:relative; box-shadow:0 10px 40px -12px rgba(20,24,40,.35);
    background:repeating-conic-gradient(#e9ebef 0 25%, #fff 0 50%) 0 0/20px 20px; }
  .stage canvas{ display:block; width:100%; height:100%; }
  .overlay{ position:absolute; inset:0; width:100%; height:100%; overflow:visible; pointer-events:none; }
  .overlay .hd{ pointer-events:all; }
  .loupe{ position:fixed; z-index:40; width:180px; height:180px; border-radius:50%; border:3px solid #fff; box-shadow:0 6px 24px rgba(0,0,0,.35); pointer-events:none; background:#fff; }
  .badge{ position:fixed; z-index:50; background:var(--ink); color:#fff; font-size:11.5px; padding:.25rem .45rem; border-radius:.35rem; pointer-events:none; font-variant-numeric:tabular-nums; white-space:nowrap; }

  /* ---------- right panel ---------- */
  .side{ background:var(--panel); border-left:1px solid var(--line); display:flex; flex-direction:column; min-height:0; }
  .props{ flex:1 1 auto; overflow:auto; padding:1rem; border-bottom:1px solid var(--line); min-height:0; }
  .layers{ flex:0 0 34%; overflow:auto; padding:.75rem 1rem 1rem; }
  .side h3{ margin:0 0 .75rem; font-size:13px; text-transform:uppercase; letter-spacing:.06em; color:var(--muted); font-weight:600; }
  .side h4{ margin:1rem 0 .5rem; font-size:12.5px; color:var(--ink-2); font-weight:600; }
  .row{ display:grid; grid-template-columns:repeat(2, 1fr); gap:.5rem; margin-bottom:.5rem; }
  .row.three{ grid-template-columns:repeat(3, 1fr); }
  .row.four{ grid-template-columns:repeat(4, 1fr); }
  .row.one{ grid-template-columns:1fr; }
  .field label{ display:block; font-size:11.5px; color:var(--muted); margin-bottom:.2rem; }
  .field input[type=text], .field input[type=number], .field select{
    width:100%; border:1px solid var(--line); border-radius:.45rem; padding:.4rem .5rem; background:#fff; }
  .field input[type=color]{ width:100%; height:32px; border:1px solid var(--line); border-radius:.45rem; padding:2px; background:#fff; }
  .field input[type=range]{ width:100%; }
  .seg{ display:flex; border:1px solid var(--line); border-radius:.5rem; overflow:hidden; }
  .seg button{ flex:1; border:none; background:#fff; padding:.4rem .3rem; font-size:12.5px; }
  .seg button + button{ border-left:1px solid var(--line); }
  .seg button.on{ background:var(--accent); color:#fff; }
  .actions{ display:flex; flex-wrap:wrap; gap:.4rem; margin-top:.6rem; }
  .hint{ font-size:12px; color:var(--muted); line-height:1.5; }
  .hint kbd{ font-family:inherit; font-size:11px; border:1px solid var(--line); border-bottom-width:2px; border-radius:.3rem; padding:0 .3rem; background:#fafbfc; color:var(--ink-2); }
  .bg-thumb{ width:100%; max-height:10rem; object-fit:contain; border:1px solid var(--line); border-radius:.5rem; display:block; margin:.25rem 0 .5rem; background:#f6f7f9; }
  .swatches{ display:flex; flex-wrap:wrap; gap:.35rem; margin-bottom:.6rem; }
  .sw{ width:24px; height:24px; border-radius:50%; border:1px solid #cfd4dc; padding:0; font-size:12px; color:var(--muted); line-height:1; }
  .sw.on{ outline:2px solid var(--accent); outline-offset:2px; }
  .corner{ display:grid; grid-template-columns:6.6rem 1fr 1fr; gap:.4rem; align-items:center; margin-bottom:.35rem; padding:.2rem .3rem; border-radius:.45rem; cursor:pointer; }
  .corner.on{ background:#f3eefe; outline:1px solid #ddd0fb; }
  .corner .cn{ font-size:12px; color:var(--ink-2); display:flex; align-items:center; gap:.35rem; }
  .corner .dot{ width:18px; height:18px; border-radius:50%; background:#fff; border:2px solid var(--accent); font-size:10.5px; font-weight:700; display:grid; place-items:center; color:var(--accent); flex:none; }
  .corner.on .dot{ background:var(--accent); color:#fff; }
  .corner input{ width:100%; border:1px solid var(--line); border-radius:.4rem; padding:.3rem .4rem; background:#fff; font-variant-numeric:tabular-nums; }

  /* element list */
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
  .list li.bg-row{ cursor:pointer; border-top:1px dashed var(--line); border-radius:0 0 .5rem .5rem; margin-top:.25rem; }
  .list li.hidden-el .name{ opacity:.45; }

  .toast{ position:fixed; left:50%; bottom:24px; transform:translateX(-50%); background:var(--ink); color:#fff; padding:.6rem 1rem; border-radius:.6rem; font-size:13px; opacity:0; transition:opacity .2s; pointer-events:none; z-index:60; max-width:80vw; }
  .toast.show{ opacity:1; }
  .toast.error{ background:var(--danger); }
  .drop-zone{ position:absolute; inset:0; border:3px dashed var(--accent); background:rgba(124,58,237,.06); display:grid; place-items:center; font-weight:600; color:var(--accent); font-size:16px; z-index:5; pointer-events:none; text-align:center; }
</style>
</head>
<body>

<header class="topbar">
  <a class="back" href="{{ url('/admin/scenes') }}">← Səhnələr</a>
  <div class="title"><span id="title-name">{{ $scene->name }}</span><span class="dim" id="title-size"></span></div>
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
    <select id="sample" title="Yerləşdirərkən hansı dizaynla baxılsın — yalnız burada, saytda hər müştərinin öz qutusu görünür"></select>
    <button class="btn small" id="sample-file" title="Kompüterdən sınaq dizaynı seç (saxlanılmır)">Öz şəklim…</button>
    <label class="check" title="Dizaynı yarımşəffaf göstər — künclərin qutuya oturmasını yoxlamaq üçün (G)"><input type="checkbox" id="ghost"> Şəffaf</label>
  </div>
  <div class="tb-group">
    <span class="save-state" id="save-state">Saxlanılıb</span>
    @if($liveUrl)
      <a class="btn" href="{{ $liveUrl }}" target="_blank">Saytda bax ↗</a>
    @endif
    <button class="btn primary" id="save">Saxla</button>
  </div>
</header>

<div class="app">
  <aside class="library" id="library">
    <button class="btn primary block" id="add-design" title="Müştərinin dizaynının oturacağı yer — 4 küncünü qutunun üzünə çəkin">▦ Dizayn yeri əlavə et</button>
    <h3>Kitabxana</h3>
    <div class="seg" id="lib-tabs">
      <button type="button" data-tab="background" class="on">Fonlar</button>
      <button type="button" data-tab="object">Qutular və əşyalar</button>
    </div>
    <button class="btn small block" id="lib-upload">+ Yüklə</button>
    <div class="lib-grid" id="lib-grid"></div>
    <input type="file" id="file-lib" accept="image/png,image/webp,image/jpeg" multiple hidden>
    <input type="file" id="file-sample" accept="image/*" hidden>
  </aside>

  <section class="workspace" id="workspace">
    <div class="workspace-inner">
      <div class="stage" id="stage">
        <canvas id="canvas"></canvas>
        <svg class="overlay" id="overlay" xmlns="http://www.w3.org/2000/svg"></svg>
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

<canvas class="loupe" id="loupe" width="180" height="180" hidden></canvas>
<div class="toast" id="toast"></div>

<script src="{{ asset('js/scene-render.js') }}"></script>
<script src="{{ asset('js/cover.js') }}"></script>
<script>
(function(){
  'use strict';

  /* ================================================================
     Data
     ================================================================ */
  var DESIGN = @json($canvas);
  var MAX_SIDE = {{ \App\Http\Controllers\SceneEditorController::MAX_SIDE }};
  var ROUTES = {
    save: @json(route('scene.save', $scene)),
    asset: @json(route('scene.asset')),
    assetDestroy: @json(route('scene.asset.destroy', ['asset' => '__ID__']))
  };
  var CSRF = document.querySelector('meta[name="csrf-token"]').content;
  var LIB = @json($library);
  var SAMPLES = @json($samples);
  var doc = @json($payload);
  doc.elements = doc.elements || [];

  /* Editor-only state, never saved. */
  var images = {};          // url -> {img, alpha, aw, ah}
  var cache = {};           // scene-render scratch canvases
  var selIndex = -1;        // selected element
  var activeCorner = -1;    // corner the arrow keys move
  var zoom = 1;
  var libTab = 'background';
  var sampleImg = null;
  var sampleColor = null;   // the sample product's box colour, for renders that follow it
  var ownSample = null;

  var canvas = document.getElementById('canvas');
  var ctx = canvas.getContext('2d');
  var stage = document.getElementById('stage');
  var overlay = document.getElementById('overlay');
  var workspace = document.getElementById('workspace');
  var props = document.getElementById('props');
  var layerList = document.getElementById('layer-list');
  var saveState = document.getElementById('save-state');
  var ghost = document.getElementById('ghost');
  var loupe = document.getElementById('loupe');
  var SVGNS = 'http://www.w3.org/2000/svg';

  var CORNER_NAMES = ['Sol yuxarı', 'Sağ yuxarı', 'Sağ aşağı', 'Sol aşağı'];
  var SWATCHES = [[null, 'Ağ (rəngsiz)'], ['#1b1b1d', 'Qara'], ['#1f6f43', 'Yaşıl'], ['#a3172c', 'Qırmızı'], ['#1e3a8a', 'Göy'],
                  ['#e98fb0', 'Çəhrayı'], ['#c9a44c', 'Qızılı'], ['#5b3a29', 'Şokolad'], ['#7c3aed', 'Bənövşəyi'], ['#f2e3c6', 'Krem']];
  var BLENDS = [['source-over', 'Normal'], ['multiply', 'Multiply (tündləşdirir)'], ['screen', 'Screen (açır)'], ['overlay', 'Overlay'],
                ['soft-light', 'Soft light'], ['hard-light', 'Hard light'], ['darken', 'Darken'], ['lighten', 'Lighten']];

  /* ================================================================
     Helpers
     ================================================================ */
  function clone(o){ return JSON.parse(JSON.stringify(o)); }
  function clamp(v, a, b){ return Math.max(a, Math.min(b, v)); }
  function r2(v){ return Math.round(v * 100) / 100; }
  function rad(d){ return d * Math.PI / 180; }
  function uid(){ return 'e' + Math.random().toString(36).slice(2, 9); }
  function rotatePoint(px, py, cx, cy, deg){
    var r = rad(deg), c = Math.cos(r), s = Math.sin(r), dx = px - cx, dy = py - cy;
    return { x: cx + dx * c - dy * s, y: cy + dx * s + dy * c };
  }
  function esc(s){ return String(s == null ? '' : s).replace(/[&<>"]/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }
  function luminance(hex){
    var n = parseInt(String(hex).slice(1, 7), 16);
    return (0.299 * (n >> 16 & 255) + 0.587 * (n >> 8 & 255) + 0.114 * (n & 255)) / 255;
  }
  function W(){ return doc.width; }
  function H(){ return doc.height; }

  var toastTimer;
  function toast(msg, isError){
    var t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'toast show' + (isError ? ' error' : '');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function(){ t.className = 'toast'; }, isError ? 5000 : 2400);
  }

  function sceneOf(){
    return { w: doc.width, h: doc.height, bgColor: doc.background_color, bg: doc.background_url, elements: doc.elements };
  }
  function selected(){ return doc.elements[selIndex] || null; }

  /* ================================================================
     Images
     ================================================================ */
  function getImage(url){
    if (!url) return null;
    if (!images[url]) {
      var entry = images[url] = { img: new Image(), alpha: null, aw: 0, ah: 0 };
      entry.img.onload = function(){
        /* A small alpha map lets clicks pass through the empty parts of a
           full-size render, so the background under it stays reachable. */
        try {
          var aw = Math.max(1, Math.round(entry.img.naturalWidth / 8));
          var ah = Math.max(1, Math.round(entry.img.naturalHeight / 8));
          var c = document.createElement('canvas'); c.width = aw; c.height = ah;
          var x = c.getContext('2d'); x.drawImage(entry.img, 0, 0, aw, ah);
          var data = x.getImageData(0, 0, aw, ah).data;
          entry.alpha = new Uint8Array(aw * ah);
          for (var i = 0; i < aw * ah; i++) entry.alpha[i] = data[i * 4 + 3];
          entry.aw = aw; entry.ah = ah;
        } catch (e) {}
        render();
        renderList();
      };
      entry.img.src = url;
    }
    return images[url].img;
  }

  /* A stand-in design that shows which way is up. */
  function placeholder(){
    var c = document.createElement('canvas');
    c.width = DESIGN.width; c.height = DESIGN.height;
    var x = c.getContext('2d');
    x.fillStyle = '#f3ede6'; x.fillRect(0, 0, c.width, c.height);
    x.strokeStyle = 'rgba(124,58,237,.18)'; x.lineWidth = 18;
    for (var d = -c.height; d < c.width + c.height; d += 90) { x.beginPath(); x.moveTo(d, 0); x.lineTo(d + c.height, c.height); x.stroke(); }
    x.strokeStyle = '#7c3aed'; x.lineWidth = 16; x.strokeRect(8, 8, c.width - 16, c.height - 16);
    x.fillStyle = '#4c1d95'; x.textAlign = 'center'; x.textBaseline = 'middle';
    x.font = '700 150px Inter, sans-serif'; x.fillText('DİZAYN', c.width / 2, c.height / 2);
    x.font = '600 90px Inter, sans-serif'; x.fillText('↑ yuxarı', c.width / 2, 170);
    x.font = '700 110px Inter, sans-serif';
    [[110, 120, '1'], [c.width - 110, 120, '2'], [c.width - 110, c.height - 120, '3'], [110, c.height - 120, '4']].forEach(function(p){
      x.fillStyle = '#7c3aed'; x.beginPath(); x.arc(p[0], p[1], 78, 0, Math.PI * 2); x.fill();
      x.fillStyle = '#fff'; x.fillText(p[2], p[0], p[1] + 6);
    });
    return c;
  }
  var PLACEHOLDER = placeholder();

  var sampleSel = document.getElementById('sample');
  function renderSampleOptions(){
    var h = SAMPLES.map(function(s, i){ return '<option value="' + i + '">' + esc(s.name) + '</option>'; }).join('');
    h += '<option value="ph">Boş şablon (1-2-3-4)</option>';
    if (ownSample) h += '<option value="own">Öz şəklim</option>';
    sampleSel.innerHTML = h;
  }
  function setSample(v){
    sampleColor = (SAMPLES[+v] && SAMPLES[+v].box_color) || null;
    if (v === 'ph') sampleImg = PLACEHOLDER;
    else if (v === 'own') sampleImg = ownSample;
    else {
      var s = SAMPLES[+v];
      if (!s) { sampleImg = PLACEHOLDER; return; }
      var img = new Image();
      img.onload = function(){ if (sampleImg === img) render(); };
      img.src = s.url;
      sampleImg = img;
    }
    sampleSel.value = v;
    render();
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
    var w = doc.width, h = doc.height;
    doc = JSON.parse(s);
    if (selIndex >= doc.elements.length) selIndex = -1;
    if (w !== doc.width || h !== doc.height) sizeCanvas(true);
    refresh();
  }
  function undo(){ commit(); if (history.length < 2) return; future.push(history.pop()); restore(history[history.length - 1]); updateDirty(); }
  function redo(){ if (!future.length) return; var s = future.pop(); history.push(s); restore(s); updateDirty(); }
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
  /* The four corners of any element, top-left first, clockwise. */
  function cornersOf(el){
    if (el.type === 'design') return el.corners;
    return NefisScene.rectCorners(el.x + el.width / 2, el.y + el.height / 2, el.width, el.height, el.rotation || 0);
  }
  function centroid(pts){
    var x = 0, y = 0;
    pts.forEach(function(p){ x += p[0]; y += p[1]; });
    return [x / pts.length, y / pts.length];
  }
  function aabb(pts){
    var xs = pts.map(function(p){ return p[0]; }), ys = pts.map(function(p){ return p[1]; });
    var x1 = Math.min.apply(null, xs), x2 = Math.max.apply(null, xs), y1 = Math.min.apply(null, ys), y2 = Math.max.apply(null, ys);
    return { x1: x1, x2: x2, y1: y1, y2: y2, cx: (x1 + x2) / 2, cy: (y1 + y2) / 2 };
  }

  /* Where a scene point falls on an image element, 0..1 across its picture. */
  function imageUV(el, x, y){
    var q = rotatePoint(x, y, el.x + el.width / 2, el.y + el.height / 2, -(el.rotation || 0));
    var u = (q.x - el.x) / el.width, v = (q.y - el.y) / el.height;
    if (el.flip_x) u = 1 - u;
    if (el.flip_y) v = 1 - v;
    return { u: u, v: v };
  }

  function hitElement(i, x, y){
    var el = doc.elements[i];
    if (el.type === 'design') return NefisScene.pointInQuad(el.corners, x, y);
    var uv = imageUV(el, x, y);
    if (uv.u < 0 || uv.u > 1 || uv.v < 0 || uv.v > 1) return false;
    var e = images[el.url];
    if (e && e.alpha) {
      var ax = clamp(Math.floor(uv.u * e.aw), 0, e.aw - 1), ay = clamp(Math.floor(uv.v * e.ah), 0, e.ah - 1);
      return e.alpha[ay * e.aw + ax] > 64;
    }
    return true;
  }

  function hitTest(x, y){
    for (var i = doc.elements.length - 1; i >= 0; i--) {
      var el = doc.elements[i];
      if (el.hidden || el.locked) continue;
      if (hitElement(i, x, y)) return i;
    }
    return -1;
  }

  /* The opaque part of an image element, in scene pixels (unturned). */
  function opaqueBox(el){
    var e = images[el.url];
    if (!e || !e.alpha || el.rotation) return null;
    var x1 = e.aw, y1 = e.ah, x2 = -1, y2 = -1;
    for (var y = 0; y < e.ah; y++) for (var x = 0; x < e.aw; x++) {
      if (e.alpha[y * e.aw + x] > 128) { if (x < x1) x1 = x; if (x > x2) x2 = x; if (y < y1) y1 = y; if (y > y2) y2 = y; }
    }
    if (x2 < 0) return null;
    return { x: el.x + x1 / e.aw * el.width, y: el.y + y1 / e.ah * el.height,
             w: (x2 - x1 + 1) / e.aw * el.width, h: (y2 - y1 + 1) / e.ah * el.height };
  }

  /* ================================================================
     Rendering
     ================================================================ */
  var drag = null;
  function render(){
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    var pinning = drag && drag.moved && (drag.mode === 'corner' || drag.mode === 'edge');
    NefisScene.drawScene(ctx, sceneOf(), sampleImg, getImage, cache, {
      fast: !!(drag && drag.moved),
      designAlpha: (ghost.checked || pinning) ? 0.5 : null,
      boxColor: sampleColor
    });
    renderOverlay();
  }

  function svg(tag, attrs){
    var n = document.createElementNS(SVGNS, tag);
    for (var k in attrs) n.setAttribute(k, attrs[k]);
    return n;
  }

  var hoverIndex = -1;
  function renderOverlay(){
    overlay.setAttribute('viewBox', '0 0 ' + W() + ' ' + H());
    while (overlay.firstChild) overlay.removeChild(overlay.firstChild);
    var px = 1 / zoom;
    if (hoverIndex >= 0 && hoverIndex !== selIndex && doc.elements[hoverIndex]) {
      overlay.appendChild(svg('polygon', { points: cornersOf(doc.elements[hoverIndex]).join(' '), fill: 'none', stroke: '#a78bfa', 'stroke-width': px }));
    }
    var el = selected();
    if (!el) return;
    var pts = cornersOf(el);
    overlay.appendChild(svg('polygon', { points: pts.join(' '), fill: 'none', stroke: el.locked ? '#8a93a3' : '#7c3aed',
      'stroke-width': 1.5 * px, 'stroke-dasharray': el.locked ? (5 * px) + ' ' + (4 * px) : 'none' }));
    if (el.type === 'design') {
      /* Which way is up on the design. */
      var tm = [(pts[0][0] + pts[1][0]) / 2, (pts[0][1] + pts[1][1]) / 2];
      overlay.appendChild(svg('line', { x1: pts[0][0], y1: pts[0][1], x2: pts[1][0], y2: pts[1][1], stroke: '#ff2d8f', 'stroke-width': 3 * px }));
      var lbl = svg('text', { x: tm[0], y: tm[1] - 8 * px, 'text-anchor': 'middle', 'font-size': 11 * px, fill: '#ff2d8f', 'font-weight': 700 });
      lbl.textContent = 'yuxarı';
      overlay.appendChild(lbl);
    }
    if (el.locked) return;

    var c = centroid(pts);
    var top = [(pts[0][0] + pts[1][0]) / 2, (pts[0][1] + pts[1][1]) / 2];
    var dx = top[0] - c[0], dy = top[1] - c[1], dl = Math.hypot(dx, dy) || 1;
    var rot = [top[0] + dx / dl * 30 * px, top[1] + dy / dl * 30 * px];
    overlay.appendChild(svg('line', { x1: top[0], y1: top[1], x2: rot[0], y2: rot[1], stroke: '#7c3aed', 'stroke-width': 1.5 * px }));
    overlay.appendChild(svg('circle', { class: 'hd', 'data-handle': 'rot', cx: rot[0], cy: rot[1], r: 8 * px, fill: '#fff', stroke: '#7c3aed', 'stroke-width': 1.5 * px, style: 'cursor:grab' }));

    var mids = [0, 1, 2, 3].map(function(i){ var a = pts[i], b = pts[(i + 1) % 4]; return [(a[0] + b[0]) / 2, (a[1] + b[1]) / 2]; });
    if (el.type === 'design') {
      mids.forEach(function(m, i){
        overlay.appendChild(svg('rect', { class: 'hd', 'data-handle': 'e' + i, x: m[0] - 5 * px, y: m[1] - 5 * px, width: 10 * px, height: 10 * px,
          rx: 2 * px, fill: '#fff', stroke: '#7c3aed', 'stroke-width': 1.5 * px, style: 'cursor:move' }));
      });
      pts.forEach(function(p, i){
        var on = i === activeCorner;
        overlay.appendChild(svg('circle', { class: 'hd', 'data-handle': 'c' + i, cx: p[0], cy: p[1], r: 9 * px,
          fill: on ? '#7c3aed' : '#fff', stroke: '#7c3aed', 'stroke-width': 2 * px, style: 'cursor:crosshair' }));
        var t = svg('text', { x: p[0], y: p[1] + 3.5 * px, 'text-anchor': 'middle', 'font-size': 10 * px, 'font-weight': 700,
          fill: on ? '#fff' : '#7c3aed', 'pointer-events': 'none' });
        t.textContent = String(i + 1);
        overlay.appendChild(t);
      });
    } else {
      var names = ['nw', 'ne', 'se', 'sw'], midNames = ['n', 'e', 's', 'w'];
      mids.forEach(function(m, i){
        overlay.appendChild(svg('rect', { class: 'hd', 'data-handle': midNames[i], x: m[0] - 5 * px, y: m[1] - 5 * px, width: 10 * px, height: 10 * px,
          rx: 5 * px, fill: '#fff', stroke: '#7c3aed', 'stroke-width': 1.5 * px, style: 'cursor:pointer' }));
      });
      pts.forEach(function(p, i){
        overlay.appendChild(svg('rect', { class: 'hd', 'data-handle': names[i], x: p[0] - 6 * px, y: p[1] - 6 * px, width: 12 * px, height: 12 * px,
          rx: 3 * px, fill: '#fff', stroke: '#7c3aed', 'stroke-width': 1.5 * px, style: 'cursor:pointer' }));
      });
    }
  }

  var badge = null;
  function showBadge(text, cx, cy){
    if (!badge) { badge = document.createElement('div'); badge.className = 'badge'; document.body.appendChild(badge); }
    badge.textContent = text;
    badge.style.left = (cx + 16) + 'px';
    badge.style.top = (cy - 34) + 'px';
    badge.hidden = false;
  }
  function hideBadge(){ if (badge) badge.hidden = true; }

  /* A magnified view around the corner being placed, so it can sit exactly
     on the edge of the rendered box. */
  function showLoupe(sx, sy, clientX, clientY){
    var lx = loupe.getContext('2d'), k = 4, size = 180, half = size / k / 2;
    lx.imageSmoothingEnabled = false;
    lx.fillStyle = '#fff';
    lx.fillRect(0, 0, size, size);
    lx.drawImage(canvas, sx - half, sy - half, half * 2, half * 2, 0, 0, size, size);
    lx.strokeStyle = '#ff2d8f'; lx.lineWidth = 1;
    lx.beginPath(); lx.moveTo(size / 2, 0); lx.lineTo(size / 2, size); lx.moveTo(0, size / 2); lx.lineTo(size, size / 2); lx.stroke();
    var left = clientX + 28, topPos = clientY + 28;
    if (left + size > window.innerWidth - 8) left = clientX - size - 28;
    if (topPos + size > window.innerHeight - 8) topPos = clientY - size - 28;
    loupe.style.left = left + 'px';
    loupe.style.top = topPos + 'px';
    loupe.hidden = false;
  }

  /* ================================================================
     Zoom & canvas size
     ================================================================ */
  function sizeCanvas(fit){
    canvas.width = W();
    canvas.height = H();
    cache = {};
    document.getElementById('title-size').textContent = W() + ' × ' + H();
    if (fit) fitZoom(); else setZoom(zoom);
  }
  function setZoom(z){
    zoom = clamp(z, 0.05, 4);
    stage.style.width = (W() * zoom) + 'px';
    stage.style.height = (H() * zoom) + 'px';
    document.getElementById('zoom-val').textContent = Math.round(zoom * 100) + '%';
    renderOverlay();
  }
  function fitZoom(){
    var r = workspace.getBoundingClientRect();
    setZoom(Math.min((r.width - 96) / W(), (r.height - 96) / H()));
  }
  document.getElementById('zoom-in').onclick = function(){ setZoom(zoom * 1.2); };
  document.getElementById('zoom-out').onclick = function(){ setZoom(zoom / 1.2); };
  document.getElementById('zoom-fit').onclick = fitZoom;
  workspace.addEventListener('wheel', function(e){
    if (!e.ctrlKey) return;
    e.preventDefault();
    setZoom(zoom * (e.deltaY < 0 ? 1.1 : 1 / 1.1));
  }, { passive: false });

  function toScene(clientX, clientY){
    var r = stage.getBoundingClientRect();
    return { x: (clientX - r.left) / zoom, y: (clientY - r.top) / zoom };
  }

  /* ================================================================
     Pointer
     ================================================================ */
  stage.addEventListener('pointerdown', function(e){
    if (e.button !== 0) return;
    if (document.activeElement && document.activeElement !== document.body) document.activeElement.blur();
    var p = toScene(e.clientX, e.clientY);
    var handle = e.target.getAttribute && e.target.getAttribute('data-handle');
    if (handle && selected()) { startDrag(handle, e, p); return; }

    var hit = hitTest(p.x, p.y);
    /* A click on the current selection keeps it even where it is see-through. */
    if (hit < 0 && selected() && !selected().locked && NefisScene.pointInQuad(cornersOf(selected()), p.x, p.y)) hit = selIndex;
    if (hit !== selIndex) activeCorner = -1;
    select(hit);
    if (hit >= 0) startDrag('move', e, p);
  });

  stage.addEventListener('pointermove', function(e){
    if (drag) return;
    var p = toScene(e.clientX, e.clientY);
    var h = hitTest(p.x, p.y);
    if (h !== hoverIndex) { hoverIndex = h; renderOverlay(); }
  });
  stage.addEventListener('pointerleave', function(){ if (!drag && hoverIndex >= 0) { hoverIndex = -1; renderOverlay(); } });

  function startDrag(handle, e, p){
    var el = selected();
    if (!el || el.locked) return;
    var mode = handle === 'move' ? 'move' : handle === 'rot' ? 'rotate'
      : /^c\d$/.test(handle) ? 'corner' : /^e\d$/.test(handle) ? 'edge' : 'resize';
    if (mode === 'corner') { activeCorner = +handle.slice(1); renderProps(); }
    drag = { mode: mode, handle: handle, start: p, orig: clone(el), moved: false };
    stage.setPointerCapture(e.pointerId);
    e.preventDefault();
  }

  stage.addEventListener('pointermove', function(e){
    if (!drag) return;
    var el = selected();
    var p = toScene(e.clientX, e.clientY);
    var dx = p.x - drag.start.x, dy = p.y - drag.start.y;
    if (!drag.moved && Math.abs(dx) * zoom < 2 && Math.abs(dy) * zoom < 2) return;
    drag.moved = true;
    var o = drag.orig, loupeAt = null;

    if (drag.mode === 'move') {
      if (e.shiftKey) { if (Math.abs(dx) > Math.abs(dy)) dy = 0; else dx = 0; }
      if (el.type === 'design') {
        el.corners = o.corners.map(function(c){ return [c[0] + dx, c[1] + dy]; });
      } else {
        /* Pictures snap to the scene's edges and middle; Alt turns it off. */
        if (!e.altKey) {
          var a = aabb(cornersOf(o)), t = 6 / zoom;
          [[a.x1, 0], [a.cx, W() / 2], [a.x2, W()]].some(function(s){ if (Math.abs(s[0] + dx - s[1]) < t) { dx = s[1] - s[0]; return true; } });
          [[a.y1, 0], [a.cy, H() / 2], [a.y2, H()]].some(function(s){ if (Math.abs(s[0] + dy - s[1]) < t) { dy = s[1] - s[0]; return true; } });
        }
        el.x = o.x + dx; el.y = o.y + dy;
      }
      var bb = aabb(cornersOf(el));
      showBadge('X ' + Math.round(bb.x1) + '   Y ' + Math.round(bb.y1), e.clientX, e.clientY);
    } else if (drag.mode === 'corner') {
      var k = +drag.handle.slice(1);
      if (e.shiftKey) {
        /* Shift: grow or shrink the whole shape about its middle. */
        var c0 = centroid(o.corners);
        var f = Math.hypot(p.x - c0[0], p.y - c0[1]) / (Math.hypot(drag.start.x - c0[0], drag.start.y - c0[1]) || 1);
        el.corners = o.corners.map(function(c){ return [c0[0] + (c[0] - c0[0]) * f, c0[1] + (c[1] - c0[1]) * f]; });
      } else {
        el.corners = clone(o.corners);
        el.corners[k] = [o.corners[k][0] + dx, o.corners[k][1] + dy];
      }
      loupeAt = el.corners[k];
      showBadge(k + 1 + ':  X ' + r2(el.corners[k][0]) + '   Y ' + r2(el.corners[k][1]), e.clientX, e.clientY);
    } else if (drag.mode === 'edge') {
      var i = +drag.handle.slice(1), j = (i + 1) % 4;
      el.corners = clone(o.corners);
      el.corners[i] = [o.corners[i][0] + dx, o.corners[i][1] + dy];
      el.corners[j] = [o.corners[j][0] + dx, o.corners[j][1] + dy];
      loupeAt = [p.x, p.y];
    } else if (drag.mode === 'rotate') {
      var cc = centroid(cornersOf(o));
      var ang = (Math.atan2(p.y - cc[1], p.x - cc[0]) - Math.atan2(drag.start.y - cc[1], drag.start.x - cc[0])) * 180 / Math.PI;
      if (e.shiftKey) ang = Math.round(ang / 15) * 15;
      if (el.type === 'design') {
        el.corners = o.corners.map(function(c){ var q = rotatePoint(c[0], c[1], cc[0], cc[1], ang); return [q.x, q.y]; });
      } else {
        el.rotation = (((o.rotation || 0) + ang + 540) % 360) - 180;
      }
      showBadge((ang >= 0 ? '+' : '') + ang.toFixed(1) + '°', e.clientX, e.clientY);
    } else if (drag.mode === 'resize') {
      resizeImage(el, o, drag.handle, drag.start, p, e.shiftKey);
      showBadge(Math.round(el.width) + ' × ' + Math.round(el.height), e.clientX, e.clientY);
    }
    render();
    if (loupeAt) showLoupe(loupeAt[0], loupeAt[1], e.clientX, e.clientY);
    renderProps(true);
  });

  stage.addEventListener('pointerup', function(){
    if (!drag) return;
    var el = selected(), moved = drag.moved;
    drag = null;
    if (moved && el) {
      if (el.type === 'design') el.corners = el.corners.map(function(c){ return [r2(c[0]), r2(c[1])]; });
      else ['x', 'y', 'width', 'height', 'rotation'].forEach(function(k){ el[k] = r2(el[k]); });
      commit();
    }
    loupe.hidden = true;
    hideBadge();
    render();
    renderProps();
  });

  /* Resizes a picture from one of its handles; the opposite side stays put.
     Corners keep the proportions unless Shift is held. */
  function resizeImage(el, o, handle, start, p, shift){
    var dirs = { nw: [-1, -1], n: [0, -1], ne: [1, -1], e: [1, 0], se: [1, 1], s: [0, 1], sw: [-1, 1], w: [-1, 0] }[handle];
    var sx = dirs[0], sy = dirs[1];
    var cx = o.x + o.width / 2, cy = o.y + o.height / 2, rot = o.rotation || 0;
    var a = rotatePoint(p.x, p.y, cx, cy, -rot), b = rotatePoint(start.x, start.y, cx, cy, -rot);
    var w = Math.max(8, o.width + sx * (a.x - b.x));
    var h = Math.max(8, o.height + sy * (a.y - b.y));
    if (sx !== 0 && sy !== 0 && !shift) { var k = Math.max(w / o.width, h / o.height); w = o.width * k; h = o.height * k; }
    var fx = -sx * o.width / 2, fy = -sy * o.height / 2;
    var ncx = sx === 0 ? 0 : fx + sx * w / 2, ncy = sy === 0 ? 0 : fy + sy * h / 2;
    var c = rotatePoint(cx + ncx, cy + ncy, cx, cy, rot);
    el.width = w; el.height = h;
    el.x = c.x - w / 2; el.y = c.y - h / 2;
  }

  /* ================================================================
     Structure
     ================================================================ */
  function select(i){
    if (i !== selIndex) activeCorner = -1;
    selIndex = i;
    hoverIndex = -1;
    render();
    renderProps();
    renderList();
  }
  function refresh(){ render(); renderProps(); renderList(); renderLibrary(); updateDirty(); document.getElementById('title-name').textContent = doc.name; }

  /* Pictures the same shape as the scene cover it exactly — a render made at
     the background's size lands where it was rendered. */
  function addImage(asset){
    var w = asset.width, h = asset.height, x, y;
    if (!doc.elements.length && !doc.background) {
      doc.width = w; doc.height = h; sizeCanvas(true);
    }
    if (Math.abs(w / h - W() / H()) < 0.01) { x = 0; y = 0; w = W(); h = H(); }
    else { var k = Math.min(1, W() / w, H() / h); w *= k; h *= k; x = (W() - w) / 2; y = (H() - h) / 2; }
    doc.elements.push({ id: uid(), type: 'image', name: asset.name || 'Şəkil', image: asset.image, url: asset.url,
      x: r2(x), y: r2(y), width: r2(w), height: r2(h), rotation: 0, opacity: 100, blend: 'source-over',
      flip_x: false, flip_y: false, tint: null, sheen: 0, recolor: true, tint_all: false, locked: false, hidden: false });
    getImage(asset.url);
    select(doc.elements.length - 1);
    commit();
    renderLibrary();
  }

  function setBackground(asset){
    if (asset && (asset.width !== W() || asset.height !== H())) {
      /* Keep everything where it was relative to the picture. */
      var sx = asset.width / W(), sy = asset.height / H();
      doc.elements.forEach(function(el){
        if (el.type === 'design') el.corners = el.corners.map(function(c){ return [r2(c[0] * sx), r2(c[1] * sy)]; });
        else { el.x = r2(el.x * sx); el.y = r2(el.y * sy); el.width = r2(el.width * sx); el.height = r2(el.height * sy); }
      });
      doc.width = asset.width; doc.height = asset.height;
      sizeCanvas(true);
    }
    doc.background = asset ? asset.image : null;
    doc.background_url = asset ? asset.url : null;
    commit();
    refresh();
  }

  function addDesign(){
    /* Start inside the topmost render, the right way up; the owner then pulls
       each corner onto the face. */
    var render_ = null;
    for (var i = doc.elements.length - 1; i >= 0; i--) if (doc.elements[i].type === 'image' && !doc.elements[i].hidden) { render_ = doc.elements[i]; break; }
    var box = render_ && opaqueBox(render_);
    var ratio = DESIGN.width / DESIGN.height, w, h, cx, cy;
    if (box) { h = Math.min(box.h, box.w / ratio) * 0.85; cx = box.x + box.w / 2; cy = box.y + box.h / 2; }
    else { h = H() * 0.55; cx = W() / 2; cy = H() / 2; }
    w = h * ratio;
    var n = doc.elements.filter(function(e){ return e.type === 'design'; }).length;
    doc.elements.push({ id: uid(), type: 'design', name: n ? 'Dizayn ' + (n + 1) : 'Dizayn',
      corners: NefisScene.rectCorners(cx, cy, w, h, 0).map(function(c){ return [r2(c[0]), r2(c[1])]; }),
      opacity: 100, blend: 'source-over', shade: render_ ? 100 : 0, shade_from: render_ ? render_.id : null,
      locked: false, hidden: false });
    select(doc.elements.length - 1);
    commit();
    toast('Künclərə (1-2-3-4) çəkərək dizaynı qutunun üzünə oturdun');
  }

  function removeSelected(){
    var el = selected();
    if (!el) return;
    doc.elements.splice(selIndex, 1);
    doc.elements.forEach(function(e){ if (e.shade_from === el.id) e.shade_from = null; });
    selIndex = -1;
    commit();
    refresh();
  }

  function duplicateSelected(){
    var el = selected();
    if (!el) return;
    var copy = clone(el);
    copy.id = uid();
    copy.locked = false;
    if (copy.type === 'design') copy.corners = copy.corners.map(function(c){ return [c[0] + 24, c[1] + 24]; });
    else { copy.x += 24; copy.y += 24; }
    doc.elements.splice(selIndex + 1, 0, copy);
    select(selIndex + 1);
    commit();
  }

  function moveElement(where){
    var i = selIndex, n = doc.elements.length;
    var to = where === 'front' ? n - 1 : where === 'back' ? 0 : where === 'up' ? Math.min(n - 1, i + 1) : Math.max(0, i - 1);
    if (to === i) return;
    var el = doc.elements.splice(i, 1)[0];
    doc.elements.splice(to, 0, el);
    select(to);
    commit();
  }

  /* Corner-pin helpers: turn or mirror the design inside the same four points. */
  function cycleCorners(el, map){ var c = el.corners; el.corners = map.map(function(i){ return c[i]; }); }
  function straighten(el){
    var c = el.corners, cc = centroid(c);
    var w = (Math.hypot(c[1][0] - c[0][0], c[1][1] - c[0][1]) + Math.hypot(c[2][0] - c[3][0], c[2][1] - c[3][1])) / 2;
    var ang = Math.atan2(c[1][1] - c[0][1], c[1][0] - c[0][0]) * 180 / Math.PI;
    var h = w * DESIGN.height / DESIGN.width;
    el.corners = NefisScene.rectCorners(cc[0], cc[1], w, h, ang).map(function(p){ return [r2(p[0]), r2(p[1])]; });
  }
  function transformAll(el, fn){
    var pts = cornersOf(el), cc = centroid(pts);
    if (el.type === 'design') el.corners = el.corners.map(function(c){ var q = fn(c, cc); return [r2(q[0]), r2(q[1])]; });
    return cc;
  }

  /* ================================================================
     Properties panel
     ================================================================ */
  function field(label, html){ return '<div class="field"><label>' + label + '</label>' + html + '</div>'; }
  function num(key, val, step){ return '<input type="number" data-k="' + key + '" value="' + (val == null ? '' : r2(+val)) + '"' + (step ? ' step="' + step + '"' : '') + '>'; }
  function txt(key, val, ph){ return '<input type="text" data-k="' + key + '" value="' + esc(val) + '"' + (ph ? ' placeholder="' + esc(ph) + '"' : '') + '>'; }
  function range(key, val, min, max){ return '<input type="range" data-k="' + key + '" min="' + min + '" max="' + max + '" value="' + val + '">'; }
  function seg(key, val, opts){
    return '<div class="seg">' + opts.map(function(o){
      return '<button type="button" data-seg="' + key + '" data-v="' + o[0] + '"' + (String(val) === String(o[0]) ? ' class="on"' : '') + '>' + o[1] + '</button>';
    }).join('') + '</div>';
  }
  function orderActions(el){
    return '<h4>Sıra</h4><div class="actions">'
      + '<button class="btn small" data-act="front">Ən önə</button><button class="btn small" data-act="up">Bir irəli</button>'
      + '<button class="btn small" data-act="down">Bir geri</button><button class="btn small" data-act="back">Ən arxaya</button></div>'
      + '<div class="actions" style="margin-top:.75rem">'
      + '<label class="check"><input type="checkbox" data-k="locked"' + (el.locked ? ' checked' : '') + '> Kilidlə</label>'
      + '<label class="check" title="Gizli qat saytda da görünmür"><input type="checkbox" data-k="hidden"' + (el.hidden ? ' checked' : '') + '> Gizlət</label></div>'
      + '<div class="actions"><button class="btn small" data-act="dup">Təkrarla</button><button class="btn small danger" data-act="del">Sil</button></div>';
  }

  function renderProps(live){
    var el = selected();
    if (live) {
      if (!el) return;
      props.querySelectorAll('input[data-k]').forEach(function(inp){
        if (document.activeElement === inp || inp.type !== 'number') return;
        if (el[inp.dataset.k] != null) inp.value = r2(el[inp.dataset.k]);
      });
      props.querySelectorAll('input[data-corner]').forEach(function(inp){
        if (document.activeElement === inp || !el.corners) return;
        inp.value = r2(el.corners[+inp.dataset.corner][+inp.dataset.axis]);
      });
      return;
    }

    var h = '';
    if (!el) {
      h += '<h3>Səhnə</h3>';
      h += '<div class="row one">' + field('Ad (müştəri miniatürün üzərində görür)', '<input type="text" data-scene="name" value="' + esc(doc.name) + '">') + '</div>';
      h += '<label class="check" style="margin-bottom:.5rem"><input type="checkbox" data-scene="is_active"' + (doc.is_active ? ' checked' : '') + '> Saytda göstər</label>';
      h += '<h4>Fon</h4>';
      if (doc.background_url) h += '<img class="bg-thumb" src="' + esc(doc.background_url) + '" alt="">';
      else h += '<p class="hint">Fon yoxdur. Soldakı <b>Fonlar</b> kitabxanasından birini seçin və ya yükləyin.</p>';
      h += '<div class="actions"><button class="btn small" data-act="pick-bg">Fonu seç</button>'
         + (doc.background ? '<button class="btn small danger" data-act="clear-bg">Fonu götür</button>' : '') + '</div>';
      h += '<div class="row" style="margin-top:.6rem">' + field('Fon rəngi', '<input type="color" data-scene="background_color" value="' + esc(doc.background_color || '#ffffff') + '">')
         + field('&nbsp;', '<button class="btn small block" data-act="clear-color"' + (doc.background_color ? '' : ' disabled') + '>Rəngsiz</button>') + '</div>';
      h += '<p class="hint">Ölçü: ' + W() + ' × ' + H() + ' px — fon şəklindən götürülür.</p>';
      h += '<h4>Necə qurulur</h4><p class="hint">1. Fon seçin. 2. <b>Qutular və əşyalar</b>dan boş qutunun renderini qoyun. '
         + '3. <b>▦ Dizayn yeri</b> əlavə edin və 1-2-3-4 künclərini qutunun üzünün künclərinə çəkin. '
         + 'Saytda hər dizayn bu yerə özü oturur.</p>';
      h += '<h4>Qısayollar</h4><p class="hint">'
         + '<kbd>1</kbd>–<kbd>4</kbd> küncü seç, <kbd>←</kbd><kbd>→</kbd><kbd>↑</kbd><kbd>↓</kbd> 1px (<kbd>Shift</kbd> 10px)<br>'
         + 'Küncü <kbd>Shift</kbd> ilə çəkmək — bütövlükdə böyüt/kiçilt<br>'
         + '<kbd>G</kbd> dizaynı şəffaf göstər · <kbd>Delete</kbd> sil · <kbd>Ctrl</kbd>+<kbd>D</kbd> təkrarla<br>'
         + '<kbd>Ctrl</kbd>+<kbd>Z</kbd> geri · <kbd>Ctrl</kbd>+<kbd>S</kbd> saxla · <kbd>Ctrl</kbd>+təkər zoom</p>';
      props.innerHTML = h;
      return;
    }

    if (el.type === 'design') {
      h += '<h3>Dizayn yeri (corner pin)</h3>';
      h += '<div class="row one">' + field('Ad', txt('name', el.name)) + '</div>';
      h += '<h4>Künclər</h4>';
      el.corners.forEach(function(c, i){
        h += '<div class="corner' + (i === activeCorner ? ' on' : '') + '" data-pick="' + i + '"><span class="cn"><span class="dot">' + (i + 1) + '</span>' + CORNER_NAMES[i] + '</span>'
           + '<input type="number" step="0.5" data-corner="' + i + '" data-axis="0" value="' + r2(c[0]) + '" title="X">'
           + '<input type="number" step="0.5" data-corner="' + i + '" data-axis="1" value="' + r2(c[1]) + '" title="Y"></div>';
      });
      h += '<div class="actions"><button class="btn small" data-act="turn" title="Dizaynı eyni yerdə 90° döndər">↻ 90°</button>'
         + '<button class="btn small" data-act="mirror-x" title="Üfüqi güzgü">⇋</button><button class="btn small" data-act="mirror-y" title="Şaquli güzgü">⇅</button>'
         + '<button class="btn small" data-act="straighten" title="Perspektivi sil, dizaynın öz nisbətində düzbucaqlı et">Düzbucaqlı</button></div>';
      h += '<div class="actions"><button class="btn small" data-act="shrink">− 2%</button><button class="btn small" data-act="grow">+ 2%</button>'
         + '<button class="btn small" data-act="rot-l">⟲ 0.5°</button><button class="btn small" data-act="rot-r">⟳ 0.5°</button></div>';
      h += '<h4>Görünüş</h4>';
      h += '<div class="row one">' + field('Görünmə ' + el.opacity + '%', range('opacity', el.opacity, 0, 100)) + '</div>';
      h += '<div class="row one">' + field('Qarışma', seg('blend', el.blend || 'source-over', [['source-over', 'Normal'], ['multiply', 'Multiply']])) + '</div>';
      var imgs = doc.elements.filter(function(e){ return e.type === 'image'; });
      h += '<h4>İşıq və faktura</h4>';
      h += '<p class="hint" style="margin-top:0">Seçilmiş renderin işıq-kölgəsi və kağız fakturası dizaynın üzərinə düşür — qutu canlı görünür.</p>';
      h += '<div class="row one">' + field('Mənbə', '<select data-k="shade_from"><option value="">Yoxdur</option>' + imgs.map(function(e){
             return '<option value="' + esc(e.id) + '"' + (e.id === el.shade_from ? ' selected' : '') + '>' + esc(e.name || 'Şəkil') + '</option>';
           }).join('') + '</select>') + '</div>';
      h += '<div class="row one">' + field('Güc ' + (el.shade || 0) + '%', range('shade', el.shade || 0, 0, 200)) + '</div>';
      h += orderActions(el);
    } else {
      h += '<h3>Şəkil</h3>';
      h += '<div class="row one">' + field('Ad', txt('name', el.name)) + '</div>';
      h += '<div class="row four">' + field('X', num('x', el.x)) + field('Y', num('y', el.y)) + field('En', num('width', el.width)) + field('Hünd.', num('height', el.height)) + '</div>';
      h += '<div class="row">' + field('Bucaq °', num('rotation', el.rotation || 0, 0.5)) + field('Görünmə ' + el.opacity + '%', range('opacity', el.opacity, 0, 100)) + '</div>';
      h += '<div class="row one">' + field('Qarışma', '<select data-k="blend">' + BLENDS.map(function(b){
             return '<option value="' + b[0] + '"' + ((el.blend || 'source-over') === b[0] ? ' selected' : '') + '>' + b[1] + '</option>';
           }).join('') + '</select>') + '</div>';
      h += '<h4>Qutunun rəngi</h4>';
      h += '<p class="hint" style="margin-top:0">Ağ renderi istənilən rəngə boyayır — işıq, kölgə və faktura qalır.</p>';
      h += '<div class="swatches">' + SWATCHES.map(function(s){
             var on = (el.tint || null) === s[0];
             return '<button type="button" class="sw' + (on ? ' on' : '') + '" data-tint="' + (s[0] || '') + '" title="' + s[1] + '" style="background:' + (s[0] || '#ffffff') + '">' + (s[0] ? '' : '∅') + '</button>';
           }).join('') + '</div>';
      h += '<div class="row">' + field('Rəng', '<input type="color" data-k="tint" value="' + esc(el.tint || '#ffffff') + '">')
         + field('Parıltı ' + (el.sheen || 0) + '%', range('sheen', el.sheen || 0, 0, 100)) + '</div>';
      h += '<label class="check" title="Qutu hər dizaynda o dizaynın rənginə boyanır"><input type="checkbox" data-k="recolor"' + (el.recolor ? ' checked' : '') + '> Dizaynın rəngini götür</label>';
      if (el.recolor) h += '<p class="hint" style="margin:.2rem 0 0 1.5rem">Qutu hər dizaynın öz rəngini alır: Qutu redaktorunda seçilmiş və ya dizaynın kənarından avtomatik götürülmüş. Yuxarıdakı rəng yalnız rəngi bilinməyən dizaynlar üçündür.'
         + (sampleColor ? ' İndi nümunənin rəngi göstərilir: <b>' + esc(sampleColor) + '</b>.' : '') + '</p>';
      h += '<label class="check" style="margin-top:.35rem" title="Adətən yalnız ağ kağız boyanır; şokolad və s. öz rəngində qalır"><input type="checkbox" data-k="tint_all"' + (el.tint_all ? ' checked' : '') + '> Bütün şəkli boya (şokolad da)</label>';
      h += '<div class="actions"><button class="btn small" data-act="flip-x"' + (el.flip_x ? ' style="border-color:#7c3aed"' : '') + '>⇋ Güzgü</button>'
         + '<button class="btn small" data-act="flip-y"' + (el.flip_y ? ' style="border-color:#7c3aed"' : '') + '>⇅ Güzgü</button></div>';
      h += '<div class="actions"><button class="btn small" data-act="fill">Bütün səhnə</button><button class="btn small" data-act="natural">Orijinal ölçü</button>'
         + '<button class="btn small" data-act="center">Mərkəzə</button></div>';
      h += orderActions(el);
    }
    props.innerHTML = h;
  }

  props.addEventListener('input', function(e){
    var t = e.target;
    if (t.dataset.scene) {
      if (t.dataset.scene === 'is_active') doc.is_active = t.checked;
      else if (t.dataset.scene === 'background_color') { doc.background_color = t.value; render(); }
      else { doc.name = t.value; document.getElementById('title-name').textContent = t.value; }
      commitSoon();
      return;
    }
    var el = selected();
    if (!el) return;
    if (t.dataset.corner !== undefined) {
      if (t.value === '') return;
      el.corners[+t.dataset.corner][+t.dataset.axis] = +t.value;
      render(); commitSoon();
      return;
    }
    var k = t.dataset.k;
    if (!k) return;
    if (t.type === 'checkbox') { el[k] = t.checked; commit(); refresh(); return; }
    if (k === 'shade_from') el.shade_from = t.value || null;
    else if (k === 'blend' || k === 'name' || k === 'tint') el[k] = t.value;
    else if (t.type === 'number' || t.type === 'range') {
      if (t.value === '') return;
      el[k] = +t.value;
      if (k === 'opacity') t.closest('.field').querySelector('label').textContent = 'Görünmə ' + el.opacity + '%';
      if (k === 'shade') t.closest('.field').querySelector('label').textContent = 'Güc ' + el.shade + '%';
      if (k === 'sheen') t.closest('.field').querySelector('label').textContent = 'Parıltı ' + el.sheen + '%';
    }
    render();
    if (k === 'name') renderList();
    commitSoon();
  });
  props.addEventListener('change', function(){ commit(); });

  props.addEventListener('click', function(e){
    var pick = e.target.closest('[data-pick]');
    if (pick && e.target.tagName !== 'INPUT') { activeCorner = +pick.dataset.pick; renderOverlay(); renderProps(); return; }
    if (pick && e.target.tagName === 'INPUT') { activeCorner = +pick.dataset.pick; renderOverlay(); props.querySelectorAll('.corner').forEach(function(n){ n.classList.toggle('on', +n.dataset.pick === activeCorner); }); }
    var b = e.target.closest('button');
    if (!b) return;
    var el = selected();
    if (b.dataset.seg && el) { el[b.dataset.seg] = b.dataset.v; commit(); refresh(); return; }
    if (b.dataset.tint !== undefined && el) {
      el.tint = b.dataset.tint || null;
      /* A dark box needs some of the render's highlights back to read as 3D. */
      if (el.tint && !el.sheen && luminance(el.tint) < 0.25) el.sheen = 12;
      commit(); refresh(); return;
    }
    var act = b.dataset.act;
    if (!act) return;
    if (act === 'pick-bg') { setTab('background'); toast('Soldakı fonlardan birinə klikləyin'); return; }
    if (act === 'clear-bg') { setBackground(null); return; }
    if (act === 'clear-color') { doc.background_color = null; commit(); refresh(); return; }
    if (!el) return;
    if (act === 'del') return removeSelected();
    if (act === 'dup') return duplicateSelected();
    if (['front', 'back', 'up', 'down'].indexOf(act) >= 0) return moveElement(act);
    if (act === 'turn') cycleCorners(el, [1, 2, 3, 0]);
    else if (act === 'mirror-x') cycleCorners(el, [1, 0, 3, 2]);
    else if (act === 'mirror-y') cycleCorners(el, [3, 2, 1, 0]);
    else if (act === 'straighten') straighten(el);
    else if (act === 'grow' || act === 'shrink') {
      var f = act === 'grow' ? 1.02 : 1 / 1.02;
      transformAll(el, function(c, cc){ return [cc[0] + (c[0] - cc[0]) * f, cc[1] + (c[1] - cc[1]) * f]; });
    } else if (act === 'rot-l' || act === 'rot-r') {
      var d = act === 'rot-r' ? 0.5 : -0.5;
      transformAll(el, function(c, cc){ var q = rotatePoint(c[0], c[1], cc[0], cc[1], d); return [q.x, q.y]; });
    }
    else if (act === 'flip-x') el.flip_x = !el.flip_x;
    else if (act === 'flip-y') el.flip_y = !el.flip_y;
    else if (act === 'fill') { el.x = 0; el.y = 0; el.width = W(); el.height = H(); el.rotation = 0; }
    else if (act === 'natural') {
      var entry = images[el.url];
      if (entry && entry.img.naturalWidth) {
        var cx = el.x + el.width / 2, cy = el.y + el.height / 2;
        el.width = entry.img.naturalWidth; el.height = entry.img.naturalHeight;
        el.x = r2(cx - el.width / 2); el.y = r2(cy - el.height / 2);
      }
    } else if (act === 'center') { el.x = r2((W() - el.width) / 2); el.y = r2((H() - el.height) / 2); }
    commit(); refresh();
  });

  /* ================================================================
     Element list
     ================================================================ */
  function renderList(){
    var h = '';
    for (var i = doc.elements.length - 1; i >= 0; i--) {
      var el = doc.elements[i];
      var thumb = el.type === 'design' ? '<span style="color:#7c3aed;font-weight:700">▦</span>' : '<img src="' + esc(el.url) + '" alt="">';
      var kind = el.type === 'design' ? 'dizayn yeri' + (el.shade_from && el.shade ? ' · işıq ' + el.shade + '%' : '') : 'şəkil' + (el.recolor ? ' · dizaynın rəngi' : el.tint ? ' · rəng ' + el.tint : '') + (el.blend && el.blend !== 'source-over' ? ' · ' + el.blend : '');
      h += '<li draggable="true" data-index="' + i + '" class="' + (i === selIndex ? 'on' : '') + (el.hidden ? ' hidden-el' : '') + '">'
         + '<span class="thumb">' + thumb + '</span>'
         + '<span class="name">' + esc(el.name || (el.type === 'design' ? 'Dizayn' : 'Şəkil')) + '<br><span class="kind">' + esc(kind) + '</span></span>'
         + '<button class="mini" data-eye="' + i + '" title="Gizlət / göstər (saytda da)">' + (el.hidden ? '🙈' : '👁') + '</button>'
         + '<button class="mini" data-lock="' + i + '" title="Kilid">' + (el.locked ? '🔒' : '🔓') + '</button></li>';
    }
    h += '<li class="bg-row" data-bg="1"><span class="thumb">' + (doc.background_url ? '<img src="' + esc(doc.background_url) + '" alt="">'
       : '<span style="width:100%;height:100%;background:' + esc(doc.background_color || 'transparent') + '"></span>') + '</span>'
       + '<span class="name">Fon<br><span class="kind">' + (doc.background ? 'şəkil' : doc.background_color ? 'rəng' : 'yoxdur') + ' · səhnə ayarları</span></span></li>';
    layerList.innerHTML = h;
  }

  layerList.addEventListener('click', function(e){
    var eye = e.target.dataset.eye, lock = e.target.dataset.lock;
    if (eye !== undefined) { doc.elements[eye].hidden = !doc.elements[eye].hidden; commit(); refresh(); return; }
    if (lock !== undefined) { doc.elements[lock].locked = !doc.elements[lock].locked; commit(); refresh(); return; }
    if (e.target.closest('li[data-bg]')) { select(-1); return; }
    var li = e.target.closest('li[data-index]');
    if (li) select(+li.dataset.index);
  });

  var dragRow = null;
  layerList.addEventListener('dragstart', function(e){
    var li = e.target.closest('li[data-index]');
    if (!li) return;
    dragRow = +li.dataset.index;
    e.dataTransfer.effectAllowed = 'move';
  });
  function clearDropMarks(){ layerList.querySelectorAll('.drop-before, .drop-after').forEach(function(n){ n.classList.remove('drop-before', 'drop-after'); }); }
  layerList.addEventListener('dragover', function(e){
    if (dragRow === null) return;
    var li = e.target.closest('li[data-index]');
    clearDropMarks();
    if (!li) return;
    e.preventDefault();
    var r = li.getBoundingClientRect();
    li.classList.add(e.clientY < r.top + r.height / 2 ? 'drop-before' : 'drop-after');
  });
  layerList.addEventListener('dragend', function(){ dragRow = null; clearDropMarks(); });
  layerList.addEventListener('drop', function(e){
    if (dragRow === null) return;
    e.preventDefault();
    var li = e.target.closest('li[data-index]');
    var from = dragRow; dragRow = null; clearDropMarks();
    if (!li) return;
    var before = e.clientY < li.getBoundingClientRect().top + li.getBoundingClientRect().height / 2;
    var to = +li.dataset.index;
    var el = doc.elements.splice(from, 1)[0];
    if (to > from) to--;
    /* The list shows the top first, so "before" means higher in the stack. */
    var at = before ? to + 1 : to;
    doc.elements.splice(at, 0, el);
    select(at);
    commit();
  });

  /* ================================================================
     Library
     ================================================================ */
  var libGrid = document.getElementById('lib-grid');
  function setTab(t){
    libTab = t;
    document.querySelectorAll('#lib-tabs button').forEach(function(b){ b.classList.toggle('on', b.dataset.tab === t); });
    renderLibrary();
  }
  document.getElementById('lib-tabs').addEventListener('click', function(e){ var b = e.target.closest('button'); if (b) setTab(b.dataset.tab); });

  function renderLibrary(){
    var used = {};
    if (doc.background) used[doc.background] = true;
    doc.elements.forEach(function(el){ if (el.image) used[el.image] = true; });
    var list = LIB.filter(function(a){ return a.kind === libTab; });
    libGrid.innerHTML = list.map(function(a){
      return '<button type="button" class="tile' + (used[a.image] ? ' in-use' : '') + '" data-asset="' + a.id + '" title="' + esc(a.name) + ' — ' + a.width + '×' + a.height + '">'
        + '<div class="pic"><img src="' + esc(a.url) + '" alt="" loading="lazy"></div><div class="nm">' + esc(a.name) + '</div>'
        + '<span class="x" data-del="' + a.id + '" title="Kitabxanadan sil">✕</span></button>';
    }).join('') || '<div class="lib-empty">' + (libTab === 'background'
        ? 'Hələ fon yoxdur. <b>+ Yüklə</b> ilə JPG/PNG seçin və ya faylları bura sürükləyin.'
        : 'Hələ render yoxdur. Boş qutunun şəffaf PNG renderini <b>+ Yüklə</b> ilə əlavə edin və ya bura sürükləyin.') + '</div>';
  }

  libGrid.addEventListener('click', function(e){
    var del = e.target.dataset.del;
    if (del) {
      e.stopPropagation();
      var a = LIB.filter(function(x){ return String(x.id) === del; })[0];
      if (!a || !confirm('"' + a.name + '" kitabxanadan silinsin?')) return;
      request('DELETE', ROUTES.assetDestroy.replace('__ID__', del)).then(function(){
        LIB = LIB.filter(function(x){ return x !== a; });
        renderLibrary();
        toast('Silindi');
      }).catch(function(err){ toast(err.message, true); });
      return;
    }
    var tile = e.target.closest('[data-asset]');
    if (!tile) return;
    var asset = LIB.filter(function(x){ return String(x.id) === tile.dataset.asset; })[0];
    if (!asset) return;
    if (asset.kind === 'background') setBackground(asset); else addImage(asset);
  });

  function request(method, url, body){
    var headers = { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' };
    if (body && !(body instanceof FormData)) { headers['Content-Type'] = 'application/json'; body = JSON.stringify(body); }
    return fetch(url, { method: method, headers: headers, body: body, credentials: 'same-origin' })
      .then(function(r){
        return r.json().catch(function(){ return {}; }).then(function(res){
          if (!r.ok) throw new Error(res.message || ('Xəta ' + r.status));
          return res;
        });
      });
  }

  /* Renders come at 3080×3850 and weigh megabytes; shrinking them here to what
     a screen needs keeps uploads quick and the hosting light. */
  function shrink(file){
    return new Promise(function(resolve){
      var url = URL.createObjectURL(file), img = new Image();
      var base = file.name.replace(/\.[^.]+$/, '');
      img.onload = function(){
        var w = img.naturalWidth, h = img.naturalHeight, k = Math.min(1, MAX_SIDE / Math.max(w, h));
        if (k >= 1) { URL.revokeObjectURL(url); resolve({ blob: file, name: file.name, base: base }); return; }
        var c = document.createElement('canvas');
        c.width = Math.round(w * k); c.height = Math.round(h * k);
        var x = c.getContext('2d');
        x.imageSmoothingQuality = 'high';
        x.drawImage(img, 0, 0, c.width, c.height);
        URL.revokeObjectURL(url);
        var png = !/jpe?g/i.test(file.type);
        c.toBlob(function(b){ resolve({ blob: b || file, name: base + (png ? '.png' : '.jpg'), base: base }); }, png ? 'image/png' : 'image/jpeg', 0.92);
      };
      img.onerror = function(){ URL.revokeObjectURL(url); resolve({ blob: file, name: file.name, base: base }); };
      img.src = url;
    });
  }

  function upload(files, kind, apply){
    var list = Array.prototype.slice.call(files).filter(function(f){ return /^image\/(png|jpeg|webp)$/.test(f.type); });
    if (!list.length) { toast('PNG, JPG və ya WebP seçin', true); return; }
    var done = 0;
    toast('Yüklənir… (' + list.length + ')');
    list.reduce(function(chain, file){
      return chain.then(function(){ return shrink(file); }).then(function(s){
        var fd = new FormData();
        fd.append('file', s.blob, s.name);
        fd.append('kind', kind);
        fd.append('name', s.base);
        return request('POST', ROUTES.asset, fd);
      }).then(function(asset){
        LIB.unshift(asset);
        done++;
        if (apply || (kind === 'background' && !doc.background)) {
          if (kind === 'background') setBackground(asset); else addImage(asset);
        }
        setTab(kind);
      });
    }, Promise.resolve()).then(function(){
      toast(done > 1 ? done + ' şəkil kitabxanaya əlavə olundu' : 'Kitabxanaya əlavə olundu');
    }).catch(function(err){ toast(err.message, true); });
  }

  document.getElementById('lib-upload').onclick = function(){ document.getElementById('file-lib').click(); };
  document.getElementById('file-lib').onchange = function(){ upload(this.files, libTab, false); this.value = ''; };

  function hasFiles(e){ return e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types, 'Files') >= 0; }
  function dropArea(area, label, onDrop){
    var zone = null;
    area.addEventListener('dragover', function(e){
      if (dragRow !== null || !hasFiles(e)) return;
      e.preventDefault();
      if (!zone) { zone = document.createElement('div'); zone.className = area === workspace ? 'drop-zone' : 'lib-drop'; area.appendChild(zone); }
      zone.textContent = label();
    });
    area.addEventListener('dragleave', function(e){ if (zone && !area.contains(e.relatedTarget)) { zone.remove(); zone = null; } });
    area.addEventListener('drop', function(e){
      if (dragRow !== null || !hasFiles(e)) return;
      e.preventDefault();
      if (zone) { zone.remove(); zone = null; }
      onDrop(e.dataTransfer.files);
    });
  }
  dropArea(document.getElementById('library'), function(){ return libTab === 'background' ? 'Fon kimi əlavə et' : 'Qutu / əşya kimi əlavə et'; },
    function(files){ upload(files, libTab, false); });
  /* Onto the canvas: a JPG (no transparency) is a background, a PNG is an object. */
  dropArea(workspace, function(){ return 'Buraxın: JPG — fon, PNG — qutu / əşya'; }, function(files){
    var bg = [], obj = [];
    Array.prototype.forEach.call(files, function(f){ (/jpe?g/i.test(f.type) ? bg : obj).push(f); });
    if (bg.length) upload(bg.slice(0, 1), 'background', true);
    if (obj.length) upload(obj, 'object', true);
  });

  /* ================================================================
     Sample design
     ================================================================ */
  sampleSel.onchange = function(){ setSample(sampleSel.value); };
  document.getElementById('sample-file').onclick = function(){ document.getElementById('file-sample').click(); };
  document.getElementById('file-sample').onchange = function(){
    var file = this.files[0]; this.value = '';
    if (!file) return;
    var r = new FileReader();
    r.onload = function(ev){
      var img = new Image();
      img.onload = function(){ ownSample = img; renderSampleOptions(); setSample('own'); };
      img.src = ev.target.result;
    };
    r.readAsDataURL(file);
  };
  ghost.onchange = render;

  /* ================================================================
     Save
     ================================================================ */
  function preview(){
    try {
      var s = 480 / W(), c = document.createElement('canvas');
      c.width = 480; c.height = Math.round(H() * s);
      var x = c.getContext('2d');
      x.fillStyle = '#ffffff'; x.fillRect(0, 0, c.width, c.height);
      NefisScene.drawScene(x, NefisScene.scaled(sceneOf(), s), sampleImg, getImage, {}, {});
      return c.toDataURL('image/jpeg', 0.82);
    } catch (e) { return null; }
  }

  function save(){
    commit();
    if (!doc.elements.some(function(e){ return e.type === 'design' && !e.hidden; })) {
      toast('Diqqət: səhnədə dizayn yeri yoxdur — müştərinin qutusu burada görünməyəcək', true);
    }
    var payload = {
      name: doc.name || 'Səhnə', is_active: !!doc.is_active,
      background: doc.background || null, background_color: doc.background_color || null,
      width: W(), height: H(),
      elements: doc.elements.map(function(el){ var o = clone(el); delete o.url; return o; }),
      preview: preview()
    };
    var btn = document.getElementById('save');
    btn.disabled = true;
    saveState.textContent = 'Saxlanılır…';
    request('POST', ROUTES.save, payload).then(function(res){
      savedSnapshot = snapshot();
      updateDirty();
      saveState.textContent = 'Saxlanıldı ' + res.saved_at;
      saveState.className = 'save-state ok';
      toast('Saxlanıldı — saytda yenilənib');
      /* Products whose catalogue cover is drawn in this scene get a new one. */
      var covers = res.covers || [];
      if (covers.length && window.NefisCover) {
        NefisCover.runAll(covers, CSRF, function(i){ toast('Kataloq qapaqları yenilənir… ' + (i + 1) + '/' + covers.length); })
          .then(function(failed){ toast(failed ? failed + ' qapaq yenilənmədi' : 'Kataloq qapaqları yeniləndi (' + covers.length + ')', !!failed); });
      }
    }).catch(function(err){ toast('Saxlanılmadı: ' + err.message, true); updateDirty(); })
      .then(function(){ btn.disabled = false; });
  }
  document.getElementById('save').onclick = save;
  document.getElementById('undo').onclick = undo;
  document.getElementById('redo').onclick = redo;
  document.getElementById('add-design').onclick = addDesign;
  window.addEventListener('beforeunload', function(e){ if (snapshot() !== savedSnapshot) { e.preventDefault(); e.returnValue = ''; } });

  /* ================================================================
     Keyboard
     ================================================================ */
  document.addEventListener('keydown', function(e){
    var el0 = document.activeElement, tag = (el0 && el0.tagName) || '';
    var typing = tag === 'TEXTAREA' || tag === 'SELECT' || (tag === 'INPUT' && /^(text|number|search|email|url)$/.test(el0.type));
    var mod = e.ctrlKey || e.metaKey;
    var key = e.key.toLowerCase();

    if (mod && key === 's') { e.preventDefault(); save(); return; }
    if (typing) return;
    if (mod && key === 'z' && !e.shiftKey) { e.preventDefault(); undo(); return; }
    if (mod && (key === 'y' || (key === 'z' && e.shiftKey))) { e.preventDefault(); redo(); return; }
    if (mod && key === 'd') { e.preventDefault(); duplicateSelected(); return; }
    if (mod && e.key === '0') { e.preventDefault(); fitZoom(); return; }
    if (!mod && key === 'g') { ghost.checked = !ghost.checked; render(); return; }
    if (e.key === 'Escape') { if (activeCorner >= 0) { activeCorner = -1; renderOverlay(); renderProps(); } else select(-1); return; }

    var el = selected();
    if (!el) return;
    if (e.key === 'Delete' || e.key === 'Backspace') { e.preventDefault(); removeSelected(); return; }
    if (el.type === 'design' && /^[1-4]$/.test(e.key) && !mod) { activeCorner = +e.key - 1; renderOverlay(); renderProps(); return; }
    var step = e.shiftKey ? 10 : 1;
    var mv = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] }[e.key];
    if (!mv || el.locked) return;
    e.preventDefault();
    if (el.type === 'design') {
      if (activeCorner >= 0) {
        var c = el.corners[activeCorner];
        el.corners[activeCorner] = [r2(c[0] + mv[0]), r2(c[1] + mv[1])];
      } else {
        el.corners = el.corners.map(function(c){ return [r2(c[0] + mv[0]), r2(c[1] + mv[1])]; });
      }
    } else { el.x = r2(el.x + mv[0]); el.y = r2(el.y + mv[1]); }
    render(); renderProps(true); commitSoon();
  });

  /* ================================================================
     Start
     ================================================================ */
  doc.elements.forEach(function(el){ if (el.url) getImage(el.url); });
  if (doc.background_url) getImage(doc.background_url);
  renderSampleOptions();
  setSample(SAMPLES.length ? '0' : 'ph');
  sizeCanvas(false);
  window.addEventListener('resize', renderOverlay);
  fitZoom();
  refresh();
})();
</script>
</body>
</html>
