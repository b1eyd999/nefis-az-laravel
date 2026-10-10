@extends('layouts.app')

@push('head')
  {{-- The faces the designs are lettered in; a slot without a file of its own
       is drawn in one of these by name. --}}
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,500&family=Inter:wght@400;500;600;700&family=Great+Vibes&family=Poppins:wght@600&family=Titan+One&family=Bungee&family=Fredoka:wght@500;600&family=Sacramento&family=Creepster&family=Source+Sans+3:wght@400;600&family=Orbitron:wght@600;800&family=Anton&family=Cinzel:wght@400;700&family=Bangers&family=Luckiest+Guy&family=Oswald:wght@500;700&family=Bevan&family=Archivo+Black&family=Caveat:wght@600&family=Pacifico&family=Montserrat:wght@300;500&display=swap" rel="stylesheet">
@endpush

@section('title', __($page['title']) . ' — ' . __('Şirkətlər üçün') . ', Nefis')
@section('meta_description', \App\Support\Seo::snippet(__($page['lede'])))

@section('page_style')
  .co-hero{ display:grid; gap:2rem; align-items:center; }
  @media (min-width:900px){ .co-hero{ grid-template-columns:1.05fr .95fr; gap:3.5rem; } }
  .co-facts{ display:flex; flex-wrap:wrap; gap:.6rem; margin-top:1.4rem; }
  .co-fact{ border:1px solid var(--line); border-radius:.9rem; padding:.55rem .85rem; background:var(--paper); }
  .co-fact b{ display:block; font-size:1.05rem; letter-spacing:-.02em; }
  .co-fact span{ font-size:.75rem; color:var(--cocoa-soft); text-transform:uppercase; letter-spacing:.05em; }

  /* what it costs, by the number */
  .co-ladder{ margin-top:1.6rem; }
  .co-ladder > b{ display:block; font-size:1rem; margin-bottom:.7rem; }
  .co-ladder .slot-hint{ margin-top:.7rem; }
  .co-steps{ display:flex; flex-wrap:wrap; gap:.5rem; }
  .co-step{
    display:flex; align-items:baseline; gap:.3rem; padding:.55rem .8rem;
    border:1px solid var(--line); border-radius:.8rem; background:var(--paper);
  }
  .co-step span{ font-size:.75rem; color:var(--cocoa-soft); }
  .co-step b{ font-size:1.0625rem; letter-spacing:-.02em; font-variant-numeric:tabular-nums; }
  .co-step i{ font-style:normal; font-size:.6875rem; color:var(--cocoa-soft); }
  .co-quote{
    display:block; margin-top:.35rem; font-size:.8125rem; font-weight:600;
    color:var(--gold-deep); font-variant-numeric:tabular-nums;
  }

  /* the two faces, side by side, at the size they are printed */
  .co-faces{ display:flex; gap:1.2rem; justify-content:center; align-items:flex-start; }
  .co-face{ flex:0 1 13rem; }
  .co-face canvas{ display:block; width:100%; border-radius:.45rem;
    box-shadow:0 18px 40px -18px rgba(58,38,23,.55), 0 2px 6px rgba(58,38,23,.18); }
  .co-face figcaption{ margin-top:.5rem; text-align:center; font-size:.78rem; color:var(--cocoa-soft); }

  .co-try{ display:grid; gap:2rem; align-items:start; }
  @media (min-width:900px){ .co-try{ grid-template-columns:1fr 1fr; gap:3rem; } }
  .co-stage{ padding:2rem 1rem; border-radius:var(--radius);
    background:radial-gradient(ellipse at 50% 35%, var(--cream-2), transparent 72%); }
  .co-controls{ display:flex; flex-direction:column; gap:1rem; }
  .co-file{ display:flex; align-items:center; justify-content:center; gap:.5rem; border:1.5px dashed var(--ring);
    border-radius:.9rem; padding:1rem; text-align:center; cursor:pointer; font-weight:600; margin:0; word-break:break-word; }
  .co-file:hover{ border-color:var(--gold); }
  .co-file.done{ border-style:solid; border-color:var(--gold); }
  .co-file input{ position:absolute; width:1px; height:1px; opacity:0; pointer-events:none; }
  .co-swatches{ display:flex; flex-wrap:wrap; gap:.5rem; }
  .co-swatch{ width:2.4rem; height:2.4rem; border-radius:50%; border:2px solid var(--line); cursor:pointer; padding:0;
    position:relative; }
  .co-swatch[aria-pressed="true"]{ border-color:var(--flame); box-shadow:0 0 0 3px rgba(240,84,32,.2); }
  .co-swatch span{ position:absolute; inset:auto auto -1.35rem 50%; transform:translateX(-50%); font-size:.65rem;
    white-space:nowrap; color:var(--cocoa-soft); opacity:0; transition:opacity .2s; }
  .co-swatch:hover span, .co-swatch[aria-pressed="true"] span{ opacity:1; }
  .co-swatch-row{ padding-bottom:1.3rem; }

  .co-cards{ display:grid; gap:1rem; grid-template-columns:repeat(auto-fit, minmax(14rem, 1fr)); }
  .co-card{ border:1px solid var(--line); border-radius:var(--radius); padding:1.1rem 1.2rem; background:var(--paper);
    transition:border-color .3s, transform .3s var(--ease); }
  .co-card:hover{ border-color:var(--gold); transform:translateY(-2px); }
  .co-card .ico{ font-size:1.6rem; display:block; margin-bottom:.4rem; }
  .co-card b{ display:block; margin-bottom:.25rem; }
  .co-card p{ margin:0; font-size:.88rem; color:var(--cocoa-soft); }

  .co-scenes{ display:grid; gap:1.2rem; grid-template-columns:repeat(auto-fit, minmax(15rem, 1fr)); }
  .co-scene{ margin:0; }
  .co-scene canvas{ display:block; width:100%; height:auto; border-radius:var(--radius-sm);
    box-shadow:var(--shadow-sm); background:var(--cream-2); aspect-ratio:3 / 4; }
  .co-scene figcaption{ margin-top:.5rem; font-size:.82rem; color:var(--cocoa-soft); }
  .co-gallery{ display:grid; gap:1rem; grid-template-columns:repeat(auto-fit, minmax(16rem, 1fr)); }
  .co-shot{ margin:0; }
  .co-shot img{ display:block; width:100%; border-radius:var(--radius-sm); box-shadow:var(--shadow-sm); }
  .co-shot figcaption{ margin-top:.5rem; font-size:.82rem; color:var(--cocoa-soft); }

  /* A company with a designer of its own: our template goes out, their
     finished artwork comes back through the same form. Folded away until
     they say they have one, so it does not lengthen the form for everyone. */
  .co-designer{ border:1px solid var(--line); border-radius:var(--radius);
    padding:.95rem 1.1rem; background:var(--cream); }
  .co-designer .co-check{ display:flex; align-items:center; gap:.6rem; margin:0; cursor:pointer; }
  .co-designer .co-check input{ width:1.05rem; height:1.05rem; accent-color:var(--terracotta); margin:0; }
  .co-designer .co-check span{ display:inline; margin:0; font-size:.9375rem; font-weight:600;
    color:var(--cocoa); }
  .co-designer-body{ display:none; margin-top:.9rem; }
  .co-designer.is-open .co-designer-body{ display:block; }
  .co-designer:has(.co-check input:checked) .co-designer-body{ display:block; }
  .co-designer-body > p{ font-size:.875rem; line-height:1.65; color:var(--cocoa-soft); margin:0 0 .9rem; }
  .co-designer-grid{ display:grid; gap:1rem; }
  @media (min-width:38rem){ .co-designer-grid{ grid-template-columns:1fr 1fr; align-items:center; } }
  .co-tpl{ display:flex; align-items:center; gap:.85rem; text-decoration:none; color:inherit;
    border:1px solid var(--line); border-radius:.85rem; padding:.7rem .85rem; background:var(--paper); }
  .co-tpl:hover{ border-color:var(--cocoa-faint); }
  .co-tpl img{ width:3rem; height:4.2rem; object-fit:cover; object-position:top center;
    border-radius:.35rem; border:1px solid var(--line); background:#fff; flex:none; }
  .co-tpl b{ display:block; font-size:.9rem; }
  .co-tpl small{ display:block; font-size:.78rem; color:var(--cocoa-soft); margin-top:.2rem; }
  .co-design-file{ display:block; margin-top:1rem; }
  .co-designer-body input[type=file]{ font-size:.85rem; }
  .co-designer-body label > small{ display:block; font-size:.78rem; color:var(--cocoa-soft); margin-top:.35rem; }

  /* Until now a rejected form came back silent; a wrong design file has to
     say what was wrong with it. */
  .co-errors{ list-style:none; margin:1.2rem 0 0; padding:.85rem 1rem; border-radius:var(--radius);
    background:rgba(181,71,63,.08); border:1px solid rgba(181,71,63,.3); color:var(--red);
    font-size:.875rem; line-height:1.6; }
  .co-errors li + li{ margin-top:.3rem; }

  .co-form{ display:grid; gap:1rem; grid-template-columns:repeat(auto-fit, minmax(14rem, 1fr)); }
  .co-form .full{ grid-column:1 / -1; }
  .co-form label > span{ display:block; font-size:.8rem; font-weight:600; color:var(--cocoa-soft); margin-bottom:.3rem; }
  .co-form input, .co-form textarea{ width:100%; }
  .co-sent{ background:var(--ok-bg); color:var(--ok-fg); border:1px solid var(--ok-line);
    border-radius:var(--radius-sm); padding:1rem 1.2rem; font-weight:600; }
@endsection

@section('content')
<section>
  <div class="wrap">
    <div class="co-hero">
      <div>
        <p class="eyebrow">{{ __($page['eyebrow']) }}</p>
        <h1>{{ __($page['title']) }}</h1>
        <p class="lede">{{ __($page['lede']) }}</p>

        <div class="co-facts">
          <div class="co-fact"><span>{{ __($page['size_label']) }}</span><b>{{ __($page['size']) }}</b></div>
          <div class="co-fact"><span>{{ __($page['min_qty_label']) }}</span><b>{{ $page['min_qty'] }} {{ __('ədəd') }}</b></div>
          <div class="co-fact"><span>{{ __($page['lead_label']) }}</span><b>{{ __($page['lead']) }}</b></div>
        </div>

        <p class="slot-hint" style="margin-top:1rem;">{{ __($page['min_qty_note']) }}</p>

        {{-- The price, by the number. The page used to say only that a bigger
             order costs less each and then name no figure at all, so every
             enquiry began with somebody asking what it costs. Shown only once
             the owner has written his own steps. --}}
        @if($page['ladder'])
          <div class="co-ladder">
            <b>{{ __($page['ladder_title']) }}</b>
            <div class="co-steps">
              @foreach($page['ladder'] as $step)
                <div class="co-step">
                  <span>{{ $step['from'] }}+ {{ __('ədəd') }}</span>
                  <b>{{ \App\Support\Price::format($step['price']) }}</b>
                  <i>/ {{ __($page['ladder_per_label']) }}</i>
                </div>
              @endforeach
            </div>
            <p class="slot-hint">{{ __($page['ladder_note']) }}</p>
          </div>
        @endif

        <a class="btn btn-primary" href="#muraciet" style="margin-top:1.4rem;">{{ __($page['form_button']) }}</a>
      </div>

      {{-- The box itself, before a word is read about it. --}}
      <div class="co-stage">
        <div class="co-faces">
          <figure class="co-face">
            <canvas id="co-front" aria-label="{{ __('Qutunun ön tərəfi') }}"></canvas>
            <figcaption>{{ __($page['front_title']) }}</figcaption>
          </figure>
          <figure class="co-face">
            <canvas id="co-back" aria-label="{{ __('Qutunun arxa tərəfi') }}"></canvas>
            <figcaption>{{ __($page['back_title']) }}</figcaption>
          </figure>
        </div>
      </div>
    </div>
  </div>
</section>

<section>
  <div class="wrap">
    <h2>{{ __($page['faces_title']) }}</h2>
    <div class="co-cards" style="margin-top:1.2rem;">
      <div class="co-card"><span class="ico">🏷️</span><b>{{ __($page['front_title']) }}</b><p>{{ __($page['front_text']) }}</p></div>
      <div class="co-card"><span class="ico">📱</span><b>{{ __($page['back_title']) }}</b><p>{{ __($page['back_text']) }}</p></div>
    </div>

  </div>
</section>

{{-- Try it on: the company sees its own mark on the box before it asks a price. --}}
<section id="yoxla">
  <div class="wrap">
    <h2>{{ __($page['try_title']) }}</h2>
    <p class="lede" style="max-width:48rem;">{{ __($page['try_note']) }}</p>

    <div class="co-try" style="margin-top:1.8rem;">
      <div class="co-stage">
        <div class="co-faces">
          <figure class="co-face">
            <canvas id="co-try-front" aria-label="{{ __('Qutunun ön tərəfi') }}"></canvas>
            <figcaption>{{ __($page['front_title']) }}</figcaption>
          </figure>
          <figure class="co-face">
            <canvas id="co-try-back" aria-label="{{ __('Qutunun arxa tərəfi') }}"></canvas>
            <figcaption>{{ __($page['back_title']) }}</figcaption>
          </figure>
        </div>
      </div>

      <div class="co-controls">
        <label class="co-file" id="co-logo-label">
          <span id="co-logo-name">🖼️ {{ __('Loqonuzu yükləyin') }}</span>
          <input type="file" id="co-logo" accept="image/png,image/jpeg,image/webp,image/svg+xml">
        </label>
        <p class="slot-hint" style="margin-top:-.6rem;">{{ __('PNG şəffaf fonla ən yaxşı görünür. 4 MB-a qədər.') }}</p>

        <div>
          <span style="display:block; font-size:.8rem; font-weight:600; color:var(--cocoa-soft); margin-bottom:.45rem;">{{ __('Qutunun rəngi') }}</span>
          <div class="co-swatches co-swatch-row">
            @foreach($page['colors'] as $i => $color)
              <button type="button" class="co-swatch" data-color="{{ $color['hex'] }}"
                      style="background:{{ $color['hex'] }}" aria-pressed="{{ $i === 0 ? 'true' : 'false' }}"
                      title="{{ $color['name'] }}"><span>{{ $color['name'] }}</span></button>
            @endforeach
          </div>
        </div>

        <label><span>{{ __('Şüar və ya bir cümlə') }}</span>
          <input type="text" id="co-slogan" maxlength="60" placeholder="{{ __('Məs. Həyata güvənlə baxın') }}"></label>

        <label><span>{{ __('Əlaqə nömrəsi') }}</span>
          <input type="text" id="co-phone" maxlength="30" placeholder="+994 __ ___ __ __"></label>

        <label><span>{{ __('QR kod hara aparsın') }}</span>
          <input type="text" id="co-qr" maxlength="300" placeholder="instagram.com/…"></label>
      </div>
    </div>
  </div>
</section>

{{-- The same box, standing where it will actually stand. The photographs
     are real; only the printed face is drawn, with whatever logo the visitor
     has just uploaded warped onto it. --}}
<section id="sehnelar">
  <div class="wrap">
    <h2>{{ __($page['gallery_title']) }}</h2>
    <p class="lede" style="max-width:48rem;">{{ __('Yuxarıda seçdiyiniz loqo və rəng elə burada da görünür.') }}</p>
    <div class="co-scenes" style="margin-top:1.4rem;">
      @foreach($page['scenes'] as $i => $scene)
        <figure class="co-scene">
          <canvas class="co-scene-canvas" id="co-scene-{{ $i }}"
                  data-photo="{{ asset($scene['image']) }}"
                  data-corners="{{ json_encode($scene['corners']) }}"
                  aria-label="{{ __($scene['caption']) }}"></canvas>
          <figcaption>{{ __($scene['caption']) }}</figcaption>
        </figure>
      @endforeach
    </div>
  </div>
</section>

@if(! empty($page['whom']))
<section>
  <div class="wrap">
    <h2>{{ __($page['whom_title']) }}</h2>
    <div class="co-cards" style="margin-top:1.2rem;">
      @foreach($page['whom'] as $who)
        <div class="co-card">
          @if(! empty($who['icon']))<span class="ico">{{ $who['icon'] }}</span>@endif
          <b>{{ __($who['title']) }}</b>
          @if(! empty($who['text']))<p>{{ __($who['text']) }}</p>@endif
        </div>
      @endforeach
    </div>
  </div>
</section>
@endif

@if(! empty($page['perks']))
<section>
  <div class="wrap">
    <h2>{{ __($page['perks_title']) }}</h2>
    <div class="co-cards" style="margin-top:1.2rem;">
      @foreach($page['perks'] as $perk)
        <div class="co-card">
          <b>{{ __($perk['title']) }}</b>
          @if(! empty($perk['text']))<p>{{ __($perk['text']) }}</p>@endif
        </div>
      @endforeach
    </div>
  </div>
</section>
@endif

@if(! empty($page['gallery']))
  <section>
    <div class="wrap">
      <h2>{{ __($page['gallery_title']) }}</h2>
      <div class="co-gallery" style="margin-top:1.2rem;">
        @foreach($page['gallery'] as $shot)
          <figure class="co-shot">
            <img src="{{ \App\Support\Media::url($shot['image']) }}" alt="{{ $shot['caption'] ?? __($page['title']) }}" loading="lazy">
            @if(! empty($shot['caption']))<figcaption>{{ __($shot['caption']) }}</figcaption>@endif
          </figure>
        @endforeach
      </div>
    </div>
  </section>
@endif

<section id="muraciet">
  <div class="wrap" style="max-width:52rem;">
    <h2>{{ __($page['form_title']) }}</h2>
    <p class="lede">{{ __($page['form_note']) }}</p>

    @if(session('corporate.sent'))
      <p class="co-sent" style="margin-top:1.2rem;">✓ {{ session('corporate.sent') }}</p>
    @endif

    @if($errors->any())
      <ul class="co-errors">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
      </ul>
    @endif

    <form method="POST" action="{{ lroute('corporate.store') }}" enctype="multipart/form-data"
          class="co-form" style="margin-top:1.4rem;">
      @csrf
      <label><span>{{ __('Şirkətin adı') }} *</span>
        <input type="text" name="company" value="{{ old('company') }}" required maxlength="150"></label>
      <label><span>{{ __('Əlaqədar şəxs') }}</span>
        <input type="text" name="person" value="{{ old('person') }}" maxlength="150"></label>
      <label><span>{{ __('Telefon') }} *</span>
        <input type="text" name="phone" value="{{ old('phone', '+994 ') }}" required maxlength="40"></label>
      <label><span>{{ __('E-poçt') }}</span>
        <input type="email" name="email" value="{{ old('email') }}" maxlength="150"></label>
      <label><span>{{ __('Neçə ədəd') }} *</span>
        <input type="number" name="quantity" id="co-qty" value="{{ old('quantity', $page['min_qty']) }}"
               min="{{ $page['min_qty'] }}" step="1" required inputmode="numeric">
        {{-- What that number costs, worked out as he types it. The figures
             come from the owner's own ladder and the server quotes the same
             ones back in the Telegram notice. --}}
        @if($page['ladder'])
          <small class="co-quote" id="co-quote" aria-live="polite"></small>
        @endif
      </label>
      <label><span>{{ __('Loqo') }}</span>
        <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml"></label>
      <label class="full"><span>{{ __('Şüar') }}</span>
        <input type="text" name="slogan" value="{{ old('slogan') }}" maxlength="120"></label>
      <label class="full"><span>{{ __('QR kod hara aparsın') }}</span>
        <input type="text" name="qr_target" value="{{ old('qr_target') }}" maxlength="300"></label>
      <label class="full"><span>{{ __('Qeyd') }}</span>
        <textarea name="note" rows="3" maxlength="1000">{{ old('note') }}</textarea></label>

      {{-- A company that draws its own box: the template out, the finished
           file back. Open from the start if the last attempt was rejected,
           so the message above is beside the field it is about. --}}
      <div class="full co-designer{{ $errors->has('design') ? ' is-open' : '' }}" id="co-designer">
        <label class="co-check">
          <input type="checkbox" @checked($errors->has('design'))>
          <span>{{ __($page['designer_check']) }}</span>
        </label>
        <div class="co-designer-body">
          <p>{{ __($page['designer_note']) }}</p>
          <div class="co-designer-grid">
            <a class="co-tpl" href="{{ asset(\App\Support\CorporatePage::TEMPLATE) }}" download>
              <img src="{{ asset(\App\Support\CorporatePage::TEMPLATE) }}" alt="" loading="lazy" decoding="async">
              <span>
                <b>{{ __($page['designer_template']) }}</b>
                <small>{{ __($page['designer_template_note']) }}</small>
              </span>
            </a>
            <a class="co-tpl" href="{{ asset(\App\Support\CorporatePage::BACK_SHOT) }}" target="_blank" rel="noopener">
              <img src="{{ asset(\App\Support\CorporatePage::BACK_SHOT) }}" alt="" loading="lazy" decoding="async"
                   style="object-position:center">
              <span>
                <b>{{ __($page['back_title']) }} — {{ __('nümunə') }}</b>
                <small>{{ __($page['back_shot']) }}</small>
              </span>
            </a>
          </div>
          <label class="co-design-file"><span>{{ __($page['designer_field']) }}</span>
            <input type="file" name="design" accept=".pdf,.ai,.eps,.png,.jpg,.jpeg,.webp,.zip">
            <small>{{ __($page['designer_formats']) }}</small></label>
        </div>
      </div>

      {{-- Filled in by the try-on above, so the request carries the colour
           they were actually looking at when they decided to write. --}}
      <input type="hidden" name="box_color" id="co-color-field" value="{{ old('box_color', $page['colors'][0]['hex'] ?? '#1B3A6B') }}">

      <div class="full">
        <button type="submit" class="btn btn-primary">{{ __($page['form_button']) }}</button>
      </div>
    </form>
  </div>
</section>
@endsection

@section('page_script')
@if($page['ladder'])
<script>
/* What the number he has typed costs, worked out as he types it.
   The steps are the owner's own, and the server quotes the same ones back in
   the notice it sends itself — this only saves him asking. */
(function () {
  var steps = @json($page['ladder']);
  var box = document.getElementById('co-qty');
  var out = document.getElementById('co-quote');
  if (!box || !out || !steps.length) return;

  var least = steps[0].from;

  function money(value) {
    // The shop writes 7 ₼ and 7.50 ₼, never 7.00 ₼.
    var rounded = Math.round(value * 100) / 100;
    return (rounded % 1 === 0 ? rounded.toFixed(0) : rounded.toFixed(2)) + ' ₼';
  }

  function show() {
    var many = parseInt(box.value, 10);
    if (!many || many < least) {
      // Below the first step there is no price to name, so the line says
      // where the prices start rather than going blank.
      out.textContent = least + '+ ' + @json(__('ədəddən'));
      return;
    }
    /* The highest step the number reaches — 250 against steps of 100 and 300
       is priced at 100, not at 300. The other reading would quote a discount
       nobody has earned. */
    var unit = null;
    for (var i = 0; i < steps.length; i++) {
      if (many >= steps[i].from) unit = steps[i].price;
    }
    if (unit === null) return;
    out.textContent = money(unit) + ' × ' + many + ' = ' + money(unit * many);
  }

  box.addEventListener('input', show);
  show();
})();
</script>
@endif
{{-- The same warper the shop's own mockups are drawn with, so a logo sits on
     this box exactly as a design sits on a chocolate box. --}}
<script src="{{ asset('js/scene-render.js') }}?v={{ \App\Support\Assets::version('js/scene-render.js') }}"></script>
<script defer src="{{ asset('js/corporate-box.js') }}?v={{ \App\Support\Assets::version('js/corporate-box.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  if (!window.NefisCorporate) return;

  var QR = 'https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js';
  var first = @json($page['colors'][0]['hex'] ?? '#1B3A6B');

  /* The pair at the top is a still life: it shows the product, nobody edits it. */
  var shown = window.NefisCorporate.init({ front: 'co-front', back: 'co-back', color: first, qrScript: QR });
  shown.set('slogan', @json(__($page['eyebrow'])));
  shown.set('qr', @json(url('/')));

  /* The photographs, and where the printed face sits in each of them. */
  var scenes = Array.prototype.map.call(document.querySelectorAll('.co-scene-canvas'), function (cv) {
    var photo = new Image();
    photo.src = cv.dataset.photo;
    photo.onload = paintScenes;
    return { cv: cv, photo: photo, corners: JSON.parse(cv.dataset.corners) };
  });
  var warpCache = {};
  var faceForScenes = null;

  function paintScenes() {
    if (!window.NefisScene || !faceForScenes) return;
    scenes.forEach(function (s) {
      if (!s.photo.complete || !s.photo.naturalWidth) return;
      var w = 760;
      var h = Math.round(w * s.photo.naturalHeight / s.photo.naturalWidth);
      if (s.cv.width !== w) { s.cv.width = w; s.cv.height = h; }
      window.NefisScene.drawScene(s.cv.getContext('2d'), {
        w: w, h: h, bg: s.photo.src,
        elements: [{ type: 'design', id: 'face', opacity: 100,
          corners: s.corners.map(function (p) { return [p[0] * w, p[1] * h]; }) }],
      }, faceForScenes, function () { return s.photo; }, warpCache, {});
    });
  }

  var box = window.NefisCorporate.init({ front: 'co-try-front', back: 'co-try-back', color: first, qrScript: QR,
    onDraw: function (front) { faceForScenes = front; paintScenes(); } });

  var field = document.getElementById('co-color-field');

  document.querySelectorAll('.co-swatch').forEach(function (button) {
    button.addEventListener('click', function () {
      document.querySelectorAll('.co-swatch').forEach(function (other) {
        other.setAttribute('aria-pressed', other === button ? 'true' : 'false');
      });
      box.set('color', button.dataset.color);
      if (field) field.value = button.dataset.color;
    });
  });

  [['co-slogan', 'slogan'], ['co-phone', 'phone'], ['co-qr', 'qr']].forEach(function (pair) {
    var input = document.getElementById(pair[0]);
    if (input) input.addEventListener('input', function () { box.set(pair[1], input.value); });
  });

  /* The designer fold. CSS opens it on its own where :has() is understood;
     this is for the browsers where it is not. */
  var designer = document.getElementById('co-designer');
  if (designer) {
    var check = designer.querySelector('.co-check input');
    var sync = function () { designer.classList.toggle('is-open', check.checked); };
    check.addEventListener('change', sync);
    sync();
  }

  var logo = document.getElementById('co-logo');
  if (logo) {
    logo.addEventListener('change', function () {
      var file = logo.files && logo.files[0];
      var label = document.getElementById('co-logo-label');
      var name = document.getElementById('co-logo-name');
      box.setLogo(file).then(function (ok) {
        if (label) label.classList.toggle('done', !!ok);
        if (name) name.textContent = ok ? '✓ ' + file.name : '🖼️ ' + @json(__('Loqonuzu yükləyin'));
      });
    });
  }
});
</script>
@endsection
