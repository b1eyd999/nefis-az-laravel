{{-- A banner the owner writes himself, in admin → Slaydlar.

     It began on the home page and is now wherever he puts a slide: the
     markup, the sheet (public/css/site.css) and the script (public/js/hero.js)
     are shared, and each page passes its own slides in. --}}
@php
  $slides = $slides ?? collect();
  $many = $slides->count() > 1;
  $autoplay = $autoplay ?? \App\Models\Setting::get(\App\Models\Setting::HERO_AUTOPLAY) === '1';
  $interval = $interval ?? (int) (\App\Models\Setting::get(\App\Models\Setting::HERO_INTERVAL) ?: 6);
@endphp
@if($slides->isNotEmpty())
{{-- The owner's own banner. One slide stands still; several turn. --}}
<section class="hero{{ $many ? ' hero-slider' : '' }}" id="hero"
         @if($many) data-autoplay="{{ $autoplay ? 1 : 0 }}" data-interval="{{ $interval }}" aria-roledescription="carousel" aria-label="Nefis Şokolad Evi" @endif>
  <div class="hero-track">
    @foreach($slides as $i => $s)
      <div class="hero-slide{{ $i === 0 ? ' is-on' : '' }}"
           @if($many) role="group" aria-roledescription="slide" aria-label="{{ $i + 1 }} / {{ $slides->count() }}" @if($i > 0) aria-hidden="true" @endif @endif>
        <div class="wrap">
          <div>
            @if($s->eyebrow)<span class="eyebrow hero-in d1">{{ $s->tr('eyebrow') }}</span>@endif
            {{-- The banner carries the owner's own words and can be switched
                 off altogether, so the page's heading is not kept in it. --}}
            <h2 class="hero-title hero-in d2">{!! nl2br(e($s->tr('title'))) !!}</h2>
            @if($s->text)<p class="lede hero-in d3">{{ \App\Models\HeroSlide::numbers($s->tr('text')) }}</p>@endif
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
                @foreach(($s->tr('badges') ?: []) as $badge)
                  <div class="hero-badge"><span class="dot"></span> {{ \App\Models\HeroSlide::numbers($badge) }}</div>
                @endforeach
              </div>
            @endif
          </div>
          {{-- A slide without a picture is just its words: an empty panel
               beside them only looks like something is missing. --}}
          @if($s->image)
            <div class="hero-visual hero-in d3 has-img">
              @if($s->ribbon)<div class="hero-ribbon">{{ $s->ribbon }}</div>@endif
              <img src="{{ $s->imageUrl() }}" alt="{{ str_replace("\n", ' ', $s->title) }}" class="fit-{{ $s->image_fit === 'contain' ? 'contain' : 'cover' }}"
                   @if($i > 0) loading="lazy" @endif>
            </div>
          @endif
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
<script src="{{ \App\Support\Assets::url('js/hero.js') }}" defer></script>
@endif
