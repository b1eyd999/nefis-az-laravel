@extends('layouts.app')

@section('title', __('Nefis, Şəkilli Şokolad Qutuları və Fərdi Hədiyyələr Bakıda'))
@section('meta_description', __('Ad günü, sevgiliyə, 8 Mart və hər münasibətə fərdi hədiyyə: öz şəkliniz və sözlərinizlə şokolad qutusu. Onlayn sifariş, Bakıda və bütün Azərbaycanda çatdırılma.'))

@php
  // The questions on the home page, shown below and given to search engines as an FAQ.
  $faq = [
    ['q' => __('Necə sifariş verə bilərəm?'), 'a' => __('Kolleksiyadan dizayn seçin, şəklinizi yükləyin, səbətə əlavə edib qeydiyyatdan keçərək sifarişi tamamlayın.')],
    ['q' => __('Hansı şokolad növləri mövcuddur?'), 'a' => __('Kinder, Milka, Alionka və digər premium brendlərin dizaynında qutular təklif edirik.')],
    ['q' => __('Çatdırılma nə qədər vaxt aparır?'), 'a' => __('Sifariş adətən 1-3 iş günü ərzində hazırlanıb çatdırılır.')],
    ['q' => __('Bakı xaricinə çatdırılma varmı?'), 'a' => __('Bəli, Azərbaycan daxilində bütün bölgələrə çatdırılma mövcuddur.')],
    ['q' => __('Fərdi sifarişi geri qaytara bilərəmmi?'), 'a' => __('Fərdi hazırlanan məhsullar üçün geri qaytarma tətbiq olunmur, lakin çatdırılma zamanı zədə aşkar olarsa əvəz edilir.')],
  ];
@endphp

@push('jsonld')
  {{ \App\Support\Seo::jsonLd(['@graph' => array_merge(\App\Support\Seo::organization()['@graph'], [\App\Support\Seo::faq($faq)])]) }}
@endpush

@section('page_style')
  .p-card-media{ aspect-ratio:4/5; }
  /* several slides: stacked in one grid cell, so the banner keeps the tallest one's height */
  /* The banner's own controls: dots that stretch into a lit bar. A slide is
     changed by them, by a swipe, or by waiting. */
    transition:width .35s var(--ease), background .35s, box-shadow .35s; }
  /* Under the cursor a dot lights up whole, in honey yellow. */
    box-shadow:0 0 12px rgba(245,179,1,.65); }
@endsection

