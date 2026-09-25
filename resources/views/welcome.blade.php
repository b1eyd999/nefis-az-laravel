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
  .hero .hero-title{ margin-top:1.25rem; font-size:clamp(2.25rem, 5vw, 3.75rem); line-height:1.08; }
  .hero-visual.has-img{ background:var(--cream-2); }
  .hero-visual.has-img::before{ display:none; }
  .hero-visual img{ position:absolute; inset:0; width:100%; height:100%; }
  .hero-visual img.fit-cover{ object-fit:cover; }
  .hero-visual img.fit-contain{ object-fit:contain; padding:1.5rem; }
  .hero-visual .hero-ribbon{ z-index:2; }
  /* several slides: stacked in one grid cell, so the banner keeps the tallest one's height */
  .hero-slider .hero-track{ display:grid; }
  .hero-slider .hero-slide{ grid-area:1 / 1; opacity:0; visibility:hidden; transition:opacity .7s var(--ease), visibility .7s; }
  .hero-slider .hero-slide.is-on{ opacity:1; visibility:visible; }
  .hero-slider .hero-slide:not(.is-on) .hero-in{ animation:none; opacity:0; }
  .hero-nav{ position:relative; z-index:2; display:flex; align-items:center; justify-content:center; gap:1rem; margin-top:2.5rem; }
  /* The banner's own controls: dots that stretch into a lit bar. A slide is
     changed by them, by a swipe, or by waiting. */
  .hero-dots{ display:flex; align-items:center; gap:.5rem; }
  .hero-dot{ width:.6rem; height:.6rem; border-radius:999px; border:0; padding:0; background:var(--line);
    transition:width .35s var(--ease), background .35s, box-shadow .35s; }
  /* Under the cursor a dot lights up whole, in honey yellow. */
  .hero-dot:hover{ width:1.9rem; background:linear-gradient(120deg, #FFD166, #F5B301);
    box-shadow:0 0 12px rgba(245,179,1,.65); }
  .hero-dot.is-on{ width:1.9rem; background:var(--flame-grad); box-shadow:0 4px 12px -4px var(--flame-shadow); }
  .hero-dot:focus-visible{ outline:2px solid var(--flame); outline-offset:3px; }
  @media (prefers-reduced-motion:reduce){ .hero-slider .hero-slide{ transition:none; } }
  .collections-foot{ display:flex; justify-content:center; margin-top:3rem; }
@endsection

@section('content')

  <!-- HERO: the owner's slides (Ana səhifə slaydları); with more than one they turn. -->
  @php $many = $slides->count() > 1; @endphp
  <section class="hero{{ $many ? ' hero-slider' : '' }}" id="hero"
           @if($many) data-autoplay="{{ $autoplay ? 1 : 0 }}" data-interval="{{ $interval }}" aria-roledescription="carousel" aria-label="Nefis Şokolad Evi" @endif>
    <div class="hero-track">
      @foreach($slides as $i => $s)
        <div class="hero-slide{{ $i === 0 ? ' is-on' : '' }}"
             @if($many) role="group" aria-roledescription="slide" aria-label="{{ $i + 1 }} / {{ $slides->count() }}" @if($i > 0) aria-hidden="true" @endif @endif>
          <div class="wrap">
            <div>
              @if($s->eyebrow)<span class="eyebrow hero-in d1">{{ $s->tr('eyebrow') }}</span>@endif
              @if($i === 0)
                <h1 class="hero-title hero-in d2">{!! nl2br(e($s->tr('title'))) !!}</h1>
              @else
                <h2 class="hero-title hero-in d2">{!! nl2br(e($s->tr('title'))) !!}</h2>
              @endif
              @if($s->text)<p class="lede hero-in d3">{{ $s->tr('text') }}</p>@endif
              @php
                $b1 = \App\Models\HeroSlide::href($s->button1_url);
                $b2 = \App\Models\HeroSlide::href($s->button2_url);
                $ext = fn ($u) => $u && preg_match('#^https?://#i', $u);
              @endphp
              @if(($s->button1_label && $b1) || ($s->button2_label && $b2))
                <div class="hero-ctas hero-in d4">
                  @if($s->button1_label && $b1)
                    <a href="{{ $b1 }}" class="btn btn-primary" @if($ext($b1)) target="_blank" rel="noopener" @endif @if($many && $i > 0) tabindex="-1" @endif>
                      {{ $s->tr('button1_label') }}
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 17L17 7M17 7H9M17 7V15"/></svg>
                    </a>
                  @endif
                  @if($s->button2_label && $b2)
                    <a href="{{ $b2 }}" class="btn btn-ghost" @if($ext($b2)) target="_blank" rel="noopener" @endif @if($many && $i > 0) tabindex="-1" @endif>{{ $s->tr('button2_label') }}</a>
                  @endif
                </div>
              @endif
              @if($s->badges)
                <div class="hero-badges hero-in d5">
                  @foreach($s->badges as $badge)
                    <div class="hero-badge"><span class="dot"></span> {{ $badge }}</div>
                  @endforeach
                </div>
              @endif
            </div>
            <div class="hero-visual hero-in d3{{ $s->image ? ' has-img' : '' }}">
              @if($s->ribbon)<div class="hero-ribbon">{{ $s->ribbon }}</div>@endif
              @if($s->image)
                <img src="{{ $s->imageUrl() }}" alt="{{ str_replace("\n", ' ', $s->title) }}" class="fit-{{ $s->image_fit === 'contain' ? 'contain' : 'cover' }}"
                     @if($i > 0) loading="lazy" @endif>
              @else
                <div class="ph">
                  <div class="ring">🎁</div>
                  <p>{{ __('Sizin şokolad qutunuzun görüntüsü tezliklə burada') }}</p>
                </div>
              @endif
            </div>
          </div>
        </div>
      @endforeach
    </div>
    @if($many)
      <div class="hero-nav">
        <div class="hero-dots">
          @foreach($slides as $i => $s)
            <button type="button" class="hero-dot{{ $i === 0 ? ' is-on' : '' }}" data-go="{{ $i }}" aria-label="{{ __('Slayd') }} {{ $i + 1 }}" @if($i === 0) aria-current="true" @endif></button>
          @endforeach
        </div>
      </div>
    @endif
  </section>

  <!-- COLLECTIONS -->
  <section id="collections">
    <div class="wrap">
      <div class="section-head reveal">
        <span class="eyebrow">{{ __('Kolleksiya') }}</span>
        <h2>{{ __('Hər Zövqə Uyğun Dizaynlar') }}</h2>
        <p class="lede">{{ __('Bir dizayn seçin, öz şəklinizi yükləyin və canlı önizləməni görün.') }}</p>
      </div>
      <div class="cards-grid">
        @forelse($products as $product)
          @include('partials.p-card', ['product' => $product])
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
/* The opening banner's slides: dots, arrows, a swipe, and turning on their own. */
(function(){
  var hero = document.querySelector('.hero-slider');
  if (!hero) return;
  var slides = Array.prototype.slice.call(hero.querySelectorAll('.hero-slide'));
  var dots = Array.prototype.slice.call(hero.querySelectorAll('.hero-dot'));
  var current = 0, timer = null;
  var autoplay = hero.dataset.autoplay === '1' && !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var every = (parseInt(hero.dataset.interval, 10) || 6) * 1000;

  function go(i){
    i = (i + slides.length) % slides.length;
    if (i === current) return;
    slides[current].classList.remove('is-on');
    slides[current].setAttribute('aria-hidden', 'true');
    slides[current].querySelectorAll('a').forEach(function(a){ a.setAttribute('tabindex', '-1'); });
    slides[i].classList.add('is-on');
    slides[i].removeAttribute('aria-hidden');
    slides[i].querySelectorAll('a').forEach(function(a){ a.removeAttribute('tabindex'); });
    dots.forEach(function(d, k){ d.classList.toggle('is-on', k === i); if (k === i) d.setAttribute('aria-current', 'true'); else d.removeAttribute('aria-current'); });
    current = i;
  }
  function start(){ stop(); if (autoplay) timer = setInterval(function(){ go(current + 1); }, every); }
  function stop(){ if (timer) clearInterval(timer); timer = null; }

  hero.querySelectorAll('.hero-arrow').forEach(function(b){
    b.addEventListener('click', function(){ go(current + parseInt(b.dataset.dir, 10)); start(); });
  });
  dots.forEach(function(d){ d.addEventListener('click', function(){ go(parseInt(d.dataset.go, 10)); start(); }); });

  /* a swipe on a phone */
  var x0 = null;
  hero.addEventListener('touchstart', function(e){ x0 = e.touches[0].clientX; }, { passive: true });
  hero.addEventListener('touchend', function(e){
    if (x0 === null) return;
    var dx = e.changedTouches[0].clientX - x0;
    if (Math.abs(dx) > 50) { go(current + (dx < 0 ? 1 : -1)); start(); }
    x0 = null;
  });

  /* hold still while being read or when the tab is away */
  hero.addEventListener('mouseenter', stop);
  hero.addEventListener('mouseleave', start);
  hero.addEventListener('focusin', stop);
  document.addEventListener('visibilitychange', function(){ document.hidden ? stop() : start(); });
  start();
})();
</script>
@endsection
