/*
 * Draws a box wrapped as a gift onto the box's own canvas: the paper's
 * pattern over the whole face, a ribbon tied crosswise and a bow where it
 * crosses. The result goes into the mockups exactly like a box design does,
 * so the scenes' renders give it their light and shadow.
 *
 *   NefisWrap.draw(ctx, w, h, patternImage, { ribbon: 'satin'|'twine'|'none', color: '#F3D3B4', scale: .5 })
 *   NefisWrap.averageColor(patternImage) -> '#rrggbb'   (for the box's sides)
 */
(function (global) {
  'use strict';

  function rgb(hex) {
    var m = /^#?([0-9a-f]{6})$/i.exec(hex || '');
    var n = m ? parseInt(m[1], 16) : 0xF3D3B4;
    return [n >> 16, (n >> 8) & 255, n & 255];
  }

  /* amt < 0 darkens towards black, amt > 0 lightens towards white. */
  function shade(hex, amt, alpha) {
    var c = rgb(hex).map(function (v) { return Math.round(amt < 0 ? v * (1 + amt) : v + (255 - v) * amt); });
    return 'rgba(' + c[0] + ',' + c[1] + ',' + c[2] + ',' + (alpha == null ? 1 : alpha) + ')';
  }

  function ready(img) { return img && img.complete && img.naturalWidth > 0; }

  /* ---------- paper ---------- */
  var tileCache = { src: null, w: 0, canvas: null };

  function drawPaper(c, w, h, img, scale, tilePx, noFold) {
    if (ready(img)) {
      /* The pattern at the size it is printed: one tile a share of the box wide.
         Scaled once into a canvas of its own, then repeated. */
      var tw = Math.max(8, Math.round(tilePx || w * (scale || 0.5)));
      if (tileCache.src !== img.src || tileCache.w !== tw) {
        var t = document.createElement('canvas');
        t.width = tw;
        t.height = Math.max(1, Math.round(img.naturalHeight * tw / img.naturalWidth));
        t.getContext('2d').drawImage(img, 0, 0, t.width, t.height);
        tileCache = { src: img.src, w: tw, canvas: t };
      }
      c.fillStyle = c.createPattern(tileCache.canvas, 'repeat');
    } else {
      c.fillStyle = '#EFE6DA';
    }
    c.fillRect(0, 0, w, h);

    /* A sheet of paper, not a flat fill: light from the top left, and the
       fold where the paper meets along the bottom. */
    var g = c.createLinearGradient(0, 0, w, h);
    g.addColorStop(0, 'rgba(255,255,255,.12)');
    g.addColorStop(0.55, 'rgba(255,255,255,0)');
    g.addColorStop(1, 'rgba(0,0,0,.10)');
    c.fillStyle = g;
    c.fillRect(0, 0, w, h);

    if (noFold) return;
    var fy = h * 0.86;
    var fold = c.createLinearGradient(0, fy - h * 0.01, 0, fy + h * 0.012);
    fold.addColorStop(0, 'rgba(0,0,0,0)');
    fold.addColorStop(0.5, 'rgba(0,0,0,.10)');
    fold.addColorStop(0.55, 'rgba(255,255,255,.10)');
    fold.addColorStop(1, 'rgba(0,0,0,0)');
    c.fillStyle = fold;
    c.fillRect(0, fy - h * 0.01, w, h * 0.022);
  }

  /* ---------- satin ribbon ---------- */
  function satinBand(c, x, y, bw, bh, vertical, color) {
    var g = vertical ? c.createLinearGradient(x, 0, x + bw, 0) : c.createLinearGradient(0, y, 0, y + bh);
    g.addColorStop(0, shade(color, -0.22));
    g.addColorStop(0.18, shade(color, 0.06));
    g.addColorStop(0.45, shade(color, 0.32));
    g.addColorStop(0.62, shade(color, 0.14));
    g.addColorStop(1, shade(color, -0.24));
    c.fillStyle = g;
    c.fillRect(x, y, bw, bh);

    /* The woven edges of a satin ribbon. */
    var e = Math.max(1, (vertical ? bw : bh) * 0.05);
    c.fillStyle = shade(color, -0.3, 0.45);
    if (vertical) { c.fillRect(x, y, e, bh); c.fillRect(x + bw - e, y, e, bh); }
    else { c.fillRect(x, y, bw, e); c.fillRect(x, y + bh - e, bw, e); }
  }

  function withShadow(c, u, fn) {
    c.save();
    c.shadowColor = 'rgba(0,0,0,.28)';
    c.shadowBlur = u * 0.025;
    c.shadowOffsetY = u * 0.008;
    fn();
    c.restore();
  }

  function loopPath(c, cx, cy, u, dir) {
    var x = function (v) { return cx + dir * v * u; };
    c.beginPath();
    c.moveTo(cx, cy - 0.015 * u);
    c.bezierCurveTo(x(0.05), cy - 0.22 * u, x(0.33), cy - 0.2 * u, x(0.3), cy - 0.02 * u);
    c.bezierCurveTo(x(0.28), cy + 0.1 * u, x(0.09), cy + 0.09 * u, cx, cy + 0.025 * u);
    c.closePath();
  }

  function loopHollow(c, cx, cy, u, dir) {
    var x = function (v) { return cx + dir * v * u; };
    c.beginPath();
    c.moveTo(x(0.045), cy - 0.012 * u);
    c.bezierCurveTo(x(0.09), cy - 0.14 * u, x(0.25), cy - 0.13 * u, x(0.24), cy - 0.025 * u);
    c.bezierCurveTo(x(0.225), cy + 0.04 * u, x(0.1), cy + 0.04 * u, x(0.045), cy - 0.012 * u);
    c.closePath();
  }

  function tailPath(c, cx, cy, u, dir, len, spread) {
    var x = function (v) { return cx + dir * v * u; };
    c.beginPath();
    c.moveTo(x(-0.012), cy + 0.015 * u);
    c.quadraticCurveTo(x(0.05), cy + len * 0.45 * u, x(spread), cy + len * u);
    c.lineTo(x(spread - 0.04), cy + (len - 0.012) * u);      // the V cut
    c.lineTo(x(spread - 0.062), cy + (len + 0.035) * u);
    c.quadraticCurveTo(x(0.0), cy + len * 0.5 * u, x(0.035), cy + 0.02 * u);
    c.closePath();
  }

  function ribbonFill(c, cx, cy, u, color) {
    var g = c.createRadialGradient(cx, cy, u * 0.02, cx, cy, u * 0.34);
    g.addColorStop(0, shade(color, -0.18));
    g.addColorStop(0.45, shade(color, 0.22));
    g.addColorStop(1, shade(color, 0.02));
    return g;
  }

  function satinBow(c, cx, cy, u, color) {
    var outline = shade(color, -0.45, 0.35);
    withShadow(c, u, function () {
      c.fillStyle = ribbonFill(c, cx, cy, u, color);
      c.strokeStyle = outline;
      c.lineWidth = Math.max(1, u * 0.004);
      /* tails first, loops over them */
      tailPath(c, cx, cy, u, -1, 0.3, 0.2); c.fill(); c.stroke();
      tailPath(c, cx, cy, u, 1, 0.27, 0.15); c.fill(); c.stroke();
      loopPath(c, cx, cy, u, -1); c.fill(); c.stroke();
      loopPath(c, cx, cy, u, 1); c.fill(); c.stroke();
    });
    /* the inside of each loop, in shadow */
    c.fillStyle = shade(color, -0.35, 0.35);
    loopHollow(c, cx, cy, u, -1); c.fill();
    loopHollow(c, cx, cy, u, 1); c.fill();

    /* satin catches the light along the top of each loop */
    c.lineCap = 'round';
    c.strokeStyle = shade(color, 0.6, 0.55);
    c.lineWidth = u * 0.012;
    [-1, 1].forEach(function (dir) {
      c.beginPath();
      c.moveTo(cx + dir * 0.07 * u, cy - 0.1 * u);
      c.quadraticCurveTo(cx + dir * 0.17 * u, cy - 0.2 * u, cx + dir * 0.27 * u, cy - 0.09 * u);
      c.stroke();
    });

    /* the knot */
    var kw = u * 0.085, kh = u * 0.075;
    var kg = c.createLinearGradient(cx - kw / 2, 0, cx + kw / 2, 0);
    kg.addColorStop(0, shade(color, -0.25));
    kg.addColorStop(0.5, shade(color, 0.25));
    kg.addColorStop(1, shade(color, -0.25));
    withShadow(c, u, function () {
      c.fillStyle = kg;
      c.beginPath();
      if (c.roundRect) c.roundRect(cx - kw / 2, cy - kh / 2, kw, kh, u * 0.02);
      else c.rect(cx - kw / 2, cy - kh / 2, kw, kh);
      c.fill();
    });
    c.strokeStyle = outline;
    c.stroke();
  }

  /* ---------- jute twine ---------- */
  function cord(c, pts, u, color, curve) {
    var lw = Math.max(2, u * 0.016);
    function path() {
      c.beginPath();
      c.moveTo(pts[0][0], pts[0][1]);
      if (curve) c.bezierCurveTo(pts[1][0], pts[1][1], pts[2][0], pts[2][1], pts[3][0], pts[3][1]);
      else for (var i = 1; i < pts.length; i++) c.lineTo(pts[i][0], pts[i][1]);
    }
    c.lineCap = 'round';
    c.lineJoin = 'round';
    path();
    c.strokeStyle = shade(color, -0.35);
    c.lineWidth = lw;
    c.stroke();
    /* the twist: light and dark turns along the cord */
    path();
    c.strokeStyle = shade(color, 0.25, 0.85);
    c.lineWidth = lw * 0.72;
    c.setLineDash([lw * 0.75, lw * 0.5]);
    c.stroke();
    c.setLineDash([]);
  }

  function twine(c, w, h, cx, cy, color, noBow) {
    var u = w, gap = u * 0.022;
    withShadow(c, u, function () {
      /* wound twice each way, as in the shop */
      cord(c, [[cx - gap, 0], [cx - gap, h]], u, color);
      cord(c, [[cx + gap, 0], [cx + gap, h]], u, color);
      cord(c, [[0, cy - gap], [w, cy - gap]], u, color);
      cord(c, [[0, cy + gap], [w, cy + gap]], u, color);
      if (noBow) return;
      /* the bow: two loose loops and two ends */
      cord(c, [[cx, cy], [cx - 0.14 * u, cy - 0.16 * u], [cx - 0.3 * u, cy - 0.02 * u], [cx, cy]], u, color, true);
      cord(c, [[cx, cy], [cx + 0.14 * u, cy - 0.15 * u], [cx + 0.28 * u, cy + 0.02 * u], [cx, cy]], u, color, true);
      cord(c, [[cx, cy], [cx - 0.05 * u, cy + 0.12 * u], [cx - 0.16 * u, cy + 0.2 * u], [cx - 0.2 * u, cy + 0.3 * u]], u, color, true);
      cord(c, [[cx, cy], [cx + 0.06 * u, cy + 0.1 * u], [cx + 0.12 * u, cy + 0.22 * u], [cx + 0.1 * u, cy + 0.32 * u]], u, color, true);
    });
    if (noBow) return;
    /* the knot */
    c.fillStyle = shade(color, -0.15);
    c.beginPath();
    c.arc(cx, cy, u * 0.02, 0, Math.PI * 2);
    c.fill();
  }

  /* ---------- the whole wrap ---------- */
  function draw(c, w, h, img, opts) {
    opts = opts || {};
    var color = opts.color || '#F3D3B4';
    var ribbon = opts.ribbon || 'satin';
    c.save();
    drawPaper(c, w, h, img, opts.scale, null, opts.back);
    if (opts.back) seam(c, w, h);

    /* The ribbon crosses a little above the middle, as it is tied. */
    var cx = w * 0.5, cy = h * 0.42;
    if (ribbon === 'satin') {
      var bw = w * 0.075;
      withShadow(c, w, function () {
        satinBand(c, cx - bw / 2, 0, bw, h, true, color);
        satinBand(c, 0, cy - bw / 2, w, bw, false, color);
      });
      if (!opts.back) satinBow(c, cx, cy, w, color);
    } else if (ribbon === 'twine') {
      twine(c, w, h, cx, cy, color, opts.back);
    }
    c.restore();
  }

  /* Where the paper overlaps itself along the back. */
  function seam(c, w, h) {
    var x = w * 0.64;
    var g = c.createLinearGradient(x - w * 0.02, 0, x + w * 0.012, 0);
    g.addColorStop(0, 'rgba(0,0,0,0)');
    g.addColorStop(0.7, 'rgba(0,0,0,.16)');
    g.addColorStop(0.72, 'rgba(255,255,255,.22)');
    g.addColorStop(1, 'rgba(255,255,255,0)');
    c.fillStyle = g;
    c.fillRect(x - w * 0.02, 0, w * 0.032, h);
  }

  /* A long side of the box, `unitW` being the face's width, so the paper and
     the ribbon keep the face's size as they go round. */
  function drawSide(c, d, h, img, opts, unitW) {
    opts = opts || {};
    var color = opts.color || '#F3D3B4';
    c.save();
    drawPaper(c, d, h, img, 0, unitW * (opts.scale || 0.5), true);
    var cy = h * 0.42;
    if (opts.ribbon === 'satin') {
      var bw = unitW * 0.075;
      satinBand(c, 0, cy - bw / 2, d, bw, false, color);
    } else if (opts.ribbon === 'twine') {
      var gap = unitW * 0.022;
      cord(c, [[0, cy - gap], [d, cy - gap]], unitW, color);
      cord(c, [[0, cy + gap], [d, cy + gap]], unitW, color);
    }
    c.restore();
  }

  /* An end of the box: the paper folded in, the long ribbon over it. */
  function drawEnd(c, w, d, img, opts) {
    opts = opts || {};
    var color = opts.color || '#F3D3B4';
    c.save();
    drawPaper(c, w, d, img, opts.scale, null, true);
    /* the folds: two flaps from the long edges meeting in the middle */
    c.fillStyle = 'rgba(0,0,0,.10)';
    c.beginPath(); c.moveTo(0, 0); c.lineTo(d * 0.5, d * 0.5); c.lineTo(0, d); c.closePath(); c.fill();
    c.beginPath(); c.moveTo(w, 0); c.lineTo(w - d * 0.5, d * 0.5); c.lineTo(w, d); c.closePath(); c.fill();
    c.strokeStyle = 'rgba(0,0,0,.18)';
    c.lineWidth = Math.max(1, w * 0.004);
    c.beginPath();
    c.moveTo(0, 0); c.lineTo(d * 0.5, d * 0.5); c.lineTo(w - d * 0.5, d * 0.5); c.lineTo(w, 0);
    c.moveTo(0, d); c.lineTo(d * 0.5, d * 0.5); c.moveTo(w, d); c.lineTo(w - d * 0.5, d * 0.5);
    c.stroke();
    var cx = w * 0.5;
    if (opts.ribbon === 'satin') {
      var bw = w * 0.075;
      satinBand(c, cx - bw / 2, 0, bw, d, true, color);
    } else if (opts.ribbon === 'twine') {
      var gap = w * 0.022;
      cord(c, [[cx - gap, 0], [cx - gap, d]], w, color);
      cord(c, [[cx + gap, 0], [cx + gap, d]], w, color);
    }
    c.restore();
  }

  /* The paper's overall colour, so a mockup can dye the box's sides to match. */
  function averageColor(img) {
    if (!ready(img)) return null;
    try {
      var t = document.createElement('canvas');
      t.width = t.height = 16;
      var tc = t.getContext('2d');
      tc.drawImage(img, 0, 0, 16, 16);
      var d = tc.getImageData(0, 0, 16, 16).data, r = 0, g = 0, b = 0, n = 0;
      for (var i = 0; i < d.length; i += 4) { if (d[i + 3] < 20) continue; r += d[i]; g += d[i + 1]; b += d[i + 2]; n++; }
      if (!n) return null;
      var hex = function (v) { return ('0' + Math.round(v / n).toString(16)).slice(-2); };
      return '#' + hex(r) + hex(g) + hex(b);
    } catch (e) {
      return null;   // a picture from another site without CORS cannot be read
    }
  }

  global.NefisWrap = { draw: draw, drawSide: drawSide, drawEnd: drawEnd, averageColor: averageColor };
})(window);
