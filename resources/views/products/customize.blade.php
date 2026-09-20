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
  .angle-thumb canvas{ width:100%; height:100%; object-fit:cover; display:block; }
  textarea.text-input{ resize:vertical; }
  .choc-brands{ display:flex; flex-wrap:wrap; gap:.4rem; margin:.6rem 0 .25rem; }
  .choc-brand{
    position:relative; white-space:nowrap;
    display:inline-flex; align-items:center; gap:.35rem; padding:.4rem .8rem; border-radius:999px;
    border:1px solid var(--line); background:var(--paper); color:var(--cocoa-soft);
    font-size:.8125rem; font-weight:600; line-height:1.2; transition:background .2s, color .2s, border-color .2s, box-shadow .2s;
  }
  .choc-brand span{ font-size:.6875rem; font-weight:700; opacity:.6; }
  .choc-brand:hover{ border-color:var(--gold); color:var(--cocoa); }
  .choc-brand.active{ background:var(--cocoa); color:var(--cream); border-color:var(--cocoa); }
  .choc-brand:focus-visible{ outline:2px solid var(--gold); outline-offset:2px; }
  /* The owner's top brands (Milka, Alpen Gold…): orange, with a slow glow. */
  .choc-brand.top{ background:linear-gradient(135deg, #FB923C, #EA580C); border-color:#F97316; color:#fff; box-shadow:0 0 10px rgba(249,115,22,.4); }
  .choc-brand.top span{ opacity:.85; }
  .choc-brand.top:hover{ color:#fff; border-color:#FDBA74; box-shadow:0 0 16px rgba(249,115,22,.6); }
  .choc-brand.top.active{ background:linear-gradient(135deg, #FB923C, #EA580C); color:#fff; border-color:#FDBA74;
    box-shadow:0 0 0 2px var(--paper), 0 0 0 4px #F97316, 0 0 18px rgba(249,115,22,.55); }
  .choc-brand.top:not(.active){ animation:choc-glow 2.6s ease-in-out infinite; }
  @keyframes choc-glow{ 50%{ box-shadow:0 0 18px rgba(249,115,22,.7); } }
  @media (prefers-reduced-motion: reduce){ .choc-brand.top:not(.active){ animation:none; } }
  /* The brand the chosen bar is in, so the choice is not lost when browsing another brand. */
  .choc-brand.has-pick::after{ content:''; position:absolute; top:-.1rem; right:-.1rem; width:.6rem; height:.6rem; border-radius:50%;
    background:var(--gold); box-shadow:0 0 0 2px var(--paper); }
  .choc-group{ margin-top:.25rem; }
  .choc-group[hidden]{ display:none; }
  .choc-error{ margin:.5rem 0 0; font-size:.875rem; font-weight:600; color:#dc2626; }
  .choc-error[hidden]{ display:none; }
  .sum-name{ min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .choc-grid{ display:grid; grid-template-columns:repeat(auto-fill, minmax(8.5rem, 1fr)); gap:.625rem; margin-top:.5rem; }
  .choc-card{ position:relative; display:flex; flex-direction:column; gap:.3rem; padding:.6rem; border:1.5px solid var(--line); border-radius:.9rem;
    background:var(--paper); cursor:pointer; transition:border-color .15s, box-shadow .15s; margin:0; font-weight:400; }
  .choc-card:hover{ border-color:var(--gold); }
  .choc-card input{ position:absolute; opacity:0; pointer-events:none; }
  .choc-card:has(input:checked){ border-color:var(--gold); box-shadow:0 0 0 3px var(--ring); }
  .choc-card:has(input:checked)::after{ content:'✓'; position:absolute; top:.4rem; right:.5rem; width:1.4rem; height:1.4rem; border-radius:50%;
    background:var(--gold); color:#fff; font-size:.8rem; display:grid; place-items:center; }
  .choc-card:has(input:focus-visible){ outline:2px solid var(--gold); outline-offset:2px; }
  .choc-pic{ aspect-ratio:1/1; display:grid; place-items:center; border-radius:.6rem; background:#fff; overflow:hidden; font-size:2rem; }
  .choc-pic img{ width:100%; height:100%; object-fit:contain; }
  .choc-name{ font-size:.8125rem; line-height:1.3; color:var(--cocoa); }
  .choc-meta{ display:flex; justify-content:space-between; align-items:baseline; gap:.3rem; font-size:.75rem; color:var(--cocoa-soft); margin-top:auto; }
  .choc-meta b{ color:var(--gold-deep); font-size:.875rem; }
  .price-sum{ border:1px solid var(--line); border-radius:.9rem; padding:.75rem 1rem; display:flex; flex-direction:column; gap:.35rem; font-size:.9375rem; }
  .price-sum div{ display:flex; justify-content:space-between; gap:1rem; color:var(--cocoa-soft); }
  .price-sum .total{ color:var(--cocoa); font-weight:700; font-size:1.0625rem; border-top:1px solid var(--line); padding-top:.45rem; margin-top:.1rem; }
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
        @php $firstScene = $viewData[0]['scene'] ?? null; @endphp
        <div class="stage" id="stage" @if($firstScene) style="aspect-ratio: {{ $firstScene['w'] }} / {{ $firstScene['h'] }};" @endif>
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
              <button type="button" class="angle-thumb{{ $loop->first ? ' active' : '' }}" data-angle="{{ $loop->index }}"
                      title="{{ $view['label'] ?? $product->name }}" aria-label="{{ $view['label'] ?? $product->name }}">
                @if(array_key_exists('scene', $view))
                  {{-- Drawn live, so every thumbnail shows the customer's own box. --}}
                  <canvas></canvas>
                @else
                  <img src="{{ $view['bg'] ?: $view['url'] }}" alt="{{ $view['label'] ?? $product->name }}">
                @endif
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
              @elseif($slot->max_lines > 1 || str_contains((string) $slot->default_value, "\n"))
                {{-- Room for more than one line, so Enter breaks the line here too.
                     A text input would quietly drop the line breaks. --}}
                @php $value = old('custom_texts.' . $index, $slot->default_value); @endphp
                <textarea class="text-input" id="text-input-{{ $index }}" name="custom_texts[{{ $index }}]"
                          @if($slot->link_key) data-link="{{ $slot->link_key }}" data-link-lead @endif
                          maxlength="{{ $slot->max_length }}"
                          rows="{{ min(4, max(2, substr_count((string) $value, "\n") + 1)) }}"
                          placeholder="{{ $slot->placeholder ?: 'Məs. Ad Soyad və ya qısa mesaj' }}">{{ $value }}</textarea>
                <p class="slot-hint">Yeni sətir üçün Enter basın.</p>
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

        @if($chocolates->isNotEmpty())
          {{-- The bar that goes inside the box. --}}
          {{-- One brand at a time, so the list stays short however many bars there are. The owner's
               top brands come first and glow orange; the rest follow A–Z, the unbranded last. --}}
          @php
            $top = array_values(array_intersect(\App\Models\Setting::topBrands(), $chocolates->pluck('brand')->unique()->all()));
            $brands = $chocolates->groupBy('brand')->sortBy(function ($bars, $brand) use ($top) {
                $rank = array_search($brand, $top, true);

                return $rank !== false ? sprintf('0%03d', $rank)
                    : ($brand === \App\Support\ChocolateBrand::OTHER ? '2' : '1') . mb_strtolower($brand);
            });
            $picked = $chocolates->firstWhere('id', (int) old('chocolate_id'));
            $openBrand = $picked['brand'] ?? $brands->keys()->first();
          @endphp
          <div class="choc-block" id="choc-block">
            <label>Qutunun içindəki şokolad</label>
            @if($brands->count() > 1)
              <div class="choc-brands" aria-label="Marka seçin">
                @foreach($brands as $brand => $bars)
                  <button type="button" class="choc-brand{{ in_array($brand, $top, true) ? ' top' : '' }}{{ $brand === $openBrand ? ' active' : '' }}{{ $picked && $picked['brand'] === $brand ? ' has-pick' : '' }}"
                          data-brand="{{ $brand }}" aria-pressed="{{ $brand === $openBrand ? 'true' : 'false' }}">{{ $brand }} <span>{{ $bars->count() }}</span></button>
                @endforeach
              </div>
            @endif
            <div role="radiogroup" aria-label="Şokolad seçin">
              @foreach($brands as $brand => $bars)
                <div class="choc-group" data-brand="{{ $brand }}" @if($brand !== $openBrand) hidden @endif>
                  <div class="choc-grid">
                    @foreach($bars as $choc)
                      <label class="choc-card">
                        <input type="radio" name="chocolate_id" value="{{ $choc['id'] }}" data-price="{{ $choc['price'] }}" data-name="{{ $choc['name'] }}" data-brand="{{ $brand }}"
                               @checked($picked && $picked['id'] === $choc['id'])>
                        <span class="choc-pic">
                          @if($choc['image'])<img src="{{ $choc['image'] }}" alt="" loading="lazy">@else🍫@endif
                        </span>
                        <span class="choc-name">{{ $choc['name'] }}</span>
                        <span class="choc-meta">{{ $choc['weight'] }}<b>+{{ \App\Support\Price::format($choc['price']) }}</b></span>
                      </label>
                    @endforeach
                  </div>
                </div>
              @endforeach
            </div>
            <p class="choc-error" id="choc-error" role="alert" @unless($errors->has('chocolate_id')) hidden @endunless>{{ $errors->first('chocolate_id') ?: 'Qutunun içinə şokolad seçin.' }}</p>
          </div>
        @endif

        <div>
          <label for="quantity">Say</label>
          <input type="number" id="quantity" name="quantity" value="1" min="1" max="20" style="max-width:7rem;">
        </div>

        @if($product->price || $chocolates->isNotEmpty())
          <div class="price-sum" id="price-sum" data-box="{{ (float) $product->price }}">
            <div><span>Qutu</span><span>{{ $product->price ? \App\Support\Price::format($product->price) : 'sorğu ilə' }}</span></div>
            @if($chocolates->isNotEmpty())
              <div><span id="sum-choc-name" class="sum-name">Şokolad</span><span id="sum-choc">seçilməyib</span></div>
            @endif
            <div class="total"><span>Cəmi</span><span id="sum-total">—</span></div>
          </div>
        @endif

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
<script src="{{ asset('js/scene-render.js') }}"></script>
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
  /* Pictures of the owner's mockup scenes, and the scratch canvases their
     warps are drawn in (one set for the big view, one per thumbnail). */
  var sceneImages = {}, sceneCache = {}, thumbCaches = {};
  function sceneImage(url){
    if (!url) return null;
    if (!sceneImages[url]) {
      sceneImages[url] = new Image();
      sceneImages[url].onload = function(){ draw(); };
      sceneImages[url].src = url;
    }
    return sceneImages[url];
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

    if (a.scene) {
      /* The owner's mockup: the box design corner-pinned onto a rendered box. */
      NefisScene.drawScene(ctx, a.scene, renderMockup(), sceneImage, sceneCache, { boxColor: a.boxColor });
    } else if (a.bg) {
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
    scheduleThumbs();
  }

  /* Thumbnails show the customer's own box in every scene. Every view of a
     box shares one design, so the mockup just drawn serves them all. */
  var thumbCanvases = Array.prototype.slice.call(document.querySelectorAll('.angle-thumb canvas'));
  var thumbTimer = null;
  function scheduleThumbs(){
    if (!thumbCanvases.length) return;
    clearTimeout(thumbTimer);
    thumbTimer = setTimeout(drawThumbs, 250);
  }
  function drawThumbs(){
    var design = mockupCanvas;
    if (!design.width) return;
    thumbCanvases.forEach(function(tc){
      var i = Number(tc.parentNode.dataset.angle), a = ANGLES[i];
      var sw = a.scene ? a.scene.w : a.tw, sh = a.scene ? a.scene.h : a.th;
      var s = 112 / Math.min(sw, sh);
      tc.width = Math.round(sw * s);
      tc.height = Math.round(sh * s);
      var tctx = tc.getContext('2d');
      tctx.clearRect(0, 0, tc.width, tc.height);
      if (a.scene) {
        NefisScene.drawScene(tctx, NefisScene.scaled(a.scene, s), design, sceneImage, thumbCaches[i] || (thumbCaches[i] = {}), { boxColor: a.boxColor });
      } else {
        tctx.drawImage(design, 0, 0, tc.width, tc.height);
      }
    });
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

    if (a.scene) {
      canvas.width = a.scene.w;
      canvas.height = a.scene.h;
    } else if (a.bg) {
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
    /* On a mockup, undo the corner pin; off the box there is nothing to drag. */
    if (a.scene) return NefisScene.designPoint(a.scene, p.x, p.y, a.tw, a.th);
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
    if (!p) return -1;
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
    if (a && b) {
      photos[dragSlot].offsetX += b.x - a.x;
      photos[dragSlot].offsetY += b.y - a.y;
    }
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

/* Brand chips: one brand's bars at a time. */
(function(){
  var block = document.getElementById('choc-block');
  if (!block) return;
  var chips = block.querySelectorAll('.choc-brand');
  var groups = block.querySelectorAll('.choc-group');
  var radios = block.querySelectorAll('input[name="chocolate_id"]');
  var error = document.getElementById('choc-error');
  function show(brand){
    chips.forEach(function(c){ var on = c.dataset.brand === brand; c.classList.toggle('active', on); c.setAttribute('aria-pressed', on ? 'true' : 'false'); });
    groups.forEach(function(g){ g.hidden = g.dataset.brand !== brand; });
  }
  chips.forEach(function(c){ c.addEventListener('click', function(){ show(c.dataset.brand); }); });
  radios.forEach(function(r){
    r.addEventListener('change', function(){
      chips.forEach(function(c){ c.classList.toggle('has-pick', c.dataset.brand === r.dataset.brand); });
      error.hidden = true;
    });
  });

  // No bar chosen: say so here rather than let the browser point at a bar hidden under another brand.
  document.getElementById('customize-form').addEventListener('submit', function(e){
    if (block.querySelector('input[name="chocolate_id"]:checked')) return;
    e.preventDefault();
    error.hidden = false;
    block.scrollIntoView({ behavior: 'smooth', block: 'center' });
  });

})();

/* The running price: the box, the chosen bar, times how many. */
(function(){
  var sum = document.getElementById('price-sum');
  if (!sum) return;
  var qty = document.getElementById('quantity');
  var chocOut = document.getElementById('sum-choc');
  var chocName = document.getElementById('sum-choc-name');
  var totalOut = document.getElementById('sum-total');
  var box = parseFloat(sum.dataset.box) || 0;
  function fmt(v){ v = Math.round(v * 100) / 100; return (v % 1 === 0 ? v.toFixed(0) : v.toFixed(2)) + ' ₼'; }
  function update(){
    var picked = document.querySelector('input[name="chocolate_id"]:checked');
    var choc = picked ? parseFloat(picked.dataset.price) || 0 : 0;
    var n = Math.max(1, parseInt(qty.value, 10) || 1);
    if (chocOut) chocOut.textContent = picked ? fmt(choc) : 'seçilməyib';
    if (chocName) chocName.textContent = picked ? picked.dataset.name : 'Şokolad';
    var each = box + choc;
    totalOut.textContent = each > 0 ? fmt(each * n) + (n > 1 ? ' (' + n + ' × ' + fmt(each) + ')' : '') : '—';
  }
  document.querySelectorAll('input[name="chocolate_id"]').forEach(function(r){ r.addEventListener('change', update); });
  qty.addEventListener('input', update);
  update();
})();
</script>
@endsection
