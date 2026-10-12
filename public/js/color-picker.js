/* A colour picker the way Photoshop does it, for the two editors.
 *
 * The owner chooses his colours in Photoshop and asked for the same dialog in
 * the box editor. What he had was Chrome's own popover — a square, a hue strip
 * and R/G/B — with no field to type a hex into, so a colour already decided in
 * Photoshop could not be carried across at all.
 *
 * This takes over every `<input type="color">` on the page without either
 * editor knowing: one capturing listener stops the browser's own picker,
 * shows this dialog instead, and on «Qəbul et» writes the value back and
 * fires `input` and `change` exactly as the native control would. Both
 * editors already listen for those, so nothing else had to move.
 *
 * Nothing changes on the box until he accepts, which is how Photoshop
 * behaves — the two swatches show the new colour against the old one — and it
 * keeps a drag across the square out of the undo history.
 *
 * Opt out on a single input with `data-native`.
 */
(function () {
  if (window.NefisColor) { return; }

  var RECENT_KEY = 'nefis-colors-recent';
  var MOST_RECENT = 14;

  /* ------------------------------------------------------------ colours */

  function clamp(v, lo, hi) { return v < lo ? lo : (v > hi ? hi : v); }

  /** '#ffca47', 'ffca47', '#fc4' → [255, 202, 71]; anything else → null. */
  function parseHex(text) {
    var s = String(text == null ? '' : text).trim().replace(/^#/, '');
    if (/^[0-9a-fA-F]{3}$/.test(s)) {
      s = s[0] + s[0] + s[1] + s[1] + s[2] + s[2];
    }
    if (!/^[0-9a-fA-F]{6}$/.test(s)) { return null; }

    return [parseInt(s.slice(0, 2), 16), parseInt(s.slice(2, 4), 16), parseInt(s.slice(4, 6), 16)];
  }

  function toHex(rgb) {
    return '#' + rgb.map(function (v) {
      return ('0' + clamp(Math.round(v), 0, 255).toString(16)).slice(-2);
    }).join('');
  }

  /** h 0–360, s 0–100, v 0–100 → [r, g, b] 0–255. */
  function hsvToRgb(h, s, v) {
    h = ((h % 360) + 360) % 360;
    s = clamp(s, 0, 100) / 100;
    v = clamp(v, 0, 100) / 100;

    var c = v * s;
    var x = c * (1 - Math.abs(((h / 60) % 2) - 1));
    var m = v - c;
    var p = h < 60 ? [c, x, 0] : h < 120 ? [x, c, 0] : h < 180 ? [0, c, x]
      : h < 240 ? [0, x, c] : h < 300 ? [x, 0, c] : [c, 0, x];

    return p.map(function (n) { return Math.round((n + m) * 255); });
  }

  function rgbToHsv(rgb) {
    var r = rgb[0] / 255, g = rgb[1] / 255, b = rgb[2] / 255;
    var max = Math.max(r, g, b), min = Math.min(r, g, b), d = max - min;
    var h = 0;

    if (d !== 0) {
      if (max === r) { h = ((g - b) / d) % 6; }
      else if (max === g) { h = ((b - r) / d) + 2; }
      else { h = ((r - g) / d) + 4; }
      h *= 60;
      if (h < 0) { h += 360; }
    }

    return [h, max === 0 ? 0 : (d / max) * 100, max * 100];
  }

  /* ------------------------------------------------- what he used before */

  function recent() {
    try {
      var saved = JSON.parse(localStorage.getItem(RECENT_KEY) || '[]');

      return Array.isArray(saved) ? saved.filter(parseHex).slice(0, MOST_RECENT) : [];
    } catch (e) { return []; }
  }

  function remember(hex) {
    try {
      var list = recent().filter(function (c) { return c.toLowerCase() !== hex.toLowerCase(); });
      list.unshift(hex);
      localStorage.setItem(RECENT_KEY, JSON.stringify(list.slice(0, MOST_RECENT)));
    } catch (e) { /* a private window keeps no history, and that is fine */ }
  }

  /* -------------------------------------------------------- the dialog */

  var veil = null, ui = null;
  var state = { h: 0, s: 0, v: 0 };
  var current = '#000000';          // the colour it opened on
  var onDone = null;

  function build() {
    veil = document.createElement('div');
    veil.className = 'npick-veil';
    veil.setAttribute('hidden', '');
    veil.innerHTML =
      '<div class="npick" role="dialog" aria-modal="true" aria-label="Rəng seçimi">'
      + '<div class="npick-head"><span>Rəng seçimi</span><span class="npick-spacer"></span>'
      + '<button type="button" class="npick-x" data-act="cancel" title="Bağla">&times;</button></div>'
      + '<div class="npick-body">'
      + '<div class="npick-sb" tabindex="0" role="slider" aria-label="Doyğunluq və parlaqlıq">'
      + '<div class="npick-sb-dot"></div></div>'
      + '<div class="npick-hue" tabindex="0" role="slider" aria-label="Rəng çaları"><div class="npick-hue-dot"></div></div>'
      + '<div class="npick-side">'
      + '<div class="npick-compare">'
      + '<div class="npick-stack">'
      + '<span class="npick-tag">yeni</span>'
      + '<div class="npick-swatches">'
      + '<div class="npick-new"></div>'
      + '<div class="npick-old" title="Əvvəlki rəngə qayıt"></div>'
      + '</div>'
      + '<span class="npick-tag">cаri</span>'
      + '</div>'
      + '<button type="button" class="npick-eye" data-act="eye" title="Ekrandan rəng götür" hidden>'
      + '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'
      + '<path d="m19 3 2 2-9 9-2-2 9-9Z"/><path d="m10 12-6 6v2h2l6-6"/></svg></button>'
      + '</div>'
      + '<div class="npick-rows">'
      + '<div class="npick-f"><label for="npick-h">H</label><input id="npick-h" type="number" min="0" max="360" step="1" data-ch="h"><span class="npick-unit">°</span></div>'
      + '<div class="npick-f"><label for="npick-r">R</label><input id="npick-r" type="number" min="0" max="255" step="1" data-ch="r"><span class="npick-unit"></span></div>'
      + '<div class="npick-f"><label for="npick-s">S</label><input id="npick-s" type="number" min="0" max="100" step="1" data-ch="s"><span class="npick-unit">%</span></div>'
      + '<div class="npick-f"><label for="npick-g">G</label><input id="npick-g" type="number" min="0" max="255" step="1" data-ch="g"><span class="npick-unit"></span></div>'
      + '<div class="npick-f"><label for="npick-b">B</label><input id="npick-b" type="number" min="0" max="100" step="1" data-ch="v"><span class="npick-unit">%</span></div>'
      + '<div class="npick-f"><label for="npick-bb">B</label><input id="npick-bb" type="number" min="0" max="255" step="1" data-ch="b"><span class="npick-unit"></span></div>'
      + '</div>'
      + '<div class="npick-hexrow"><label for="npick-hex">#</label>'
      + '<input id="npick-hex" type="text" spellcheck="false" autocomplete="off" maxlength="7" data-ch="hex"></div>'
      + '<div class="npick-recent"></div>'
      + '<p class="npick-note">Photoshop-dakı « # » qutusuna yazdığınız rəngi bura da yaza bilərsiniz.</p>'
      + '</div></div>'
      + '<div class="npick-foot">'
      + '<button type="button" class="npick-btn" data-act="reset">Əvvəlki</button>'
      + '<button type="button" class="npick-btn" data-act="cancel">Ləğv et</button>'
      + '<button type="button" class="npick-btn primary" data-act="ok">Qəbul et</button>'
      + '</div></div>';

    document.body.appendChild(veil);

    ui = {
      card: veil.querySelector('.npick'),
      sb: veil.querySelector('.npick-sb'),
      sbDot: veil.querySelector('.npick-sb-dot'),
      hue: veil.querySelector('.npick-hue'),
      hueDot: veil.querySelector('.npick-hue-dot'),
      fresh: veil.querySelector('.npick-new'),
      old: veil.querySelector('.npick-old'),
      eye: veil.querySelector('.npick-eye'),
      hex: veil.querySelector('#npick-hex'),
      recent: veil.querySelector('.npick-recent'),
      nums: {}
    };
    veil.querySelectorAll('[data-ch]').forEach(function (el) {
      if (el.dataset.ch !== 'hex') { ui.nums[el.dataset.ch] = el; }
    });

    if (window.EyeDropper) { ui.eye.hidden = false; }

    wire();
  }

  /* ------------------------------------------------------------- paint */

  function rgb() { return hsvToRgb(state.h, state.s, state.v); }
  function hex() { return toHex(rgb()); }

  function paint(skip) {
    var now = rgb();

    ui.sb.style.background =
      'linear-gradient(to top, #000, rgba(0,0,0,0)),'
      + 'linear-gradient(to right, #fff, rgba(255,255,255,0)),'
      + 'hsl(' + state.h + ', 100%, 50%)';
    ui.sbDot.style.left = state.s + '%';
    ui.sbDot.style.top = (100 - state.v) + '%';
    ui.hueDot.style.top = (state.h / 360 * 100) + '%';
    ui.fresh.style.background = toHex(now);

    if (skip !== 'h') { ui.nums.h.value = Math.round(state.h); }
    if (skip !== 's') { ui.nums.s.value = Math.round(state.s); }
    if (skip !== 'v') { ui.nums.v.value = Math.round(state.v); }
    if (skip !== 'r') { ui.nums.r.value = now[0]; }
    if (skip !== 'g') { ui.nums.g.value = now[1]; }
    if (skip !== 'b') { ui.nums.b.value = now[2]; }
    if (skip !== 'hex') { ui.hex.value = toHex(now).slice(1); ui.hex.classList.remove('bad'); }
  }

  function setRgb(value, skip) {
    var hsv = rgbToHsv(value);
    // A black or a grey has no hue of its own; keeping the one he was on
    // stops the square jumping back to red every time he drags to the bottom.
    state.h = (hsv[1] === 0 || hsv[2] === 0) ? state.h : hsv[0];
    state.s = hsv[1];
    state.v = hsv[2];
    paint(skip);
  }

  function drawRecent() {
    var list = recent();
    ui.recent.innerHTML = '';
    list.forEach(function (colour) {
      var b = document.createElement('button');
      b.type = 'button';
      b.style.background = colour;
      b.title = colour;
      b.addEventListener('click', function () { setRgb(parseHex(colour)); });
      ui.recent.appendChild(b);
    });
  }

  /* ------------------------------------------------------------- input */

  function fromPointer(el, event, vertical) {
    var box = el.getBoundingClientRect();
    var x = clamp((event.clientX - box.left) / box.width, 0, 1);
    var y = clamp((event.clientY - box.top) / box.height, 0, 1);

    if (vertical) {
      state.h = y * 360;
    } else {
      state.s = x * 100;
      state.v = (1 - y) * 100;
    }
    paint();
  }

  function dragger(el, vertical) {
    el.addEventListener('pointerdown', function (e) {
      e.preventDefault();
      el.setPointerCapture(e.pointerId);
      fromPointer(el, e, vertical);

      function move(ev) { fromPointer(el, ev, vertical); }
      function up() {
        el.removeEventListener('pointermove', move);
        el.removeEventListener('pointerup', up);
        el.removeEventListener('pointercancel', up);
      }
      el.addEventListener('pointermove', move);
      el.addEventListener('pointerup', up);
      el.addEventListener('pointercancel', up);
    });

    el.addEventListener('keydown', function (e) {
      var step = e.shiftKey ? 10 : 1;
      var moved = true;

      if (vertical) {
        if (e.key === 'ArrowUp') { state.h = (state.h - step + 360) % 360; }
        else if (e.key === 'ArrowDown') { state.h = (state.h + step) % 360; }
        else { moved = false; }
      } else {
        if (e.key === 'ArrowLeft') { state.s = clamp(state.s - step, 0, 100); }
        else if (e.key === 'ArrowRight') { state.s = clamp(state.s + step, 0, 100); }
        else if (e.key === 'ArrowUp') { state.v = clamp(state.v + step, 0, 100); }
        else if (e.key === 'ArrowDown') { state.v = clamp(state.v - step, 0, 100); }
        else { moved = false; }
      }

      if (moved) { e.preventDefault(); paint(); }
    });
  }

  function wire() {
    dragger(ui.sb, false);
    dragger(ui.hue, true);

    ui.hex.addEventListener('input', function () {
      var parsed = parseHex(ui.hex.value);
      ui.hex.classList.toggle('bad', !parsed && ui.hex.value.trim() !== '');
      if (parsed) { setRgb(parsed, 'hex'); }
    });
    // Pasting «#ffca47» is the whole point, so the hash is tolerated and then
    // tidied away rather than refused.
    ui.hex.addEventListener('blur', function () { paint(); });

    Object.keys(ui.nums).forEach(function (key) {
      ui.nums[key].addEventListener('input', function () {
        var el = ui.nums[key];
        if (el.value === '') { return; }
        var n = +el.value;
        if (isNaN(n)) { return; }

        if (key === 'h') { state.h = ((n % 360) + 360) % 360; paint('h'); }
        else if (key === 's') { state.s = clamp(n, 0, 100); paint('s'); }
        else if (key === 'v') { state.v = clamp(n, 0, 100); paint('v'); }
        else {
          var now = rgb();
          now[key === 'r' ? 0 : key === 'g' ? 1 : 2] = clamp(n, 0, 255);
          setRgb(now, key);
        }
      });
      ui.nums[key].addEventListener('blur', function () { paint(); });
    });

    ui.old.addEventListener('click', function () { setRgb(parseHex(current) || [0, 0, 0]); });

    veil.addEventListener('click', function (e) {
      if (e.target === veil) { close(null); return; }

      var act = e.target.closest('[data-act]');
      if (!act) { return; }

      if (act.dataset.act === 'ok') { close(hex()); }
      else if (act.dataset.act === 'cancel') { close(null); }
      else if (act.dataset.act === 'reset') { setRgb(parseHex(current) || [0, 0, 0]); }
      else if (act.dataset.act === 'eye') { dropper(); }
    });

    veil.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { e.preventDefault(); close(null); }
      else if (e.key === 'Enter' && e.target.tagName !== 'BUTTON') { e.preventDefault(); close(hex()); }
    });

    /* Nothing that happens inside this dialog is meant for the editor behind
       it. Both editors listen on `document`: an arrow key would nudge the
       selected layer while he is walking the square, Escape would drop the
       selection out from under him, and the wheel would zoom the canvas. */
    ['pointerdown', 'mousedown', 'click', 'keydown', 'keyup', 'wheel'].forEach(function (name) {
      veil.addEventListener(name, function (e) { e.stopPropagation(); });
    });
  }

  function dropper() {
    if (!window.EyeDropper) { return; }

    new window.EyeDropper().open()
      .then(function (result) {
        var parsed = parseHex(result.sRGBHex);
        if (parsed) { setRgb(parsed); }
      })
      .catch(function () { /* he pressed Escape, which is an answer too */ });
  }

  /* -------------------------------------------------------- open, close */

  function open(colour, done) {
    if (!veil) { build(); }

    current = parseHex(colour) ? toHex(parseHex(colour)) : '#000000';
    onDone = done;
    ui.old.style.background = current;
    setRgb(parseHex(current));
    drawRecent();

    veil.removeAttribute('hidden');
    ui.hex.focus();
    ui.hex.select();
  }

  function close(value) {
    veil.setAttribute('hidden', '');
    var done = onDone;
    onDone = null;
    if (done) { done(value); }
  }

  /* ------------------------------- standing in for the browser's own one */

  function ours(el) {
    return el && el.tagName === 'INPUT' && el.type === 'color' && !el.hasAttribute('data-native');
  }

  function take(e) {
    var el = e.target;
    if (!ours(el) || el.disabled || el.readOnly) { return; }

    e.preventDefault();
    e.stopPropagation();

    if (veil && !veil.hasAttribute('hidden')) { return; }

    // The input is held in the closure rather than in a variable of the
    // dialog's own: closing clears the dialog's state before it calls back.
    var input = el;

    open(el.value, function (chosen) {
      if (chosen === null) { return; }

      input.value = chosen;
      remember(chosen);
      // What the native control fires, in the order it fires it: both editors
      // listen for these two and need no telling that anything has changed.
      input.dispatchEvent(new Event('input', { bubbles: true }));
      input.dispatchEvent(new Event('change', { bubbles: true }));
    });
  }

  ['mousedown', 'click'].forEach(function (name) {
    document.addEventListener(name, take, true);
  });
  document.addEventListener('keydown', function (e) {
    if ((e.key === 'Enter' || e.key === ' ') && ours(document.activeElement)) {
      e.preventDefault();
      take({ target: document.activeElement, preventDefault: function () {}, stopPropagation: function () {} });
    }
  }, true);

  window.NefisColor = { open: open, parseHex: parseHex, toHex: toHex, hsvToRgb: hsvToRgb, rgbToHsv: rgbToHsv };
})();
