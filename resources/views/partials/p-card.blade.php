{{-- A design on a catalogue card: its picture, name, price and the way to fill it in. --}}
@php $link = $product->isCustomizable() ? lroute('products.customize', $product->slug) : lroute('designs.index'); @endphp
<div class="p-card reveal">
  <a href="{{ $link }}" class="p-card-media">
    @if($product->tag)<span class="tag">{{ $product->tag }}</span>@endif
    {{-- Counted from the orders themselves, so the flame moves to whatever is
         selling now instead of sitting where somebody once put it. --}}
    @if(\App\Support\BestSellers::has($product->id))
      <span class="p-hot" title="{{ __('Ən çox sifariş olunanlardan') }}">
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
          <path d="M13.5 2c.3 3-1 4.6-2.4 6.1C9.6 9.7 8 11.3 8 14a6 6 0 0 0 12 0c0-3.6-2.4-5.6-4-7.3-.4 1.3-1.2 2-2 2.3.3-2.6-.2-5.3-.5-7z"/>
          <path d="M11 22a4 4 0 0 1-1.6-6.6c.1 1 .7 1.7 1.5 2.1-.2-1.6.4-3 1.6-4 .2 1.4 1 2 1.8 2.6A4 4 0 0 1 11 22z" opacity=".55"/>
        </svg>
        <b>HOT</b>
      </span>
    @endif
    <img src="{{ \App\Support\Media::url($product->catalogImage()) }}" alt="{{ $product->tr('name') }}, {{ __('şəkilli şokolad qutusu') }}" loading="lazy">
  </a>
  <div class="p-card-body">
    <h3>{{ $product->tr('name') }}</h3>
    <p>{{ $product->tr('description') ?: __($product->categoryLabel()) }}</p>
    <div class="p-card-foot">
      <span class="p-card-price">{{ $product->price ? \App\Support\Price::format($product->price) : __('Qiymət sorğu ilə') }}</span>
      <a href="{{ $link }}" class="p-card-link">{{ $product->isCustomizable() ? __('Fərdiləşdir') : __('Önizlə') }} <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
    </div>
  </div>
</div>
