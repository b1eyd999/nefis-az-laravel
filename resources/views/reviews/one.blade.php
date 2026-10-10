{{-- One review, as a visitor reads it. Used on the reviews page and under
     the design the review is about. --}}
<article class="rv-card">
  <div class="rv-head">
    @include('partials.stars', ['of' => $review->stars])
    <b class="rv-who">{{ $review->who() }}</b>
    @if($review->approved_at)
      <time class="rv-when" datetime="{{ $review->approved_at->toDateString() }}">
        {{ $review->approved_at->format('d.m.Y') }}
      </time>
    @endif
  </div>

  @if(($showProduct ?? false) && $review->product)
    <a class="rv-what" href="{{ lroute('products.customize', $review->product->slug) }}">{{ $review->product->name }}</a>
  @endif

  @if(filled($review->body))
    <p class="rv-body">{{ $review->body }}</p>
  @endif

  @if($review->photoUrl())
    {{-- His own photograph of the box, which is worth more than anything the
         shop can shoot itself. --}}
    <a class="rv-shot" href="{{ $review->photoUrl() }}" target="_blank" rel="noopener">
      <img src="{{ $review->photoUrl() }}" alt="{{ __('Müştərinin şəkli') }}" loading="lazy" decoding="async">
    </a>
  @endif

  @if(filled($review->reply))
    <div class="rv-reply">
      <b>Nefis.az</b>
      <p>{{ $review->reply }}</p>
    </div>
  @endif
</article>
