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
  /* The face the box has had since the page was written, and the one every
     instance keeps until a size says otherwise. A size is millimetres, not a
     ratio, because that is what the owner measures and types. */
  var FACE = { w: 45, h: 90 };
  var SCALE = 12;                      // px per mm — enough to read on a laptop
  var PAD = 5;                         // mm of quiet margin inside the face

  /* The longest side the face is ever drawn at, before the screen's own
     doubling. At 12 px/mm the 160 mm size would be 1920 px tall and, doubled,
     nearly eight megapixels - four of those on one page is a phone's whole
     memory for a picture shown 200 px wide. The face is shown at about a
     fifth of this, so nothing visible is lost. */
  var MAX_PX = 1080;

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
      /* The printed face, in millimetres, and where it sits inside the
         owner's render. Both come from the size the visitor picked. */
      face: { w: FACE.w, h: FACE.h },
      shots: { front: null, back: null },
      rect: { l: 0, t: 0, r: 1, b: 1 },
      /* The same face as four corners, which is what it really is on a
         render of a box standing at an angle. The rectangle above is the
         fallback for a browser without the warper. */
      quad: null,
    };

    /* The warper keeps its working canvases here; one bag per box. */
    var warpCache = {};

    /* The face is drawn off screen and then shown — on its own when there is
       no render, inside the render when there is. Two reasons to keep it
       apart: the photographs of a counter want the printed face alone, not
       the render's shadow warped along with it; and the render's own shape
       has nothing to do with the face's. */
    var faces = { front: document.createElement('canvas'), back: document.createElement('canvas') };

    /* Ink that can be read on whatever colour the box is dyed. */
    function ink() {
      return luminance(state.color) > 0.62 ? '#15130F' : '#FFFFFF';
    }

    /**
     * The same layout, on a face of another shape.
     *
     * The box was drawn for one size, 45 x 90, and the arrangement on it -
     * where the logo sits, how much quiet margin it has, where the line and
     * the number go - was measured in millimetres against that face. Three
     * sizes later those millimetres mean something else: on 40 x 60 the
     * logo's band and the slogan beneath it would have met in the middle,
     * and on 85 x 160 the whole of it would have huddled at the top of a
     * tall empty card.
     *
     * So the layout is stretched with the face rather than kept: `kv` and
     * `kh` scale every length along their own axis, which is an affine
     * stretch of an arrangement that already works and therefore cannot
     * collide at any shape. Type and the thin lines take the smaller of the
     * two, so a letter is never wider, relative to the room it has, than it
     * is on the size this was all measured on.
     *
     * At 45 x 90 all three are 1 and every number below is what it was.
     */
    function metrics() {
      var kv = state.face.h / FACE.h;
      var kh = state.face.w / FACE.w;

      return { kv: kv, kh: kh, type: Math.min(kv, kh) };
    }

    function prepare(canvas) {
      var dpr = Math.min(2, window.devicePixelRatio || 1);
      var scale = Math.min(SCALE, MAX_PX / Math.max(state.face.w, state.face.h));
      canvas.width = Math.round(state.face.w * scale * dpr);
      canvas.height = Math.round(state.face.h * scale * dpr);
      var c = canvas.getContext('2d');
      c.setTransform(dpr * scale, 0, 0, dpr * scale, 0, 0);   // now drawing in mm
      return c;
    }

    /**
     * Put the finished face on the canvas the page shows.
     *
     * With no render that is the face itself. With one, the render is laid
     * down first and the face painted into the rectangle the owner marked —
     * so the edges, the shadow and the depth are his, and the colour and the
     * logo are still the visitor's.
     */
    function present(canvas, faceCv, shot) {
      if (!canvas) return;

      if (!shot || !shot.naturalWidth) {
        canvas.width = faceCv.width;
        canvas.height = faceCv.height;
        canvas.style.aspectRatio = state.face.w + ' / ' + state.face.h;
        canvas.getContext('2d').drawImage(faceCv, 0, 0);
        return;
      }

      var dpr = Math.min(2, window.devicePixelRatio || 1);
      var w = Math.min(1200, shot.naturalWidth);
      var h = Math.round(w * shot.naturalHeight / shot.naturalWidth);
      canvas.width = Math.round(w * dpr);
      canvas.height = Math.round(h * dpr);
      canvas.style.aspectRatio = shot.naturalWidth + ' / ' + shot.naturalHeight;

      var c = canvas.getContext('2d');
      c.setTransform(dpr, 0, 0, dpr, 0, 0);
      c.clearRect(0, 0, w, h);

      /* Four corners and the warper that draws a logo onto a photograph of
         a counter: the face follows the box however it is standing. */
      if (state.quad && window.NefisScene) {
        window.NefisScene.drawScene(c, {
          w: w, h: h, bg: 'render',
          elements: [{
            type: 'design', id: 'face', opacity: 100,
            corners: state.quad.map(function (point) { return [point[0] * w, point[1] * h]; }),
          }],
        }, faceCv, function () { return shot; }, warpCache, {});
        return;
      }

      /* No warper on this browser: the render with the face laid flat into
         the rectangle the corners fit inside. Square on it is exact; on a
         turned box it is close enough to be worth more than nothing. */
      c.drawImage(shot, 0, 0, w, h);
      c.drawImage(faceCv,
        state.rect.l * w, state.rect.t * h,
        (state.rect.r - state.rect.l) * w, (state.rect.b - state.rect.t) * h);
    }

    /* The card itself: the colour, a soft sheen down one side, a thin edge. */
    function base(c) {
      var m = metrics();

      c.clearRect(0, 0, state.face.w, state.face.h);
      c.fillStyle = state.color;
      c.fillRect(0, 0, state.face.w, state.face.h);

      var sheen = c.createLinearGradient(0, 0, state.face.w, state.face.h);
      sheen.addColorStop(0, 'rgba(255,255,255,.14)');
      sheen.addColorStop(0.45, 'rgba(255,255,255,0)');
      sheen.addColorStop(1, 'rgba(0,0,0,.10)');
      c.fillStyle = sheen;
      c.fillRect(0, 0, state.face.w, state.face.h);

      c.strokeStyle = 'rgba(0,0,0,.18)';
      c.lineWidth = 0.3 * m.type;
      c.strokeRect(0.15 * m.kh, 0.15 * m.kv,
        state.face.w - 0.3 * m.kh, state.face.h - 0.3 * m.kv);
    }

    function drawFront() {
      if (!front) return;
      var c = prepare(faces.front);
      base(c);

      var m = metrics();
      var padH = PAD * m.kh;
      var padV = PAD * m.kv;
      var room = state.face.w - padH * 2;
      var band = 26 * m.kv;            // the logo's own height
      c.fillStyle = ink();
      c.textAlign = 'center';

      if (state.logo) {
        var at = contain(state.logo, padH, padV + 4 * m.kv, room, band);
        if (at) c.drawImage(state.logo, at.x, at.y, at.w, at.h);
      } else {
        c.save();
        c.setLineDash([1.6 * m.type, 1.4 * m.type]);
        c.strokeStyle = ink();
        c.globalAlpha = 0.45;
        c.lineWidth = 0.4 * m.type;
        c.strokeRect(padH, padV + 4 * m.kv, room, band);
        c.globalAlpha = 0.7;
        c.font = '600 ' + (3.2 * m.type) + 'px ' + FONT;
        c.fillText('LOQONUZ', state.face.w / 2, padV + 18.5 * m.kv);
        c.restore();
      }

      if (state.slogan) {
        c.font = '600 ' + (3.6 * m.type) + 'px ' + FONT;
        wrap(c, state.slogan.toUpperCase(), state.face.w / 2,
          state.face.h - band, room, 4.6 * m.kv, 3);
      }

      if (state.phone) {
        c.globalAlpha = 0.85;
        c.font = '500 ' + (3.2 * m.type) + 'px ' + FONT;
        c.fillText(state.phone, state.face.w / 2, state.face.h - padV - 2 * m.kv);
        c.globalAlpha = 1;
      }

      present(front, faces.front, state.shots.front);
    }

    function drawBack() {
      if (!back) return;
      var c = prepare(faces.back);
      base(c);

      var m = metrics();
      /* The code is square whatever the face is, so it takes the smaller of
         the two scales - a QR stretched along one axis does not read. */
      var side = 26 * m.type;
      var quiet = 2 * m.type;
      var x = (state.face.w - side) / 2;
      var y = (state.face.h - side) / 2 - 4 * m.kv;

      var code = state.qr ? makeQr(state.qr) : null;
      if (code) {
        /* The code is printed on white whatever the box is dyed: a scanner
           wants that contrast, and a dark code on a dark box does not read. */
        c.fillStyle = '#FFFFFF';
        c.fillRect(x - quiet, y - quiet, side + quiet * 2, side + quiet * 2);
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
        c.setLineDash([1.6 * m.type, 1.4 * m.type]);
        c.strokeStyle = ink();
        c.globalAlpha = 0.45;
        c.lineWidth = 0.4 * m.type;
        c.strokeRect(x - quiet, y - quiet, side + quiet * 2, side + quiet * 2);
        c.restore();
      }

      c.fillStyle = ink();
      c.textAlign = 'center';
      c.globalAlpha = 0.85;
      c.font = '500 ' + (3 * m.type) + 'px ' + FONT;
      c.fillText(state.qr ? 'Kodu oxudun' : 'QR kod', state.face.w / 2, y + side + 8 * m.kv);
      c.globalAlpha = 1;

      present(back, faces.back, state.shots.back);
    }

    function draw() {
      drawFront();
      drawBack();
      /* Whoever is showing this box somewhere else — the photographs of a
         counter, say — is told it has changed. */
      if (options.onDraw) options.onDraw(faces.front, faces.back, state);
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

    /**
     * The size the visitor picked: its proportions at once, its renders when
     * they arrive. A render that fails to load leaves the box drawn plainly
     * rather than leaving an empty frame on the page.
     */
    function setSize(size) {
      if (!size) return;

      state.face = { w: Number(size.w) || FACE.w, h: Number(size.h) || FACE.h };
      state.shots = { front: null, back: null };
      state.rect = size.face
        ? { l: Number(size.face.l) || 0, t: Number(size.face.t) || 0,
            r: Number(size.face.r) || 1, b: Number(size.face.b) || 1 }
        : { l: 0, t: 0, r: 1, b: 1 };
      state.quad = Array.isArray(size.quad) && size.quad.length === 4 ? size.quad : null;
      draw();

      ['front', 'back'].forEach(function (which) {
        if (!size[which]) return;
        var img = new Image();
        img.onload = function () { state.shots[which] = img; draw(); };
        img.src = size[which];
      });
    }

    function set(key, value) {
      if (key === 'color' && !/^#[0-9a-fA-F]{6}$/.test(String(value))) return;
      state[key] = value;
      draw();
    }

    if (options.size) setSize(options.size);

    draw();
    whenQr(draw, options.qrScript);

    return { set: set, setSize: setSize, setLogo: setLogo, state: state, redraw: draw };
  }

  return { init: init };
})();
