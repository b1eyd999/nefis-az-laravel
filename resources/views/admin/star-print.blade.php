<!doctype html>
<html lang="az">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Ulduz xəritəsi · sifariş #{{ $item->order_id }}</title>
<style>
  body{ margin:0; background:#15171d; color:#e8eaf0; font:15px/1.5 Inter, system-ui, sans-serif; padding:1.5rem; }
  .wrap{ max-width:56rem; margin:0 auto; }
  h1{ font-size:1.2rem; margin:0 0 .35rem; }
  .facts{ color:#aab0bf; font-size:.9rem; margin-bottom:1rem; }
  .facts b{ color:#e8eaf0; font-weight:600; }
  canvas{ width:100%; max-width:34rem; height:auto; border-radius:.6rem; display:block; }
  .btn{ display:inline-block; margin-top:1rem; background:#e8792b; color:#fff; border:0; border-radius:.5rem;
        padding:.65rem 1.1rem; font:inherit; font-weight:600; cursor:pointer; }
  .note{ color:#8d94a6; font-size:.82rem; margin-top:.75rem; max-width:34rem; }
</style>
</head>
<body>
<div class="wrap">
  <h1>Ulduz xəritəsi — sifariş #{{ $item->order_id }}</h1>
  <p class="facts">
    <b>{{ \Illuminate\Support\Carbon::parse($sky['date'])->format('d.m.Y') }}</b>, saat <b>{{ $sky['time'] }}</b>
    @if(! empty($sky['place'])) · <b>{{ $sky['place'] }}</b> @endif
    · {{ $coordinates }} · {{ ['heart' => 'ürək', 'full' => 'tam sahə', 'rectangle' => 'düzbucaqlı'][$shape] ?? 'dairə' }}, {{ $style }}
  </p>
  <canvas id="sky" width="{{ $width }}" height="{{ $height }}"></canvas>
  <button type="button" class="btn" id="save">Çap üçün yüklə (3000 px)</button>
  <p class="note">Şəkil burada, brauzerdə çəkilir — saxlanılan yalnız tarix, saat və yerdir, ona görə istənilən ölçüdə yenidən almaq olar.</p>
</div>
<script src="{{ asset('js/star-data.js') }}?v={{ \App\Support\Assets::version('js/star-data.js') }}"></script>
{{-- The constellation names and the Milky Way live here; without it both
     switches would be quietly ignored on the printed sheet. --}}
<script src="{{ asset('js/star-extra.js') }}?v={{ \App\Support\Assets::version('js/star-extra.js') }}"></script>
<script src="{{ asset('js/star-map.js') }}?v={{ \App\Support\Assets::version('js/star-map.js') }}"></script>
<script>
  var canvas = document.getElementById('sky');
  NefisStarMap.draw(canvas, {
    date: @json($sky['date']), time: @json($sky['time']), tzOffset: @json($sky['tz'] ?? 4),
    lat: @json((float) $sky['lat']), lon: @json((float) $sky['lon']),
    shape: @json($shape), style: @json($style), ring: @json($ring),
    /* Exactly what was ordered: the switches travel on the order line. */
    lines: @json($look['lines']), labels: @json($look['labels']),
    milkyWay: @json($look['milky']), heart: @json($look['heart']),
    size: Math.min(canvas.width, canvas.height),
    box: { x: 0, y: 0, w: canvas.width, h: canvas.height }
  });
  document.getElementById('save').addEventListener('click', function(){
    canvas.toBlob(function(b){
      var a = document.createElement('a');
      a.href = URL.createObjectURL(b);
      a.download = 'ulduz-{{ $item->order_id }}-{{ $item->id }}.png';
      a.click();
      setTimeout(function(){ URL.revokeObjectURL(a.href); }, 5000);
    }, 'image/png');
  });
</script>
</body>
</html>
