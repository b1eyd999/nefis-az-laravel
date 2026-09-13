@extends('layouts.app')

@section('title', $product->name . ' — Fərdiləşdir — Nefis Şokolad Evi')

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
          <div class="drop-hint" id="drop-hint">Öncə sağdan şəklinizi yükləyin</div>
          @if($product->angles->isNotEmpty())
            <button type="button" class="angle-arrow prev" id="angle-prev" aria-label="Əvvəlki görünüş">‹</button>
            <button type="button" class="angle-arrow next" id="angle-next" aria-label="Sonrakı görünüş">›</button>
          @endif
        </div>
        @if($product->angles->isNotEmpty())
          <div class="angle-thumbs" id="angle-thumbs">
            <button type="button" class="angle-thumb active" data-angle="0">
              <img src="{{ asset('storage/' . $product->template_image) }}" alt="{{ $product->name }}">
            </button>
            @foreach($product->angles as $angle)
              <button type="button" class="angle-thumb" data-angle="{{ $loop->iteration }}">
                <img src="{{ asset('storage/' . $angle->template_image) }}" alt="{{ $angle->label ?? $product->name }}">
              </button>
            @endforeach
          </div>
        @endif
      </div>

      <form class="customize-panel" method="POST" action="{{ route('cart.add') }}" enctype="multipart/form-data" id="customize-form">
        @csrf
        <input type="hidden" name="product_id" value="{{ $product->id }}">

        <div>
          <label>1. Şəklinizi Yükləyin</label>
          <label class="upload-box" for="photo-input">
            <div class="ico">📷</div>
            <div id="upload-label">Şəkil seçmək üçün klikləyin</div>
          </label>
          <input type="file" id="photo-input" name="photo" accept="image/*" required style="display:none;">
        </div>

        <div id="adjust-controls" style="display:none;">
          <label>2. Şəkli Tənzimləyin</label>
          <div class="range-row">
            <span class="lbl">Yaxınlaşdır</span>
            <input type="range" id="zoom-range" min="100" max="300" value="100">
          </div>
          <p style="font-size:.8125rem; color:var(--cocoa-soft); margin-top:.5rem;">Şəkli sürükləyərək mövqeyini dəyişə bilərsiniz.</p>
        </div>

        @if($product->allow_text)
          <div>
            <label for="custom_text">3. Mətn Əlavə Edin (istəyə bağlı)</label>
            <input type="text" id="custom_text" name="custom_text" maxlength="60" placeholder="Məs. Ad Soyad və ya qısa mesaj">
          </div>
        @endif

        <div>
          <label for="quantity">Say</label>
          <input type="number" id="quantity" name="quantity" value="1" min="1" max="20" style="max-width:7rem;">
        </div>

        <button type="submit" class="btn btn-primary btn-block" id="add-to-cart-btn" disabled>Səbətə Əlavə Et</button>
      </form>
    </div>
  </div>
</section>
@endsection

