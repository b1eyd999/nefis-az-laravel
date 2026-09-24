{{-- A design on a catalogue card: its picture, name, price and the way to fill it in. --}}
@php $link = $product->isCustomizable() ? lroute('products.customize', $product->slug) : lroute('designs.index'); @endphp
<div class="p-card reveal">
  <a href="{{ $link }}" class="p-card-media">
    @if($product->tag)<span class="tag">{{ $product->tag }}</span>@endif
    <img src="{{ \App\Support\Media::url($product->catalogImage()) }}" alt="{{ $product->name }} — şəkilli şokolad qutusu" loading="lazy">
  </a>
  <div class="p-card-body">
    <h3>{{ $product->name }}</h3>
    <p>{{ $product->description ?: $product->categoryLabel() }}</p>
    <div class="p-card-foot">
      <span class="p-card-price">{{ $product->price ? \App\Support\Price::format($product->price) : 'Qiymət sorğu ilə' }}</span>
      <a href="{{ $link }}" class="p-card-link">{{ $product->isCustomizable() ? 'Fərdiləşdir' : 'Önizlə' }} <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
    </div>
  </div>
</div>
