@extends('layouts.app')

@section('title', $product->name . ' — Fərdiləşdir — Nefis Şokolad Evi')

@php
  $photoSlots = $product->photoSlots;
  $textSlots = $product->textSlots;
@endphp

@section('page_style')
  .slot-block [hidden]{ display:none !important; }
  .slot-block{ border-top:1px solid var(--line); padding-top:1.25rem; }
  .slot-block:first-of-type{ border-top:none; padding-top:0; }
  .slot-block .zoom-row{ margin-top:.75rem; }
  .slot-block .rotate-row{ margin-top:.5rem; }
  .rotate-reset{
    flex:none; width:2rem; height:2rem; border-radius:50%; border:1px solid var(--line);
    background:var(--paper); color:var(--cocoa); font-size:.9rem; line-height:1;
  }
  .rotate-reset:hover{ border-color:var(--gold); }
  .slot-hint{ font-size:.8125rem; color:var(--cocoa-soft); margin-top:.5rem; }
@endsection

@section('content')
<section class="page-hero" style="padding-bottom:0;">
  <div class="wrap">
    <span class="eyebrow" style="justify-content:center;">Fərdiləşdirmə</span>
    <h1>{{ $product->name }}</h1>
    @if($product->description)
      <p class="lede" style="margin-inline:auto;">{{ $product->description }}</p>
    @endif
  </div>
</section>

