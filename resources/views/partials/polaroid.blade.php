{{-- A Polaroid letter: the photo in the window with the words written under it, or — with no photo —
     the words alone in the window like a note. Live previews update it with public/js/polaroid.js. --}}
@php
  $photo = $photo ?? null;
  $text = $text ?? null;
  $placeholder = $placeholder ?? 'Sözləriniz burada';
@endphp
<figure class="polaroid{{ $photo ? '' : ' no-photo' }} {{ \App\Support\Letter::sizeClass($text) }}" data-placeholder="{{ $placeholder }}"
        @isset($id) id="{{ $id }}" @endisset>
  <span class="pol-photo">
    <img @if($photo) src="{{ $photo }}" @else hidden @endif alt="">
    <span class="pol-note{{ ! $photo && ! $text ? ' is-placeholder' : '' }}">{{ $photo ? '' : ($text ?: $placeholder) }}</span>
  </span>
  <figcaption class="pol-text">{{ $photo ? $text : '' }}</figcaption>
</figure>