@section('page_script')
<script>
(function(){
  "use strict";

  var FONT_FAMILY = '"' + @json($product->text_font_family ?: 'Inter') + '", Inter, sans-serif';
  @if($product->text_font_file)
    var fontFace = new FontFace(@json($product->text_font_family ?: 'Inter'), 'url(' + @json(asset('storage/' . $product->text_font_file)) + ')');
    fontFace.load().then(function(loaded){
      document.fonts.add(loaded);
      draw();
    }).catch(function(){});
  @endif

  var ANGLES = [
    {
      url: @json(asset('storage/' . $product->template_image)),
      tw: {{ $product->template_width }},
      th: {{ $product->template_height }},
      area: {
        x: {{ $product->photo_area_x }},
        y: {{ $product->photo_area_y }},
        w: {{ $product->photo_area_width }},
        h: {{ $product->photo_area_height }},
        rotation: {{ $product->photo_area_rotation }},
        shape: @json($product->photo_area_shape)
      },
      text: {
        allow: {{ $product->allow_text ? 'true' : 'false' }},
        x: {{ $product->text_x }},
        y: {{ $product->text_y }},
        maxWidth: {{ $product->text_max_width }},
        fontSize: {{ $product->text_font_size }},
        color: @json($product->text_color),
        align: @json($product->text_align)
      }
    }
    @foreach($product->angles as $angle)
    ,{
      url: @json(asset('storage/' . $angle->template_image)),
      tw: {{ $angle->template_width }},
      th: {{ $angle->template_height }},
      area: {
        x: {{ $angle->photo_area_x }},
        y: {{ $angle->photo_area_y }},
        w: {{ $angle->photo_area_width }},
        h: {{ $angle->photo_area_height }},
        rotation: {{ $angle->photo_area_rotation }},
        shape: @json($angle->photo_area_shape)
      },
      text: {
        allow: {{ $angle->allow_text ? 'true' : 'false' }},
        x: {{ $angle->text_x }},
        y: {{ $angle->text_y }},
        maxWidth: {{ $angle->text_max_width }},
        fontSize: {{ $angle->text_font_size }},
        color: @json($angle->text_color),
        align: @json($angle->text_align)
      }
    }
    @endforeach
  ];

  var canvas = document.getElementById('preview-canvas');
  var ctx = canvas.getContext('2d');
  var dropHint = document.getElementById('drop-hint');
  var photoInput = document.getElementById('photo-input');
  var uploadLabel = document.getElementById('upload-label');
  var adjustControls = document.getElementById('adjust-controls');
  var zoomRange = document.getElementById('zoom-range');
  var customText = document.getElementById('custom_text');
  var addBtn = document.getElementById('add-to-cart-btn');
  var anglePrev = document.getElementById('angle-prev');
  var angleNext = document.getElementById('angle-next');
  var angleThumbs = document.querySelectorAll('.angle-thumb');

  var template = new Image();
  var photoImg = null;
  var photoState = { scale: 1, offsetX: 0, offsetY: 0 };
  var templateReady = false;
  var activeAngle = 0;

  function currentAngle(){ return ANGLES[activeAngle]; }
  function areaCenterX(){ return currentAngle().area.x + currentAngle().area.w / 2; }
  function areaCenterY(){ return currentAngle().area.y + currentAngle().area.h / 2; }

  function loadAngle(index){
    activeAngle = index;
    var a = ANGLES[index];
    canvas.width = a.tw;
    canvas.height = a.th;
    templateReady = false;
    template = new Image();
    template.onload = function(){ templateReady = true; draw(); };
    template.src = a.url;

    angleThumbs.forEach(function(btn){
      btn.classList.toggle('active', Number(btn.dataset.angle) === index);
    });
  }

  function draw(){
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    if (templateReady) ctx.drawImage(template, 0, 0, canvas.width, canvas.height);

    var area = currentAngle().area;
    var text = currentAngle().text;

    if (photoImg) {
      ctx.save();
      ctx.translate(areaCenterX(), areaCenterY());
      ctx.rotate(area.rotation * Math.PI / 180);
      ctx.beginPath();
      if (area.shape === 'ellipse') {
        ctx.ellipse(0, 0, area.w / 2, area.h / 2, 0, 0, Math.PI * 2);
      } else {
        ctx.rect(-area.w / 2, -area.h / 2, area.w, area.h);
      }
      ctx.clip();
      var baseScale = Math.max(area.w / photoImg.width, area.h / photoImg.height);
      var scale = baseScale * photoState.scale;
      var w = photoImg.width * scale;
      var h = photoImg.height * scale;
      ctx.drawImage(photoImg, -w / 2 + photoState.offsetX, -h / 2 + photoState.offsetY, w, h);
      ctx.restore();
    }

    if (text.allow && customText && customText.value) {
      ctx.save();
      ctx.font = '600 ' + text.fontSize + 'px ' + FONT_FAMILY;
      ctx.fillStyle = text.color;
      ctx.textAlign = text.align;
      ctx.textBaseline = 'middle';
      wrapText(ctx, customText.value, text.x, text.y, text.maxWidth, text.fontSize * 1.2, text.fontSize * 0.08);
      ctx.restore();
    }
  }

  function wrapText(ctx, text, x, y, maxWidth, lineHeight, strokeWidth){
    var words = text.split(' ');
    var lines = [];
    var line = '';
    for (var i = 0; i < words.length; i++) {
      var test = line ? line + ' ' + words[i] : words[i];
      if (ctx.measureText(test).width > maxWidth && line) {
        lines.push(line);
        line = words[i];
      } else {
        line = test;
      }
    }
    if (line) lines.push(line);
    var startY = y - ((lines.length - 1) * lineHeight) / 2;
    lines.forEach(function(l, i){
      var ly = startY + i * lineHeight;
      if (strokeWidth) {
        ctx.lineWidth = strokeWidth;
        ctx.strokeStyle = 'rgba(0,0,0,.5)';
        ctx.lineJoin = 'round';
        ctx.strokeText(l, x, ly);
      }
      ctx.fillText(l, x, ly);
    });
  }

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
    btn.addEventListener('click', function(){
      loadAngle(Number(btn.dataset.angle));
    });
  });

  photoInput.addEventListener('change', function(){
    var file = photoInput.files && photoInput.files[0];
    if (!file) return;
    uploadLabel.textContent = file.name;
    var reader = new FileReader();
    reader.onload = function(e){
      var img = new Image();
      img.onload = function(){
        photoImg = img;
        photoState = { scale: 1, offsetX: 0, offsetY: 0 };
        zoomRange.value = 100;
        dropHint.style.display = 'none';
        adjustControls.style.display = '';
        addBtn.disabled = false;
        draw();
      };
      img.src = e.target.result;
    };
    reader.readAsDataURL(file);
  });

  zoomRange.addEventListener('input', function(){
    photoState.scale = zoomRange.value / 100;
    draw();
  });

  if (customText) {
    customText.addEventListener('input', draw);
  }

  /* drag to reposition */
  var dragging = false, lastX = 0, lastY = 0;
  function toCanvasCoords(clientX, clientY){
    var rect = canvas.getBoundingClientRect();
    var scaleX = canvas.width / rect.width;
    var scaleY = canvas.height / rect.height;
    return { x: (clientX - rect.left) * scaleX, y: (clientY - rect.top) * scaleY };
  }
  function startDrag(clientX, clientY){
    if (!photoImg) return;
    dragging = true;
    var p = toCanvasCoords(clientX, clientY);
    lastX = p.x; lastY = p.y;
  }
  function moveDrag(clientX, clientY){
    if (!dragging) return;
    var p = toCanvasCoords(clientX, clientY);
    photoState.offsetX += (p.x - lastX);
    photoState.offsetY += (p.y - lastY);
    lastX = p.x; lastY = p.y;
    draw();
  }
  function endDrag(){ dragging = false; }

  canvas.addEventListener('mousedown', function(e){ startDrag(e.clientX, e.clientY); });
  window.addEventListener('mousemove', function(e){ moveDrag(e.clientX, e.clientY); });
  window.addEventListener('mouseup', endDrag);
  canvas.addEventListener('touchstart', function(e){ var t = e.touches[0]; startDrag(t.clientX, t.clientY); }, { passive:true });
  canvas.addEventListener('touchmove', function(e){ var t = e.touches[0]; moveDrag(t.clientX, t.clientY); }, { passive:true });
  canvas.addEventListener('touchend', endDrag);
})();
</script>
@endsection
