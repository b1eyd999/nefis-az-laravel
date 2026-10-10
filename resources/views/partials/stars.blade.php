{{-- Stars out of five, drawn once and used wherever a rating is shown.
     $of may be a half-way average (4.3), so the last lit star is clipped
     rather than rounded: a 4.3 that drew four stars and a 4.7 that drew five
     would make the same shop look like two different shops. --}}
@php
  $of = max(0, min(\App\Models\Review::MOST, (float) $of));
  $part = round($of / \App\Models\Review::MOST * 100, 2);
@endphp
<span class="stars" role="img"
      aria-label="{{ __(':stars / 5', ['stars' => rtrim(rtrim(number_format($of, 1, '.', ''), '0'), '.')]) }}">
  <span class="stars-dim">★★★★★</span>
  <span class="stars-lit" style="width:{{ $part }}%">★★★★★</span>
</span>
