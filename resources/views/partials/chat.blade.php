{{-- The chat in the corner of the page. What is written here goes to the
     shop's Telegram, and the shop's answer comes back into the same window.
     A screenshot can be picked, dragged in, or simply pasted. --}}
<div class="chat" id="chat">
  <button type="button" class="chat-open" id="chat-open" aria-label="{{ __('Bizə yazın') }}" aria-expanded="false">
    {{-- A bubble the shape of a chocolate square, with three dots that answer
         one after the other, as if someone were already typing. --}}
    <svg class="chat-ico" viewBox="0 0 24 24" fill="none" aria-hidden="true">
      <path d="M5.6 3.6h12.8a2 2 0 0 1 2 2v8.6a2 2 0 0 1-2 2h-6.1l-4.4 3.6a.6.6 0 0 1-1-.47v-3.13H5.6a2 2 0 0 1-2-2V5.6a2 2 0 0 1 2-2Z"
            fill="currentColor" opacity=".18"/>
      <path d="M5.6 3.6h12.8a2 2 0 0 1 2 2v8.6a2 2 0 0 1-2 2h-6.1l-4.4 3.6a.6.6 0 0 1-1-.47v-3.13H5.6a2 2 0 0 1-2-2V5.6a2 2 0 0 1 2-2Z"
            stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
      <path d="M12 3.9v12M3.9 9.9h16.2" stroke="currentColor" stroke-width=".9" opacity=".35"/>
      <circle class="cd cd1" cx="8.4" cy="9.9" r="1.15" fill="currentColor"/>
      <circle class="cd cd2" cx="12" cy="9.9" r="1.15" fill="currentColor"/>
      <circle class="cd cd3" cx="15.6" cy="9.9" r="1.15" fill="currentColor"/>
    </svg>
    <span class="chat-dot" id="chat-dot" hidden></span>
  </button>

  <div class="chat-panel" id="chat-panel" hidden>
    <div class="chat-head">
      <span class="chat-mark"><img src="/images/logo.svg" alt="Nefis"></span>
      <span class="chat-who">
        <b>{{ __('Nefis') }}</b>
        <small>{{ \App\Support\Contact::hours() ?: __('Sualınızı yazın, cavab yazacağıq') }}</small>
      </span>
      <button type="button" class="chat-close" id="chat-close" aria-label="{{ __('Bağla') }}">✕</button>
    </div>

    <div class="chat-log" id="chat-log">
      <div class="chat-line shop"><span>{{ __('Salam! Nə soruşmaq istəyirsiniz? İstəsəniz ekran şəkli də göndərə bilərsiniz.') }}</span></div>
    </div>

    <form class="chat-form" id="chat-form">
      @csrf
      <div class="chat-shot" id="chat-shot" hidden>
        <img id="chat-shot-img" alt="">
        <button type="button" id="chat-shot-drop" aria-label="{{ __('Sil') }}">✕</button>
      </div>
      <div class="chat-row">
        <label class="chat-clip" title="{{ __('Şəkil əlavə edin') }}">
          <input type="file" id="chat-file" accept="image/*" hidden>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M20 11.5 12.2 19.3a4.6 4.6 0 0 1-6.5-6.5l8.2-8.2a3.1 3.1 0 1 1 4.4 4.4l-8.2 8.2a1.5 1.5 0 1 1-2.2-2.2l7.5-7.5"/>
          </svg>
        </label>
        <textarea id="chat-text" rows="1" maxlength="1000" placeholder="{{ __('Mesajınızı yazın…') }}"></textarea>
        <button type="submit" class="chat-send" id="chat-send" aria-label="{{ __('Göndər') }}">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M4 12 20 4l-8 16-2-6-6-2Z"/>
          </svg>
        </button>
      </div>
      <p class="chat-hint" id="chat-hint" hidden></p>
    </form>
  </div>
</div>

