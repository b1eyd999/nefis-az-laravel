<!DOCTYPE html>
<html lang="az">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $product->name }} — Yerləşdirmə</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Great+Vibes&display=swap" rel="stylesheet">
<style>
  *,*::before,*::after{ box-sizing:border-box; }
  body{
    margin:0; background:#15100c; color:#f3e9dc;
    font:15px/1.5 Inter, system-ui, sans-serif;
  }
  header{
    display:flex; align-items:center; gap:1rem; flex-wrap:wrap;
    padding:.85rem 1.25rem; background:#1e1712; border-bottom:1px solid #35291f; position:sticky; top:0; z-index:20;
  }
  header h1{ font-size:1.05rem; margin:0; font-weight:600; }
  header .sp{ flex:1; }
  .tabs{ display:flex; gap:.4rem; flex-wrap:wrap; }
  .tab{
    padding:.4rem .8rem; border-radius:999px; border:1px solid #3d2f23; background:#241c15;
    color:#cbb9a5; font-size:.8125rem; font-weight:600; cursor:pointer;
  }
  .tab.active{ background:#c08a3e; border-color:#c08a3e; color:#1a1208; }
  .btn{
    padding:.55rem 1.1rem; border-radius:.55rem; border:1px solid transparent;
    font-weight:600; font-size:.875rem; cursor:pointer;
  }
  .btn-primary{ background:#c08a3e; color:#1a1208; }
  .btn-ghost{ background:transparent; color:#cbb9a5; border-color:#3d2f23; }
  .btn-danger{ background:transparent; color:#e0705f; border-color:#5a3029; }
  .flash{ background:#24402a; color:#bfe6c6; padding:.5rem .9rem; border-radius:.5rem; font-size:.875rem; }

  main{ display:grid; grid-template-columns:1fr 26rem; gap:1.25rem; padding:1.25rem; align-items:start; }
  @media (max-width:1100px){ main{ grid-template-columns:1fr; } }

  .stage-wrap{ background:#1e1712; border:1px solid #35291f; border-radius:.9rem; padding:1rem; }
  .stage{ position:relative; margin:0 auto; user-select:none; touch-action:none; }
  .stage img{ display:block; width:100%; height:auto; pointer-events:none; }
  .stage img.overlay{ position:absolute; inset:0; }

  .slot{ position:absolute; cursor:move; }
  .slot .body{
    position:absolute; inset:0; border:2px solid #4da3ff; background:rgba(77,163,255,.16);
  }
  .slot.ellipse .body{ border-radius:50%; }
  .slot.text .body{ border-style:dashed; border-color:#ffd166; background:rgba(255,209,102,.12); }
  .slot.active .body{ box-shadow:0 0 0 2px #fff inset; }
  .slot .tag{
    position:absolute; top:-1.35rem; left:0; font-size:.6875rem; font-weight:700;
    background:#4da3ff; color:#06233f; padding:.05rem .4rem; border-radius:.3rem; white-space:nowrap;
  }
  .slot.text .tag{ background:#ffd166; color:#3a2600; }
  .slot .grip{
    position:absolute; right:-6px; bottom:-6px; width:13px; height:13px; border-radius:3px;
    background:#fff; border:2px solid #4da3ff; cursor:nwse-resize;
  }
  .slot.text .grip{ border-color:#ffd166; }
  .slot .preview-text{
    position:absolute; inset:0; display:flex; align-items:center; overflow:hidden;
    pointer-events:none; white-space:pre-wrap; line-height:1.2;
  }

  .panel{ background:#1e1712; border:1px solid #35291f; border-radius:.9rem; padding:1rem; }
  .panel h2{ font-size:.8125rem; letter-spacing:.08em; text-transform:uppercase; color:#9c8672; margin:0 0 .75rem; }
  .card{ border:1px solid #35291f; border-radius:.6rem; padding:.7rem; margin-bottom:.6rem; background:#241c15; }
  .card.active{ border-color:#c08a3e; }
  .card .row{ display:flex; gap:.4rem; align-items:center; margin-bottom:.4rem; }
  .card .row:last-child{ margin-bottom:0; }
  label.mini{ font-size:.6875rem; color:#9c8672; display:block; margin-bottom:.15rem; }
  input,select{
    width:100%; padding:.35rem .5rem; border-radius:.4rem; border:1px solid #3d2f23;
    background:#15100c; color:#f3e9dc; font:inherit; font-size:.8125rem;
  }
  input[type=color]{ padding:2px; height:1.9rem; }
  .grid4{ display:grid; grid-template-columns:repeat(4,1fr); gap:.4rem; }
  .grid3{ display:grid; grid-template-columns:repeat(3,1fr); gap:.4rem; }
  .grid2{ display:grid; grid-template-columns:repeat(2,1fr); gap:.4rem; }
  .hint{ font-size:.75rem; color:#8a7461; margin-top:.5rem; line-height:1.45; }
</style>
</head>
<body>

<header>
  <h1>{{ $product->name }}</h1>
  <div class="tabs" id="tabs"></div>
  <span class="sp"></span>
  @if(session('status'))<span class="flash">{{ session('status') }}</span>@endif
  <label style="display:flex;align-items:center;gap:.4rem;font-size:.8125rem;color:#cbb9a5;">
    <input type="checkbox" id="toggle-overlay" checked style="width:auto;"> Üst qat
  </label>
  <a class="btn btn-ghost" href="{{ route('products.customize', $product->slug) }}" target="_blank">Saytda bax</a>
  <button class="btn btn-primary" id="save">Yadda saxla</button>
</header>

<main>
  <div class="stage-wrap">
    <div class="stage" id="stage">
      <img id="template" src="" alt="">
      <img id="overlay" class="overlay" src="" alt="" hidden>
    </div>
  </div>

  <div>
    <div class="panel" style="margin-bottom:1rem;">
      <h2>Foto sahələri</h2>
      <div id="photo-list"></div>
      <button class="btn btn-ghost" id="add-photo" style="width:100%;">+ Foto sahəsi</button>
    </div>

    <div class="panel">
      <h2>Mətn sahələri</h2>
      <div id="text-list"></div>
      <button class="btn btn-ghost" id="add-text" style="width:100%;">+ Mətn sahəsi</button>
      <p class="hint">
        Mətnin nöqtəsi <strong>şaquli mərkəzdir</strong>. Düzülüş sola/mərkəzə/sağa
        görə X həmin kənarı göstərir — saytdakı render ilə eynidir.
      </p>
    </div>
  </div>
</main>

<form id="save-form" method="POST" action="{{ route('layout.update', $product->slug) }}" hidden>@csrf</form>

<script>
(function(){
  "use strict";

  var VIEWS = @json($views);
  var active = 0;
  var selected = null; /* {kind:'photo'|'text', index:n} */

  var stage = document.getElementById('stage');
  var tplImg = document.getElementById('template');
  var ovImg = document.getElementById('overlay');
  var tabs = document.getElementById('tabs');
  var photoList = document.getElementById('photo-list');
  var textList = document.getElementById('text-list');

  function view(){ return VIEWS[active]; }
  function scale(){ return stage.clientWidth / view().width || 1; }

  /* ---------- fonts used by text slots ---------- */
  VIEWS.forEach(function(v){
    v.text_slots.forEach(function(t){
      if (!t.font_file || !t.font_family) return;
      try {
        new FontFace(t.font_family, 'url(/storage/' + t.font_file + ')').load()
          .then(function(f){ document.fonts.add(f); render(); }).catch(function(){});
      } catch(e){}
    });
  });

  /* ---------- tabs ---------- */
  function buildTabs(){
    tabs.innerHTML = '';
    VIEWS.forEach(function(v, i){
      var b = document.createElement('button');
      b.className = 'tab' + (i === active ? ' active' : '');
      b.textContent = v.label;
      b.addEventListener('click', function(){ active = i; selected = null; loadView(); });
      tabs.appendChild(b);
    });
  }

  function loadView(){
    var v = view();
    tplImg.src = v.template;
    if (v.overlay) { ovImg.src = v.overlay; ovImg.hidden = !document.getElementById('toggle-overlay').checked; }
    else { ovImg.hidden = true; ovImg.removeAttribute('src'); }
    stage.style.aspectRatio = v.width + ' / ' + v.height;
    stage.style.maxWidth = Math.min(v.width, 760) + 'px';
    buildTabs();
    render();
  }

  document.getElementById('toggle-overlay').addEventListener('change', function(){
    ovImg.hidden = !this.checked || !view().overlay;
  });

  /* ---------- rendering the boxes ---------- */
  function clearBoxes(){
    stage.querySelectorAll('.slot').forEach(function(n){ n.remove(); });
  }

  function textBoxRect(t){
    var w = t.max_width;
    var h = Math.max(t.font_size * 1.35, 18);
    var left = t.align === 'left' ? t.x : (t.align === 'right' ? t.x - w : t.x - w / 2);
    return { left: left, top: t.y - h / 2, width: w, height: h };
  }

  function render(){
    clearBoxes();
    var s = scale();
    var v = view();

    v.photo_slots.forEach(function(p, i){
      var el = document.createElement('div');
      el.className = 'slot' + (p.shape === 'ellipse' ? ' ellipse' : '') +
                     (selected && selected.kind === 'photo' && selected.index === i ? ' active' : '');
      el.style.left = (p.x * s) + 'px';
      el.style.top = (p.y * s) + 'px';
      el.style.width = (p.width * s) + 'px';
      el.style.height = (p.height * s) + 'px';
      el.style.transform = 'rotate(' + p.rotation + 'deg)';
      el.innerHTML = '<div class="body"></div><span class="tag">' +
        (p.label || ('Şəkil ' + (i + 1))) + '</span><span class="grip"></span>';
      attachDrag(el, 'photo', i);
      stage.appendChild(el);
    });

    v.text_slots.forEach(function(t, i){
      var r = textBoxRect(t);
      var el = document.createElement('div');
      el.className = 'slot text' + (selected && selected.kind === 'text' && selected.index === i ? ' active' : '');
      el.style.left = (r.left * s) + 'px';
      el.style.top = (r.top * s) + 'px';
      el.style.width = (r.width * s) + 'px';
      el.style.height = (r.height * s) + 'px';
      if (t.rotation) {
        /* Same pivot the storefront uses: the slot's own anchor point. */
        el.style.transformOrigin = ((t.x - r.left) * s) + 'px 50%';
        el.style.transform = 'rotate(' + t.rotation + 'deg)';
      }
      var preview = document.createElement('div');
      preview.className = 'preview-text';
      preview.textContent = t.default_value || t.placeholder || (t.label || 'Mətn');
      preview.style.fontSize = (t.font_size * s) + 'px';
      preview.style.color = t.color;
      preview.style.justifyContent = t.align === 'left' ? 'flex-start' : (t.align === 'right' ? 'flex-end' : 'center');
      preview.style.fontFamily = t.font_family ? ('"' + t.font_family + '", Inter, sans-serif') : 'Inter, sans-serif';
      preview.style.fontWeight = String(t.font_weight || 600);
      if (t.stroke_width > 0 && t.stroke_color) {
        preview.style.webkitTextStroke = (t.stroke_width * s) + 'px ' + t.stroke_color;
        preview.style.paintOrder = 'stroke fill';
      }
      el.innerHTML = '<div class="body"></div><span class="tag">' +
        (t.label || ('Mətn ' + (i + 1))) + '</span><span class="grip"></span>';
      el.appendChild(preview);
      attachDrag(el, 'text', i);
      stage.appendChild(el);
    });

    buildPanels();
  }

  /* ---------- drag & resize ---------- */
  function attachDrag(el, kind, index){
    var grip = el.querySelector('.grip');

    function start(e, mode){
      e.preventDefault();
      e.stopPropagation();
      selected = { kind: kind, index: index };
      var s = scale();
      var startX = (e.touches ? e.touches[0].clientX : e.clientX);
      var startY = (e.touches ? e.touches[0].clientY : e.clientY);
      var slot = kind === 'photo' ? view().photo_slots[index] : view().text_slots[index];
      var base = kind === 'photo'
        ? { x: slot.x, y: slot.y, w: slot.width, h: slot.height }
        : { x: slot.x, y: slot.y, w: slot.max_width, h: slot.font_size };

      function move(ev){
        var cx = (ev.touches ? ev.touches[0].clientX : ev.clientX);
        var cy = (ev.touches ? ev.touches[0].clientY : ev.clientY);
        var dx = Math.round((cx - startX) / s);
        var dy = Math.round((cy - startY) / s);

        if (mode === 'move') {
          slot.x = base.x + dx;
          slot.y = base.y + dy;
        } else if (kind === 'photo') {
          slot.width = Math.max(10, base.w + dx);
          slot.height = Math.max(10, base.h + dy);
        } else {
          slot.max_width = Math.max(20, base.w + dx);
          slot.font_size = Math.max(6, base.h + Math.round(dy / 2));
        }
        render();
      }
      function end(){
        window.removeEventListener('mousemove', move);
        window.removeEventListener('mouseup', end);
        window.removeEventListener('touchmove', move);
        window.removeEventListener('touchend', end);
      }
      window.addEventListener('mousemove', move);
      window.addEventListener('mouseup', end);
      window.addEventListener('touchmove', move, { passive:false });
      window.addEventListener('touchend', end);
    }

    el.addEventListener('mousedown', function(e){ start(e, 'move'); });
    el.addEventListener('touchstart', function(e){ start(e, 'move'); }, { passive:false });
    grip.addEventListener('mousedown', function(e){ start(e, 'resize'); });
    grip.addEventListener('touchstart', function(e){ start(e, 'resize'); }, { passive:false });
  }

  /* ---------- side panels ---------- */
  function field(label, value, oninput, type, opts){
    var wrap = document.createElement('div');
    var l = document.createElement('label');
    l.className = 'mini'; l.textContent = label;
    var input;
    if (type === 'select') {
      input = document.createElement('select');
      opts.forEach(function(o){
        var op = document.createElement('option');
        op.value = o[0]; op.textContent = o[1];
        if (String(value) === String(o[0])) op.selected = true;
        input.appendChild(op);
      });
      input.addEventListener('change', function(){ oninput(input.value); });
    } else {
      input = document.createElement('input');
      input.type = type || 'text';
      input.value = value === null || value === undefined ? '' : value;
      input.addEventListener('input', function(){
        oninput(type === 'number' ? parseInt(input.value || '0', 10) : input.value);
      });
    }
    wrap.appendChild(l); wrap.appendChild(input);
    return wrap;
  }

  function buildPanels(){
    var v = view();

    photoList.innerHTML = '';
    v.photo_slots.forEach(function(p, i){
      var card = document.createElement('div');
      card.className = 'card' + (selected && selected.kind === 'photo' && selected.index === i ? ' active' : '');
      card.addEventListener('mousedown', function(){ selected = { kind:'photo', index:i }; render(); });

      var head = document.createElement('div'); head.className = 'row';
      head.appendChild(field('Ad', p.label || '', function(val){ p.label = val; render(); }));
      var del = document.createElement('button');
      del.className = 'btn btn-danger'; del.textContent = '×';
      del.style.marginTop = '.9rem';
      del.addEventListener('click', function(){ v.photo_slots.splice(i,1); selected = null; render(); });
      head.appendChild(del);
      card.appendChild(head);

      var g = document.createElement('div'); g.className = 'grid4';
      g.appendChild(field('X', p.x, function(val){ p.x = val; render(); }, 'number'));
      g.appendChild(field('Y', p.y, function(val){ p.y = val; render(); }, 'number'));
      g.appendChild(field('En', p.width, function(val){ p.width = val; render(); }, 'number'));
      g.appendChild(field('Hünd.', p.height, function(val){ p.height = val; render(); }, 'number'));
      card.appendChild(g);

      var g2 = document.createElement('div'); g2.className = 'grid2'; g2.style.marginTop = '.4rem';
      g2.appendChild(field('Bucaq', p.rotation, function(val){ p.rotation = val; render(); }, 'number'));
      g2.appendChild(field('Forma', p.shape, function(val){ p.shape = val; render(); }, 'select',
        [['rectangle','Düzbucaq'],['ellipse','Oval']]));
      card.appendChild(g2);

      photoList.appendChild(card);
    });

    textList.innerHTML = '';
    v.text_slots.forEach(function(t, i){
      var card = document.createElement('div');
      card.className = 'card' + (selected && selected.kind === 'text' && selected.index === i ? ' active' : '');
      card.addEventListener('mousedown', function(){ selected = { kind:'text', index:i }; render(); });

      var head = document.createElement('div'); head.className = 'row';
      head.appendChild(field('Ad', t.label || '', function(val){ t.label = val; render(); }));
      var del = document.createElement('button');
      del.className = 'btn btn-danger'; del.textContent = '×';
      del.style.marginTop = '.9rem';
      del.addEventListener('click', function(){ v.text_slots.splice(i,1); selected = null; render(); });
      head.appendChild(del);
      card.appendChild(head);

      card.appendChild(field('İlkin mətn', t.default_value || '', function(val){ t.default_value = val; render(); }));

      var g = document.createElement('div'); g.className = 'grid4'; g.style.marginTop = '.4rem';
      g.appendChild(field('X', t.x, function(val){ t.x = val; render(); }, 'number'));
      g.appendChild(field('Y', t.y, function(val){ t.y = val; render(); }, 'number'));
      g.appendChild(field('Maks en', t.max_width, function(val){ t.max_width = val; render(); }, 'number'));
      g.appendChild(field('Ölçü', t.font_size, function(val){ t.font_size = val; render(); }, 'number'));
      card.appendChild(g);

      var g2 = document.createElement('div'); g2.className = 'grid3'; g2.style.marginTop = '.4rem';
      g2.appendChild(field('Rəng', t.color || '#000000', function(val){ t.color = val; render(); }, 'color'));
      g2.appendChild(field('Düzülüş', t.align, function(val){ t.align = val; render(); }, 'select',
        [['left','Sol'],['center','Mərkəz'],['right','Sağ']]));
      g2.appendChild(field('Maks sətir', t.max_lines || 1, function(val){ t.max_lines = Math.max(1, val); render(); }, 'number'));
      card.appendChild(g2);

      var g2b = document.createElement('div'); g2b.className = 'grid3'; g2b.style.marginTop = '.4rem';
      g2b.appendChild(field('Bucaq °', t.rotation || 0, function(val){ t.rotation = Math.max(-180, Math.min(180, val)); render(); }, 'number'));
      g2b.appendChild(field('Kontur rəngi', t.stroke_color || '#000000', function(val){ t.stroke_color = val; render(); }, 'color'));
      g2b.appendChild(field('Kontur qalınlığı', t.stroke_width || 0, function(val){ t.stroke_width = Math.max(0, val); render(); }, 'number'));
      card.appendChild(g2b);

      var g3 = document.createElement('div'); g3.className = 'grid2'; g3.style.marginTop = '.4rem';
      g3.appendChild(field('Maks simvol', t.max_length, function(val){ t.max_length = val; }, 'number'));
      g3.appendChild(field('İpucu', t.placeholder || '', function(val){ t.placeholder = val; }));
      card.appendChild(g3);

      card.appendChild(field('Şrift adı', t.font_family || '', function(val){ t.font_family = val; render(); }));
      card.appendChild(field('Şrift faylı', t.font_file || '', function(val){ t.font_file = val; }));

      textList.appendChild(card);
    });
  }

  document.getElementById('add-photo').addEventListener('click', function(){
    var v = view();
    v.photo_slots.push({
      label: 'Şəkil ' + (v.photo_slots.length + 1),
      x: Math.round(v.width * 0.25), y: Math.round(v.height * 0.25),
      width: Math.round(v.width * 0.5), height: Math.round(v.height * 0.35),
      rotation: 0, shape: 'rectangle'
    });
    selected = { kind:'photo', index: v.photo_slots.length - 1 };
    render();
  });

  document.getElementById('add-text').addEventListener('click', function(){
    var v = view();
    v.text_slots.push({
      label: 'Mətn ' + (v.text_slots.length + 1),
      x: Math.round(v.width / 2), y: Math.round(v.height * 0.85),
      max_width: Math.round(v.width * 0.7), font_size: Math.max(14, Math.round(v.height / 40)),
      color: '#000000', align: 'center',
      font_family: null, font_file: null,
      default_value: '', placeholder: '', max_length: 60, max_lines: 1,
      rotation: 0, shadow_blur: 0, shadow_x: 0, shadow_y: 0
    });
    selected = { kind:'text', index: v.text_slots.length - 1 };
    render();
  });

  /* ---------- save ---------- */
  document.getElementById('save').addEventListener('click', function(){
    var form = document.getElementById('save-form');
    form.querySelectorAll('input[name^="views"]').forEach(function(n){ n.remove(); });

    VIEWS.forEach(function(v, vi){
      function put(name, value){
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'views[' + vi + ']' + name;
        input.value = value === null || value === undefined ? '' : value;
        form.appendChild(input);
      }
      put('[type]', v.type);
      put('[id]', v.id);
      v.photo_slots.forEach(function(p, i){
        ['label','x','y','width','height','rotation','shape'].forEach(function(k){
          put('[photo_slots][' + i + '][' + k + ']', p[k]);
        });
      });
      v.text_slots.forEach(function(t, i){
        ['label','x','y','max_width','font_size','color','align','font_family','font_file','default_value','placeholder','max_length','max_lines',
         'rotation','font_weight','stroke_color','stroke_width','shadow_color','shadow_blur','shadow_x','shadow_y','link_key'].forEach(function(k){
          put('[text_slots][' + i + '][' + k + ']', t[k]);
        });
      });
    });

    form.submit();
  });

  window.addEventListener('resize', render);
  tplImg.addEventListener('load', render);
  loadView();
})();
</script>
</body>
</html>
