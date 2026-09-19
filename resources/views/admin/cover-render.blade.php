<!DOCTYPE html>
<html lang="az">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="referrer" content="no-referrer">
<title>Qapaqlar hazırlanır</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
<style>
  :root{ --ink:#1d2433; --muted:#8a93a3; --line:#e3e6ec; --bg:#eef0f4; --accent:#7c3aed; --ok:#16a34a; --danger:#dc2626; }
  *{ box-sizing:border-box; }
  body{ margin:0; min-height:100vh; display:grid; place-items:center; background:var(--bg); font-family:Inter, system-ui, sans-serif; color:var(--ink); padding:16px; }
  .card{ width:100%; max-width:440px; background:#fff; border:1px solid var(--line); border-radius:14px; padding:24px; box-shadow:0 10px 40px -18px rgba(20,24,40,.35); }
  h1{ font-size:17px; margin:0 0 4px; }
  p{ margin:0 0 16px; color:var(--muted); font-size:13px; }
  ul{ list-style:none; margin:0; padding:0; max-height:50vh; overflow:auto; }
  li{ display:flex; justify-content:space-between; gap:12px; padding:8px 0; border-top:1px solid var(--line); font-size:13.5px; }
  li span:last-child{ color:var(--muted); white-space:nowrap; }
  li.ok span:last-child{ color:var(--ok); }
  li.err span:last-child{ color:var(--danger); }
  a.btn{ display:inline-block; margin-top:16px; padding:8px 14px; border-radius:8px; background:var(--accent); color:#fff; text-decoration:none; font-weight:600; font-size:13.5px; }
</style>
</head>
<body>
<div class="card">
  <h1>Kataloq qapaqları hazırlanır…</h1>
  <p>Dizayn seçdiyiniz səhnəyə yerləşdirilir. Səhifəni bağlamayın.</p>
  <ul id="list">
    @foreach($jobs as $job)
      <li><span>{{ $job['name'] }}</span><span>gözləyir</span></li>
    @endforeach
  </ul>
  <a class="btn" id="back" href="{{ $back }}" hidden>Geri qayıt</a>
</div>
<script src="{{ asset('js/scene-render.js') }}"></script>
<script src="{{ asset('js/cover.js') }}"></script>
<script>
(function(){
  var jobs = @json($jobs), back = @json($back);
  var rows = document.querySelectorAll('#list li');
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  NefisCover.runAll(jobs, csrf, function(i, job, err){
    rows[i].className = err ? 'err' : 'ok';
    rows[i].lastChild.textContent = err ? ('alınmadı: ' + err.message) : 'hazırdır ✓';
  }).then(function(failed){
    if (failed) { document.getElementById('back').hidden = false; return; }
    setTimeout(function(){ location.href = back; }, jobs.length ? 700 : 0);
  });
})();
</script>
</body>
</html>