@push('chat_script')
<script>
(function(){
  var box = document.getElementById('chat');
  if (!box) return;
  var open = document.getElementById('chat-open');
  var panel = document.getElementById('chat-panel');
  var log = document.getElementById('chat-log');
  var form = document.getElementById('chat-form');
  var text = document.getElementById('chat-text');
  var file = document.getElementById('chat-file');
  var shot = document.getElementById('chat-shot');
  var shotImg = document.getElementById('chat-shot-img');
  var hint = document.getElementById('chat-hint');
  var dot = document.getElementById('chat-dot');
  var token = form.querySelector('input[name="_token"]').value;
  var last = 0, timer = null, picked = null;

  function line(m){
    var el = document.createElement('div');
    el.className = 'chat-line ' + (m.side === 'shop' ? 'shop' : 'me');
    if (m.image){
      var a = document.createElement('a');
      a.href = m.image; a.target = '_blank'; a.rel = 'noopener';
      var img = document.createElement('img');
      img.src = m.image; img.alt = '';
      a.appendChild(img);
      el.appendChild(a);
    }
    if (m.body){
      var s = document.createElement('span');
      s.textContent = m.body;
      el.appendChild(s);
    }
    if (m.at){
      var t = document.createElement('i');
      t.textContent = m.at;
      el.appendChild(t);
    }
    log.appendChild(el);
    log.scrollTop = log.scrollHeight;
  }

  function poll(){
    fetch(@json(route('chat.poll')) + '?after=' + last, { headers:{ 'Accept':'application/json' } })
      .then(function(r){ return r.ok ? r.json() : null; })
      .then(function(d){
        if (!d || !d.messages) return;
        d.messages.forEach(function(m){
          if (m.id <= last) return;
          last = m.id;
          /* The visitor's own lines are already on the screen. */
          if (m.side === 'shop'){ line(m); if (panel.hidden) dot.hidden = false; }
        });
      })
      .catch(function(){});
  }

  function watch(on){
    if (timer) clearInterval(timer);
    timer = on ? setInterval(poll, 5000) : setInterval(poll, 25000);
  }

  open.addEventListener('click', function(){
    panel.hidden = !panel.hidden;
    open.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
    if (!panel.hidden){ dot.hidden = true; text.focus(); poll(); }
    watch(!panel.hidden);
  });
  document.getElementById('chat-close').addEventListener('click', function(){
    panel.hidden = true; open.setAttribute('aria-expanded', 'false'); watch(false);
  });

  function take(f){
    if (!f || !/^image\//.test(f.type)) return;
    if (f.size > 5 * 1024 * 1024){ say(@json(__('Şəkil 5 MB-dan böyük ola bilməz.'))); return; }
    picked = f;
    shotImg.src = URL.createObjectURL(f);
    shot.hidden = false;
  }
  function say(t){ hint.textContent = t; hint.hidden = !t; }

  file.addEventListener('change', function(){ take(file.files[0]); });
  document.getElementById('chat-shot-drop').addEventListener('click', function(){
    picked = null; shot.hidden = true; file.value = '';
  });
  /* A screenshot is usually in the clipboard, not in a folder. */
  panel.addEventListener('paste', function(e){
    var items = (e.clipboardData || {}).items || [];
    for (var i = 0; i < items.length; i++){
      if (items[i].type.indexOf('image') === 0){ take(items[i].getAsFile()); e.preventDefault(); break; }
    }
  });
  panel.addEventListener('dragover', function(e){ e.preventDefault(); });
  panel.addEventListener('drop', function(e){
    e.preventDefault();
    if (e.dataTransfer && e.dataTransfer.files[0]) take(e.dataTransfer.files[0]);
  });

  text.addEventListener('input', function(){
    text.style.height = 'auto';
    text.style.height = Math.min(text.scrollHeight, 110) + 'px';
  });
  text.addEventListener('keydown', function(e){
    if (e.key === 'Enter' && !e.shiftKey){ e.preventDefault(); form.requestSubmit(); }
  });

  form.addEventListener('submit', function(e){
    e.preventDefault();
    var body = text.value.trim();
    if (!body && !picked) return;

    var data = new FormData();
    data.append('_token', token);
    data.append('body', body);
    data.append('page', location.pathname);
    if (picked) data.append('image', picked);

    var mine = { side:'me', body: body, image: picked ? shotImg.src : null, at:'' };
    line(mine);
    text.value = ''; text.style.height = 'auto';
    var sending = picked; picked = null; shot.hidden = true; file.value = '';
    say('');

    fetch(@json(route('chat.send')), { method:'POST', body:data, headers:{ 'Accept':'application/json' } })
      .then(function(r){ return r.json().then(function(d){ return { ok:r.ok, d:d }; }); })
      .then(function(res){
        if (!res.ok){ say(@json(__('Mesaj getmədi, bir azdan yenidən yoxlayın.'))); return; }
        if (res.d && res.d.message) last = Math.max(last, res.d.message.id);
      })
      .catch(function(){ say(@json(__('Mesaj getmədi, bir azdan yenidən yoxlayın.'))); })
      .finally(function(){ if (sending) URL.revokeObjectURL(mine.image); });
  });

  /* Even closed, the window listens now and then: an answer lights the dot. */
  poll();
  watch(false);
})();
</script>
@endpush
