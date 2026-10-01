<!doctype html>
<html lang="az">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Lokasiya xəritəsi · sifariş #{{ $item->order_id }}</title>
<style>
  body{ margin:0; background:#15171d; color:#e8eaf0; font:15px/1.5 Inter, system-ui, sans-serif; padding:1.5rem; }
  .wrap{ max-width:56rem; margin:0 auto; }
  h1{ font-size:1.2rem; margin:0 0 .35rem; }
  .facts{ color:#aab0bf; font-size:.9rem; margin-bottom:1rem; }
  .facts b{ color:#e8eaf0; font-weight:600; }
  canvas{ width:100%; max-width:34rem; height:auto; border-radius:.6rem; display:block; background:#0d0d0d; }
  .btn{ display:inline-block; margin-top:1rem; background:#e8792b; color:#fff; border:0; border-radius:.5rem;
        padding:.65rem 1.1rem; font:inherit; font-weight:600; cursor:pointer; }
  .btn[disabled]{ opacity:.5; cursor:default; }
  .note{ color:#8d94a6; font-size:.82rem; margin-top:.75rem; max-width:34rem; }
</style>
</head>
<body>
<div class="wrap">
  <h1>Lokasiya xəritəsi — sifariş #{{ $item->order_id }}</h1>
  <p class="facts">
    @if(! empty($spot['place'])) <b>{{ $spot['place'] }}</b> · @endif
    {{ $coordinates }} · yaxınlıq <b>{{ $zoom }}</b>
    · {{ ['heart' => 'ürək', 'home' => 'ev', 'full' => 'tam sahə', 'rectangle' => 'düzbucaqlı'][$shape] ?? 'dairə' }}, {{ $style }}
    @if(! empty($spot['date'])) · <b>{{ \Illuminate\Support\Carbon::parse($spot['date'])->format('d.m.Y') }}</b>@if(! empty($spot['withTime']) && ! empty($spot['time'])), {{ $spot['time'] }}@endif @endif
  </p>
  <canvas id="map" width="{{ $width }}" height="{{ $height }}"></canvas>
  <button type="button" class="btn" id="save" disabled>Küçələr gəlir…</button>
  @if($stale)
    <p class="note" style="color:#f0a350;">Diqqət: bu dizaynda artıq xəritə pəncərəsi yoxdur — forma standart götürülüb.
      Sifariş veriləndəki görünüşü dizaynın tarixçəsindən yoxlayın.</p>
  @endif
  <p class="note">Şəkil burada, brauzerdə çəkilir — saxlanılan yalnız yer və yaxınlıqdır, ona görə istənilən ölçüdə yenidən almaq olar.</p>
  <p class="note">Çapda bu sətir də olmalıdır: <b>{{ \App\Support\StreetMap::credit() }}</b></p>
</div>
<script src="{{ asset('js/street-map.js') }}?v={{ \App\Support\Assets::version('js/street-map.js') }}"></script>
<script>
  var canvas = document.getElementById('map');
  var save = document.getElementById('save');

  function paint(){
    NefisStreetMap.draw(canvas, {
      lat: @json((float) $spot['lat']), lon: @json((float) $spot['lon']), zoom: @json($zoom),
      shape: @json($shape), style: @json($style),
      marker: @json($marker), pin: @json($pin),
      size: Math.max(canvas.width, canvas.height),
      box: { x: 0, y: 0, w: canvas.width, h: canvas.height },
      /* The press wants every street: the biggest picture the service draws,
         at twice the pixels. */
      quality: 2000, scale: 2,
      onReady: function(){
        paint();
        save.disabled = false;
        save.textContent = 'Çap üçün yüklə ({{ max($width, $height) }} px)';
      }
    });
  }
  paint();

  save.addEventListener('click', function(){
    canvas.toBlob(function(b){
      var a = document.createElement('a');
      a.href = URL.createObjectURL(b);
      a.download = 'lokasiya-{{ $item->order_id }}-{{ $item->id }}.png';
      a.click();
      setTimeout(function(){ URL.revokeObjectURL(a.href); }, 5000);
    }, 'image/png');
  });
</script>
</body>
</html>
