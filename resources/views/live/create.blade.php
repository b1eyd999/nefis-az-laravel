@extends('layouts.app')

@section('title', 'Canlı şəkil — Nefis Şokolad Evi')
@section('meta_description', 'Şəklinizi canlandırın: QR kodu oxudub telefonu şəklə tutanda üstündə sizin videonuz oynayır — tətbiq yükləmədən.')

@section('page_style')
  .live-grid{ display:grid; gap:2.5rem; align-items:start; }
  @media (min-width:900px){ .live-grid{ grid-template-columns:1fr 1fr; gap:4rem; } }
  .live-stage{ display:flex; flex-direction:column; align-items:center; gap:1rem; padding:2rem 1rem; border-radius:var(--radius);
    background:radial-gradient(ellipse at 50% 40%, var(--cream-2), transparent 72%); }
  /* a phone held over the picture */
  .live-phone{ position:relative; width:min(16rem, 72vw); aspect-ratio:9/18; border-radius:2.2rem; background:#17110D; padding:.7rem;
    box-shadow:0 24px 60px rgba(23,17,13,.35), inset 0 0 0 2px rgba(255,255,255,.08); }
  .live-screen{ position:relative; width:100%; height:100%; border-radius:1.6rem; overflow:hidden; display:flex; align-items:center; justify-content:center;
    background:linear-gradient(160deg, #6f655b, #4a423a); }
  .live-pic{ position:relative; width:78%; box-shadow:0 10px 24px rgba(0,0,0,.45); background:#fff; }
  .live-pic img, .live-pic video{ display:block; width:100%; height:100%; object-fit:cover; }
  .live-pic video{ position:absolute; inset:0; object-fit:contain; }
  .live-pic.empty{ aspect-ratio:3/4; display:flex; align-items:center; justify-content:center; text-align:center; padding:1rem;
    background:rgba(255,255,255,.12); color:#f3e6d6; font-size:.85rem; border:1.5px dashed rgba(255,255,255,.4); box-shadow:none; }
  .live-scan{ position:absolute; left:50%; bottom:1rem; transform:translateX(-50%); padding:.35rem .8rem; border-radius:999px; font-size:.7rem;
    background:rgba(23,17,13,.7); color:#fff; white-space:nowrap; }
  .live-caption{ font-size:.85rem; color:var(--cocoa-soft); text-align:center; max-width:20rem; }
  .live-form{ display:flex; flex-direction:column; gap:1.1rem; }
  .live-file{ display:flex; align-items:center; justify-content:center; gap:.5rem; border:1.5px dashed var(--ring); border-radius:.9rem;
    padding:1rem; text-align:center; cursor:pointer; font-weight:600; margin:0; word-break:break-word; }
  .live-file:hover{ border-color:var(--gold); }
  .live-file.done{ border-style:solid; border-color:var(--gold); }
  .live-file input{ position:absolute; width:1px; height:1px; opacity:0; pointer-events:none; }
  .live-hint{ font-size:.8125rem; color:var(--cocoa-soft); margin-top:.35rem; }
  .live-hint.bad{ color:#b42318; font-weight:600; }
  .live-prep{ display:flex; align-items:center; gap:.6rem; font-size:.875rem; font-weight:600; }
  .live-prep[hidden]{ display:none; }
  .live-bar{ flex:1; height:.4rem; border-radius:999px; background:var(--line); overflow:hidden; }
  .live-bar i{ display:block; height:100%; width:0; background:var(--gold); transition:width .3s; }
  .live-price{ display:flex; justify-content:space-between; align-items:baseline; border:1px solid var(--line); border-radius:.9rem; padding:.8rem 1rem; }
  .live-price b{ font-size:1.3rem; color:var(--gold-deep); }
  .live-steps{ margin:0; padding:0; list-style:none; display:grid; gap:.7rem; counter-reset:s; }
  .live-steps li{ display:flex; gap:.75rem; align-items:center; font-size:.9rem; line-height:1.5; }
  .live-steps .step-art{ flex:none; width:2.75rem; height:2.75rem; fill:none; stroke:currentColor; stroke-width:1.8;
    stroke-linecap:round; stroke-linejoin:round; color:var(--gold-deep); }
  @media (max-width: 380px){ .live-steps .step-art{ width:2.25rem; height:2.25rem; } }
  .live-steps li::before{ counter-increment:s; content:counter(s); flex:none; width:1.6rem; height:1.6rem; border-radius:50%; display:flex; align-items:center;
    justify-content:center; background:var(--cream-2); color:var(--gold-deep); font-weight:700; font-size:.8rem; }
@endsection

@section('content')
  <section class="page-hero" style="padding-bottom:0;">
    <div class="wrap">
      <span class="eyebrow" style="justify-content:center;">Yeni · AR</span>
      <h1>Canlı şəkil</h1>
      <p class="lede" style="margin-inline:auto;">Şəklinizi canlandırın: QR kodu oxudub telefonu şəklə tutanda, üstündə sizin videonuz oynayır — heç bir tətbiq yükləmədən.</p>
    </div>
  </section>

  <section>
    <div class="wrap">
      @if($errors->any())
        <div class="alert alert-error">
          <ul style="margin:0; padding-left:1.1rem;">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
      @endif

      <div class="live-grid">
        <div class="live-stage">
          <div class="live-phone" aria-hidden="true">
            <div class="live-screen">
              <div class="live-pic empty" id="live-pic">Şəkil və video seçin — burada necə canlanacağını görəcəksiniz</div>
              <span class="live-scan" id="live-scan" hidden>▶ Video şəklin üstündə oynayır</span>
            </div>
          </div>
          <p class="live-caption">Telefonda belə görünəcək: kamera şəkli tanıyır və video onun üzərinə düşür.</p>
        </div>

        <form class="live-form" id="live-form" method="POST" action="{{ lroute('live.store') }}" enctype="multipart/form-data">
          @csrf
          <div>
            <label>1. Canlanacaq şəkil</label>
            <label class="live-file" id="photo-pick">
              <input type="file" name="ar_photo" id="ar-photo" accept="image/*" required>
              <span id="photo-name">📷 Şəkil seçin</span>
            </label>
            <p class="live-hint">Bu şəkil QR kodla birlikdə çap olunur. Aydın, detallı şəkillər kamera tərəfindən daha yaxşı tanınır.</p>
          </div>
          <div>
            <label>2. Video</label>
            <label class="live-file" id="video-pick">
              <input type="file" name="ar_video" id="ar-video" accept="video/mp4,video/quicktime,video/webm,video/*" required>
              <span id="video-name">🎬 Video seçin (MP4/MOV, {{ $maxMb }} MB-a qədər)</span>
            </label>
            <p class="live-hint" id="video-hint">Ən yaxşısı 10–30 saniyəlik video. Öz ölçüsündə (məs. 9:16), kəsilmədən oynayır.</p>
          </div>

          <input type="file" name="ar_mind" id="ar-mind" hidden>
          <div class="live-prep" id="prep" hidden>
            <span id="prep-text">Şəkil kamera üçün hazırlanır…</span>
            <span class="live-bar"><i id="prep-bar"></i></span>
          </div>
          <p class="live-hint" id="prep-hint" hidden>Hazırlanarkən bu səhifədən çıxmayın.</p>

          <div class="live-price"><span>Qiymət</span><b>{{ \App\Support\Price::format($price) }}</b></div>
          <button type="submit" class="btn btn-primary btn-block" id="live-submit">Səbətə at</button>

          {{-- Drawn, because "the camera recognises the picture and the video
               plays over it" is a sentence people read twice. --}}
          <ol class="live-steps">
            <li>
              <svg class="step-art" viewBox="0 0 48 48" aria-hidden="true">
                <path d="M24 3v11M20 7l4-4 4 4" />
                <rect x="5" y="16" width="24" height="19" rx="3" />
                <path d="M9 30l5-6 4 4 3-3 4 5" />
                <circle cx="12" cy="22" r="1.8" fill="currentColor" stroke="none" />
                <circle cx="35" cy="32" r="9" />
                <path d="M32.5 27.5l6 4.5-6 4.5z" />
              </svg>
              <span>Şəkli və videonu yükləyirsiniz — qalanını sistem özü hazırlayır.</span>
            </li>
            <li>
              <svg class="step-art" viewBox="0 0 48 48" aria-hidden="true">
                <path d="M22 18h22v24H22z" opacity=".5" />
                <path d="M22 18l4-5h14l4 5" opacity=".5" /><path d="M33 13v29" opacity=".5" />
                <rect x="3" y="8" width="24" height="33" rx="2" fill="var(--paper)" />
                <path d="M7 21l4-5 3 4 3-3 4 5" />
                <path d="M7 27h5v5H7z" /><path d="M18 27h5v5h-5z" /><path d="M7 33h5v5H7z" />
                <path d="M15 27v2" /><path d="M15 33h2v2h-2z" /><path d="M20 35h3v3h-3z" /><path d="M15 38h2" />
              </svg>
              <span>Şəkli QR kodla birlikdə çap edib sifarişinizlə göndəririk.</span>
            </li>
            <li>
              <svg class="step-art" viewBox="0 0 48 48" aria-hidden="true">
                <rect x="2" y="27" width="20" height="15" rx="2" />
                <path d="M5 38l4-5 3 3 3-4 5 6" />
                <rect x="23" y="4" width="20" height="30" rx="3" fill="var(--paper)" />
                <path d="M29 13l9 5.5-9 5.5z" />
                <path d="M21 24l-3 3M25 36l-2 3M17 22l-2-2" opacity=".5" />
                <path d="M40 38l1.5 3 3 1.5-3 1.5-1.5 3-1.5-3-3-1.5 3-1.5z" opacity=".6" />
              </svg>
              <span>Hədiyyəni alan QR kodu oxudur, telefonu şəklə tutur — video şəklin üstündə oynayır.</span>
            </li>
          </ol>
        </form>
      </div>
    </div>
  </section>
@endsection

@section('page_script')
<script src="{{ asset('js/live-target.js') }}"></script>
<script>
(function(){
  var MAX = {{ $maxMb }} * 1024 * 1024;
  var form = document.getElementById('live-form');
  var photo = document.getElementById('ar-photo'), video = document.getElementById('ar-video'), mind = document.getElementById('ar-mind');
  var pic = document.getElementById('live-pic'), scan = document.getElementById('live-scan');
  var prep = document.getElementById('prep'), prepText = document.getElementById('prep-text'), prepBar = document.getElementById('prep-bar');
  var prepHint = document.getElementById('prep-hint'), submit = document.getElementById('live-submit');
  var job = null, picUrl = null, vidUrl = null;

  NefisLive.preload().catch(function(){});

  function preview(){
    pic.innerHTML = '';
    if (!picUrl) {
      pic.className = 'live-pic empty';
      pic.textContent = 'Şəkil və video seçin — burada necə canlanacağını görəcəksiniz';
      pic.style.aspectRatio = '';
      scan.hidden = true;
      return;
    }
    pic.className = 'live-pic';
    var img = new Image();
    img.src = picUrl;
    img.alt = '';
    pic.appendChild(img);
    if (vidUrl) {
      var v = document.createElement('video');
      v.src = vidUrl; v.muted = true; v.loop = true; v.autoplay = true; v.playsInline = true;
      v.setAttribute('playsinline', '');
      pic.appendChild(v);
      v.play().catch(function(){});
    }
    scan.hidden = !vidUrl;
  }

  /* The picture: turned the right way up (phones store it sideways), kept
     sharp enough to print, and prepared for the camera. */
  photo.addEventListener('change', function(){
    var f = photo.files && photo.files[0];
    if (!f || f.__nefis) return;
    document.getElementById('photo-name').textContent = '📷 ' + f.name;
    document.getElementById('photo-pick').classList.add('done');
    mind.value = '';
    var mine = job = NefisLive.loadFile(f).then(function(img){
      if (picUrl) URL.revokeObjectURL(picUrl);
      picUrl = img.src;
      pic.style.aspectRatio = img.naturalWidth + ' / ' + img.naturalHeight;
      preview();
      var print = NefisLive.flatten(img, 3000);
      return NefisLive.toBlob(print, 'image/jpeg', 0.92).then(function(b){
        var name = f.name.replace(/\.[^.]+$/, '') + '.jpg';
        if (mine === job && NefisLive.attach(photo, b, name)) photo.files[0].__nefis = true;
        prep.hidden = false; prepHint.hidden = false;
        prepText.textContent = 'Şəkil kamera üçün hazırlanır…';
        prepBar.style.width = '0%';
        return NefisLive.compile(print, function(p){ if (mine === job) prepBar.style.width = p + '%'; });
      });
    }).then(function(blob){
      if (mine !== job) return;
      NefisLive.attach(mind, blob, 'target.mind');
      prepText.textContent = '✓ Şəkil kamera üçün hazırdır';
      prepBar.style.width = '100%';
      prepHint.hidden = true;
    }).catch(function(){
      if (mine !== job) return;
      /* Not this browser: the shop prepares it by hand instead. */
      prepText.textContent = 'Şəkil qəbul olundu — kamera üçün biz hazırlayacağıq';
      prepHint.hidden = true;
    });
  });

  video.addEventListener('change', function(){
    var f = video.files && video.files[0];
    var hint = document.getElementById('video-hint');
    if (vidUrl) { URL.revokeObjectURL(vidUrl); vidUrl = null; }
    if (!f) { preview(); return; }
    document.getElementById('video-name').textContent = '🎬 ' + f.name;
    document.getElementById('video-pick').classList.add('done');
    var big = f.size > MAX;
    hint.classList.toggle('bad', big);
    hint.textContent = big
      ? 'Video ' + (f.size / 1048576).toFixed(1) + ' MB-dır — {{ $maxMb }} MB-dan kiçik olmalıdır. Qısaldın və ya sıxın.'
      : 'Ən yaxşısı 10–30 saniyəlik video. Öz ölçüsündə (məs. 9:16), kəsilmədən oynayır.';
    vidUrl = URL.createObjectURL(f);
    preview();
  });

  /* Sent once the picture is ready — or has failed to get ready. */
  form.addEventListener('submit', function(e){
    var f = video.files && video.files[0];
    if (f && f.size > MAX) { e.preventDefault(); video.focus(); return; }
    if (!job || form.dataset.go) return;
    e.preventDefault();
    submit.disabled = true;
    submit.textContent = 'Hazırlanır…';
    var go = function(){ form.dataset.go = '1'; submit.textContent = 'Göndərilir…'; form.submit(); };
    job.then(go, go);
  });
})();
</script>
@endsection
