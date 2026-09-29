@extends('layouts.app')

@php
  $seoImage = \App\Support\Media::url($product->catalogImage());
  // Google shows about 155 characters. Str::limit() used to cut at an exact
  // count, mid-word, and then the sentence below was glued on top — so every
  // design page offered a broken word and lost its delivery promise off the
  // end. Cut on a space, and only as far as the tail leaves room for.
  $seoTail = ' ' . __('Şəklinizi və sözlərinizi əlavə edin, Bakıda çatdırılma.');
  $seoIntro = \App\Support\Seo::snippet($product->tr('description'), 155 - mb_strlen($seoTail));
  $seoText = $seoIntro !== ''
      ? $seoIntro . $seoTail
      : '«' . $product->tr('name') . '» ' . __('dizaynında fərdi şokolad qutusu: şəklinizi və sözlərinizi əlavə edin, önizləməni dərhal görün')
        . ($product->price ? ', ' . \App\Support\Price::format($product->price) . '-dan' : '')
        . '. ' . __('Ad günü və sevdiklərinizə hədiyyə, Bakıda çatdırılma.');

  // What a box actually costs: the box plus the cheapest bar that has to go
  // in it. The offer says the same, so the marked-up price is one a customer
  // can really pay.
  $chocFrom = $chocolates->pluck('price')->filter(fn ($p) => $p > 0);
  $priceFrom = $product->price ? (float) $product->price + (float) ($chocFrom->min() ?? 0) : null;
  $priceTo = $product->price ? (float) $product->price + (float) ($chocFrom->max() ?? 0) : null;
@endphp
@section('title', $product->tr('name') . ', ' . __('şəkilli şokolad qutusu') . ' | Nefis')
@section('meta_description', $seoText)
@if($seoImage)
  @section('og_image', $seoImage)
@endif
@section('og_type', 'product')

@push('head')
  {{-- The first view's own pictures. Their addresses are otherwise buried in
       a data block near the end of a very long document, so nothing starts
       fetching them until the whole page has been read. --}}
  @php
    $firstView = $viewData[0] ?? null;
    $firstArt = array_values(array_filter(array_unique([
        $firstView['bg'] ?? null,
        $firstView['url'] ?? null,
        ...array_map(fn ($l) => $l['url'] ?? null, array_merge(
            $firstView['layers']['below'] ?? [], $firstView['layers']['above'] ?? [])),
    ])));
  @endphp
  @foreach(array_slice($firstArt, 0, 3) as $art)
    <link rel="preload" as="image" fetchpriority="high" href="{{ $art }}">
  @endforeach
  {{-- The faces the designs are lettered in; a slot without a file of its own
       is drawn in one of these by name. --}}
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,500&family=Inter:wght@400;500;600;700&family=Great+Vibes&family=Poppins:wght@600&family=Titan+One&family=Bungee&family=Fredoka:wght@500;600&family=Sacramento&family=Creepster&family=Source+Sans+3:wght@400;600&family=Orbitron:wght@600;800&family=Anton&family=Cinzel:wght@400;700&family=Bangers&family=Luckiest+Guy&family=Oswald:wght@500;700&family=Bevan&family=Archivo+Black&family=Caveat:wght@600&family=Pacifico&family=Montserrat:wght@300;500&display=swap" rel="stylesheet">
@endpush


@push('jsonld')
  {{ \App\Support\Seo::jsonLd(['@graph' => array_values(array_filter([
      array_filter([
          '@type' => 'Product',
          'name' => $product->tr('name') . ', ' . __('şəkilli şokolad qutusu'),
          'image' => $seoImage ? [$seoImage] : null,
          '@id' => lroute('products.customize', $product->slug) . '#product',
          'description' => $product->tr('description') ? (string) $product->tr('description') : $seoText,
          'sku' => 'nefis-' . $product->id,
          'category' => __('Fərdi şokolad qutusu'),
          'brand' => ['@type' => 'Brand', 'name' => 'Nefis'],
          // A bar always goes inside, so the box alone is not a price anyone
          // pays: what is offered is a range, from the cheapest bar to the
          // dearest.
          'offers' => $priceFrom ? [
              '@type' => 'AggregateOffer',
              'url' => lroute('products.customize', $product->slug),
              'priceCurrency' => 'AZN',
              'lowPrice' => number_format($priceFrom, 2, '.', ''),
              'highPrice' => number_format((float) $priceTo, 2, '.', ''),
              'offerCount' => max(1, $chocFrom->count()),
              'availability' => 'https://schema.org/InStock',
              'itemCondition' => 'https://schema.org/NewCondition',
              'seller' => ['@id' => url('/') . '#store'],
              // Google asks every offer these two as well.
              'shippingDetails' => \App\Support\Seo::shipping(),
              'hasMerchantReturnPolicy' => \App\Support\Seo::returns(),
          ] : null,
      ]),
      \App\Support\Seo::breadcrumbs([
          [__('Ana səhifə'), lroute('home')],
          [__('Dizaynlar'), lroute('designs.index')],
          [$product->tr('name'), lroute('products.customize', $product->slug)],
      ]),
  ]))]) }}
@endpush

@php
  $photoSlots = $product->photoSlots;
  // Does this design cut faces out? The preview turns between the front and
  // the other angles, so a cut-out window on any of them counts.
  $cutsFaces = $photoSlots->contains(fn ($slot) => $slot->cutout)
      || $product->angles->contains(fn ($angle) => $angle->photoSlots->contains(fn ($slot) => $slot->cutout));
  $textSlots = $product->textSlots;
@endphp