<section>
  <div class="wrap">
    @if(session('status'))
      <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if($errors->any())
      <div class="alert alert-error">
        <ul style="margin:0; padding-left:1.1rem;">
          @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <div class="customizer">
      <div>
        <div class="stage" id="stage">
          <canvas id="preview-canvas"></canvas>
          @if($photoSlots->isNotEmpty())
            <div class="drop-hint" id="drop-hint">Öncə sağdan şəklinizi yükləyin</div>
          @endif
          @if(count($viewData) > 1)
            <button type="button" class="angle-arrow prev" id="angle-prev" aria-label="Əvvəlki görünüş">‹</button>
            <button type="button" class="angle-arrow next" id="angle-next" aria-label="Sonrakı görünüş">›</button>
          @endif
        </div>
        @if(count($viewData) > 1)
          <div class="angle-thumbs" id="angle-thumbs">
            @foreach($viewData as $view)
              <button type="button" class="angle-thumb{{ $loop->first ? ' active' : '' }}" data-angle="{{ $loop->index }}">
                <img src="{{ $view['bg'] ?: $view['url'] }}" alt="{{ $view['label'] ?? $product->name }}">
              </button>
            @endforeach
          </div>
        @endif
      </div>

      <form class="customize-panel" method="POST" action="{{ route('cart.add') }}" enctype="multipart/form-data" id="customize-form">
        @csrf
        <input type="hidden" name="product_id" value="{{ $product->id }}">

        @foreach($photoSlots as $index => $slot)
          <div class="slot-block" data-slot="{{ $index }}">
            <label>{{ $loop->iteration }}. {{ $slot->label ?: 'Şəkil' }}</label>
            @if($slot->shape === 'ellipse')
              <div class="photo-guide">
                <svg viewBox="0 0 120 150" width="64" height="80" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <ellipse cx="60" cy="78" rx="42" ry="54" stroke="var(--gold)" stroke-width="3"/>
                  <line x1="10" y1="78" x2="110" y2="78" stroke="var(--gold)" stroke-width="1.5" stroke-dasharray="4 4"/>
                  <line x1="60" y1="22" x2="60" y2="134" stroke="var(--gold)" stroke-width="1.5" stroke-dasharray="4 4"/>
                </svg>
                <p>Üzünüz şəklin mərkəzində, düz kameraya baxaraq çəkilmiş olsun</p>
              </div>
            @endif
            <label class="upload-box" for="photo-input-{{ $index }}">
              <div class="ico">📷</div>
              <div class="upload-label">Şəkil seçmək üçün klikləyin</div>
            </label>
            <input type="file" class="photo-input" id="photo-input-{{ $index }}" name="photos[{{ $index }}]"
                   accept="image/*" required style="display:none;">
            <div class="range-row zoom-row" hidden>
              <span class="lbl">Yaxınlaşdır</span>
              <input type="range" class="zoom-range" min="50" max="500" value="100">
            </div>
            <div class="range-row rotate-row" hidden>
              <span class="lbl">Fırlat</span>
              <input type="range" class="rotate-range" min="-180" max="180" value="0">
              <button type="button" class="rotate-reset" title="Sıfırla">↺</button>
            </div>
            <p class="slot-hint" hidden>Şəkli önizləmədə sürükləyərək mövqeyini dəyişə bilərsiniz.</p>
          </div>
        @endforeach

        @php $seenLinks = []; @endphp
        @foreach($textSlots as $index => $slot)
          @if($slot->fixed)
            {{-- Part of the design: drawn as set, never asked for, never sent. --}}
            <input type="hidden" class="text-input" data-fixed value="{{ $slot->default_value }}">
          @elseif($slot->link_key && in_array($slot->link_key, $seenLinks, true))
            {{-- A repeat of a field already shown: it follows that field. --}}
            <input type="hidden" class="text-input" data-link="{{ $slot->link_key }}"
                   name="custom_texts[{{ $index }}]"
                   value="{{ old('custom_texts.' . $index, $slot->default_value) }}">
          @else
            @php if ($slot->link_key) $seenLinks[] = $slot->link_key; @endphp
            <div>
              <label for="text-input-{{ $index }}">{{ $slot->label ?: 'Mətn' }}</label>
              @if($slot->isTime())
                {{-- Four digits; the colon is put in as the customer types. --}}
                <input type="text" class="text-input time-input" id="text-input-{{ $index }}" name="custom_texts[{{ $index }}]"
                       @if($slot->link_key) data-link="{{ $slot->link_key }}" data-link-lead @endif
                       inputmode="numeric" maxlength="5" pattern="[0-9]{2}:[0-5][0-9]" required
                       placeholder="dəq:san (məs. 03:45)" title="dəq:san, məs. 03:45"
                       value="{{ old('custom_texts.' . $index, $slot->default_value) }}">
              @else
                <input type="text" class="text-input" id="text-input-{{ $index }}" name="custom_texts[{{ $index }}]"
                       @if($slot->link_key) data-link="{{ $slot->link_key }}" data-link-lead @endif
                       maxlength="{{ $slot->max_length }}"
                       placeholder="{{ $slot->placeholder ?: 'Məs. Ad Soyad və ya qısa mesaj' }}"
                       value="{{ old('custom_texts.' . $index, $slot->default_value) }}">
              @endif
            </div>
          @endif
        @endforeach

        <div>
          <label for="quantity">Say</label>
          <input type="number" id="quantity" name="quantity" value="1" min="1" max="20" style="max-width:7rem;">
        </div>

        <button type="submit" class="btn btn-primary btn-block" id="add-to-cart-btn"
                @if($photoSlots->isNotEmpty()) disabled @endif>Səbətə Əlavə Et</button>
      </form>
    </div>
  </div>
</section>
@endsection

