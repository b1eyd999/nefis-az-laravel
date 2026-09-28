{{--
  The small notice at the bottom right: what somebody has just ordered.

  Real orders, in the reader's language, with only a first name and one
  initial. It waits until the page has settled, shows one at a time, and a
  visitor who closes it is not shown another for the rest of the visit.
--}}
@php $sales = \App\Support\RecentSales::all(); @endphp

@if($sales)
<div class="sale-toast" id="sale-toast" hidden aria-live="polite">
  <button type="button" class="sale-x" aria-label="{{ __('Bağla') }}">×</button>
  <span class="sale-pic" aria-hidden="true">🍫</span>
  <span class="sale-in">
    <b id="sale-line"></b>
    <i id="sale-ago"></i>
  </span>
</div>

@push('chat_script')
<style>
  .sale-toast{
    position:fixed; right:1rem; bottom:9.5rem; z-index:60; display:flex; align-items:center; gap:.7rem;
    max-width:min(21rem, calc(100vw - 2rem)); padding:.7rem 1.6rem .7rem .7rem;
    background:var(--paper); color:var(--cocoa); border:1px solid var(--line); border-radius:1rem;
    box-shadow:0 18px 40px -20px rgba(58,38,23,.55);
    opacity:0; transform:translateY(12px) scale(.98); pointer-events:none;
    transition:opacity .45s var(--ease), transform .45s var(--ease);
  }
  .sale-toast.on{ opacity:1; transform:none; pointer-events:auto; }
  .sale-toast[hidden]{ display:none; }
  .sale-pic{ flex:none; width:2.4rem; height:2.4rem; border-radius:.75rem; display:grid; place-items:center;
    background:var(--cream-2); font-size:1.25rem; }
  .sale-in{ display:flex; flex-direction:column; gap:.1rem; min-width:0; }
  .sale-in b{ font-size:.8125rem; font-weight:600; line-height:1.35; }
  .sale-in i{ font-style:normal; font-size:.6875rem; color:var(--cocoa-faint); }
  .sale-x{ position:absolute; top:.25rem; right:.4rem; border:0; background:none; color:var(--cocoa-faint);
    font-size:1rem; line-height:1; cursor:pointer; padding:.15rem; }
  .sale-x:hover{ color:var(--cocoa); }
  /* On a phone it sits beside the chat and Instagram buttons, not on top of
     them: the same baseline, and it stops where they start. */
  @media (max-width:640px){ .sale-toast{ left:.75rem; right:5.75rem; bottom:1.25rem; max-width:none; } }
  @media (prefers-reduced-motion:reduce){ .sale-toast{ transition:none; } }
</style>
<script>
(function(){
  var sales = @json($sales, JSON_UNESCAPED_UNICODE);
  var box = document.getElementById('sale-toast');
  if (!box || !sales.length) return;
  if (sessionStorage.getItem('nefis-sales-off') === '1') return;

  var line = document.getElementById('sale-line'), ago = document.getElementById('sale-ago');
  var qtyWord = @json(__('qutu'), JSON_UNESCAPED_UNICODE);
  var ordered = @json(__('sifariş etdi'), JSON_UNESCAPED_UNICODE);
  var i = 0, timer;

  function show(){
    var s = sales[i % sales.length];
    i++;
    line.textContent = s.who + ' · ' + s.qty + ' ' + qtyWord + ' «' + s.what + '» ' + ordered;
    ago.textContent = s.ago || '';
    box.hidden = false;
    requestAnimationFrame(function(){ box.classList.add('on'); });
    timer = setTimeout(hide, 6500);
  }
  function hide(){
    box.classList.remove('on');
    timer = setTimeout(function(){ box.hidden = true; timer = setTimeout(show, 12000); }, 500);
  }
  box.querySelector('.sale-x').addEventListener('click', function(){
    clearTimeout(timer);
    box.classList.remove('on');
    setTimeout(function(){ box.hidden = true; }, 400);
    try { sessionStorage.setItem('nefis-sales-off', '1'); } catch (e) {}
  });

  setTimeout(show, 6000);
})();
</script>
@endpush
@endif
