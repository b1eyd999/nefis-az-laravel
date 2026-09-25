@extends('layouts.app')

@section('title', \App\Support\Letter::text('title') . ', Nefis Şokolad Evi')
@section('meta_description', __('Şəkliniz və sözlərinizlə polaroid kimi çap olunan məktub, qutunun içinə və ya ayrıca hədiyyə.'))

@section('page_style')
  .letter-grid{ display:grid; gap:2.5rem; align-items:center; }
  @media (min-width:900px){ .letter-grid{ grid-template-columns:1fr 1fr; gap:4rem; } }
  .letter-stage{ padding:2.5rem 1rem; border-radius:var(--radius); background:radial-gradient(ellipse at 50% 40%, var(--cream-2), transparent 72%); }
  .letter-stage .polaroid{ max-width:22rem; }
  .letter-form{ display:flex; flex-direction:column; gap:1.1rem; }
  .letter-price{ display:flex; justify-content:space-between; align-items:baseline; border:1px solid var(--line); border-radius:.9rem; padding:.8rem 1rem; }
  .letter-price b{ font-size:1.3rem; color:var(--gold-deep); }
  .letter-file{ display:flex; align-items:center; justify-content:center; gap:.5rem; border:1.5px dashed var(--ring); border-radius:.9rem;
    padding:1rem; text-align:center; cursor:pointer; font-weight:600; margin:0; }
  .letter-file:hover{ border-color:var(--gold); }
  .letter-file input{ display:none; }
  .letter-hint{ font-size:.8125rem; color:var(--cocoa-soft); margin-top:.35rem; }
  .letter-clear{ font-size:.8125rem; text-decoration:underline; color:var(--cocoa-soft); background:none; border:0; padding:0; }
  .letter-clear[hidden]{ display:none; }
@endsection

@section('content')
  <section class="page-hero" style="padding-bottom:0;">
    <div class="wrap">
      @if($t['eyebrow'])<span class="eyebrow" style="justify-content:center;">{{ $t['eyebrow'] }}</span>@endif
      <h1>{{ $t['title'] }}</h1>
      @if($t['lede'])<p class="lede" style="margin-inline:auto;">{{ $t['lede'] }}</p>@endif
    </div>
  </section>

  <section>
    <div class="wrap">
      @if($errors->any())
        <div class="alert alert-error">
          <ul style="margin:0; padding-left:1.1rem;">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
      @endif

      <div class="letter-grid">
        <div class="letter-stage">
          @include('partials.polaroid', ['id' => 'letter-preview', 'text' => old('letter_text')])
        </div>

        <form class="letter-form" method="POST" action="{{ lroute('letters.store') }}" enctype="multipart/form-data">
          @csrf
          <div>
            <label>{{ __('Şəkil') }} <span style="font-weight:400; color:var(--cocoa-soft);">({{ __('istəyə görə') }})</span></label>
            <label class="letter-file">
              <input type="file" name="letter_photo" id="letter-photo" accept="image/*">
              <span id="letter-photo-name" data-label="📷 {{ $t['photo_label'] }}">📷 {{ $t['photo_label'] }}</span>
            </label>
            <button type="button" class="letter-clear" id="letter-clear" hidden>{{ __('Şəkli sil') }}</button>
          </div>
          <div>
            <label for="letter-text">{{ __('Mətn') }} <span style="font-weight:400; color:var(--cocoa-soft);">({{ __('istəyə görə') }})</span></label>
            <textarea id="letter-text" name="letter_text" rows="4" maxlength="{{ $max }}" placeholder="{{ $t['text_placeholder'] }}">{{ old('letter_text') }}</textarea>
            <p class="letter-hint">{{ __(':max simvola qədər.', ['max' => $max]) }} {{ $t['hint'] }}</p>
          </div>
          <div>
            <label for="letter-qty">Say</label>
            <input type="number" id="letter-qty" name="quantity" value="{{ old('quantity', 1) }}" min="1" max="20" style="max-width:7rem;">
          </div>
          <div class="letter-price"><span>{{ __('Qiymət') }}</span><b>{{ \App\Support\Price::format($price) }}</b></div>
          <button type="submit" class="btn btn-primary btn-block">{{ $t['button'] }}</button>
          @if($t['note'])<p class="letter-hint">{{ $t['note'] }}</p>@endif
        </form>
      </div>
    </div>
  </section>
@endsection

@section('page_script')
<script src="{{ asset('js/polaroid.js') }}"></script>
<script>
(function(){
  var file = document.getElementById('letter-photo');
  var name = document.getElementById('letter-photo-name');
  var clear = document.getElementById('letter-clear');
  var p = NefisPolaroid.bind(document.getElementById('letter-preview'), file, document.getElementById('letter-text'));
  file.addEventListener('change', function(){
    var f = file.files && file.files[0];
    name.textContent = f ? '📷 ' + f.name : name.dataset.label;
    clear.hidden = !f;
  });
  clear.addEventListener('click', function(){ p.clearPhoto(); name.textContent = name.dataset.label; clear.hidden = true; });
})();
</script>
@endsection
