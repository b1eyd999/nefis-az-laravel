{{-- A Polaroid letter: the photo in the window with the words written under it, or — with no photo —
     the words alone in the window like a note. Its look is the owner's (Admin → Polaroid məktub);
     live previews update it with public/js/polaroid.js. --}}
@once
  @push('head')
    {{-- The handwriting the letter is written in, and the sheet it is drawn
         on. Asked for here so the pages that never show a letter do not. --}}
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Caveat:wght@600&family=Pacifico&family=Great+Vibes&family=Sacramento&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/polaroid.css') }}">
  @endpush
@endonce
@php
  $photo = $photo ?? null;
  $text = $text ?? null;
  $placeholder = $placeholder ?? \App\Support\Letter::text('placeholder');
@endphp
<figure class="polaroid {{ \App\Support\Letter::filterClass() }}{{ $photo ? '' : ' no-photo' }} {{ \App\Support\Letter::sizeClass($text) }}"
        style="{{ \App\Support\Letter::style() }}" data-placeholder="{{ $placeholder }}" @isset($id) id="{{ $id }}" @endisset>
  <span class="pol-photo">
    <img @if($photo) src="{{ $photo }}" @else hidden @endif alt="">
    <span class="pol-note{{ ! $photo && ! $text ? ' is-placeholder' : '' }}">{{ $photo ? '' : ($text ?: $placeholder) }}</span>
  </span>
  <figcaption class="pol-text">{{ $photo ? $text : '' }}</figcaption>
</figure>
