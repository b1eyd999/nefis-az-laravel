@extends('layouts.app')

@php
  $seoImage = \App\Support\Media::url($product->catalogImage());
  $seoText = $product->description
      ? \Illuminate\Support\Str::limit($product->description, 110) . ' Şəklinizi və sözlərinizi əlavə edin — Bakıda çatdırılma.'
      : '«' . $product->name . '» dizaynında fərdi şokolad qutusu: şəklinizi və sözlərinizi əlavə edin, önizləməni dərhal görün'
        . ($product->price ? ', ' . \App\Support\Price::format($product->price) . '-dan' : '')
        . '. Ad günü və sevdiklərinizə hədiyyə — Bakıda çatdırılma.';
@endphp
@section('title', $product->name . ' — şəkilli şokolad qutusu | Nefis')
@section('meta_description', $seoText)
@if($seoImage)
  @section('og_image', $seoImage)
@endif
@section('og_type', 'product')

@push('jsonld')
  {{ \App\Support\Seo::jsonLd(['@graph' => array_values(array_filter([
      array_filter([
          '@type' => 'Product',
          'name' => $product->name . ' — şəkilli şokolad qutusu',
          'image' => $seoImage ? [$seoImage] : null,
          'description' => $seoText,
          'sku' => 'nefis-' . $product->id,
          'category' => 'Fərdi şokolad qutusu',
          'brand' => ['@type' => 'Brand', 'name' => 'Nefis'],
          'offers' => $product->price ? [
              '@type' => 'Offer',
              'url' => route('products.customize', $product->slug),
              'priceCurrency' => 'AZN',
              'price' => number_format((float) $product->price, 2, '.', ''),
              'availability' => 'https://schema.org/InStock',
              'itemCondition' => 'https://schema.org/NewCondition',
              'seller' => ['@type' => 'Organization', 'name' => 'Nefis Şokolad Evi'],
          ] : null,
      ]),
      \App\Support\Seo::breadcrumbs([
          ['Ana səhifə', route('home')],
          ['Dizaynlar', route('designs.index')],
          [$product->name, route('products.customize', $product->slug)],
      ]),
  ]))]) }}
