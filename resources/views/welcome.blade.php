@extends('layouts.app')

@section('content')

  <!-- HERO -->
  <section class="hero">
    <div class="wrap">
      <div>
        <span class="eyebrow hero-in d1">Nefis Şokolad Evi</span>
        <h1 class="hero-in d2">Hər Hədiyyə<br>Bir Xatirəyə Dönsün.</h1>
        <p class="lede hero-in d3">Öz şəklinizi, öz sözünüzü seçin — biz onu sevdiklərinizə hədiyyə edəcəyiniz ən nəfis şokolad qutusuna çeviririk.</p>
        <div class="hero-ctas hero-in d4">
          <a href="#collections" class="btn btn-primary">
            İndi Sifariş Ver
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 17L17 7M17 7H9M17 7V15"/></svg>
          </a>
          <a href="#collections" class="btn btn-ghost">Dizaynlara Bax</a>
        </div>
        <div class="hero-badges hero-in d5">
          <div class="hero-badge"><span class="dot"></span> Premium Şokolad</div>
          <div class="hero-badge"><span class="dot"></span> 100% Fərdi Dizayn</div>
          <div class="hero-badge"><span class="dot"></span> Sürətli Çatdırılma</div>
        </div>
      </div>
      <div class="hero-visual hero-in d3">
        <div class="hero-ribbon">Fərdi Hədiyyə</div>
        <div class="ph">
          <div class="ring">🎁</div>
          <p>Sizin şokolad qutunuzun<br>görüntüsü tezliklə burada</p>
        </div>
      </div>
    </div>
  </section>

  <!-- FEATURES -->
  <section class="features">
    <div class="wrap">
      <div class="features-grid">
        <div class="feature-card reveal">
          <div class="ico">📸</div>
          <h3>Fərdi Şəkil Çapı</h3>
          <p>Üz və ya tam boy şəklinizi yüksək keyfiyyətdə qutuya çap edirik.</p>
        </div>
        <div class="feature-card reveal">
          <div class="ico">🍫</div>
          <h3>Premium Şokolad</h3>
          <p>Yalnız keyfiyyətli, təzə şokolad məhsullarından istifadə edirik.</p>
        </div>
        <div class="feature-card reveal">
          <div class="ico">💌</div>
          <h3>Fərdi Yazı</h3>
          <p>İstədiyiniz mətni, adı və ya tarixi qutuya əlavə edin.</p>
        </div>
        <div class="feature-card reveal">
          <div class="ico">⚡</div>
          <h3>Sürətli Hazırlanma</h3>
          <p>Sifarişiniz qısa müddətdə hazırlanıb sizə çatdırılır.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- HOW IT WORKS -->
  <section id="how" class="tinted">
    <div class="wrap">
      <div class="section-head center">
        <span class="eyebrow" style="justify-content:center;">Necə İşləyir</span>
        <h2>Üç Addımda Fərdi Hədiyyə</h2>
        <p class="lede" style="margin-inline:auto;">Hər addım diqqətlə düşünülüb ki, xatirəniz ən nəfis formada sizə qaytarılsın.</p>
      </div>
      <div class="steps">
        <div class="step reveal">
          <div class="step-line"></div>
          <div class="num">1</div>
          <h3>Dizaynı Seçin</h3>
          <p>Kolleksiyadan xoşunuza gələn qutu dizaynını seçin.</p>
        </div>
        <div class="step reveal">
          <div class="step-line"></div>
          <div class="num">2</div>
          <h3>Şəklinizi Yükləyin</h3>
          <p>Öz şəklinizi və istədiyiniz mətni əlavə edib canlı önizləmə görün.</p>
        </div>
        <div class="step reveal">
          <div class="num">3</div>
          <h3>Sifariş Verin</h3>
          <p>Sifarişinizi göndərin, biz sizinlə əlaqə saxlayıb təsdiqləyək.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- COLLECTIONS -->
  <section id="collections">
    <div class="wrap">
      <div class="section-head reveal">
        <span class="eyebrow">Kolleksiya</span>
        <h2>Hər Zövqə Uyğun Dizaynlar</h2>
        <p class="lede">Bir dizayn seçin, öz şəklinizi yükləyin və canlı önizləməni görün.</p>
      </div>
      <div class="cards-grid">
        @forelse($products as $product)
          <div class="p-card reveal">
            <a href="{{ route('products.customize', $product->slug) }}" class="p-card-media">
              @if($product->tag)<span class="tag">{{ $product->tag }}</span>@endif
              <img src="{{ asset('storage/' . $product->template_image) }}" alt="{{ $product->name }}" loading="lazy">
            </a>
            <div class="p-card-body">
              <h3>{{ $product->name }}</h3>
              <p>{{ $product->description }}</p>
              <div class="p-card-foot">
                <span class="p-card-price">{{ $product->price ? number_format($product->price) . ' ₼' : 'Qiymət sorğu ilə' }}</span>
                <a href="{{ route('products.customize', $product->slug) }}" class="p-card-link">Fərdiləşdir <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
              </div>
            </div>
          </div>
        @empty
          <div class="p-card reveal">
            <div class="p-card-media"><span class="tag">Milli Ornament</span><span class="ph-ico">🍫</span></div>
            <div class="p-card-body">
              <h3>Azerbaijan Style</h3>
              <p>Milli ornament motivləri ilə bəzədilmiş, qürur oyadan dizayn.</p>
              <div class="p-card-foot">
                <span class="p-card-price">Tezliklə</span>
              </div>
            </div>
          </div>
          <div class="p-card reveal">
            <div class="p-card-media"><span class="tag">Cütlük Üçün</span><span class="ph-ico">💕</span></div>
            <div class="p-card-body">
              <h3>Couple Box</h3>
              <p>Sevginizi göstərmək üçün ikinizin şəkli ilə xüsusi dizayn.</p>
              <div class="p-card-foot">
                <span class="p-card-price">Tezliklə</span>
              </div>
            </div>
          </div>
          <div class="p-card reveal">
            <div class="p-card-media"><span class="tag">Klassik</span><span class="ph-ico">🎁</span></div>
            <div class="p-card-body">
              <h3>Kinder Style</h3>
              <p>Tanış və sevimli qablaşdırma üzərində sizin şəkliniz.</p>
              <div class="p-card-foot">
                <span class="p-card-price">Tezliklə</span>
              </div>
            </div>
          </div>
          <div class="p-card reveal">
            <div class="p-card-media"><span class="tag">Populyar</span><span class="ph-ico">✨</span></div>
            <div class="p-card-body">
              <h3>Milka Style</h3>
              <p>Yumşaq bənövşəyi qablaşdırma üzərində fərdi toxunuş.</p>
              <div class="p-card-foot">
                <span class="p-card-price">Tezliklə</span>
              </div>
            </div>
          </div>
        @endforelse
      </div>
    </div>
  </section>

  <!-- INSTAGRAM CTA -->
  <section>
    <div class="wrap">
      <div class="insta-band reveal">
        <span class="eyebrow" style="justify-content:center; color:var(--gold);">@nefis.az</span>
        <h2>Bizi Instagramda İzləyin</h2>
        <p>Yeni dizaynlar, müştəri işləri və elanları Instagram səhifəmizdə paylaşırıq.</p>
        <a href="https://www.instagram.com/nefis.az/" target="_blank" rel="noopener" class="btn btn-primary">
          Instagrama Keç
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 17L17 7M17 7H9M17 7V15"/></svg>
        </a>
      </div>
    </div>
  </section>

  <!-- FAQ -->
  <section id="faq" class="tinted">
    <div class="wrap">
      <div class="section-head center reveal">
        <span class="eyebrow" style="justify-content:center;">Suallar</span>
        <h2>Tez-tez Soruşulan Suallar</h2>
      </div>
      <div class="faq-list reveal">
        <details class="faq-item" open>
          <summary>Necə sifariş verə bilərəm?<span class="plus"></span></summary>
          <div class="faq-a">Kolleksiyadan dizayn seçin, şəklinizi yükləyin, səbətə əlavə edib qeydiyyatdan keçərək sifarişi tamamlayın.</div>
        </details>
        <details class="faq-item">
          <summary>Hansı şokolad növləri mövcuddur?<span class="plus"></span></summary>
          <div class="faq-a">Kinder, Milka, Alionka və digər premium brendlərin dizaynında qutular təklif edirik.</div>
        </details>
        <details class="faq-item">
          <summary>Çatdırılma nə qədər vaxt aparır?<span class="plus"></span></summary>
          <div class="faq-a">Sifariş adətən 1-3 iş günü ərzində hazırlanıb çatdırılır.</div>
        </details>
        <details class="faq-item">
          <summary>Bakı xaricinə çatdırılma varmı?<span class="plus"></span></summary>
          <div class="faq-a">Bəli, Azərbaycan daxilində bütün bölgələrə çatdırılma mövcuddur.</div>
        </details>
        <details class="faq-item">
          <summary>Fərdi sifarişi geri qaytara bilərəmmi?<span class="plus"></span></summary>
          <div class="faq-a">Fərdi hazırlanan məhsullar üçün geri qaytarma tətbiq olunmur, lakin çatdırılma zamanı zədə aşkar olarsa əvəz edilir.</div>
        </details>
      </div>
    </div>
  </section>

@endsection