@section('page_script')
@if($photoSlots->where('shape', 'ellipse')->isNotEmpty())
<script defer src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
@endif
<script src="{{ asset('js/box-render.js') }}"></script>
<script>
(function(){
  "use strict";

  var ANGLES = @json($viewData);
  var SLOT_COUNT = {{ $photoSlots->count() }};

  var FACE_MODEL_URL = 'https://cdn.jsdelivr.net/gh/justadudewhohacks/face-api.js@master/weights';
  var faceModelReady = null;
  function ensureFaceModel(){
    if (!faceModelReady) {
      faceModelReady = (typeof faceapi === 'undefined')
        ? Promise.reject(new Error('face-api not loaded'))
        : faceapi.nets.tinyFaceDetector.loadFromUri(FACE_MODEL_URL);
    }
    return faceModelReady;
  }
  if (typeof faceapi !== 'undefined' || document.querySelector('script[src*="face-api"]')) {
    setTimeout(function(){ ensureFaceModel().catch(function(){}); }, 300);
  }

  var canvas = document.getElementById('preview-canvas');
  var ctx = canvas.getContext('2d');
  var dropHint = document.getElementById('drop-hint');
  var addBtn = document.getElementById('add-to-cart-btn');
  var anglePrev = document.getElementById('angle-prev');
  var angleNext = document.getElementById('angle-next');
  var angleThumbs = document.querySelectorAll('.angle-thumb');
  var slotBlocks = Array.prototype.slice.call(document.querySelectorAll('.slot-block'));
  var textInputs = Array.prototype.slice.call(document.querySelectorAll('.text-input'));

  var template = new Image(), templateReady = false;
  var overlay = new Image(), overlayReady = false;
  var bgImg = new Image(), bgReady = false;
  var mockupCanvas = document.createElement('canvas');
  var layerImages = {};
  function layerImage(url){
    if (!layerImages[url]) {
      layerImages[url] = new Image();
      layerImages[url].onload = function(){ draw(); };
      layerImages[url].src = url;
    }
    return layerImages[url];
  }
  function drawLayers(mctx, list){
    (list || []).forEach(function(l){ NefisBox.drawLayer(mctx, layerImage(l.url), l); });
  }
  var activeAngle = 0;

  /* One entry per photo slot, kept across angle switches. */
  var photos = [];
  for (var i = 0; i < SLOT_COUNT; i++) {
    photos.push({ img: null, scale: 1, rotate: 0, offsetX: 0, offsetY: 0, faceBox: null });
  }

  function currentAngle(){ return ANGLES[activeAngle]; }
  function areaFor(slotIndex){ return currentAngle().areas[slotIndex] || null; }

  /* ---------- fonts ---------- */
  ANGLES.forEach(function(a){
    a.texts.forEach(function(t){
      if (!t.fontFile || !t.fontFamily) return;
      try {
        new FontFace(t.fontFamily, 'url(' + t.fontFile + ')').load().then(function(f){
          document.fonts.add(f);
          draw();
        }).catch(function(){});
      } catch (e) {}
    });
  });

  /* ---------- geometry ---------- */
  function coverScale(area, imgW, imgH){
    var rotRad = area.rotation * Math.PI / 180;
    var cosA = Math.abs(Math.cos(rotRad));
    var sinA = Math.abs(Math.sin(rotRad));
    var boundW = area.w * cosA + area.h * sinA;
    var boundH = area.w * sinA + area.h * cosA;
    return Math.max(boundW / imgW, boundH / imgH);
  }

  function drawPhotoInArea(mctx, area, state){
    var img = state.img;
    if (!img) return;
    var acx = area.x + area.w / 2;
    var acy = area.y + area.h / 2;
    var rotRad = area.rotation * Math.PI / 180;
    var isEllipse = area.shape === 'ellipse';

    /* An oval cutout keeps the photo upright — a tilted face looks unnatural —
       while a rectangular one carries the design's own tilt. The customer's
       own rotation is applied on top of whichever it is. */
    var baseDeg = isEllipse ? 0 : area.rotation;
    var photoRad = (baseDeg + (state.rotate || 0)) * Math.PI / 180;
    var fit = isEllipse
      ? coverScale(area, img.width, img.height)
      : Math.max(area.w / img.width, area.h / img.height);
    var s = fit * state.scale;

    mctx.save();
    mctx.translate(acx, acy);
    mctx.rotate(rotRad);
    mctx.beginPath();
    if (isEllipse) mctx.ellipse(0, 0, area.w / 2, area.h / 2, 0, 0, Math.PI * 2);
    else mctx.rect(-area.w / 2, -area.h / 2, area.w, area.h);
    mctx.clip();
    mctx.rotate(-rotRad);

    mctx.translate(state.offsetX, state.offsetY);
    mctx.rotate(photoRad);
    mctx.drawImage(img, -img.width * s / 2, -img.height * s / 2, img.width * s, img.height * s);
    mctx.restore();
  }

  /* Renders artwork + photos + overlay + text at the template's own resolution. */
  function renderMockup(){
    var a = currentAngle();
    mockupCanvas.width = a.tw;
    mockupCanvas.height = a.th;
    var mctx = mockupCanvas.getContext('2d');
    mctx.clearRect(0, 0, a.tw, a.th);

    if (a.url && templateReady) mctx.drawImage(template, 0, 0, a.tw, a.th);
    drawLayers(mctx, a.layers && a.layers.below);

    a.areas.forEach(function(area, i){
      if (photos[i]) drawPhotoInArea(mctx, area, photos[i]);
    });

    /* Foreground artwork (frames, fades, props) must cover the photo edges. */
    drawLayers(mctx, a.layers && a.layers.above);
    if (a.overlay && overlayReady) mctx.drawImage(overlay, 0, 0, a.tw, a.th);

    a.texts.forEach(function(t, i){
      var input = textInputs[i];
      if (input && input.value) NefisBox.drawText(mctx, input.value, t);
    });

    return mockupCanvas;
  }

  function draw(){
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    var a = currentAngle();

    if (a.bg) {
      if (bgReady) ctx.drawImage(bgImg, 0, 0, canvas.width, canvas.height);
      var mockup = renderMockup();
      var box = a.boxArea, cb = a.contentBox;
      var scale = box.w / cb.w;
      /* Undo the art's own rotation inside its template canvas, scale it to the
         scene's cutout, then reapply the cutout's tilt. */
      ctx.save();
      ctx.translate(box.x + box.w / 2, box.y + box.h / 2);
      ctx.rotate(box.rotation * Math.PI / 180);
      ctx.scale(scale, scale);
      ctx.rotate(-cb.rotation * Math.PI / 180);
      ctx.translate(-(cb.x + cb.w / 2), -(cb.y + cb.h / 2));
      ctx.drawImage(mockup, 0, 0, a.tw, a.th);
      ctx.restore();
    } else {
      ctx.drawImage(renderMockup(), 0, 0, canvas.width, canvas.height);
    }
  }

  /* ---------- angles ---------- */
  function loadAngle(index){
    activeAngle = index;
    var a = ANGLES[index];

    templateReady = false;
    if (a.url) {
      template = new Image();
      template.onload = function(){ templateReady = true; draw(); };
      template.src = a.url;
    }

    overlayReady = false;
    if (a.overlay) {
      overlay = new Image();
      overlay.onload = function(){ overlayReady = true; draw(); };
      overlay.src = a.overlay;
    }

    if (a.bg) {
      canvas.width = a.bgW;
      canvas.height = a.bgH;
      bgReady = false;
      bgImg = new Image();
      bgImg.onload = function(){ bgReady = true; draw(); };
      bgImg.src = a.bg;
    } else {
      canvas.width = a.tw;
      canvas.height = a.th;
    }

    angleThumbs.forEach(function(btn){
      btn.classList.toggle('active', Number(btn.dataset.angle) === index);
    });

    photos.forEach(function(state, i){
      if (!state.img) return;
      state.scale = 1;
      state.offsetX = 0;
      state.offsetY = 0;
      var block = slotBlocks[i];
      var zoom = block && block.querySelector('.zoom-range');
      if (zoom) zoom.value = 100;
      var rot = block && block.querySelector('.rotate-range');
      if (rot) rot.value = state.rotate || 0;
      var area = areaFor(i);
      if (area && area.shape === 'ellipse' && state.faceBox) frameOnFace(i, state.faceBox);
    });

    draw();
  }

  function frameOnFace(slotIndex, box){
    var area = areaFor(slotIndex);
    var state = photos[slotIndex];
    if (!area || !state.img) return;

    var faceCx = box.x + box.width / 2;
    var faceCy = box.y + box.height / 2;
    /* pad beyond the strict face box so hair/chin stay in frame */
    var boxW = box.width * 1.5;
    var boxH = box.height * 1.9;
    var faceCenterBias = -0.05;

    var desiredScale = Math.max(area.w / boxW, area.h / boxH);
    var baseScale = coverScale(area, state.img.width, state.img.height);

    state.scale = desiredScale / baseScale;
    state.offsetX = desiredScale * (state.img.width / 2 - faceCx);
    state.offsetY = desiredScale * (state.img.height / 2 - (faceCy + box.height * faceCenterBias));

    var zoom = slotBlocks[slotIndex] && slotBlocks[slotIndex].querySelector('.zoom-range');
    if (zoom) zoom.value = Math.round(state.scale * 100);
  }

  function applyAutoFraming(slotIndex, img){
    var state = photos[slotIndex];
    state.faceBox = null;
    state.scale = 1;
    state.offsetX = 0;
    state.offsetY = 0;

    var area = areaFor(slotIndex);
    if (!area || area.shape !== 'ellipse' || typeof faceapi === 'undefined') return;

    ensureFaceModel().then(function(){
      return faceapi.detectSingleFace(img, new faceapi.TinyFaceDetectorOptions());
    }).then(function(det){
      if (!det || photos[slotIndex].img !== img) return;
      photos[slotIndex].faceBox = det.box;
      frameOnFace(slotIndex, det.box);
      draw();
    }).catch(function(){});
  }

  function allSlotsFilled(){
    return photos.every(function(p){ return p.img !== null; });
  }

  slotBlocks.forEach(function(block){
    var index = Number(block.dataset.slot);
    var input = block.querySelector('.photo-input');
    var label = block.querySelector('.upload-label');
    var zoomRow = block.querySelector('.zoom-row');
    var zoom = block.querySelector('.zoom-range');
    var rotateRow = block.querySelector('.rotate-row');
    var rotate = block.querySelector('.rotate-range');
    var rotateReset = block.querySelector('.rotate-reset');
    var hint = block.querySelector('.slot-hint');

    input.addEventListener('change', function(){
      var file = input.files && input.files[0];
      if (!file) return;
      label.textContent = file.name;
      var reader = new FileReader();
      reader.onload = function(e){
        var img = new Image();
        img.onload = function(){
          photos[index].img = img;
          applyAutoFraming(index, img);
          zoomRow.hidden = false;
          rotateRow.hidden = false;
          if (rotate) rotate.value = 0;
          if (hint) hint.hidden = false;
          if (dropHint) dropHint.style.display = 'none';
          if (allSlotsFilled()) addBtn.disabled = false;
          draw();
        };
        img.src = e.target.result;
      };
      reader.readAsDataURL(file);
    });

    if (zoom) {
      zoom.addEventListener('input', function(){
        photos[index].scale = zoom.value / 100;
        draw();
      });
    }

    if (rotate) {
      rotate.addEventListener('input', function(){
        photos[index].rotate = Number(rotate.value);
        draw();
      });
    }

    if (rotateReset) {
      rotateReset.addEventListener('click', function(){
        photos[index].rotate = 0;
        rotate.value = 0;
        draw();
      });
    }
  });

  function formatTime(v){
    var d = String(v).replace(/\D/g, '').slice(0, 4);
    return d.length > 2 ? d.slice(0, 2) + ':' + d.slice(2) : d;
  }

  textInputs.forEach(function(input){
    input.addEventListener('input', function(){
      if (input.classList.contains('time-input')) input.value = formatTime(input.value);
      /* A name the design repeats is typed once and fills every copy. */
      var key = input.getAttribute('data-link');
      if (key) {
        textInputs.forEach(function(other){
          if (other !== input && other.getAttribute('data-link') === key) other.value = input.value;
        });
      }
      draw();
    });
  });

  loadAngle(0);

  if (anglePrev) {
    anglePrev.addEventListener('click', function(){
      loadAngle((activeAngle - 1 + ANGLES.length) % ANGLES.length);
    });
  }
  if (angleNext) {
    angleNext.addEventListener('click', function(){
      loadAngle((activeAngle + 1) % ANGLES.length);
    });
  }
  angleThumbs.forEach(function(btn){
    btn.addEventListener('click', function(){ loadAngle(Number(btn.dataset.angle)); });
  });

  /* ---------- drag to reposition ---------- */
  var dragSlot = -1, lastX = 0, lastY = 0;

  function toCanvasPixel(clientX, clientY){
    var rect = canvas.getBoundingClientRect();
    return {
      x: (clientX - rect.left) * (canvas.width / rect.width),
      y: (clientY - rect.top) * (canvas.height / rect.height)
    };
  }

  /* Maps a screen point into the mockup's own coordinate space, undoing the
     background scene's placement first when one is set. */
  function toMockupCoords(clientX, clientY){
    var p = toCanvasPixel(clientX, clientY);
    var a = currentAngle();
    if (!a.bg) return p;

    var box = a.boxArea, cb = a.contentBox;
    var scale = box.w / cb.w;
    var dx = p.x - (box.x + box.w / 2);
    var dy = p.y - (box.y + box.h / 2);

    var boxRad = -box.rotation * Math.PI / 180;
    var rx = dx * Math.cos(boxRad) - dy * Math.sin(boxRad);
    var ry = dx * Math.sin(boxRad) + dy * Math.cos(boxRad);
    rx /= scale;
    ry /= scale;

    var cbRad = cb.rotation * Math.PI / 180;
    var ux = rx * Math.cos(cbRad) - ry * Math.sin(cbRad);
    var uy = rx * Math.sin(cbRad) + ry * Math.cos(cbRad);

    return { x: ux + (cb.x + cb.w / 2), y: uy + (cb.y + cb.h / 2) };
  }

  function slotAtPoint(p){
    var areas = currentAngle().areas;
    for (var i = areas.length - 1; i >= 0; i--) {
      if (!photos[i] || !photos[i].img) continue;
      var area = areas[i];
      var cx = area.x + area.w / 2;
      var cy = area.y + area.h / 2;
      var rad = -area.rotation * Math.PI / 180;
      var dx = p.x - cx, dy = p.y - cy;
      var lx = dx * Math.cos(rad) - dy * Math.sin(rad);
      var ly = dx * Math.sin(rad) + dy * Math.cos(rad);
      if (area.shape === 'ellipse') {
        if ((lx * lx) / (area.w * area.w / 4) + (ly * ly) / (area.h * area.h / 4) <= 1) return i;
      } else if (Math.abs(lx) <= area.w / 2 && Math.abs(ly) <= area.h / 2) {
        return i;
      }
    }
    return -1;
  }

  function startDrag(clientX, clientY){
    var slot = slotAtPoint(toMockupCoords(clientX, clientY));
    if (slot < 0) return false;
    dragSlot = slot;
    lastX = clientX;
    lastY = clientY;
    return true;
  }

  function moveDrag(clientX, clientY){
    if (dragSlot < 0) return;
    var a = toMockupCoords(lastX, lastY);
    var b = toMockupCoords(clientX, clientY);
    photos[dragSlot].offsetX += b.x - a.x;
    photos[dragSlot].offsetY += b.y - a.y;
    lastX = clientX;
    lastY = clientY;
    draw();
  }

  canvas.addEventListener('mousedown', function(e){
    if (startDrag(e.clientX, e.clientY)) e.preventDefault();
  });
  window.addEventListener('mousemove', function(e){ moveDrag(e.clientX, e.clientY); });
  window.addEventListener('mouseup', function(){ dragSlot = -1; });

  canvas.addEventListener('touchstart', function(e){
    var t = e.touches[0];
    if (t && startDrag(t.clientX, t.clientY)) e.preventDefault();
  }, { passive: false });
  canvas.addEventListener('touchmove', function(e){
    var t = e.touches[0];
    if (t && dragSlot >= 0) { moveDrag(t.clientX, t.clientY); e.preventDefault(); }
  }, { passive: false });
  canvas.addEventListener('touchend', function(){ dragSlot = -1; });
})();
</script>
@endsection