@section('page_style')
  .slot-block [hidden]{ display:none !important; }
  .slot-block{ border-top:1px solid var(--line); padding-top:1.25rem; }
  .slot-block:first-of-type{ border-top:none; padding-top:0; }
  .slot-block .zoom-row{ margin-top:.75rem; }
  .spotify-pick{ border-top:1px solid var(--line); padding-top:1.25rem; }
  .spotify-pick .opt{ font-weight:400; opacity:.6; font-size:.85em; }
  .spotify-pick input{ width:100%; }
  .spotify-pick .slot-hint.bad{ color:#c2410c; }
  /* The song, shown the way the box will wear it: the code on its own white
     tile, the fire of the rest of the page around the card. */
  .spotify-shown{ position:relative; isolation:isolate; margin-top:.85rem; padding:.85rem .95rem 1rem;
    border-radius:1rem; display:flex; flex-direction:column; gap:.6rem;
    background:linear-gradient(150deg, rgba(29,185,84,.10), rgba(255,132,1,.10) 60%, transparent),
               var(--card, rgba(255,255,255,.04)); animation:sp-in .45s var(--ease, ease) both; }
  .spotify-shown[hidden]{ display:none; }
  @keyframes sp-in{ from{ opacity:0; transform:translateY(.4rem) scale(.985); } }
  .sp-top{ display:flex; align-items:center; gap:.5rem; font-size:.9rem; }
  .sp-mark{ width:1.35rem; height:1.35rem; flex:none; }
  .sp-ok{ opacity:.65; font-size:.82rem; }
  .sp-open{ margin-left:auto; font-size:.82rem; font-weight:600; text-decoration:underline;
    background:var(--flame-grad); -webkit-background-clip:text; background-clip:text; color:transparent;
    text-decoration-color:#F86E17; }
  .sp-tile{ background:#fff; border-radius:.7rem; padding:.55rem .7rem; display:flex; justify-content:center;
    box-shadow:0 10px 24px -16px rgba(0,0,0,.75); }
  .sp-tile img{ display:block; width:100%; max-width:18rem; height:auto; }
  .sp-note{ font-size:.8rem; opacity:.72; line-height:1.4; }
  @media (prefers-reduced-motion:reduce){ .spotify-shown{ animation:none; } }
  .slot-block .rotate-row{ margin-top:.5rem; }
  .flip-btn[aria-pressed="true"]{ background:var(--flame-grad, #F86E17); color:#fff; border-color:transparent; }
  .rotate-reset{
    flex:none; width:2rem; height:2rem; border-radius:50%; border:1px solid var(--line);
    background:var(--paper); color:var(--cocoa); font-size:.9rem; line-height:1;
  }
  .rotate-reset:hover{ border-color:var(--gold); }
  .slot-hint{ font-size:.8125rem; color:var(--cocoa-soft); margin-top:.5rem; }
  .add-hint{ font-size:.8125rem; color:var(--cocoa-soft); text-align:center; margin:.6rem 0 0; }
  .add-hint.bad{ color:#c2410c; font-weight:600; }
  .lead-note{ display:flex; gap:.75rem; align-items:flex-start; margin-bottom:1.25rem; padding:.85rem 1rem;
    border:1px solid rgba(250,117,18,.35); border-radius:.9rem;
    background:linear-gradient(120deg, rgba(255,132,1,.12) 0%, rgba(240,84,32,.06) 60%, transparent 100%); }
  .lead-note .ln-ico{ flex:none; width:2rem; height:2rem; display:grid; place-items:center; border-radius:.65rem;
    background:var(--flame-grad); color:#fff; font-size:1rem; }
  .lead-note p{ margin:0; font-size:.8125rem; line-height:1.5; color:var(--cocoa-soft); }
  .lead-note b{ color:var(--cocoa); }
  .lead-note a{ color:var(--flame-2); font-weight:600; text-decoration:underline; }
  .angle-thumb canvas{ width:100%; height:100%; object-fit:cover; display:block; }
  textarea.text-input{ resize:vertical; }
  .wrap-block{ display:flex; flex-direction:column; gap:.6rem; }
  .wrap-open .wrap-none{ margin-top:.5rem; }
  .wrap-none{ display:flex; align-items:center; justify-content:space-between; gap:.75rem; padding:.7rem .9rem; margin:0;
    border:1.5px solid var(--line); border-radius:.9rem; background:linear-gradient(140deg, var(--paper), var(--cream-2));
    cursor:pointer; font-weight:600; font-size:.875rem; position:relative;
    transition:border-color .25s, box-shadow .25s, transform .25s var(--ease); }
  .wrap-none:hover{ border-color:rgba(250,117,18,.45); transform:translateY(-1px); }
  .wrap-none b{ font-size:.8rem; color:var(--cocoa-soft); font-weight:600; }
  .wrap-none input, .wrap-swatch input{ position:absolute; opacity:0; pointer-events:none; }
  .wrap-none:has(input:checked){ border-color:var(--flame); background:linear-gradient(140deg, #FFF7EF, #FFE9D6);
    box-shadow:0 0 0 3px rgba(250,117,18,.18), 0 12px 24px -18px var(--flame-shadow); }
  .wrap-open{ margin:0; }
  .wrap-open > summary{ list-style:none; cursor:pointer; display:flex; align-items:center; gap:.6rem;
    padding:.7rem .9rem; border:1.5px solid rgba(250,117,18,.5); border-radius:.9rem; font-weight:600; font-size:.875rem;
    color:var(--cocoa); background:linear-gradient(140deg, #FFF7EF, #FFE9D6); transition:background .25s, border-color .25s; }
  .wrap-open > summary > span{ flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .wrap-open > summary > b{ font-size:.8rem; font-weight:700; color:var(--flame-2); }
  .wrap-open > summary::-webkit-details-marker{ display:none; }
  .wrap-open > summary:hover{ border-color:var(--flame); }
  .wrap-open > summary svg{ width:1rem; height:1rem; transition:transform .3s var(--ease); }
  .wrap-open[open] > summary svg{ transform:rotate(180deg); }
  .wrap-group{ border:1px solid var(--line); border-radius:1rem; padding:1rem .8rem .85rem; position:relative; margin-top:.5rem;
    background:linear-gradient(150deg, var(--paper), var(--cream-2)); }
  .wrap-price{ position:absolute; top:-.7rem; right:.8rem; background:var(--flame-grad); box-shadow:0 8px 16px -10px var(--flame-shadow); color:#fff; font-weight:800; font-size:.8rem;
    padding:.15rem .6rem; border-radius:999px; }
  /* Each paper is shown as a little wrapped parcel: it stands at a slight
     angle on its own shadow, the ribbons cross in a knot, and a sheen runs
     over the paper when the cursor passes. */
  .wrap-swatches{ display:grid; grid-template-columns:repeat(auto-fill, minmax(5.4rem, 1fr)); gap:.9rem .8rem; padding-top:.2rem; }
  .wrap-swatch{ position:relative; display:flex; flex-direction:column; align-items:center; gap:.45rem; margin:0; cursor:pointer; font-weight:400;
    perspective:600px; }
  .wrap-swatch .sw{ position:relative; width:100%; aspect-ratio:3/4; border-radius:.55rem; background-size:60px auto; background-repeat:repeat;
    border:2px solid transparent; overflow:hidden; transform:rotate(-2.5deg);
    box-shadow:0 10px 18px -12px rgba(58,38,23,.55), 0 2px 4px rgba(58,38,23,.12);
    transition:transform .4s var(--ease), box-shadow .4s var(--ease), border-color .25s; }
  /* the paper's own fold: a soft light down one side */
  .wrap-swatch .sw::before{ content:''; position:absolute; inset:0; z-index:1; pointer-events:none;
    background:linear-gradient(105deg, rgba(255,255,255,.35) 0%, transparent 38%, rgba(58,38,23,.12) 100%); }
  /* the sheen that passes over it */
  .wrap-swatch .sw::after{ content:''; position:absolute; top:-30%; bottom:-30%; left:-60%; width:45%; z-index:3; pointer-events:none;
    background:linear-gradient(90deg, transparent, rgba(255,255,255,.65), transparent); transform:skewX(-18deg) translateX(0);
    transition:transform .7s var(--ease); }
  .wrap-swatch:hover .sw::after{ transform:skewX(-18deg) translateX(420%); }
  .wrap-swatch .sw i{ position:absolute; left:50%; top:0; bottom:0; width:12%; transform:translateX(-50%); background:var(--rb); opacity:.95; z-index:2; }
  .wrap-swatch .sw i::after{ content:''; position:absolute; left:-350%; right:-350%; top:42%; height:9%; background:var(--rb); }
  /* the knot where the two ribbons meet */
  .wrap-swatch .sw i::before{ content:''; position:absolute; left:50%; top:46.5%; width:230%; height:16%; transform:translate(-50%,-50%);
    border-radius:50%; background:var(--rb); box-shadow:inset 0 0 0 1px rgba(255,255,255,.45); }
  .wrap-swatch:hover .sw{ transform:rotate(1.5deg) translateY(-5px) scale(1.04);
    box-shadow:0 18px 26px -14px rgba(58,38,23,.5), 0 3px 6px rgba(58,38,23,.14); }
  .wrap-swatch:has(input:checked) .sw{ border-color:var(--flame); transform:rotate(0deg) translateY(-3px) scale(1.04);
    box-shadow:0 0 0 3px rgba(250,117,18,.25), 0 16px 26px -14px var(--flame-shadow); }
  .wrap-swatch:has(input:checked)::after{ content:'✓'; position:absolute; top:-.35rem; right:-.15rem; width:1.35rem; height:1.35rem; border-radius:50%;
    background:var(--flame-grad); color:#fff; font-size:.75rem; display:grid; place-items:center; z-index:4;
    box-shadow:0 6px 14px -8px var(--flame-shadow), 0 0 0 2px var(--paper); }
  .wrap-swatch:has(input:focus-visible) .sw{ outline:2px solid var(--gold); outline-offset:2px; }
  .wrap-swatch .nm{ font-size:.74rem; line-height:1.25; text-align:center; color:var(--cocoa-soft); transition:color .25s; }
  .wrap-swatch:hover .nm, .wrap-swatch:has(input:checked) .nm{ color:var(--cocoa); font-weight:600; }
  .wrap-preview{ border:1px solid var(--line); border-radius:.9rem; padding:.5rem .75rem 1rem; background:radial-gradient(ellipse at 50% 30%, var(--cream-2), transparent 70%); }
  .wrap-preview[hidden]{ display:none; }
  .wrap-preview{ cursor:zoom-in; }
  .letter-block{ position:relative; overflow:hidden; border:1px solid var(--line); border-radius:1rem; padding:.85rem .95rem;
    background:linear-gradient(150deg, var(--paper), var(--cream-2));
    transition:border-color .25s, box-shadow .3s, transform .3s var(--ease); }
  .letter-block:hover{ border-color:rgba(250,117,18,.4); transform:translateY(-1px); }
  .letter-block:has(.letter-toggle input:checked){ border-color:rgba(250,117,18,.55);
    box-shadow:0 14px 30px -22px var(--flame-shadow); }
  .letter-toggle{ display:flex; align-items:center; gap:.6rem; margin:0; cursor:pointer; font-weight:600; font-size:.9rem; }
  /* The shop's own tick: a soft square that fills with the logo's orange. */
  .letter-toggle input{ appearance:none; -webkit-appearance:none; position:relative; flex:none; margin:0; cursor:pointer;
    width:1.4rem; height:1.4rem; border-radius:.5rem; border:1.5px solid var(--line); background:var(--paper);
    transition:background .25s, border-color .25s, box-shadow .25s, transform .2s var(--ease); }
  .letter-toggle input:hover{ border-color:var(--flame); transform:scale(1.05); }
  .letter-toggle input::after{ content:''; position:absolute; left:50%; top:45%; width:.34rem; height:.66rem;
    border:solid #fff; border-width:0 2px 2px 0; transform:translate(-50%,-55%) rotate(45deg) scale(.4); opacity:0;
    transition:opacity .18s, transform .3s var(--ease); }
  .letter-toggle input:checked{ background:var(--flame-grad); border-color:transparent;
    box-shadow:0 8px 16px -9px var(--flame-shadow); }
  .letter-toggle input:checked::after{ opacity:1; transform:translate(-50%,-55%) rotate(45deg) scale(1); }
  .letter-toggle input:focus-visible{ outline:2px solid var(--flame); outline-offset:2px; }
  .letter-toggle span{ flex:1; }
  .letter-toggle b{ font-size:.8rem; font-weight:800; color:#fff; background:var(--flame-grad); padding:.15rem .5rem; border-radius:999px;
    box-shadow:0 6px 14px -8px var(--flame-shadow); }
  .letter-fields{ display:grid; grid-template-columns:7.5rem 1fr; gap:1rem; margin-top:.9rem; align-items:start; }
  .letter-fields[hidden]{ display:none; }
  .letter-mini .polaroid{ max-width:7.5rem; }
  .letter-inputs{ display:flex; flex-direction:column; gap:.5rem; min-width:0; }
  .letter-file{ display:block; border:1.5px dashed rgba(250,117,18,.45); border-radius:.75rem; padding:.65rem; text-align:center; cursor:pointer;
    background:rgba(255,236,219,.45); transition:border-color .25s, background .25s;
    font-weight:600; font-size:.85rem; margin:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .letter-file:hover{ border-color:var(--flame); background:rgba(255,236,219,.85); }
  /* Out of sight but still a control: display:none cannot be focused, and a
     browser that cannot point at a required field refuses the form in silence. */
  .letter-file input{ position:absolute; width:1px; height:1px; opacity:0; pointer-events:none; }
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
  /* Folded: six bars on a phone, nine on a wide screen, the rest behind the button. */
  .choc-group:not(.open) .choc-grid > .choc-card:nth-child(n+7){ display:none; }
  @media (min-width:700px){ .choc-group:not(.open) .choc-grid > .choc-card:nth-child(n+10){ display:none; } }
  .choc-more{ display:flex; align-items:center; justify-content:center; gap:.4rem; width:100%; margin-top:.6rem;
    padding:.6rem 1rem; border:1.5px solid var(--line); border-radius:999px; background:var(--paper);
    color:var(--cocoa); font:inherit; font-size:.875rem; font-weight:600; cursor:pointer;
    transition:border-color .2s, background .2s; }
  .choc-more:hover{ border-color:rgba(250,117,18,.55); }
  .choc-more b{ font-weight:700; color:var(--cocoa-soft); }
  .choc-more svg{ width:1.05rem; height:1.05rem; transition:transform .25s var(--ease); }
  .choc-group.open .choc-more svg{ transform:rotate(180deg); }
  .choc-group.open .choc-more .m, .choc-group:not(.open) .choc-more .l{ display:none; }
  .choc-card{ position:relative; display:flex; flex-direction:column; gap:.3rem; padding:.6rem; border:1.5px solid var(--line); border-radius:.9rem;
    background:var(--paper); cursor:pointer; margin:0; font-weight:400;
    transition:border-color .25s, box-shadow .3s, transform .3s var(--ease); }
  .choc-card:hover{ border-color:rgba(250,117,18,.55); transform:translateY(-2px);
    box-shadow:0 0 0 3px rgba(250,117,18,.12), 0 12px 24px -16px var(--flame-shadow); }
  .choc-card input{ position:absolute; opacity:0; pointer-events:none; }
  .choc-card:has(input:checked){ border-color:var(--flame); box-shadow:0 0 0 3px rgba(250,117,18,.2); }
  .choc-card:has(input:checked)::after{ content:'✓'; position:absolute; top:.4rem; right:.5rem; width:1.4rem; height:1.4rem; border-radius:50%;
    background:var(--flame-grad); color:#fff; font-size:.8rem; display:grid; place-items:center;
    box-shadow:0 6px 14px -8px var(--flame-shadow); }
  .choc-card:has(input:focus-visible){ outline:2px solid var(--gold); outline-offset:2px; }
  .choc-pic{ aspect-ratio:1/1; display:grid; place-items:center; border-radius:.6rem; background:#fff; overflow:hidden; font-size:2rem; }
  .choc-pic img{ width:100%; height:100%; object-fit:contain; }
  .choc-name{ font-size:.8125rem; line-height:1.3; color:var(--cocoa); }
  .choc-meta{ display:flex; justify-content:space-between; align-items:baseline; gap:.3rem; font-size:.75rem; color:var(--cocoa-soft); margin-top:auto; }
  .choc-meta b{ color:var(--gold-deep); font-size:.875rem; }
  /* The count and the hurry stand side by side. */
  .qty-row{ display:flex; flex-wrap:wrap; align-items:flex-end; gap:1rem; }
  .qty-row > div{ flex:none; }
  .qty-row input[type="number"]{ max-width:7rem; }
  .qty-row .rush-pick{ flex:1 1 15rem; min-width:0; }
  .rush-pick{ display:flex; align-items:center; gap:.75rem; margin:0; padding:.6rem .8rem; cursor:pointer;
    border:1.5px solid var(--line); border-radius:.9rem; background:linear-gradient(140deg, var(--paper), var(--cream-2));
    transition:border-color .25s, box-shadow .3s, transform .3s var(--ease); }
  .rush-pick:hover{ border-color:rgba(250,117,18,.45); transform:translateY(-1px); }
  .rush-pick input{ position:absolute; opacity:0; width:0; height:0; }
  .rush-box{ flex:none; width:2.2rem; height:2.2rem; border-radius:.7rem; display:grid; place-items:center; font-size:1rem;
    background:var(--cream-2); transition:background .25s, transform .25s var(--ease), box-shadow .25s; }
  .rush-text{ flex:1; min-width:0; }
  .rush-text b{ display:block; font-size:.875rem; }
  .rush-text small{ display:block; font-size:.75rem; color:var(--cocoa-soft); margin-top:.1rem; }
  .rush-fee{ flex:none; font-weight:800; font-size:.75rem; color:#fff; background:var(--flame-grad); padding:.2rem .55rem; border-radius:999px; }
  .rush-pick:has(input:checked){ border-color:var(--flame); background:linear-gradient(140deg, #FFF7EF, #FFE9D6);
    box-shadow:0 0 0 3px rgba(250,117,18,.16); }
  .rush-pick:has(input:checked) .rush-box{ background:var(--flame-grad); transform:scale(1.05); box-shadow:0 8px 16px -10px var(--flame-shadow); }
  .rush-pick:has(input:focus-visible){ outline:2px solid var(--flame); outline-offset:2px; }
  .price-sum{ border:1px solid var(--line); border-radius:.9rem; padding:.75rem 1rem; display:flex; flex-direction:column; gap:.35rem; font-size:.9375rem; }
  .price-sum div{ display:flex; justify-content:space-between; gap:1rem; color:var(--cocoa-soft); }
  .price-sum div[hidden]{ display:none; }
  .price-sum .total{ color:var(--cocoa); font-weight:700; font-size:1.0625rem; border-top:1px solid var(--line); padding-top:.45rem; margin-top:.1rem; }
@endsection

@section('content')
<section class="page-hero" style="padding-bottom:0;">
  <div class="wrap">
    <nav class="crumbs" aria-label="{{ __('Səhifənin yeri') }}">
      <a href="{{ lroute('home') }}">{{ __('Ana səhifə') }}</a><span aria-hidden="true">›</span>
      <a href="{{ lroute('designs.index') }}">{{ __('Dizaynlar') }}</a><span aria-hidden="true">›</span>
      <span aria-current="page">{{ $product->tr('name') }}</span>
    </nav>
    <span class="eyebrow" style="justify-content:center;">{{ __('Fərdiləşdirmə') }}</span>
    <h1>{{ $product->tr('name') }}</h1>
    @if($priceFrom)
      <p class="price-from">{{ __(':price-dan', ['price' => \App\Support\Price::format($priceFrom)]) }}</p>
    @endif
    @if($product->tr('description'))
      <p class="lede" style="margin-inline:auto;">{{ $product->tr('description') }}</p>
    @endif
    @if($gifts->isNotEmpty())
      {{-- The occasions this design fits: a label, not a way out. The visitor
           has already chosen the design and is about to upload a photo; a row
           of links here only invites them to leave half-way. --}}
      <div class="occ-chips" style="margin-top:1.25rem;">
        <span style="width:100%; font-size:.8125rem; color:var(--cocoa-faint);">{{ __('Bu dizayn bu münasibətlərə uyğundur:') }}</span>
        @foreach($gifts as $gift)
          <span class="occ-chip is-static">{{ $gift->emoji }} {{ $gift->menu_label }}</span>
        @endforeach
      </div>
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
            <div class="drop-hint" id="drop-hint">{{ __('Öncə') }} <span class="dh-wide">{{ __('sağdan') }}</span><span class="dh-narrow">{{ __('aşağıdan') }}</span>&nbsp;{{ __('şəklinizi yükləyin') }}</div>
          @endif
          @if(count($viewData) > 1)
            <button type="button" class="angle-arrow prev" id="angle-prev" aria-label="{{ __('Əvvəlki görünüş') }}">‹</button>
            <button type="button" class="angle-arrow next" id="angle-next" aria-label="{{ __('Sonrakı görünüş') }}">›</button>
          @endif
          @include('partials.preview-mark')
        </div>
        @if(count($viewData) > 1)
          <div class="angle-thumbs" id="angle-thumbs">
            @foreach($viewData as $view)
              <button type="button" class="angle-thumb{{ $loop->first ? ' active' : '' }}" data-angle="{{ $loop->index }}"
                      title="{{ $view['label'] ?? $product->tr('name') }}" aria-label="{{ $view['label'] ?? $product->tr('name') }}">
                @if(array_key_exists('scene', $view))
                  {{-- Drawn live, so every thumbnail shows the customer's own box. --}}
                  <canvas></canvas>
                @else
                  <img src="{{ $view['bg'] ?: $view['url'] }}" alt="{{ $view['label'] ?? $product->tr('name') }}">
                @endif
              </button>
            @endforeach
          </div>
        @endif
      </div>

      <form class="customize-panel" method="POST" action="{{ lroute('cart.add') }}" enctype="multipart/form-data" id="customize-form">
        @csrf
        <input type="hidden" name="product_id" value="{{ $product->id }}">

        {{-- Said before the first field, not after the money: how long the box
             takes and what jumping the queue costs. --}}
        @include('partials.lead-note')

        @php $photoNo = 0; @endphp
        @foreach($photoSlots as $index => $slot)
          @continue($slot->isSky())
          @php $photoNo++; @endphp
          <div class="slot-block" data-slot="{{ $index }}">
            <label>{{ $photoNo }}. {{ $slot->label ? __($slot->tr('label')) : __('Şəkil') }}</label>
            {{-- Drawn, not described: what the shot has to look like. --}}
            @include('partials.photo-guide', ['small' => true])
            <label class="upload-box" for="photo-input-{{ $index }}">
              <div class="ico">@include('partials.camera-icon')</div>
              <div class="upload-label">{{ __('Şəkil seçmək üçün klikləyin') }}</div>
            </label>
            <input type="hidden" name="photo_frames[{{ $index }}]" class="photo-frame">
            <input type="file" class="photo-input" id="photo-input-{{ $index }}" name="photos[{{ $index }}]"
                   accept="image/jpeg,image/png,image/webp" required style="display:none;">
            <div class="range-row zoom-row" hidden>
              <span class="lbl">{{ __('Yaxınlaşdır') }}</span>
              <input type="range" class="zoom-range" min="50" max="500" value="100">
            </div>
            <div class="range-row rotate-row" hidden>
              <span class="lbl">{{ __('Fırlat') }}</span>
              <input type="range" class="rotate-range" min="-180" max="180" value="0">
              <button type="button" class="rotate-reset" title="{{ __('Sıfırla') }}">↺</button>
              {{-- A face cut from a photo often looks the wrong way round on a
                   drawn body: this turns it over without touching anything else. --}}
              <button type="button" class="flip-btn" aria-pressed="false" title="{{ __('Güzgü') }}">⇄</button>
            </div>
            <p class="slot-hint" hidden>{{ __('Şəkli önizləmədə sürükləyərək mövqeyini dəyişə bilərsiniz.') }}</p>
            @if($slot->cutout)
              <button type="button" class="btn btn-ghost fix-bg" hidden>{{ __('Fonu düzəlt') }}</button>
            @endif
          </div>
        @endforeach

        @php $seenLinks = []; @endphp
        @foreach($textSlots as $index => $slot)
          @if($slot->isAuto())
            {{-- The star map already knows this one — the coordinates of the
                 place, or the date of the night. Asking the customer to copy
                 it out of the page would only invite a typo. --}}
            <input type="hidden" class="text-input" data-auto="{{ $slot->auto }}"
                   name="custom_texts[{{ $index }}]"
                   value="{{ \App\Support\Sky::caption($slot->auto, $skyStart ?? null) }}">
          @elseif($slot->fixed)
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
              <label for="text-input-{{ $index }}">{{ $slot->label ? __($slot->tr('label')) : __('Mətn') }}</label>
              @if($slot->isTime())
                {{-- Four digits; the colon is put in as the customer types. --}}
                <input type="text" class="text-input time-input" id="text-input-{{ $index }}" name="custom_texts[{{ $index }}]"
                       @if($slot->link_key) data-link="{{ $slot->link_key }}" data-link-lead @endif
                       inputmode="numeric" maxlength="5" pattern="[0-9]{2}:[0-5][0-9]" required
                       placeholder="{{ __('dəq:san (məs. 03:45)') }}" title="{{ __('dəq:san, məs. 03:45') }}"
                       value="{{ old('custom_texts.' . $index, $slot->default_value) }}">
              @elseif($slot->max_lines > 1 || str_contains((string) $slot->default_value, "\n"))
                {{-- Room for more than one line, so Enter breaks the line here too.
                     A text input would quietly drop the line breaks. --}}
                @php $value = old('custom_texts.' . $index, $slot->default_value); @endphp
                <textarea class="text-input" id="text-input-{{ $index }}" name="custom_texts[{{ $index }}]"
                          @if($slot->link_key) data-link="{{ $slot->link_key }}" data-link-lead @endif
                          maxlength="{{ $slot->limit() }}"
                          rows="{{ min(4, max(2, substr_count((string) $value, "\n") + 1)) }}"
                          placeholder="{{ $slot->placeholder ? __($slot->tr('placeholder')) : __('Məs. Ad Soyad və ya qısa mesaj') }}">{{ $value }}</textarea>
                <p class="slot-hint">{{ __('Yeni sətir üçün Enter basın.') }}</p>
              @else
                <input type="text" class="text-input" id="text-input-{{ $index }}" name="custom_texts[{{ $index }}]"
                       @if($slot->link_key) data-link="{{ $slot->link_key }}" data-link-lead @endif
                       maxlength="{{ $slot->limit() }}"
                       placeholder="{{ $slot->placeholder ? __($slot->tr('placeholder')) : __('Məs. Ad Soyad və ya qısa mesaj') }}"
                       value="{{ old('custom_texts.' . $index, $slot->default_value) }}">
              @endif
            </div>
          @endif
        @endforeach

        @if(\App\Support\Sky::wanted($product))
          {{-- The night itself: a date, an hour and a place. The wording that
               goes under the stars is an ordinary caption field of the design,
               so the owner keeps control of how it is set. --}}
          <div class="sky-block" id="sky-block">
            <label>{{ __('Ulduz xəritəsi') }}</label>
            <p class="slot-hint" style="margin-bottom:.6rem;">{{ __('O gecə, o yerin üstündəki səma qutunun üzərinə düşəcək.') }}</p>
            <div class="sky-row">
              <div>
                <label for="star-date" class="sky-lbl">{{ __('Tarix') }}</label>
                <input type="date" id="star-date" name="star_date" class="text-input" required
                       max="{{ now()->addYears(1)->toDateString() }}" min="1900-01-01"
                       value="{{ old('star_date', now()->toDateString()) }}">
              </div>
              <div>
                <label for="star-time" class="sky-lbl">{{ __('Saat') }}</label>
                <input type="time" id="star-time" name="star_time" class="text-input" required
                       value="{{ old('star_time', '21:00') }}">
              </div>
            </div>
            <div class="sky-row" style="margin-top:.6rem;">
              <div>
                <label for="star-city" class="sky-lbl">{{ __('Yer') }}</label>
                <select id="star-city" class="text-input">
                  @foreach(\App\Support\Sky::places() as $place)
                    <option value="{{ $place['lat'] }},{{ $place['lon'] }}" @selected(old('star_place', 'Bakı') === $place['name'])>{{ $place['name'] }}</option>
                  @endforeach
                  <option value="other">{{ __('Başqa yer — koordinatlarla') }}</option>
                </select>
              </div>
              <div id="sky-own" hidden>
                <label class="sky-lbl">{{ __('Enlik, uzunluq') }}</label>
                <input type="text" id="star-own" class="text-input" inputmode="decimal" placeholder="38.79, 48.48">
              </div>
            </div>
            <input type="hidden" name="star_lat" id="star-lat" value="40.3777">
            <input type="hidden" name="star_lon" id="star-lon" value="49.8920">
            <input type="hidden" name="star_place" id="star-place" value="Bakı">
            <p class="slot-hint" id="sky-coords" style="margin-top:.5rem;"></p>
          </div>
        @endif

        @if($product->spotify_code)
          {{-- A song on the box. The customer pastes the link Spotify gave him
               and sees, right here, the code that will be printed — so a wrong
               link is caught by him, not by us at the press. Optional: a box
               without a song is still a box. --}}
          <div class="spotify-pick">
            <label for="spotify-uri">{{ __('Spotify mahnısı') }}
              <span class="opt">{{ __('istəyə bağlı') }}</span></label>
            <input type="text" id="spotify-uri" name="spotify_uri" inputmode="url" autocomplete="off"
                   spellcheck="false" placeholder="https://open.spotify.com/track/…"
                   value="{{ old('spotify_uri') }}">
            <p class="slot-hint" id="spotify-hint">{{ __('Spotify-da mahnını açın → Paylaş → Linki kopyala, sonra bura yapışdırın.') }}</p>
            <div class="spotify-shown" id="spotify-shown" hidden>
              <div class="sp-top">
                <svg class="sp-mark" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="12" fill="#1DB954"/><path fill="#fff" d="M17.2 10.9a.94.94 0 0 1-1.29.31c-2.5-1.53-5.65-1.87-9.36-1.03a.94.94 0 1 1-.42-1.83c4.06-.93 7.55-.53 10.36 1.19.44.27.58.85.31 1.36Zm-1.4 2.85a.78.78 0 0 1-1.08.26c-2.14-1.32-5.4-1.7-7.93-.93a.78.78 0 1 1-.45-1.5c2.89-.88 6.48-.45 8.94 1.06.37.23.49.71.26 1.11Zm-1.25 2.75a.63.63 0 0 1-.86.21c-1.87-1.14-4.22-1.4-6.99-.77a.63.63 0 1 1-.28-1.22c3.03-.69 5.63-.39 7.73.89.3.18.39.57.21.86Z"/></svg>
                <b id="spotify-kind">{{ __('Mahnı') }}</b>
                <span class="sp-ok">{{ __('hazırdır') }}</span>
                <a id="spotify-open" class="sp-open" target="_blank" rel="noopener">{{ __('dinlə ↗') }}</a>
              </div>
              <div class="sp-tile">
                <img id="spotify-code-img" alt="{{ __('Spotify kodu') }}" width="320" height="80">
              </div>
              <span class="sp-note">{{ __('Bu kod qutunun üstündə çap olunacaq — telefonun kamerası ilə oxunur.') }}</span>
            </div>
          </div>
        @endif

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
            <label>{{ __('Qutunun içindəki şokolad') }}</label>
            @if($brands->count() > 1)
              <div class="choc-brands" aria-label="{{ __('Marka seçin') }}">
                @foreach($brands as $brand => $bars)
                  <button type="button" class="choc-brand{{ in_array($brand, $top, true) ? ' top' : '' }}{{ $brand === $openBrand ? ' active' : '' }}{{ $picked && $picked['brand'] === $brand ? ' has-pick' : '' }}"
                          data-brand="{{ $brand }}" aria-pressed="{{ $brand === $openBrand ? 'true' : 'false' }}">{{ $brand === \App\Support\ChocolateBrand::OTHER ? __($brand) : $brand }} <span>{{ $bars->count() }}</span></button>
                @endforeach
              </div>
            @endif
            <div role="radiogroup" aria-label="{{ __('Şokolad seçin') }}">
              @foreach($brands as $brand => $bars)
                {{-- A brand with many bars fills a phone screen on its own, so only the
                     first few show and the rest wait behind one button. The group opens
                     by itself when the bar already chosen is further down the list. --}}
                @php
                  $pickedHere = $picked && $picked['brand'] === $brand
                      ? $bars->values()->search(fn ($b) => $b['id'] === $picked['id'])
                      : false;
                @endphp
                <div class="choc-group{{ $pickedHere !== false && $pickedHere >= 6 ? ' open' : '' }}" data-brand="{{ $brand }}" @if($brand !== $openBrand) hidden @endif>
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
                  @if($bars->count() > 6)
                    <button type="button" class="choc-more">
                      <span class="m">{{ __('Hamısını göstər') }} <b>{{ $bars->count() }}</b></span>
                      <span class="l">{{ __('Yığ') }}</span>
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                    </button>
                  @endif
                </div>
              @endforeach
            </div>
            <p class="choc-error" id="choc-error" role="alert" @unless($errors->has('chocolate_id')) hidden @endunless>{{ $errors->first('chocolate_id') ?: __('Qutunun içinə şokolad seçin.') }}</p>
          </div>
        @endif

        @if($wrappings->isNotEmpty())
          {{-- Gift wrap: swatches of paper, grouped by price. Picking one shows the box wrapped in it. --}}
          <div class="wrap-block" id="wrap-block">
            <label>{{ __('Hədiyyə qablaşdırması') }}</label>
            {{-- One row, not two: it says what is chosen and opens the papers. --}}
            <details class="wrap-open" @if(old('wrapping_id')) open @endif>
              <summary>
                {{-- The row says what is chosen, open or closed. --}}
                <span id="wrap-chosen">{{ __('Qablaşdırmasız') }}</span>
                {{-- Nothing chosen costs nothing: the row says so by staying empty. --}}
                <b id="wrap-chosen-price"></b>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
              </summary>
              <label class="wrap-none">
                <input type="radio" name="wrapping_id" value="" data-price="0" data-name="" @checked(! old('wrapping_id'))>
                <span>{{ __('Qablaşdırmasız') }}</span><b></b>
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
            </details>
            {{-- The box as it will be handed over, in the paper just picked. --}}
            <div class="wrap-preview" id="wrap-preview" data-gift-open title="{{ __('Hər tərəfdən bax') }}" hidden>
              @include('partials.gift-box', ['wrap' => null])
              <p class="wrap-preview-name" id="wrap-preview-name"></p>
              @include('partials.preview-mark')
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
                  <span id="letter-photo-name">📷 {{ __('Şəkil (istəyə görə)') }}</span>
                </label>
                <textarea name="letter_text" id="letter-text" rows="3" maxlength="{{ \App\Support\Letter::maxLength() }}"
                          placeholder="{{ __('Məktubun mətni (istəyə görə)') }}">{{ old('letter_text') }}</textarea>
                <p class="slot-hint">{{ __('Şəkil olmasa, mətn polaroidin içində yazılır.') }}</p>
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
              <span>🎬 {{ __('Canlı şəkil (AR), qutu telefonda canlanır') }}</span>
              <b>+{{ \App\Support\Price::format(\App\Support\LiveMaterials::price()) }}</b>
            </label>
            <div id="ar-fields" @unless($arOn) hidden @endunless style="margin-top:.8rem;">
              <p class="slot-hint" style="margin:0 0 .6rem;">{{ __('Qutuya QR kod çap edirik. Hədiyyəni alan QR kodu oxudub telefonu qutunun şəklinə tutanda, sizin videonuz şəklin üstündə oynayır, tətbiq yükləmədən.') }}</p>
              <label class="letter-file">
                <input type="file" name="ar_video" id="ar-video" accept="video/mp4,video/quicktime,video/webm,video/*">
                <span id="ar-video-name">🎬 {{ __('Video seçin (MP4/MOV, :mb MB-a qədər)', ['mb' => \App\Support\LiveMaterials::videoMb()]) }}</span>
              </label>
              <p class="slot-hint">{{ __('Ən yaxşısı 10–30 saniyəlik, şaquli çəkilmiş video. Qutunun dizaynı kamera üçün özü hazırlanır, "Səbətə at" basanda bir neçə saniyə çəkir.') }}</p>
              <input type="file" name="ar_photo" id="ar-photo" hidden>
              <input type="file" name="ar_mind" id="ar-mind" hidden>
            </div>
          </div>
        @endif

        <div class="qty-row">
          <div>
            <label for="quantity">{{ __('Say') }}</label>
            <input type="number" id="quantity" name="quantity" value="1" min="1" max="20">
          </div>
          @if(\App\Support\DeliveryTime::rushFee() > 0)
            {{-- Made before the others: chosen here, paid once for the order. --}}
            <label class="rush-pick" for="rush">
              <input type="checkbox" name="rush" id="rush" value="1" @checked(old('rush', \App\Support\Cart::rush()))>
              <span class="rush-box" aria-hidden="true">⚡</span>
              <span class="rush-text">
                <b>{{ __('Təcili hazırlansın') }}</b>
                <small>{{ __('Bir neçə saat ərzində hazır olur, növbədənkənar.') }}</small>
              </span>
              <span class="rush-fee">+{{ \App\Support\Price::format(\App\Support\DeliveryTime::rushFee()) }}</span>
            </label>
          @endif
        </div>

        @if($product->price || $chocolates->isNotEmpty() || $wrappings->isNotEmpty() || \App\Support\Letter::enabled())
          <div class="price-sum" id="price-sum" data-box="{{ (float) $product->price }}">
            <div><span>{{ __('Qutu') }}</span><span>{{ $product->price ? \App\Support\Price::format($product->price) : __('sorğu ilə') }}</span></div>
            @if($chocolates->isNotEmpty())
              <div><span id="sum-choc-name" class="sum-name">{{ __('Şokolad') }}</span><span id="sum-choc">{{ __('seçilməyib') }}</span></div>
            @endif
            @if(\App\Support\LiveMaterials::enabled())
              <div id="sum-ar-row" data-price="{{ \App\Support\LiveMaterials::price() }}" hidden><span>{{ __('Canlı şəkil (AR)') }}</span><span>{{ \App\Support\Price::format(\App\Support\LiveMaterials::price()) }}</span></div>
            @endif
            @if(\App\Support\Letter::enabled())
              <div id="sum-letter-row" data-price="{{ \App\Support\Letter::price() }}" hidden><span>{{ __('Polaroid məktub') }}</span><span id="sum-letter">{{ \App\Support\Price::format(\App\Support\Letter::price()) }}</span></div>
            @endif
            @if($wrappings->isNotEmpty())
              <div id="sum-wrap-row" hidden><span id="sum-wrap-name" class="sum-name">{{ __('Qablaşdırma') }}</span><span id="sum-wrap">—</span></div>
            @endif
            @if(\App\Support\DeliveryTime::rushFee() > 0)
              <div id="sum-rush-row" data-price="{{ \App\Support\DeliveryTime::rushFee() }}" hidden><span>{{ __('Təcili hazırlansın') }}</span><span>{{ \App\Support\Price::format(\App\Support\DeliveryTime::rushFee()) }}</span></div>
            @endif
            <div class="total"><span>{{ __('Cəmi') }}</span><span id="sum-total">—</span></div>
          </div>
        @endif

        <button type="submit" class="btn btn-primary btn-block btn-flame" id="add-to-cart-btn"
                @if($photoSlots->isNotEmpty()) disabled @endif>{{ __('Səbətə Əlavə Et') }}</button>
        {{-- The button is shut until the picture is there; say so, or it looks
             broken. On a design that needs no photo the line is still here,
             empty, because anything else the form refuses is said in it too. --}}
        <p class="add-hint" id="add-hint" @if($photoSlots->isEmpty()) hidden @endif>{{ $photoSlots->isNotEmpty() ? __('Əvvəlcə şəklinizi yükləyin — sonra düymə işə düşür.') : '' }}</p>
      </form>
    </div>
  </div>
</section>

@if($gifts->isNotEmpty())
  {{-- Above the chips are a label, deliberately: a visitor mid-upload should
       not be invited away. Here, past the basket button, the same occasions
       are a way on — and they carry this page's weight to the pages written
       to be found. --}}
  <section>
    <div class="wrap">
      <div class="section-head center">
        <h2>{{ __('Bu dizayn hansı münasibətlərə uyğundur') }}</h2>
      </div>
      <div class="occ-chips">
        @foreach($gifts as $gift)
          <a class="occ-chip" href="{{ $gift->url() }}">{{ $gift->emoji }} {{ $gift->linkText() }}</a>
        @endforeach
      </div>
    </div>
  </section>
@endif

@if($related->isNotEmpty())
  <section class="tinted">
    <div class="wrap">
      <div class="section-head center">
        <span class="eyebrow" style="justify-content:center;">{{ __('Oxşar dizaynlar') }}</span>
        <h2>{{ __('Bunlara da baxın') }}</h2>
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
@if($cutsFaces)
  @include('partials.cutout-brush')
@endif
@endsection

@section('page_script')
@if($cutsFaces || $photoSlots->where('shape', 'ellipse')->isNotEmpty())
<script defer src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
@endif
{{-- Before anything that opens a customer's photograph: it decodes straight
     to the size wanted, so a phone picture never becomes a bitmap the tab
     cannot carry. --}}
<script src="{{ asset('js/photo-shrink.js') }}?v={{ \App\Support\Assets::version('js/photo-shrink.js') }}"></script>
@if(\App\Support\Sky::wanted($product))
  <script src="{{ asset('js/star-data.js') }}?v={{ \App\Support\Assets::version('js/star-data.js') }}"></script>
  <script src="{{ asset('js/star-map.js') }}?v={{ \App\Support\Assets::version('js/star-map.js') }}"></script>
@endif
@if($cutsFaces)
<script defer src="{{ asset('js/face-cutout.js') }}?v={{ \App\Support\Assets::version('js/face-cutout.js') }}"></script>
<script defer src="{{ asset('js/cutout-brush.js') }}?v={{ \App\Support\Assets::version('js/cutout-brush.js') }}"></script>
@endif
<script src="{{ asset('js/box-render.js') }}?v={{ \App\Support\Assets::version('js/box-render.js') }}"></script>
<script src="{{ asset('js/scene-render.js') }}?v={{ \App\Support\Assets::version('js/scene-render.js') }}"></script>
<script src="{{ asset('js/wrap-render.js') }}?v={{ \App\Support\Assets::version('js/wrap-render.js') }}"></script>
<script src="{{ asset('js/gift-box.js') }}?v={{ \App\Support\Assets::version('js/gift-box.js') }}"></script>
<script src="{{ asset('js/polaroid.js') }}?v={{ \App\Support\Assets::version('js/polaroid.js') }}"></script>
@if(\App\Support\LiveMaterials::enabled())
<script src="{{ asset('js/live-target.js') }}?v={{ \App\Support\Assets::version('js/live-target.js') }}"></script>
@endif
<script>
(function(){
  "use strict";

  var ANGLES = @json($viewData);
  var SLOT_COUNT = {{ $photoSlots->count() }};
  var SKY_SLOT = @json($photoSlots->values()->map(fn ($s) => $s->isSky())->all());

  /* What the customer said about the night, and the little that follows from
     it. Read by the drawing below; changed by the controls further down. */
  @php
    $skyStart = \App\Support\Sky::wanted($product)
        ? ['date' => now()->toDateString(), 'time' => '21:00', 'lat' => 40.3777, 'lon' => 49.8920, 'tz' => 4]
        : null;
  @endphp
  var sky = @json($skyStart);

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
  /* Same for the cutter: its models are the slow part of the first photo. */
  setTimeout(function(){ if (window.NefisCutout) window.NefisCutout.warm(); }, 400);

  /* The song on the box. Whatever Spotify handed the customer — the share
     link, the app's own uri, a country prefix, a ?si= tail — comes down to
     one address, and the code is drawn from it on the spot. Seeing the bars
     appear is what tells him the link was the right one; the server checks
     the same thing again when the box goes into the basket. */
  var songInput = document.getElementById('spotify-uri');
  if (songInput) {
    var songHint = document.getElementById('spotify-hint');
    var songShown = document.getElementById('spotify-shown');
    var songImg = document.getElementById('spotify-code-img');
    var songKind = document.getElementById('spotify-kind');
    var songOpen = document.getElementById('spotify-open');
    var ASKED = songHint.textContent;
    var KINDS = 'track|album|playlist|artist|episode|show';
    /* The same words the order will use, so he reads one name throughout. */
    var KIND_NAMES = { track: @json(__('Mahnı')), album: @json(__('Albom')), playlist: @json(__('Pleylist')),
      artist: @json(__('İfaçı')), episode: @json(__('Epizod')), show: @json(__('Podkast')) };

    function songUri(v){
      v = (v || '').trim();
      var m = v.match(new RegExp('^spotify:(' + KINDS + '):([A-Za-z0-9]{22})$'));
      if (m) return 'spotify:' + m[1] + ':' + m[2];
      m = v.match(new RegExp('^https?://(?:open|play)\\.spotify\\.com/(?:intl-[a-z-]+/)?(' + KINDS + ')/([A-Za-z0-9]{22})'));
      return m ? 'spotify:' + m[1] + ':' + m[2] : null;
    }

    function showSong(){
      var raw = songInput.value.trim();
      var uri = songUri(raw);
      songHint.classList.toggle('bad', raw !== '' && !uri);
      songHint.textContent = uri || raw === '' ? ASKED
        : @json(__('Bu, Spotify linkinə oxşamır. Spotify-da mahnını açın → Paylaş → Linki kopyala.'));
      songShown.hidden = !uri;
      if (uri) {
        songImg.src = 'https://scannables.scdn.co/uri/plain/png/ffffff/black/640/' + uri;
        var kind = uri.split(':')[1];
        songKind.textContent = KIND_NAMES[kind] || KIND_NAMES.track;
        songOpen.href = 'https://open.spotify.com/' + kind + '/' + uri.split(':')[2];
      }
    }

    songInput.addEventListener('input', showSong);
    /* A paste lands before the value is read, so wait a tick for it. */
    songInput.addEventListener('paste', function(){ setTimeout(showSong, 0); });
    showSong();
  }

  /* How big a photograph the preview keeps in memory. The box is printed
     from the file the customer sends, not from this, so a long side of
     1600 is far more than the screen can show and far less than a phone
     chokes on. NefisPhoto decodes straight to this size. */
  var PREVIEW_MAX = 1600;
  var PHOTO_MAX_BYTES = @json(\App\Http\Controllers\CartController::PHOTO_MAX_KB * 1024);

  var canvas = document.getElementById('preview-canvas');
  var ctx = canvas.getContext('2d');
  var dropHint = document.getElementById('drop-hint');
  var addBtn = document.getElementById('add-to-cart-btn');
  var addHint = document.getElementById('add-hint');
  /* A shut button with nothing said about it reads as a broken one, so the
     line under it always tells the customer what is still missing. */
  function markAdd(bad){
    if (!addHint) return;
    addHint.hidden = ! (bad || addBtn.disabled);
    addHint.classList.toggle('bad', ! ! bad);
    addHint.textContent = cutting
      ? @json(__('Şəkil hazırlanır, bir neçə saniyə…'))
      : @json(__('Əvvəlcə şəklinizi yükləyin — sonra düymə işə düşür.'));
  }
  /* Cutting the face out takes seconds on the first photo. The button
     stays shut while it runs: otherwise a customer who taps straight
     after choosing would order the uncut photo — background, room and
     all — onto a design that is a drawn body with a hole for a head. */
  var cutting = 0;
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
    photos.push({ img: null, scale: 1, rotate: 0, flip: false, panX: 0, panY: 0, faceBox: null, framed: false });
  }

  /* The controls live in their own closure further down the page; this is
     how the night they collect reaches the drawing. */
  var MONTHS = @json(\App\Support\Sky::MONTHS);

  /* The captions the sky fills in: the coordinates of the place and the date
     of the night, written the way they are printed. The shop works the same
     strings out again on its own side, so a changed field cannot change what
     is made. */
  function autoCaption(kind){
    if (!sky) return '';
    var d = String(sky.date || '').split('-');
    if (kind === 'coords') return window.NefisStarMap ? window.NefisStarMap.coordinates(sky.lat, sky.lon) : '';
    if (kind === 'date') return (d[2] || '') + '.' + (d[1] || '') + '.' + (d[0] || '');
    if (kind === 'date_long') return Number(d[2]) + ' ' + (MONTHS[Number(d[1]) - 1] || '') + ' ' + d[0];
    if (kind === 'place') return sky.place || '';

    return '';
  }

  window.nefisSky = function(next){
    if (!sky) return;
    sky.date = next.date || sky.date;
    sky.time = next.time || sky.time;
    sky.lat = next.lat;
    sky.lon = next.lon;
    sky.tz = next.tz;
    if (next.place !== undefined) sky.place = next.place;
    document.querySelectorAll('[data-auto]').forEach(function(el){
      el.value = autoCaption(el.dataset.auto);
    });
    draw();
  };

  function currentAngle(){ return ANGLES[activeAngle]; }
  function areaFor(slotIndex){ return currentAngle().areas[slotIndex] || null; }

  /* What the preview shows is what the shop has to make: the zoom, turn,
     mirror and shift of each photo go with it, in the window's own units.
     Filled on the way out, in capture, before any other submit handler can
     stop or redirect the form. */
  document.getElementById('customize-form').addEventListener('submit', function(){
    document.querySelectorAll('.slot-block[data-slot]').forEach(function(block){
      var i = Number(block.dataset.slot), st = photos[i], area = areaFor(i), field = block.querySelector('.photo-frame');
      if (!field || !st || !st.img) return;
      field.value = JSON.stringify({
        scale: +(st.scale || 1).toFixed(3), rotate: +(st.rotate || 0).toFixed(1), flip: !!st.flip,
        panX: +(st.panX || 0).toFixed(3), panY: +(st.panY || 0).toFixed(3),
        ratio: area ? +(area.w / area.h).toFixed(3) : 1, shape: area && area.shape === 'ellipse' ? 'ellipse' : 'rectangle'
      });
    });
  }, true);

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

  /* The window's night sky, worked out for the moment the customer named.
     Drawn straight into the flat design, so every scene and the print file
     get it without knowing anything about stars. */
  function drawSkyInArea(mctx, area){
    if (!window.NefisStarMap || !sky) return;
    var d = Math.min(area.w, area.h);
    mctx.save();
    mctx.translate(area.x + area.w / 2, area.y + area.h / 2);
    mctx.rotate(area.rotation * Math.PI / 180);
    window.NefisStarMap.draw(mctx, {
      date: sky.date, time: sky.time, tzOffset: sky.tz, lat: sky.lat, lon: sky.lon,
      shape: area.shape === 'heart' ? 'heart' : 'circle',
      style: area.skyStyle || 'night', ring: area.skyRing !== false && area.shape !== 'heart',
      size: d, radius: d / 2, cx: 0, cy: 0, page: false
    });
    mctx.restore();
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
    if (state.flip) mctx.scale(-1, 1);
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
      if (area.fill === 'sky') { drawSkyInArea(mctx, area); return; }
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
    state.flip = false;

    var area = areaFor(slotIndex);
    if (!area || (area.shape !== 'ellipse' && !area.cutout) || typeof faceapi === 'undefined') return;

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
    /* A window holding the sky needs nothing uploaded: it is already full. */
    return photos.every(function(p, i){ return SKY_SLOT[i] || p.img !== null; });
  }

  /* The photo inputs are required and hidden under the preview. The browser
     refuses such a form before any submit handler runs and says nothing the
     customer can see ("An invalid form control is not focusable"), so the
     refusal is caught here — `invalid` does not bubble, hence the capture —
     and answered with the line under the button. */
  var customizeForm = document.getElementById('customize-form');
  function pointAtEmptySlot(index){
    markAdd(true);
    var block = slotBlocks.filter(function(b){ return Number(b.dataset.slot) === index; })[0];
    (block || slotBlocks[0] || addBtn).scrollIntoView({ behavior: 'smooth', block: 'center' });
  }
  /* Whatever the browser refuses, the customer hears about. Most of these
     controls are invisible by design — the photo inputs under the preview,
     the video behind its label — and a browser that cannot point at a field
     says nothing at all, which leaves the button looking broken. */
  var WHY = {
    ar_video: @json(__('Canlı şəkil üçün videonu yükləyin, ya da bu seçimi söndürün.')),
    letter_photo: @json(__('Məktub üçün şəkli yükləyin, ya da bu seçimi söndürün.')),
    quantity: @json(__('Neçə ədəd olduğunu yazın.')),
    chocolate_id: @json(__('Şokoladı seçin.'))
  };
  var WHY_ANY = @json(__('Bu xananı doldurun.'));

  customizeForm.addEventListener('invalid', function(e){
    var field = e.target;
    if (! field || ! field.name) return;
    var slot = /^photos\[(\d+)\]$/.exec(field.name);
    e.preventDefault();

    if (slot) { pointAtEmptySlot(Number(slot[1])); return; }

    if (addHint) {
      addHint.hidden = false;
      addHint.classList.add('bad');
      addHint.textContent = WHY[field.name] || field.validationMessage || WHY_ANY;
    }
    var block = field.closest('.slot-block, .extra-row, .field, label') || field.parentElement;
    (block || addBtn).scrollIntoView({ behavior: 'smooth', block: 'center' });
  }, true);
  customizeForm.addEventListener('submit', function(e){
    if (e.defaultPrevented || allSlotsFilled()) return;
    e.preventDefault();
    pointAtEmptySlot(photos.findIndex(function(p){ return p.img === null; }));
  });

  slotBlocks.forEach(function(block){
    var index = Number(block.dataset.slot);
    var input = block.querySelector('.photo-input');
    if (!input) return;          // not a photo window, whatever else it is
    var label = block.querySelector('.upload-label');
    var zoomRow = block.querySelector('.zoom-row');
    var zoom = block.querySelector('.zoom-range');
    var rotateRow = block.querySelector('.rotate-row');
    var rotate = block.querySelector('.rotate-range');
    var rotateReset = block.querySelector('.rotate-reset');
    var flipBtn = block.querySelector('.flip-btn');
    var hint = block.querySelector('.slot-hint');
    var fixBg = block.querySelector('.fix-bg');
    var labelWas = label.textContent;
    var hintWas = hint ? hint.textContent : '';

    /* A file the browser cannot open: say so and take it out — or the box
       shows its name, the preview stays empty, the hint asks for a photo that
       seems already given, and on a second choice the old picture stays in
       the preview while another one is sent. */
    function badFile(){
      input.value = '';
      photos[index].img = null;
      label.textContent = labelWas;
      if (hint){ hint.hidden = false; hint.textContent = @json(__('Bu fayl açılmadı. JPG və ya PNG şəkil seçin.')); }
      addBtn.disabled = true;
      markAdd();
      draw();
    }

    input.addEventListener('change', function(){
      var file = input.files && input.files[0];
      if (!file) return;
      label.textContent = file.name;

      /* A phone photo weighs ten to eighteen megabytes, and the shop refuses
         it only after the upload — with a refusal that comes back without
         the customer's other files. Anything over the limit is re-encoded
         here, at a size that still prints, and goes in instead. It comes back
         through this same handler, marked, so it is not shrunk twice. */
      if (file.size > PHOTO_MAX_BYTES && !file.__shrunk && window.NefisPhoto && typeof DataTransfer !== 'undefined') {
        if (hint){ hint.hidden = false; hint.textContent = @json(__('Şəkil kiçildilir…')); }
        window.NefisPhoto.load(file, 3000).then(function(pic){
          var c = pic;
          if (!pic.tagName || pic.tagName !== 'CANVAS') {
            c = document.createElement('canvas');
            c.width = pic.naturalWidth || pic.width;
            c.height = pic.naturalHeight || pic.height;
            c.getContext('2d').drawImage(pic, 0, 0, c.width, c.height);
          }
          return new Promise(function(ok, bad){ c.toBlob(function(b){ b ? ok(b) : bad(new Error('blob')); }, 'image/jpeg', 0.9); });
        }).then(function(b){
          var small = new File([b], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' });
          small.__shrunk = true;
          var box = new DataTransfer();
          box.items.add(small);
          input.files = box.files;
          input.dispatchEvent(new Event('change'));
        }).catch(function(){ badFile(); });
        return;
      }

      /* A face slot keeps only the head: the background is cut away here, in
         the customer's own browser, and the cut-out picture is what is sent.
         The cut picture comes back through this same handler, so one flag
         marks that round and is cleared at once — otherwise the second photo
         a customer picks would go in uncut. */
      var area = areaFor(index);
      var alreadyCut = input.dataset.processed === '1';
      input.dataset.processed = '';
      if (!alreadyCut && area && area.cutout && window.NefisCutout) {
        if (hint){ hint.hidden = false; hint.textContent = @json(__('Şəkil hazırlanır, bir neçə saniyə…')); }
        if (fixBg) fixBg.hidden = true;
        cutting++;
        addBtn.disabled = true;
        markAdd();
        /* prepare() answers with null instead of throwing, so every way out
           of it has to come through here or the button never opens again. */
        var settled = false;
        var settle = function(){
          if (settled) return;
          settled = true;
          cutting--;
          if (!cutting && allSlotsFilled()) addBtn.disabled = false;
          markAdd();
        };
        window.NefisCutout.prepare(file).then(function(cut){
          if (!cut) {
            /* Better to say so than to leave a square photo on a drawn body. */
            if (hint) hint.textContent = @json(__('Fonu kəsmək alınmadı. Şəkli özünüz yerləşdirin və ya fonu sadə olan başqa şəkil seçin.'));
            settle();
            return;
          }
          var box = new DataTransfer();
          box.items.add(cut);
          input.dataset.processed = '1';
          input.files = box.files;
          /* Freed before the picture goes back through this handler, so the
             reader that follows finds nothing left to wait for. */
          settle();
          input.dispatchEvent(new Event('change'));
          if (hint) hint.textContent = @json(__('Fon kəsildi. Şəkli sürükləyib böyüdə bilərsiniz.'));
          /* Whatever the machine left behind, the customer wipes himself. */
          if (fixBg) fixBg.hidden = false;
        }).catch(function(){
          if (hint) hint.textContent = @json(__('Fonu kəsmək alınmadı. Şəkli özünüz yerləşdirin və ya fonu sadə olan başqa şəkil seçin.'));
          settle();
        });
      }

      /* A photograph off a phone is twelve to forty-eight megapixels. Read
         as a data URL it becomes a base64 string of several megabytes and a
         bitmap of tens of megabytes on top, and iOS Safari answers that by
         quietly throwing the page away and loading it again — which is what
         a customer saw a few seconds after choosing a picture.

         So: no data URL, and the preview keeps a copy cut down to a size a
         phone can carry. The file itself is sent to us untouched, so nothing
         about the printing changes. */
      window.NefisPhoto.load(file, PREVIEW_MAX).then(function(shown){
        photos[index].img = shown;
        applyAutoFraming(index, shown);
        zoomRow.hidden = false;
        rotateRow.hidden = false;
        if (rotate) rotate.value = 0;
        /* Back to the slot's own words: this line may have been left saying
           the photo was being shrunk, or that the last file would not open —
           both of which are over by the time the picture is in. The cut-out
           branch writes its own ending, so it is left alone. */
        if (hint){ hint.hidden = false; if (! cutting) hint.textContent = hintWas; }
        if (dropHint) dropHint.style.display = 'none';
        if (allSlotsFilled() && !cutting) addBtn.disabled = false;
        markAdd();
        draw();
      }).catch(function(){ badFile(); });
    });

    if (fixBg) {
      fixBg.addEventListener('click', function(){
        var current = input.files && input.files[0];
        if (!current || !window.NefisBrush) return;
        window.NefisBrush.open(current).then(function(fixed){
          if (!fixed) return;
          var box = new DataTransfer();
          box.items.add(fixed);
          input.dataset.processed = '1';
          input.files = box.files;
          input.dispatchEvent(new Event('change'));
        });
      });
    }

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

    if (flipBtn) {
      flipBtn.addEventListener('click', function(){
        photos[index].flip = !photos[index].flip;
        flipBtn.setAttribute('aria-pressed', photos[index].flip ? 'true' : 'false');
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
  /* iOS takes the touch away for a call or the notification shade; without
     this the next swipe pans the photo instead of the page, by the whole
     stale distance at once. */
  canvas.addEventListener('touchcancel', function(){ dragSlot = -1; });
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
    groups.forEach(function(g){
      g.hidden = g.dataset.brand !== brand;
      if (g.hidden) g.classList.remove('open');     // the next brand starts folded again
    });
  }
  chips.forEach(function(c){ c.addEventListener('click', function(){ show(c.dataset.brand); }); });
  block.querySelectorAll('.choc-more').forEach(function(btn){
    btn.addEventListener('click', function(){
      var group = btn.closest('.choc-group');
      group.classList.toggle('open');
      if (!group.classList.contains('open')) group.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
  });
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
    hint.textContent = @json(__('Hər tərəfdən baxmaq üçün klikləyin'));
    name.appendChild(hint);
    /* the viewer reads the wrap from here */
    ['pattern', 'ribbon', 'color', 'scale'].forEach(function(k){ box.dataset[k] = r.dataset[k]; });
    preview.dataset.name = r.dataset.name;
    preview.dataset.price = price;
    NefisGift.paint(box, { pattern: r.dataset.pattern, ribbon: r.dataset.ribbon, color: r.dataset.color, scale: parseFloat(r.dataset.scale) || 0.5 });
  }
  /* The row above the papers says what is chosen right now. */
  var chosen = document.getElementById('wrap-chosen');
  var chosenPrice = document.getElementById('wrap-chosen-price');
  var sheet = document.querySelector('.wrap-open');
  function label(r){
    if (!chosen) return;
    var none = !r || !r.value;
    chosen.textContent = none ? @json(__('Qablaşdırmasız')) : r.dataset.name;
    chosenPrice.textContent = none ? '' : '+' + r.dataset.price.replace(/\.00$/, '') + ' ₼';
  }
  document.querySelectorAll('input[name="wrapping_id"]').forEach(function(r){
    r.addEventListener('change', function(){ if (r.checked) { show(r); label(r); } });
  });
  var picked = document.querySelector('input[name="wrapping_id"]:checked');
  show(picked);
  label(picked);
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
    name.textContent = f ? '📷 ' + f.name : '📷 ' + @json(__('Şəkil (istəyə görə)'));
  });

  /* The shop refuses a letter with neither words nor a picture, and a refusal
     that travels to the server and back cannot put the customer's files into
     the form again — his photo and his video are gone and he uploads them
     twice. So the same rule is kept here, before anything is sent, and before
     the live photo below spends several seconds preparing a target for
     nothing: it stands down when the form is already refused. */
  var text = document.getElementById('letter-text');
  var addHint = document.getElementById('add-hint');
  document.getElementById('customize-form').addEventListener('submit', function(e){
    if (e.defaultPrevented || ! on.checked) return;
    var hasText = text && text.value.trim() !== '';
    var hasPhoto = file && file.files && file.files.length > 0;
    if (hasText || hasPhoto) return;

    e.preventDefault();
    if (addHint) {
      addHint.hidden = false;
      addHint.classList.add('bad');
      addHint.textContent = @json(__('Məktub üçün şəkil və ya mətn əlavə edin, ya da məktubu söndürün.'));
    }
    (fields || on).scrollIntoView({ behavior: 'smooth', block: 'center' });
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
    name.textContent = f ? '🎬 ' + f.name + (f.size > MAX ? ', ' + @json(__(':mb MB-dan böyükdür!', ['mb' => \App\Support\LiveMaterials::videoMb()])) : '') : label;
  });

  /* Coming back with the browser's own back button restores this page as it
     was left: the button still disabled, still reading "Göndərilir…", and the
     flag still saying the live photo was already prepared — so the next send
     would carry the PREVIOUS design's picture and target. Put it all back. */
  var sendLabel = btn.textContent;
  window.addEventListener('pageshow', function(e){
    if (! e.persisted) return;
    delete form.dataset.live;
    btn.disabled = false;
    btn.textContent = sendLabel;
  });

  form.addEventListener('submit', function(e){
    if (e.defaultPrevented || !on.checked || form.dataset.live) return;
    var f = file.files && file.files[0];
    if (f && f.size > MAX) { e.preventDefault(); file.focus(); name.scrollIntoView({ behavior: 'smooth', block: 'center' }); return; }
    e.preventDefault();
    var text = btn.textContent;
    btn.disabled = true;
    btn.textContent = @json(__('Canlı şəkil hazırlanır…'));
    var go = function(){ form.dataset.live = '1'; btn.textContent = @json(__('Göndərilir…')); form.submit(); };
    var design;
    try { design = window.nefisDesign(); } catch (err) { go(); return; }
    NefisLive.toBlob(NefisLive.flatten(design, 2000), 'image/jpeg', 0.9)
      .then(function(b){ NefisLive.attach(photo, b, 'design.jpg'); })
      .then(function(){ return NefisLive.compile(design, function(p){ btn.textContent = @json(__('Canlı şəkil hazırlanır…')) + ' ' + p + '%'; }); })
      .then(function(blob){ NefisLive.attach(mind, blob, 'target.mind'); })
      /* Whatever this browser could not do, the shop does by hand. */
      .then(go, go);
  });
})();

/* The night the customer names: date, hour and place, straight into the
   preview. The hidden fields carry the same numbers to the shop. */
(function(){
  var block = document.getElementById('sky-block');
  if (!block || !window.nefisSky) return;
  var dateEl = document.getElementById('star-date');
  var timeEl = document.getElementById('star-time');
  var cityEl = document.getElementById('star-city');
  var ownWrap = document.getElementById('sky-own');
  var ownEl = document.getElementById('star-own');
  var latEl = document.getElementById('star-lat');
  var lonEl = document.getElementById('star-lon');
  var placeEl = document.getElementById('star-place');
  var out = document.getElementById('sky-coords');

  function apply(){
    var lat, lon, place;
    if (cityEl.value === 'other') {
      var parts = String(ownEl.value || '').split(/[,;\s]+/).filter(Boolean);
      lat = parseFloat(parts[0]);
      lon = parseFloat(parts[1]);
      place = '';
      if (!isFinite(lat) || !isFinite(lon) || Math.abs(lat) > 90 || Math.abs(lon) > 180) return;
    } else {
      var pair = cityEl.value.split(',');
      lat = parseFloat(pair[0]);
      lon = parseFloat(pair[1]);
      place = cityEl.options[cityEl.selectedIndex].textContent.trim();
    }
    latEl.value = lat;
    lonEl.value = lon;
    placeEl.value = place;
    /* Inside the country the clock is +4 all year; elsewhere the longitude is
       the honest guess. The same rule runs on the server. */
    var tz = (Math.abs(lon - 49) < 8 && Math.abs(lat - 40) < 4) ? 4 : Math.round(lon / 15);
    window.nefisSky({ date: dateEl.value, time: timeEl.value || '21:00', lat: lat, lon: lon, tz: tz, place: place });
    if (out && window.NefisStarMap) out.textContent = window.NefisStarMap.coordinates(lat, lon);
  }

  cityEl.addEventListener('change', function(){
    ownWrap.hidden = cityEl.value !== 'other';
    apply();
  });
  [dateEl, timeEl, ownEl].forEach(function(el){
    el.addEventListener('input', apply);
    el.addEventListener('change', apply);
  });
  apply();
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
      document.getElementById('sum-wrap-name').textContent = wrapPick && wrapPick.value ? @json(__('Qablaşdırma')) + ': ' + wrapPick.dataset.name : 'Qablaşdırma';
      document.getElementById('sum-wrap').textContent = fmt(wrap);
    }
    var n = Math.max(1, parseInt(qty.value, 10) || 1);
    if (chocOut) chocOut.textContent = picked ? fmt(choc) : @json(__('seçilməyib'));
    if (chocName) chocName.textContent = picked ? picked.dataset.name : @json(__('Şokolad'));
    var letterOn = document.getElementById('letter-on');
    var letterRow = document.getElementById('sum-letter-row');
    var letter = letterOn && letterOn.checked && letterRow ? parseFloat(letterRow.dataset.price) || 0 : 0;
    if (letterRow) letterRow.hidden = !(letterOn && letterOn.checked);
    var arOn = document.getElementById('ar-on');
    var arRow = document.getElementById('sum-ar-row');
    var ar = arOn && arOn.checked && arRow ? parseFloat(arRow.dataset.price) || 0 : 0;
    if (arRow) arRow.hidden = !(arOn && arOn.checked);
    var rushOn = document.getElementById('rush');
    var rushRow = document.getElementById('sum-rush-row');
    /* The hurry is for the whole order, so it is added once, not per box. */
    var rush = rushOn && rushOn.checked && rushRow ? parseFloat(rushRow.dataset.price) || 0 : 0;
    if (rushRow) rushRow.hidden = !(rushOn && rushOn.checked);
    var each = box + choc + wrap + letter + ar;
    var all = each * n + rush;
    totalOut.textContent = all > 0 ? fmt(all) + (n > 1 ? ' (' + n + ' × ' + fmt(each) + (rush ? ' + ' + fmt(rush) : '') + ')' : '') : '—';
  }
  document.querySelectorAll('input[name="chocolate_id"], input[name="wrapping_id"], #letter-on, #ar-on, #rush').forEach(function(r){ r.addEventListener('change', update); });
  qty.addEventListener('input', update);
  update();
})();
</script>
@endsection
