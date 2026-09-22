{{-- A gift box in 3D: its face wrapped by wrap-render.js, a side and a top in the same paper, and a shadow.
     Painted by public/js/gift-box.js — from data-* here, or by NefisGift.paint(). --}}
@php $w = $wrap ?? null; @endphp
<div class="gift{{ $w && $w['ribbon'] === 'twine' ? ' twine' : '' }}"
     @if($w) data-pattern="{{ $w['pattern'] }}" data-ribbon="{{ $w['ribbon'] }}" data-color="{{ $w['color'] }}" data-scale="{{ $w['scale'] }}" @endif>
  <div class="gift-3d">
    <canvas class="gift-front" width="484" height="947" aria-hidden="true"></canvas>
    <div class="gift-side"><i></i></div>
    <div class="gift-top"><i></i></div>
  </div>
  <div class="gift-shadow"></div>
</div>