@endpush

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
  .wrap-block{ display:flex; flex-direction:column; gap:.6rem; }
  .wrap-none{ display:flex; align-items:center; justify-content:space-between; gap:.75rem; padding:.6rem .85rem; margin:0;
    border:1.5px solid var(--line); border-radius:.8rem; background:var(--paper); cursor:pointer; font-weight:600; font-size:.875rem; position:relative; }
  .wrap-none b{ font-size:.8rem; color:var(--cocoa-soft); font-weight:600; }
  .wrap-none input, .wrap-swatch input{ position:absolute; opacity:0; pointer-events:none; }
  .wrap-none:has(input:checked){ border-color:var(--gold); box-shadow:0 0 0 3px var(--ring); }
  .wrap-group{ border:1px solid var(--line); border-radius:.9rem; padding:.9rem .75rem .8rem; position:relative; margin-top:.35rem; }
  .wrap-price{ position:absolute; top:-.7rem; right:.8rem; background:var(--gold); color:#fff; font-weight:800; font-size:.8rem;
    padding:.15rem .6rem; border-radius:999px; }
  .wrap-swatches{ display:grid; grid-template-columns:repeat(auto-fill, minmax(4.6rem, 1fr)); gap:.6rem; }
  .wrap-swatch{ position:relative; display:flex; flex-direction:column; align-items:center; gap:.3rem; margin:0; cursor:pointer; font-weight:400; }
  .wrap-swatch .sw{ position:relative; width:100%; aspect-ratio:3/4; border-radius:.6rem; background-size:60px auto; background-repeat:repeat;
    border:2px solid transparent; box-shadow:0 2px 8px rgba(0,0,0,.18); overflow:hidden; transition:transform .15s, border-color .15s; }
  .wrap-swatch .sw i{ position:absolute; left:50%; top:0; bottom:0; width:12%; transform:translateX(-50%); background:var(--rb); opacity:.95; }
  .wrap-swatch .sw i::after{ content:''; position:absolute; left:-350%; right:-350%; top:42%; height:9%; background:var(--rb); }
  .wrap-swatch:hover .sw{ transform:translateY(-2px); }
  .wrap-swatch:has(input:checked) .sw{ border-color:var(--gold); box-shadow:0 0 0 3px var(--ring), 0 2px 8px rgba(0,0,0,.18); }
  .wrap-swatch:has(input:checked)::after{ content:'✓'; position:absolute; top:.3rem; right:.3rem; width:1.2rem; height:1.2rem; border-radius:50%;
    background:var(--gold); color:#fff; font-size:.7rem; display:grid; place-items:center; }
  .wrap-swatch:has(input:focus-visible) .sw{ outline:2px solid var(--gold); outline-offset:2px; }
  .wrap-swatch .nm{ font-size:.72rem; line-height:1.25; text-align:center; color:var(--cocoa-soft); }
  .wrap-preview{ border:1px solid var(--line); border-radius:.9rem; padding:.5rem .75rem 1rem; background:radial-gradient(ellipse at 50% 30%, var(--cream-2), transparent 70%); }
  .wrap-preview[hidden]{ display:none; }
  .wrap-preview{ cursor:zoom-in; }
  .letter-block{ border:1px solid var(--line); border-radius:.9rem; padding:.75rem .85rem; }
  .letter-toggle{ display:flex; align-items:center; gap:.6rem; margin:0; cursor:pointer; font-weight:600; font-size:.9rem; }
  .letter-toggle input{ width:1.15rem; height:1.15rem; flex:none; accent-color:var(--gold); }
  .letter-toggle span{ flex:1; }
  .letter-toggle b{ font-size:.85rem; color:var(--gold-deep); }
  .letter-fields{ display:grid; grid-template-columns:7.5rem 1fr; gap:1rem; margin-top:.9rem; align-items:start; }
  .letter-fields[hidden]{ display:none; }
  .letter-mini .polaroid{ max-width:7.5rem; }
  .letter-inputs{ display:flex; flex-direction:column; gap:.5rem; min-width:0; }
  .letter-file{ display:block; border:1.5px dashed var(--ring); border-radius:.7rem; padding:.6rem; text-align:center; cursor:pointer;
    font-weight:600; font-size:.85rem; margin:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .letter-file:hover{ border-color:var(--gold); }
  .letter-file input{ display:none; }
  .wrap-preview-name small{ display:block; font-weight:500; font-size:.72rem; color:var(--cocoa-faint); margin-top:.15rem; }
  .wrap-preview .gift{ max-width:15rem; margin-inline:auto; }
  .wrap-preview-name{ text-align:center; font-size:.85rem; font-weight:600; color:var(--cocoa); margin:0; }

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
  /* the form sits to the right on a wide screen, under the picture on a phone */
  .dh-narrow{ display:none; }
  @media (max-width:959px){ .dh-wide{ display:none; } .dh-narrow{ display:inline; } }
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
  .price-sum div[hidden]{ display:none; }
  .price-sum .total{ color:var(--cocoa); font-weight:700; font-size:1.0625rem; border-top:1px solid var(--line); padding-top:.45rem; margin-top:.1rem; }
@endsection

@section('content')
<section class="page-hero" style="padding-bottom:0;">
  <div class="wrap">
    <nav class="crumbs" aria-label="Səhifənin yeri">
      <a href="{{ route('home') }}">Ana səhifə</a><span aria-hidden="true">›</span>
      <a href="{{ route('designs.index') }}">Dizaynlar</a><span aria-hidden="true">›</span>
      <span aria-current="page">{{ $product->name }}</span>
    </nav>
    <span class="eyebrow" style="justify-content:center;">Fərdiləşdirmə</span>
    <h1>{{ $product->name }}</h1>
    @if($product->description)
      <p class="lede" style="margin-inline:auto;">{{ $product->description }}</p>
    @endif
    @if($gifts->isNotEmpty())
      <nav class="occ-chips" style="margin-top:1.25rem;" aria-label="Hədiyyə fikirləri">
        <span style="width:100%; font-size:.8125rem; color:var(--cocoa-faint);">Bu dizayn bu münasibətlərə uyğundur:</span>
        @foreach($gifts as $gift)
          <a class="occ-chip" href="{{ $gift->url() }}">{{ $gift->emoji }} {{ $gift->menu_label }}</a>
        @endforeach
      </nav>
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
            <div class="drop-hint" id="drop-hint">Öncə <span class="dh-wide">sağdan</span><span class="dh-narrow">aşağıdan</span>&nbsp;şəklinizi yükləyin</div>
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
            {{-- Drawn, not described: what the shot has to look like. --}}
            @include('partials.photo-guide', ['small' => true])
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
                          maxlength="{{ $slot->limit() }}"
                          rows="{{ min(4, max(2, substr_count((string) $value, "\n") + 1)) }}"
                          placeholder="{{ $slot->placeholder ?: 'Məs. Ad Soyad və ya qısa mesaj' }}">{{ $value }}</textarea>
                <p class="slot-hint">Yeni sətir üçün Enter basın.</p>
              @else
                <input type="text" class="text-input" id="text-input-{{ $index }}" name="custom_texts[{{ $index }}]"
                       @if($slot->link_key) data-link="{{ $slot->link_key }}" data-link-lead @endif
                       maxlength="{{ $slot->limit() }}"
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

        @if($wrappings->isNotEmpty())
          {{-- Gift wrap: swatches of paper, grouped by price. Picking one shows the box wrapped in it. --}}
          <div class="wrap-block" id="wrap-block">
            <label>Hədiyyə qablaşdırması</label>
            <label class="wrap-none">
              <input type="radio" name="wrapping_id" value="" data-price="0" data-name="" @checked(! old('wrapping_id'))>
              <span>Qablaşdırmasız</span><b>pulsuz</b>
            </label>
            @foreach($wrappings->groupBy(fn ($w) => number_format($w['price'], 2, '.', '')) as $price => $group)
              <div class="wrap-group">
                <div class="wrap-price">+{{ \App\Support\Price::format((float) $price) }}</div>
                <div class="wrap-swatches">
                  @foreach($group as $w)
                    <label class="wrap-swatch" title="{{ $w['name'] }}">
                      <input type="radio" name="wrapping_id" value="{{ $w['id'] }}" data-price="{{ $w['price'] }}" data-name="{{ $w['name'] }}"
                             data-pattern="{{ $w['pattern'] }}" data-ribbon="{{ $w['ribbon'] }}" data-color="{{ $w['color'] }}" data-scale="{{ $w['scale'] }}"
                             @checked((string) old('wrapping_id') === (string) $w['id'])>
                      <span class="sw" style="background-image:url('{{ $w['pattern'] }}')">
                        @if($w['ribbon'] !== 'none')<i style="--rb: {{ $w['color'] }}"></i>@endif
                      </span>
                      <span class="nm">{{ $w['name'] }}</span>
                    </label>
                  @endforeach
                </div>
              </div>
            @endforeach
            {{-- The box as it will be handed over, in the paper just picked. --}}
            <div class="wrap-preview" id="wrap-preview" data-gift-open title="Hər tərəfdən bax" hidden>
              @include('partials.gift-box', ['wrap' => null])
              <p class="wrap-preview-name" id="wrap-preview-name"></p>
            </div>
          </div>
        @endif

        @if(\App\Support\Letter::enabled())
          {{-- A Polaroid letter inside the box: a photo, a few words, or both. --}}
          @php $letterOn = (bool) old('letter_on'); @endphp
          <div class="letter-block" id="letter-block">
            <label class="letter-toggle">
              <input type="checkbox" name="letter_on" value="1" id="letter-on" @checked($letterOn)>
              <span>💌 {{ \App\Support\Letter::text('box_label') }}</span>
              <b>+{{ \App\Support\Price::format(\App\Support\Letter::price()) }}</b>
            </label>
            <div class="letter-fields" id="letter-fields" @unless($letterOn) hidden @endunless>
              <div class="letter-mini">@include('partials.polaroid', ['id' => 'letter-preview', 'text' => old('letter_text')])</div>
              <div class="letter-inputs">
                <label class="letter-file">
                  <input type="file" name="letter_photo" id="letter-photo" accept="image/*">
                  <span id="letter-photo-name">📷 Şəkil (istəyə görə)</span>
                </label>
                <textarea name="letter_text" id="letter-text" rows="3" maxlength="{{ \App\Support\Letter::maxLength() }}"
                          placeholder="Məktubun mətni (istəyə görə)">{{ old('letter_text') }}</textarea>
                <p class="slot-hint">Şəkil olmasa, mətn polaroidin içində yazılır.</p>
              </div>
            </div>
          </div>
        @endif

        @if(\App\Support\LiveMaterials::enabled())
          {{-- A live photo: the customer's video plays over the box when a phone's camera sees it.
               The box's own design becomes the picture the camera looks for, made ready on sending. --}}
          @php $arOn = (bool) old('ar_on'); @endphp
          <div class="letter-block" id="ar-block">
            <label class="letter-toggle">
              <input type="checkbox" name="ar_on" value="1" id="ar-on" @checked($arOn)>
              <span>🎬 Canlı şəkil (AR) — qutu telefonda canlanır</span>
              <b>+{{ \App\Support\Price::format(\App\Support\LiveMaterials::price()) }}</b>
            </label>
            <div id="ar-fields" @unless($arOn) hidden @endunless style="margin-top:.8rem;">
              <p class="slot-hint" style="margin:0 0 .6rem;">Qutuya QR kod çap edirik. Hədiyyəni alan QR kodu oxudub telefonu qutunun şəklinə tutanda, sizin videonuz şəklin üstündə oynayır — tətbiq yükləmədən.</p>
              <label class="letter-file">
                <input type="file" name="ar_video" id="ar-video" accept="video/mp4,video/quicktime,video/webm,video/*">
                <span id="ar-video-name">🎬 Video seçin (MP4/MOV, {{ \App\Support\LiveMaterials::videoMb() }} MB-a qədər)</span>
              </label>
              <p class="slot-hint">Ən yaxşısı 10–30 saniyəlik, şaquli çəkilmiş video. Qutunun dizaynı kamera üçün özü hazırlanır — "Səbətə at" basanda bir neçə saniyə çəkir.</p>
              <input type="file" name="ar_photo" id="ar-photo" hidden>
              <input type="file" name="ar_mind" id="ar-mind" hidden>
            </div>
          </div>
        @endif

        <div>
          <label for="quantity">Say</label>
          <input type="number" id="quantity" name="quantity" value="1" min="1" max="20" style="max-width:7rem;">
        </div>

        @if($product->price || $chocolates->isNotEmpty() || $wrappings->isNotEmpty() || \App\Support\Letter::enabled())
          <div class="price-sum" id="price-sum" data-box="{{ (float) $product->price }}">
            <div><span>Qutu</span><span>{{ $product->price ? \App\Support\Price::format($product->price) : 'sorğu ilə' }}</span></div>
            @if($chocolates->isNotEmpty())
              <div><span id="sum-choc-name" class="sum-name">Şokolad</span><span id="sum-choc">seçilməyib</span></div>
            @endif
            @if(\App\Support\LiveMaterials::enabled())
              <div id="sum-ar-row" data-price="{{ \App\Support\LiveMaterials::price() }}" hidden><span>Canlı şəkil (AR)</span><span>{{ \App\Support\Price::format(\App\Support\LiveMaterials::price()) }}</span></div>
            @endif
            @if(\App\Support\Letter::enabled())
              <div id="sum-letter-row" data-price="{{ \App\Support\Letter::price() }}" hidden><span>Polaroid məktub</span><span id="sum-letter">{{ \App\Support\Price::format(\App\Support\Letter::price()) }}</span></div>
            @endif
            @if($wrappings->isNotEmpty())
              <div id="sum-wrap-row" hidden><span id="sum-wrap-name" class="sum-name">Qablaşdırma</span><span id="sum-wrap">—</span></div>
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

@if($related->isNotEmpty())
  <section class="tinted">
    <div class="wrap">
      <div class="section-head center">
        <span class="eyebrow" style="justify-content:center;">Oxşar dizaynlar</span>
        <h2>Bunlara da baxın</h2>
      </div>
      <div class="cards-grid">
        {{-- a name of its own: the page's own $product is still needed below --}}
        @foreach($related as $other)
          @include('partials.p-card', ['product' => $other])
        @endforeach
      </div>
    </div>
  </section>
@endif
@endsection

@section('page_script')
@if($photoSlots->where('shape', 'ellipse')->isNotEmpty())
<script defer src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
@endif
<script src="{{ asset('js/box-render.js') }}"></script>
<script src="{{ asset('js/scene-render.js') }}"></script>
<script src="{{ asset('js/wrap-render.js') }}"></script>
<script src="{{ asset('js/gift-box.js') }}"></script>
<script src="{{ asset('js/polaroid.js') }}"></script>
@if(\App\Support\LiveMaterials::enabled())
<script src="{{ asset('js/live-target.js') }}"></script>
@endif
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

  /* One entry per photo slot, kept across angle switches. Zoom is a multiple of
     the cutout's own fit and the pan is a share of the cutout's size, so the
     same framing carries from the flat design into every mockup. */
  var photos = [];
  for (var i = 0; i < SLOT_COUNT; i++) {
    photos.push({ img: null, scale: 1, rotate: 0, panX: 0, panY: 0, faceBox: null, framed: false });
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

    mctx.translate((state.panX || 0) * area.w, (state.panY || 0) * area.h);
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

  /* The flat design as it is printed — the picture a live photo's camera looks for. */
  window.nefisDesign = function(){
    var c = document.createElement('canvas');
    var m = renderMockup();
    c.width = m.width; c.height = m.height;
    c.getContext('2d').drawImage(m, 0, 0);
    return c;
  };

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
      /* What the customer framed by hand stays; only automatic face framing
         follows this view's own cutout. */
      var area = areaFor(i);
      if (!state.framed && area && area.shape === 'ellipse' && state.faceBox) frameOnFace(i, state.faceBox);
      var block = slotBlocks[i];
      var zoom = block && block.querySelector('.zoom-range');
      if (zoom) zoom.value = Math.round(state.scale * 100);
      var rot = block && block.querySelector('.rotate-range');
      if (rot) rot.value = state.rotate || 0;
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
    state.panX = desiredScale * (state.img.width / 2 - faceCx) / area.w;
    state.panY = desiredScale * (state.img.height / 2 - (faceCy + box.height * faceCenterBias)) / area.h;

    var zoom = slotBlocks[slotIndex] && slotBlocks[slotIndex].querySelector('.zoom-range');
    if (zoom) zoom.value = Math.round(state.scale * 100);
  }

  function applyAutoFraming(slotIndex, img){
    var state = photos[slotIndex];
    state.faceBox = null;
    state.scale = 1;
    state.panX = 0;
    state.panY = 0;
    state.framed = false;

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
        photos[index].framed = true;
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
      var area = areaFor(dragSlot);
      if (area) {
        photos[dragSlot].panX += (b.x - a.x) / area.w;
        photos[dragSlot].panY += (b.y - a.y) / area.h;
        photos[dragSlot].framed = true;
      }
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

/* Gift wrap: the picked paper, shown on a box of its own under the swatches. */
(function(){
  var preview = document.getElementById('wrap-preview');
  if (!preview) return;
  var box = preview.querySelector('.gift');
  var name = document.getElementById('wrap-preview-name');
  function show(r){
    if (!r || !r.value) { preview.hidden = true; return; }
    preview.hidden = false;
    var price = '+' + r.dataset.price.replace(/\.00$/, '') + ' ₼';
    name.innerHTML = '';
    name.appendChild(document.createTextNode(r.dataset.name + ' · ' + price));
    var hint = document.createElement('small');
    hint.textContent = 'Hər tərəfdən baxmaq üçün klikləyin';
    name.appendChild(hint);
    /* the viewer reads the wrap from here */
    ['pattern', 'ribbon', 'color', 'scale'].forEach(function(k){ box.dataset[k] = r.dataset[k]; });
    preview.dataset.name = r.dataset.name;
    preview.dataset.price = price;
    NefisGift.paint(box, { pattern: r.dataset.pattern, ribbon: r.dataset.ribbon, color: r.dataset.color, scale: parseFloat(r.dataset.scale) || 0.5 });
  }
  document.querySelectorAll('input[name="wrapping_id"]').forEach(function(r){
    r.addEventListener('change', function(){ if (r.checked) show(r); });
  });
  show(document.querySelector('input[name="wrapping_id"]:checked'));
})();

/* "Nümunəyə bax": the window with the sketches of a good and a bad shot. */
(function(){
  var modal = document.getElementById('photo-guide-modal');
  if (!modal) return;
  var last = null;
  function open(btn){ last = btn; modal.hidden = false; document.body.style.overflow = 'hidden'; }
  function close(){ modal.hidden = true; document.body.style.overflow = ''; if (last) last.focus(); }

  document.querySelectorAll('[data-photo-guide]').forEach(function(btn){
    btn.addEventListener('click', function(){ open(btn); });
  });
  document.getElementById('photo-guide-close').addEventListener('click', close);
  modal.addEventListener('click', function(e){ if (e.target === modal) close(); });
  document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && !modal.hidden) close(); });
})();

/* The Polaroid letter: switched on, its fields open and the Polaroid follows them. */
(function(){
  var on = document.getElementById('letter-on');
  if (!on) return;
  var fields = document.getElementById('letter-fields');
  var file = document.getElementById('letter-photo');
  var name = document.getElementById('letter-photo-name');
  NefisPolaroid.bind(document.getElementById('letter-preview'), file, document.getElementById('letter-text'));
  on.addEventListener('change', function(){ fields.hidden = !on.checked; });
  file.addEventListener('change', function(){
    var f = file.files && file.files[0];
    name.textContent = f ? '📷 ' + f.name : '📷 Şəkil (istəyə görə)';
  });
})();

/* The live photo: switched on, the video field opens. On sending, the box's
   design is drawn, prepared for the camera and sent along with the video. */
(function(){
  var on = document.getElementById('ar-on');
  if (!on) return;
  var MAX = {{ \App\Support\LiveMaterials::videoMb() }} * 1024 * 1024;
  var form = document.getElementById('customize-form');
  var fields = document.getElementById('ar-fields');
  var file = document.getElementById('ar-video');
  var name = document.getElementById('ar-video-name');
  var photo = document.getElementById('ar-photo');
  var mind = document.getElementById('ar-mind');
  var btn = document.getElementById('add-to-cart-btn');
  var label = name.textContent;
  function toggle(){
    fields.hidden = !on.checked;
    file.required = on.checked;
    if (on.checked) NefisLive.preload().catch(function(){});
  }
  on.addEventListener('change', toggle);
  toggle();
  file.addEventListener('change', function(){
    var f = file.files && file.files[0];
    name.textContent = f ? '🎬 ' + f.name + (f.size > MAX ? ' — {{ \App\Support\LiveMaterials::videoMb() }} MB-dan böyükdür!' : '') : label;
  });

  form.addEventListener('submit', function(e){
    if (e.defaultPrevented || !on.checked || form.dataset.live) return;
    var f = file.files && file.files[0];
    if (f && f.size > MAX) { e.preventDefault(); file.focus(); name.scrollIntoView({ behavior: 'smooth', block: 'center' }); return; }
    e.preventDefault();
    var text = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Canlı şəkil hazırlanır…';
    var go = function(){ form.dataset.live = '1'; btn.textContent = 'Göndərilir…'; form.submit(); };
    var design;
    try { design = window.nefisDesign(); } catch (err) { go(); return; }
    NefisLive.toBlob(NefisLive.flatten(design, 2000), 'image/jpeg', 0.9)
      .then(function(b){ NefisLive.attach(photo, b, 'design.jpg'); })
      .then(function(){ return NefisLive.compile(design, function(p){ btn.textContent = 'Canlı şəkil hazırlanır… ' + p + '%'; }); })
      .then(function(blob){ NefisLive.attach(mind, blob, 'target.mind'); })
      /* Whatever this browser could not do, the shop does by hand. */
      .then(go, go);
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
    var wrapPick = document.querySelector('input[name="wrapping_id"]:checked');
    var wrap = wrapPick && wrapPick.value ? parseFloat(wrapPick.dataset.price) || 0 : 0;
    var wrapRow = document.getElementById('sum-wrap-row');
    if (wrapRow) {
      wrapRow.hidden = !(wrapPick && wrapPick.value);
      document.getElementById('sum-wrap-name').textContent = wrapPick && wrapPick.value ? 'Qablaşdırma: ' + wrapPick.dataset.name : 'Qablaşdırma';
      document.getElementById('sum-wrap').textContent = fmt(wrap);
    }
    var n = Math.max(1, parseInt(qty.value, 10) || 1);
    if (chocOut) chocOut.textContent = picked ? fmt(choc) : 'seçilməyib';
    if (chocName) chocName.textContent = picked ? picked.dataset.name : 'Şokolad';
    var letterOn = document.getElementById('letter-on');
    var letterRow = document.getElementById('sum-letter-row');
    var letter = letterOn && letterOn.checked && letterRow ? parseFloat(letterRow.dataset.price) || 0 : 0;
    if (letterRow) letterRow.hidden = !(letterOn && letterOn.checked);
    var arOn = document.getElementById('ar-on');
    var arRow = document.getElementById('sum-ar-row');
    var ar = arOn && arOn.checked && arRow ? parseFloat(arRow.dataset.price) || 0 : 0;
    if (arRow) arRow.hidden = !(arOn && arOn.checked);
    var each = box + choc + wrap + letter + ar;
    totalOut.textContent = each > 0 ? fmt(each * n) + (n > 1 ? ' (' + n + ' × ' + fmt(each) + ')' : '') : '—';
  }
  document.querySelectorAll('input[name="chocolate_id"], input[name="wrapping_id"], #letter-on, #ar-on').forEach(function(r){ r.addEventListener('change', update); });
  qty.addEventListener('input', update);
  update();
})();
</script>
@endsection