@section('content')

  @include('partials.hero')

  <!-- COLLECTIONS -->
  <section id="collections">
    <div class="wrap">
      {{-- The page's one heading, whatever the banner above is doing. It is
           a label over the designs, not a billboard: at the old size it ran
           to three lines and pushed the first row of boxes off the screen. --}}
      <div class="section-head center reveal" style="margin-bottom:1.75rem;">
        <span class="eyebrow" style="justify-content:center;">{{ __('Kolleksiya') }}</span>
        <h1 style="font-size:clamp(1.5rem, 2.6vw, 2rem); line-height:1.25; max-width:34rem; margin-inline:auto;">{{ __('Bakıda şəkilli şokolad qutuları və fərdi hədiyyələr') }}</h1>
      </div>
      <div class="cards-grid">
        @forelse($products as $product)
          @include('partials.p-card', ['product' => $product, 'first' => $loop->index < 2])
        @empty
          <div class="p-card reveal">
            <div class="p-card-media"><span class="tag">{{ __('Milli Ornament') }}</span><span class="ph-ico">🍫</span></div>
            <div class="p-card-body">
              <h3>Azerbaijan Style</h3>
              <p>{{ __('Milli ornament motivləri ilə bəzədilmiş, qürur oyadan dizayn.') }}</p>
              <div class="p-card-foot">
                <span class="p-card-price">{{ __('Tezliklə') }}</span>
              </div>
            </div>
          </div>
          <div class="p-card reveal">
            <div class="p-card-media"><span class="tag">{{ __('Cütlük Üçün') }}</span><span class="ph-ico">💕</span></div>
            <div class="p-card-body">
              <h3>Couple Box</h3>
              <p>{{ __('Sevginizi göstərmək üçün ikinizin şəkli ilə xüsusi dizayn.') }}</p>
              <div class="p-card-foot">
                <span class="p-card-price">{{ __('Tezliklə') }}</span>
              </div>
            </div>
          </div>
          <div class="p-card reveal">
            <div class="p-card-media"><span class="tag">{{ __('Klassik') }}</span><span class="ph-ico">🎁</span></div>
            <div class="p-card-body">
              <h3>Kinder Style</h3>
              <p>{{ __('Tanış və sevimli qablaşdırma üzərində sizin şəkliniz.') }}</p>
              <div class="p-card-foot">
                <span class="p-card-price">{{ __('Tezliklə') }}</span>
              </div>
            </div>
          </div>
          <div class="p-card reveal">
            <div class="p-card-media"><span class="tag">{{ __('Populyar') }}</span><span class="ph-ico">✨</span></div>
            <div class="p-card-body">
              <h3>Milka Style</h3>
              <p>{{ __('Yumşaq bənövşəyi qablaşdırma üzərində fərdi toxunuş.') }}</p>
              <div class="p-card-foot">
                <span class="p-card-price">{{ __('Tezliklə') }}</span>
              </div>
            </div>
          </div>
        @endforelse
      </div>
      @if(($designCount ?? 0) > $products->count())
        <div class="collections-foot reveal">
          <a href="{{ lroute('designs.index') }}" class="btn btn-ghost">
            {{ __('Bütün :count dizayna bax', ['count' => $designCount]) }}
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
          </a>
        </div>
      @endif

      {{-- The sentence search engines read, kept word for word but moved below
           the boxes. It used to stand between the heading and the first design
           and, with a second heading saying the same thing again, pushed the
           whole shop more than two screens down on a phone. A customer comes
           here to see the boxes; this is for whoever arrives from a search. --}}
      <p class="lede collections-note">{{ \App\Models\HeroSlide::numbers(__('Öz şəklinizi və sözlərinizi seçdiyiniz dizaynın üzərinə əlavə edin, önizləməni elə burada görün — qutunu biz yığıb Bakıya çatdırırıq. Ad günü, sevgiliyə, toya, yeni doğulana və korporativ hədiyyələr üçün {dizayn} hazır dizayn.')) }}</p>
    </div>
  </section>

  <!-- FEATURES -->
  <section class="features">
    <div class="wrap">
      <div class="features-grid">
        <div class="feature-card reveal">
          <div class="ico">📸</div>
          <h3>{{ __('Fərdi Şəkil Çapı') }}</h3>
          <p>{{ __('Üz və ya tam boy şəklinizi yüksək keyfiyyətdə qutuya çap edirik.') }}</p>
        </div>
        <div class="feature-card reveal">
          <div class="ico">🍫</div>
          <h3>{{ __('Premium Şokolad') }}</h3>
          <p>{{ __('Yalnız keyfiyyətli, təzə şokolad məhsullarından istifadə edirik.') }}</p>
        </div>
        <div class="feature-card reveal">
          <div class="ico">💌</div>
          <h3>{{ __('Fərdi Yazı') }}</h3>
          <p>{{ __('İstədiyiniz mətni, adı və ya tarixi qutuya əlavə edin.') }}</p>
        </div>
        <div class="feature-card reveal">
          <div class="ico">⚡</div>
          <h3>{{ __('Sürətli Hazırlanma') }}</h3>
          <p>{{ __('Sifarişiniz qısa müddətdə hazırlanıb sizə çatdırılır.') }}</p>
        </div>
      </div>
    </div>
  </section>

  <!-- HOW IT WORKS -->
  <section id="how" class="tinted">
    <div class="wrap">
      <div class="section-head center">
        <span class="eyebrow" style="justify-content:center;">{{ __('Necə İşləyir') }}</span>
        <h2>{{ __('Üç Addımda Fərdi Hədiyyə') }}</h2>
        <p class="lede" style="margin-inline:auto;">{{ __('Hər addım diqqətlə düşünülüb ki, xatirəniz ən nəfis formada sizə qaytarılsın.') }}</p>
      </div>
      <div class="steps">
        <div class="step reveal">
          <div class="step-line"></div>
          <div class="num">1</div>
          <h3>{{ __('Dizaynı Seçin') }}</h3>
          <p>{{ __('Kolleksiyadan xoşunuza gələn qutu dizaynını seçin.') }}</p>
        </div>
        <div class="step reveal">
          <div class="step-line"></div>
          <div class="num">2</div>
          <h3>{{ __('Şəklinizi Yükləyin') }}</h3>
          <p>{{ __('Öz şəklinizi və istədiyiniz mətni əlavə edib canlı önizləmə görün.') }}</p>
        </div>
        <div class="step reveal">
          <div class="num">3</div>
          <h3>{{ __('Sifariş Verin') }}</h3>
          <p>{{ __('Sifarişinizi göndərin, biz sizinlə əlaqə saxlayıb təsdiqləyək.') }}</p>
        </div>
      </div>
    </div>
  </section>


  <!-- GIFT IDEAS: one page per occasion people search for -->
  @if($gifts->isNotEmpty())
    <section id="gifts" class="tinted">
      <div class="wrap">
        <div class="section-head center reveal">
          <span class="eyebrow" style="justify-content:center;">{{ __('Hədiyyə fikirləri') }}</span>
          <h2>{{ __('Hər Münasibətə Fərdi Hədiyyə') }}</h2>
          <p class="lede" style="margin-inline:auto;">{{ __('Ad günü, sevgiliyə, 8 Mart, körpəyə, kimə və nə üçün hədiyyə axtarırsınız?') }}</p>
        </div>
        <div class="occ-grid">
          @foreach($gifts->take(8) as $gift)
            <a class="occ-card reveal" href="{{ $gift->url() }}">
              <span class="occ-ico">{{ $gift->emoji ?: '🎁' }}</span>
              <div>
                <h3>{{ $gift->linkText() }}</h3>
                <p>{{ \Illuminate\Support\Str::limit((string) $gift->intro, 90) }}</p>
              </div>
            </a>
          @endforeach
        </div>
        @if($gifts->count() > 8)
          <div class="collections-foot reveal">
            <a href="{{ lroute('gifts.index') }}" class="btn btn-ghost">{{ __('Bütün hədiyyə fikirləri') }}
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </a>
          </div>
        @endif
      </div>
    </section>
  @endif

  <!-- INSTAGRAM CTA -->
  <section>
    <div class="wrap">
      <div class="insta-band reveal">
        <span class="eyebrow" style="justify-content:center; color:var(--gold);">@nefis.az</span>
        <h2>{{ __('Bizi Instagramda İzləyin') }}</h2>
        <p>{{ __('Yeni dizaynlar, müştəri işləri və elanları Instagram səhifəmizdə paylaşırıq.') }}</p>
        <a href="https://www.instagram.com/nefis.az/" target="_blank" rel="noopener" class="btn btn-primary">
          {{ __('Instagrama Keç') }}
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 17L17 7M17 7H9M17 7V15"/></svg>
        </a>
      </div>
    </div>
  </section>

  <!-- FAQ -->
  <section id="faq" class="tinted">
    <div class="wrap">
      <div class="section-head center reveal">
        <span class="eyebrow" style="justify-content:center;">{{ __('Suallar') }}</span>
        <h2>{{ __('Tez-tez Soruşulan Suallar') }}</h2>
      </div>
      <div class="faq-list reveal">
        @foreach($faq as $i => $f)
          <details class="faq-item" @if($i === 0) open @endif>
            <summary>{{ $f['q'] }}<span class="plus"></span></summary>
            <div class="faq-a">{{ $f['a'] }}</div>
          </details>
        @endforeach
      </div>
    </div>
  </section>

@endsection

@section('page_script')
<script>
/* The banner's own script now lives in public/js/hero.js, because the
   banner is no longer only this page's. */
</script>
@endsection
