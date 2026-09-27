/* The company's own box, drawn while they decide.
 *
 * A company will not order five hundred of anything it has not seen, and it
 * will not wait for us to make a mockup by hand before it can picture one.
 * So the box is drawn here, in their browser, as they upload the logo and
 * pick the colour: the front with their mark, their line and their number,
 * the back with the QR code that will be printed on it.
 *
 * Everything stays on their machine until they actually send the request.
 *
 * Each init() is its own box with its own state — the page shows two of them
 * at once, one as a still life and one the visitor plays with, and they must
 * not share a thing.
 */
window.NefisCorporate = (function () {
  'use strict';

  /* The real box: 45 × 90 mm on the face it is printed on. */
  var FACE = { w: 45, h: 90 };
  var SCALE = 12;                      // px per mm — enough to read on a laptop
  var PAD = 5;                         // mm of quiet margin inside the face

  /* The phone this is looked at on draws San Francisco; the rest stand in. */
  var FONT = '-apple-system, BlinkMacSystemFont, "SF Pro Text", Inter, "Segoe UI", sans-serif';

  /* Fetched once for the whole page, however many boxes ask for it. */
  var qrWanted = [];
  var qrAsked = false;

  function whenQr(redraw, src) {
    if (typeof window.qrcode === 'function' || !src) return;
    qrWanted.push(redraw);
    if (qrAsked) return;
    qrAsked = true;

    var script = document.createElement('script');
    script.src = src;
    script.onload = function () { qrWanted.forEach(function (fn) { fn(); }); qrWanted = []; };
    document.head.appendChild(script);
  }

  function luminance(hex) {
    var n = parseInt(String(hex).replace('#', '').slice(0, 6), 16);
    if (isNaN(n)) return 0;
    return (0.299 * (n >> 16 & 255) + 0.587 * (n >> 8 & 255) + 0.114 * (n & 255)) / 255;
  }

  function makeQr(text) {
    if (typeof window.qrcode !== 'function') return null;
    try {
      var q = window.qrcode(0, 'M');
      q.addData(String(text));
      q.make();
      return q;
    } catch (e) {
      return null;                     // too long for a code
    }
  }

  /** Fits a picture inside a box without cropping or stretching it. */
  function contain(img, x, y, w, h) {
    var iw = img.naturalWidth || img.width;
    var ih = img.naturalHeight || img.height;
    if (!iw || !ih) return null;
    var k = Math.min(w / iw, h / ih);
    return { x: x + (w - iw * k) / 2, y: y + (h - ih * k) / 2, w: iw * k, h: ih * k };
  }

  /** Lays a line out over as many rows as it needs, up to a limit. */
  function wrap(c, text, cx, top, width, lineHeight, maxLines) {
    var words = String(text).split(/\s+/);
    var lines = [];
    var line = '';

    for (var i = 0; i < words.length; i++) {
      var next = line ? line + ' ' + words[i] : words[i];
      if (c.measureText(next).width > width && line) {
        lines.push(line);
        line = words[i];
        if (lines.length === maxLines) break;
      } else {
        line = next;
      }
    }
    if (lines.length < maxLines && line) lines.push(line);

    for (var j = 0; j < lines.length; j++) {
      c.fillText(lines[j], cx, top + j * lineHeight);
    }
  }

  function init(options) {
    var front = document.getElementById(options.front);
    var back = document.getElementById(options.back);

    var state = {
      logo: null,
      color: /^#[0-9a-fA-F]{6}$/.test(options.color || '') ? options.color : '#1B3A6B',
      slogan: '',
      phone: '',
      qr: '',
    };

    /* Ink that can be read on whatever colour the box is dyed. */
    function ink() {
      return luminance(state.color) > 0.62 ? '#15130F' : '#FFFFFF';
    }

    function prepare(canvas) {
      var dpr = Math.min(2, window.devicePixelRatio || 1);
      canvas.width = FACE.w * SCALE * dpr;
      canvas.height = FACE.h * SCALE * dpr;
      canvas.style.aspectRatio = FACE.w + ' / ' + FACE.h;
      var c = canvas.getContext('2d');
      c.setTransform(dpr * SCALE, 0, 0, dpr * SCALE, 0, 0);   // now drawing in mm
      return c;
    }

    /* The card itself: the colour, a soft sheen down one side, a thin edge. */
    function base(c) {
      c.clearRect(0, 0, FACE.w, FACE.h);
      c.fillStyle = state.color;
      c.fillRect(0, 0, FACE.w, FACE.h);

      var sheen = c.createLinearGradient(0, 0, FACE.w, FACE.h);
      sheen.addColorStop(0, 'rgba(255,255,255,.14)');
      sheen.addColorStop(0.45, 'rgba(255,255,255,0)');
      sheen.addColorStop(1, 'rgba(0,0,0,.10)');
      c.fillStyle = sheen;
      c.fillRect(0, 0, FACE.w, FACE.h);

      c.strokeStyle = 'rgba(0,0,0,.18)';
      c.lineWidth = 0.3;
      c.strokeRect(0.15, 0.15, FACE.w - 0.3, FACE.h - 0.3);
    }

    function drawFront() {
      if (!front) return;
      var c = prepare(front);
      base(c);

      var room = FACE.w - PAD * 2;
      c.fillStyle = ink();
      c.textAlign = 'center';

      if (state.logo) {
        var at = contain(state.logo, PAD, PAD + 4, room, 26);
        if (at) c.drawImage(state.logo, at.x, at.y, at.w, at.h);
      } else {
        c.save();
        c.setLineDash([1.6, 1.4]);
        c.strokeStyle = ink();
        c.globalAlpha = 0.45;
        c.lineWidth = 0.4;
        c.strokeRect(PAD, PAD + 4, room, 26);
        c.globalAlpha = 0.7;
        c.font = '600 3.2px ' + FONT;
        c.fillText('LOQONUZ', FACE.w / 2, PAD + 18.5);
        c.restore();
      }

      if (state.slogan) {
        c.font = '600 3.6px ' + FONT;
        wrap(c, state.slogan.toUpperCase(), FACE.w / 2, FACE.h - 26, room, 4.6, 3);
      }

      if (state.phone) {
        c.globalAlpha = 0.85;
        c.font = '500 3.2px ' + FONT;
        c.fillText(state.phone, FACE.w / 2, FACE.h - PAD - 2);
        c.globalAlpha = 1;
      }
    }

    function drawBack() {
      if (!back) return;
      var c = prepare(back);
      base(c);

      var side = 26;
      var x = (FACE.w - side) / 2;
      var y = (FACE.h - side) / 2 - 4;

      var code = state.qr ? makeQr(state.qr) : null;
      if (code) {
        /* The code is printed on white whatever the box is dyed: a scanner
           wants that contrast, and a dark code on a dark box does not read. */
        c.fillStyle = '#FFFFFF';
        c.fillRect(x - 2, y - 2, side + 4, side + 4);
        var n = code.getModuleCount();
        var cell = side / n;
        c.fillStyle = '#000000';
        for (var r = 0; r < n; r++) {
          for (var col = 0; col < n; col++) {
            if (code.isDark(r, col)) c.fillRect(x + col * cell, y + r * cell, cell + 0.02, cell + 0.02);
          }
        }
      } else {
        c.save();
        c.setLineDash([1.6, 1.4]);
        c.strokeStyle = ink();
        c.globalAlpha = 0.45;
        c.lineWidth = 0.4;
        c.strokeRect(x - 2, y - 2, side + 4, side + 4);
        c.restore();
      }

      c.fillStyle = ink();
      c.textAlign = 'center';
      c.globalAlpha = 0.85;
      c.font = '500 3px ' + FONT;
      c.fillText(state.qr ? 'Kodu oxudun' : 'QR kod', FACE.w / 2, y + side + 8);
      c.globalAlpha = 1;
    }

    function draw() {
      drawFront();
      drawBack();
      /* Whoever is showing this box somewhere else — the photographs of a
         counter, say — is told it has changed. */
      if (options.onDraw) options.onDraw(front, back, state);
    }

    /** The logo the company chose, read straight from their own machine. */
    function setLogo(file) {
      if (!file) { state.logo = null; draw(); return Promise.resolve(false); }

      return new Promise(function (resolve) {
        var url = URL.createObjectURL(file);
        var img = new Image();
        img.onload = function () { URL.revokeObjectURL(url); state.logo = img; draw(); resolve(true); };
        img.onerror = function () { URL.revokeObjectURL(url); state.logo = null; draw(); resolve(false); };
        img.src = url;
      });
    }

    function set(key, value) {
      if (key === 'color' && !/^#[0-9a-fA-F]{6}$/.test(String(value))) return;
      state[key] = value;
      draw();
    }

    draw();
    whenQr(draw, options.qrScript);

    return { set: set, setLogo: setLogo, state: state, redraw: draw };
  }

  return { init: init };
})();
