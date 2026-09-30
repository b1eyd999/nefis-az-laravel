{{-- The pictures on a card: a row of thumbnails, each opening full size. --}}
@php $pictures = $task->pictures(); @endphp
@if($pictures)
  <div class="ph-shots">
    @foreach($pictures as $shot)
      <a href="{{ $shot['url'] }}" target="_blank" rel="noopener">
        <img src="{{ $shot['url'] }}" alt="" loading="lazy">
      </a>
    @endforeach
  </div>
@endif
