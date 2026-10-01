/**
 * The streets around a place, poured into a window on a box.
 *
 * Built to the same contract as public/js/star-map.js, because the same three
 * pages call both: the owner's box editor, the customer's live mock-up and the
 * workshop's printing sheet. draw(target, opts) returns {cx, cy, r, colours}.
 *
 * The difference from the sky is that the streets are a picture that has to
 * arrive first. draw() never waits: it paints what it has — the page colour,
 * then the streets once they are here — and calls opts.onReady when a picture
 * has just landed, so the caller can draw the frame again.
 *
 * The streets themselves are OpenStreetMap's, fetched through this site.
 */
(function () {
  'use strict';

  /**
   * The four printed looks. `shade` is what a half-drawn window shows, and
   * `invert` turns the fetched drawing inside out — the black-background look
   * is the white-background one reversed, which is how it keeps its contrast.
   */
  var STYLES = {
    ink: { page: '#ffffff', ink: '#ffffff', rim: '#ffffff', shade: '#e9e9e9', invert: true },
    paper: { page: '#ffffff', ink: '#111111', rim: '#111111', shade: '#e9e9e9' },
    sea: { page: '#eaf1f4', ink: '#2d4f5e', rim: '#2d4f5e', shade: '#cfdee5' },
    colour: { page: '#f2efe9', ink: '#3a3226', rim: '#3a3226', shade: '#e3ddd1' },
  };

  /** The mark on the spot is the shop's own orange, whatever the look. */
  var MARK = '#E8792B';

  /**
   * The service prints its own caption along the bottom of every drawing. It
   * belongs on the page, not on a chocolate box, so the picture is asked for
   * this much taller and the strip is cut off — off the top as well, or the
   * place the customer framed would sit below the middle.
   */
  var CREDIT = 24;

  function styleOf(name) {
    return STYLES[name] || STYLES.ink;
  }

  /* Pictures already fetched, by their address. A box editor redraws on every
     drag, and the streets must not be asked for again each time. */
  var pictures = {};

  /**
   * The picture currently in hand, and the place it was drawn around.
   *
   * The service takes three or four seconds to draw a picture whatever its
   * size, so asking for a new one every time the finger moves is what made
   * the map arrive late. More ground is fetched than the window shows, and
   * while the view stays inside that spare the same picture is simply slid
   * across — no asking, nothing to wait for. A new one is fetched only when
   * the view nears the edge of what is in hand.
   */
  var held = null;

  /** How far the view may wander from the picture's centre, as a share of the
   *  window. The spare is (margin - 1) / 2 on each side; staying well inside
   *  it means the window never shows the picture's own edge. */
  var WANDER = 0.22;

  function picture(url, onReady) {
    var held = pictures[url];
    if (held) {
      return held.ok ? held.img : null;
    }

    var img = new Image();
    var entry = { img: img, ok: false };
    pictures[url] = entry;
    img.onload = function () {
      entry.ok = true;
      if (onReady) {
        onReady();
      }
    };
    img.onerror = function () {
      // Left not-ok on purpose: the window keeps its plain colour rather than
      // flashing a broken picture, and nothing asks for it twice.
      if (onReady) {
        onReady();
      }
    };
    img.src = url;

    return null;
  }

  /**
   * How much wider than the window the picture is fetched.
   *
   * A finger dragging the map must not drag emptiness in behind it, so more
   * ground is asked for than the window shows and the spare lies just outside
   * the clip, ready. What the window shows is unchanged — the extra is only
   * there to be slid in — so the printing sheet, which asks for no margin,
   * still prints exactly what was framed.
   */
  function marginOf(opts) {
    return Math.max(1, Math.min(2.5, opts.margin || 1));
  }

  /** The address the streets are asked for, with room for the caption cut. */
  function source(opts, w, h) {
    var scale = opts.scale || 1;
    return (opts.endpoint || '/lokasiya/sekil')
      + '?lat=' + encodeURIComponent(Number(opts.lat || 0).toFixed(5))
      + '&lon=' + encodeURIComponent(Number(opts.lon || 0).toFixed(5))
      + '&zoom=' + encodeURIComponent(opts.zoom || 15)
      + '&style=' + encodeURIComponent(opts.style || 'ink')
      + '&w=' + Math.round(w) + '&h=' + Math.round(h + 2 * CREDIT)
      + (scale > 1 ? '&scale=' + scale : '');
  }

  /** The outline the streets are poured into. */
  function clipShape(ctx, shape, cx, cy, r, box) {
    ctx.beginPath();

    if ((shape === 'full' || shape === 'rectangle') && box) {
      ctx.rect(box.x, box.y, box.w, box.h);

      return;
    }

    if (shape === 'home' && box) {
      /* A house: walls on the lower two thirds, a roof over them. Drawn from
         the window's own corners so it fills whatever rectangle it is given. */
      var eaves = box.y + box.h * 0.34;
      ctx.moveTo(box.x + box.w / 2, box.y);
      ctx.lineTo(box.x + box.w, eaves);
      ctx.lineTo(box.x + box.w, box.y + box.h);
      ctx.lineTo(box.x, box.y + box.h);
      ctx.lineTo(box.x, eaves);
      ctx.closePath();

      return;
    }

    if (shape === 'heart') {
      var k = Math.min(r / 16, (2 * r) / 22);
      var mid = -6;
      for (var i = 0; i <= 240; i++) {
        var t = (i / 240) * Math.PI * 2;
        var x = 16 * Math.pow(Math.sin(t), 3);
        var y = 13 * Math.cos(t) - 5 * Math.cos(2 * t) - 2 * Math.cos(3 * t) - Math.cos(4 * t);
        var px = cx + x * k, py = cy - (y - mid) * k;
        i ? ctx.lineTo(px, py) : ctx.moveTo(px, py);
      }
      ctx.closePath();

      return;
    }

    ctx.arc(cx, cy, r, 0, Math.PI * 2);
  }

  /** The mark on the spot the customer chose. */
  function drawMarker(ctx, kind, x, y, size, colour) {
    if (!kind || kind === 'none') {
      return;
    }

    ctx.save();
    ctx.fillStyle = colour;
    ctx.strokeStyle = colour;
    ctx.beginPath();

    if (kind === 'heart') {
      var k = size / 26;
      for (var i = 0; i <= 120; i++) {
        var t = (i / 120) * Math.PI * 2;
        var hx = 16 * Math.pow(Math.sin(t), 3);
        var hy = 13 * Math.cos(t) - 5 * Math.cos(2 * t) - 2 * Math.cos(3 * t) - Math.cos(4 * t);
        var px = x + hx * k, py = y - hy * k;
        i ? ctx.lineTo(px, py) : ctx.moveTo(px, py);
      }
      ctx.closePath();
      ctx.fill();
    } else if (kind === 'star') {
      var outer = size / 2, inner = outer * 0.42;
      for (var j = 0; j < 10; j++) {
        var a = (Math.PI / 5) * j - Math.PI / 2;
        var rad = j % 2 ? inner : outer;
        var sx = x + Math.cos(a) * rad, sy = y + Math.sin(a) * rad;
        j ? ctx.lineTo(sx, sy) : ctx.moveTo(sx, sy);
      }
      ctx.closePath();
      ctx.fill();
    } else {
      /* A pin: a circle resting on a point, the point on the spot itself. */
      var rr = size * 0.32;
      var top = y - size * 0.62;
      ctx.arc(x, top, rr, Math.PI * 0.85, Math.PI * 0.15);
      ctx.lineTo(x, y);
      ctx.closePath();
      ctx.fill();
      ctx.beginPath();
      ctx.arc(x, top, rr * 0.38, 0, Math.PI * 2);
      ctx.globalCompositeOperation = 'destination-out';
      ctx.fill();
    }

    ctx.restore();
  }

  /**
   * Draw the streets into `target`, which may be a canvas or a bare 2d
   * context. Everything about the look comes from `opts`; nothing is read off
   * the page.
   */
  function draw(target, opts) {
    opts = opts || {};

    var canvas = target.getContext ? target : null;
    var ctx = canvas ? canvas.getContext('2d') : target;
    var size = opts.size || (canvas ? Math.min(canvas.width, canvas.height) : 1000);
    var colours = styleOf(opts.style);
    var full = opts.shape === 'full';
    var boxed = full || opts.shape === 'rectangle' || opts.shape === 'home';
    var drawn = 0;
    var cx = opts.cx == null ? (canvas ? canvas.width / 2 : size / 2) : opts.cx;
    var cy = opts.cy == null ? (canvas ? canvas.height / 2 : size / 2) : opts.cy;
    var r = (opts.radius || size / 2) * 0.98;
    var box = opts.box || (canvas
      ? { x: 0, y: 0, w: canvas.width, h: canvas.height }
      : { x: cx - size / 2, y: cy - size / 2, w: size, h: size });

    if (boxed) {
      size = Math.max(box.w, box.h);
      cx = box.x + box.w / 2;
      cy = box.y + box.h / 2;
      r = Math.sqrt(box.w * box.w + box.h * box.h) / 2;
    }

    if (opts.page !== false && canvas) {
      ctx.fillStyle = colours.page;
      ctx.fillRect(0, 0, canvas.width, canvas.height);
    }

    ctx.save();
    clipShape(ctx, opts.shape, cx, cy, r, box);
    ctx.clip();

    /* The window's own colour first, so a map that has not arrived yet still
       looks like part of the box rather than a hole in it. */
    ctx.fillStyle = colours.page;
    ctx.fillRect(box.x, box.y, box.w, box.h);

    /* Ask for the picture in the window's own proportions, so the streets are
       never stretched. The ceiling keeps a dragged window from asking for a
       new picture at every pixel. */
    var margin = marginOf(opts);
    var wide = Math.max(box.w, box.h);
    var ask = Math.min(opts.quality || 700, 2000);
    var pw = Math.max(80, Math.round((box.w / wide) * ask * margin));
    var ph = Math.max(80, Math.round((box.h / wide) * ask * margin));
    var zoom = opts.zoom || 15;
    var style = opts.style || 'ink';
    var slid = { x: 0, y: 0 };
    var img = null;

    if (opts.lat != null) {
      /* Only a window with spare ground around it may wander; the printing
         sheet and the editor ask for no margin and must be exact. */
      var mayWander = margin > 1.05 && held
        && held.style === style && held.zoom === zoom
        && held.pw === pw && held.ph === ph;

      if (mayWander) {
        var was = toWorld(held.lat, held.lon, zoom);
        var now = toWorld(opts.lat, opts.lon, zoom);
        /* `held.drawn` turns world pixels into window pixels. */
        var offX = (was.x - now.x) * held.drawn;
        var offY = (was.y - now.y) * held.drawn;
        if (Math.abs(offX) <= box.w * WANDER && Math.abs(offY) <= box.h * WANDER) {
          img = held.img;
          slid.x = offX;
          slid.y = offY;
        }
      }

      if (!img) {
        /* Far enough to need new ground — or nothing in hand at all. */
        var fresh = picture(source(opts, pw, ph), opts.onReady);
        if (fresh) {
          img = fresh;
          held = { img: fresh, lat: opts.lat, lon: opts.lon, zoom: zoom,
            style: style, pw: pw, ph: ph, drawn: 0 };
        } else if (held && held.style === style && held.zoom === zoom && held.img) {
          /* It is on its way: hold what we have, slid into place, so the
             window never goes blank while waiting. */
          var old = toWorld(held.lat, held.lon, zoom);
          var here = toWorld(opts.lat, opts.lon, zoom);
          img = held.img;
          slid.x = (old.x - here.x) * held.drawn;
          slid.y = (old.y - here.y) * held.drawn;
        }
      }
    }

    if (img) {
      /* The service's caption is cut off the bottom, and as much again off the
         top, so the middle of what is left is still the place he framed. */
      var cut = CREDIT * (opts.scale || 1) * (img.width / Math.max(1, pw));
      var sy = Math.round(cut);
      var sh = Math.max(1, img.height - 2 * sy);

      /* Cover the window, times the margin: the picture keeps its shape and
         everything outside the window is cut off by the clip above. */
      var k = Math.max((box.w * margin) / img.width, (box.h * margin) / sh);
      var dw = img.width * k, dh = sh * k;
      /* Where the finger has pushed it since the last picture was asked for. */
      var ox = (opts.offsetX || 0) + slid.x, oy = (opts.offsetY || 0) + slid.y;
      ctx.drawImage(
        img, 0, sy, img.width, sh,
        box.x + (box.w - dw) / 2 + ox, box.y + (box.h - dh) / 2 + oy, dw, dh
      );
      drawn = k;
      if (held && held.img === img && !held.drawn) {
        held.drawn = k;
      }

      if (colours.invert) {
        /* Inside the clip everything on the canvas is ours, so turning it
           inside out turns only the window: white streets on black. */
        ctx.save();
        ctx.globalCompositeOperation = 'difference';
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(box.x, box.y, box.w, box.h);
        ctx.restore();
      }
    } else if (opts.lat != null) {
      /* Waiting, or no key set: a quiet band so the window reads as a map. */
      ctx.fillStyle = colours.shade;
      for (var y = box.y; y < box.y + box.h; y += Math.max(12, size / 28)) {
        ctx.fillRect(box.x, y, box.w, Math.max(2, size / 220));
      }
    }

    if (opts.pin !== false && opts.marker && opts.marker !== 'none') {
      drawMarker(ctx, opts.marker, cx, cy, Math.max(14, size * 0.085), opts.markerColour || MARK);
    }

    ctx.restore();

    /* The rim, drawn after the clip is lifted so it is not cut in half. */
    if (opts.rim !== false && !full) {
      ctx.save();
      ctx.strokeStyle = colours.rim;
      ctx.lineWidth = Math.max(1, size * 0.006);
      var inset = ctx.lineWidth / 2;
      clipShape(
        ctx, opts.shape, cx, cy, r - inset,
        { x: box.x + inset, y: box.y + inset, w: box.w - ctx.lineWidth, h: box.h - ctx.lineWidth }
      );
      ctx.stroke();
      ctx.restore();
    }

    /* `drawn` is how many window pixels one picture pixel became, which is
       what turns a drag in pixels back into a place on earth. */
    return { cx: cx, cy: cy, r: r, colours: colours, drawn: drawn };
  }

  /**
   * Web Mercator, the projection the pictures are drawn in. The world is
   * 256 * 2^z pixels wide at the zoom the customer sees — the service counts
   * a picture's zoom one lower, and that cancels its 512-pixel squares.
   */
  function worldWidth(zoom) {
    return 256 * Math.pow(2, Number(zoom) || 15);
  }

  function toWorld(lat, lon, zoom) {
    var w = worldWidth(zoom);
    var s = Math.sin(Number(lat) * Math.PI / 180);
    s = Math.max(-0.9999, Math.min(0.9999, s));

    return {
      x: (Number(lon) + 180) / 360 * w,
      y: (0.5 - Math.log((1 + s) / (1 - s)) / (4 * Math.PI)) * w,
    };
  }

  function fromWorld(x, y, zoom) {
    var w = worldWidth(zoom);
    var n = Math.PI - 2 * Math.PI * y / w;

    return {
      lat: 180 / Math.PI * Math.atan(0.5 * (Math.exp(n) - Math.exp(-n))),
      lon: x / w * 360 - 180,
    };
  }

  /**
   * The place the map has been dragged to: the centre moved by so many window
   * pixels, with `drawn` saying how big a picture pixel was on the window.
   */
  function dragged(lat, lon, zoom, dx, dy, drawn) {
    if (!drawn) {
      return { lat: lat, lon: lon };
    }
    var at = toWorld(lat, lon, zoom);

    return fromWorld(at.x - dx / drawn, at.y - dy / drawn, zoom);
  }

  /** Degrees as a printed coordinate: 40°22'19"N. */
  function coordinate(value, positive, negative) {
    var v = Math.abs(Number(value) || 0);
    var d = Math.floor(v);
    var m = Math.floor((v - d) * 60);
    var s = Math.round((((v - d) * 60) - m) * 60);
    if (s === 60) { s = 0; m += 1; }
    if (m === 60) { m = 0; d += 1; }

    return d + '\u00b0' + (m < 10 ? '0' : '') + m + "'" + (s < 10 ? '0' : '') + s + '"' + (Number(value) < 0 ? negative : positive);
  }

  window.NefisStreetMap = {
    draw: draw,
    styles: STYLES,
    coordinate: coordinate,
    dragged: dragged,
    /** "40°22'19"N 49°53'31"E", the way it is printed under the streets. */
    coordinates: function (lat, lon) {
      return coordinate(lat, 'N', 'S') + ' ' + coordinate(lon, 'E', 'W');
    },
    /** What must be printed beside the streets. */
    credit: '© OpenStreetMap',
  };
})();
