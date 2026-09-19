/*
 * Draws a mockup scene: background, stacked pictures, and the flat box design
 * corner-pinned onto a rendered box.
 *
 * The admin scene editor and the customer page both draw through this file,
 * so a scene looks exactly as the owner set it up.
 *
 * A scene is {w, h, bgColor, bg (url), elements: [...]} in its own pixels,
 * bottom element first:
 *   image:  {id, url, x, y, width, height, rotation, opacity, blend, flip_x, flip_y,
 *            tint (colour a white render is dyed), tint_strength (0-100, default
 *            70), sheen (0-100), recolor (takes
 *            the product's box colour instead of its own tint), tint_all (dye
 *            the whole picture, not only its white paper)}
 *   design: {id, corners: [[x,y] top-left, top-right, bottom-right, bottom-left],
 *            opacity, blend, shade (0-200), shade_from (id of an image)}
 *
 * Canvas 2D has no perspective transform, so a design is warped as a mesh of
 * small triangles, each drawn with its own affine transform.
 */
(function (global) {
  'use strict';

  /* How much of a box colour is laid on (per cent), unless a scene says. */
  var TINT_STRENGTH = 70;

  function ready(img) {
    return !!img && (img instanceof HTMLCanvasElement ? img.width > 0 : (img.complete && img.naturalWidth > 0));
  }
  function sizeOf(img) {
    return img instanceof HTMLCanvasElement ? { w: img.width, h: img.height } : { w: img.naturalWidth, h: img.naturalHeight };
  }
  function dist(a, b) { return Math.hypot(a[0] - b[0], a[1] - b[1]); }
  function luminance(hex) {
    var n = parseInt(String(hex).slice(1, 7), 16);
    return (0.299 * (n >> 16 & 255) + 0.587 * (n >> 8 & 255) + 0.114 * (n & 255)) / 255;
  }

  /* ---------- projective maths ---------- */

  /* Maps the unit square onto the quad (Heckbert): (0,0)->p0, (1,0)->p1,
     (1,1)->p2, (0,1)->p3. Returns [a,b,c,d,e,f,g,h] with
     x = (a u + b v + c) / (g u + h v + 1), y = (d u + e v + f) / (same). */
  function squareToQuad(p) {
    var x0 = p[0][0], y0 = p[0][1], x1 = p[1][0], y1 = p[1][1];
    var x2 = p[2][0], y2 = p[2][1], x3 = p[3][0], y3 = p[3][1];
    var dx3 = x0 - x1 + x2 - x3, dy3 = y0 - y1 + y2 - y3;
    if (Math.abs(dx3) < 1e-9 && Math.abs(dy3) < 1e-9) {
      return [x1 - x0, x3 - x0, x0, y1 - y0, y3 - y0, y0, 0, 0];
    }
    var dx1 = x1 - x2, dx2 = x3 - x2, dy1 = y1 - y2, dy2 = y3 - y2;
    var den = dx1 * dy2 - dx2 * dy1;
    if (Math.abs(den) < 1e-12) den = 1e-12;
    var g = (dx3 * dy2 - dx2 * dy3) / den;
    var h = (dx1 * dy3 - dx3 * dy1) / den;
    return [x1 - x0 + g * x1, x3 - x0 + h * x3, x0, y1 - y0 + g * y1, y3 - y0 + h * y3, y0, g, h];
  }

  function project(H, u, v) {
    var w = H[6] * u + H[7] * v + 1;
    return [(H[0] * u + H[1] * v + H[2]) / w, (H[3] * u + H[4] * v + H[5]) / w];
  }

  /* Quad -> unit square: u = (A x + B y + C) / w, v = (D x + E y + F) / w,
     w = G x + H y + I. The inverse of [[a b c][d e f][g h 1]] via the adjugate. */
  function quadToSquare(corners) {
    var H = squareToQuad(corners);
    var a = H[0], b = H[1], c = H[2], d = H[3], e = H[4], f = H[5], g = H[6], h = H[7];
    return [e - f * h, c * h - b, b * f - c * e,
            f * g - d, a - c * g, c * d - a * f,
            d * h - e * g, b * g - a * h, a * e - b * d];
  }

  /* Scene point -> (u, v) on the design, 0..1 inside it. */
  function toUV(corners, x, y) {
    var M = quadToSquare(corners);
    var w = M[6] * x + M[7] * y + M[8];
    if (Math.abs(w) < 1e-12) return null;
    return { u: (M[0] * x + M[1] * y + M[2]) / w, v: (M[3] * x + M[4] * y + M[5]) / w };
  }

  function pointInQuad(corners, x, y) {
    var inside = false;
    for (var i = 0, j = 3; i < 4; j = i++) {
      var xi = corners[i][0], yi = corners[i][1], xj = corners[j][0], yj = corners[j][1];
      if (((yi > y) !== (yj > y)) && (x < (xj - xi) * (y - yi) / ((yj - yi) || 1e-12) + xi)) inside = !inside;
    }
    return inside;
  }

  function quadPath(c, corners, ox, oy) {
    ox = ox || 0; oy = oy || 0;
    c.beginPath();
    c.moveTo(corners[0][0] - ox, corners[0][1] - oy);
    for (var i = 1; i < 4; i++) c.lineTo(corners[i][0] - ox, corners[i][1] - oy);
    c.closePath();
  }

  /* The corners of a w×h rectangle centred on cx,cy and turned by deg. */
  function rectCorners(cx, cy, w, h, deg) {
    var r = (deg || 0) * Math.PI / 180, cs = Math.cos(r), sn = Math.sin(r);
    return [[-w / 2, -h / 2], [w / 2, -h / 2], [w / 2, h / 2], [-w / 2, h / 2]].map(function (p) {
      return [cx + p[0] * cs - p[1] * sn, cy + p[0] * sn + p[1] * cs];
    });
  }

  /* Affine transform taking source triangle s onto destination triangle d. */
  function triTransform(s0, s1, s2, d0, d1, d2) {
    var sx1 = s1[0] - s0[0], sy1 = s1[1] - s0[1], sx2 = s2[0] - s0[0], sy2 = s2[1] - s0[1];
    var det = sx1 * sy2 - sx2 * sy1;
    if (Math.abs(det) < 1e-12) return null;
    var dx1 = d1[0] - d0[0], dy1 = d1[1] - d0[1], dx2 = d2[0] - d0[0], dy2 = d2[1] - d0[1];
    var a = (dx1 * sy2 - dx2 * sy1) / det;
    var c = (dx2 * sx1 - dx1 * sx2) / det;
    var b = (dy1 * sy2 - dy2 * sy1) / det;
    var d = (dy2 * sx1 - dy1 * sx2) / det;
    return [a, b, c, d, d0[0] - a * s0[0] - c * s0[1], d0[1] - b * s0[0] - d * s0[1]];
  }

  /* ---------- canvases ---------- */

  function pooled(cache, key, w, h) {
    var c = cache[key];
    if (!c) { c = cache[key] = document.createElement('canvas'); }
    if (c.width !== w || c.height !== h) { c.width = w; c.height = h; }
    return c;
  }

  /* A big design drawn much smaller aliases badly, above all in Safari, which
     ignores imageSmoothingQuality: halve in steps, then land on the size the
     face actually takes, so the warp itself barely has to shrink anything. */
  function prescale(src, sw, sh, tw, th, cache) {
    if (tw >= sw * 0.85 && th >= sh * 0.85) return { img: src, w: sw, h: sh };
    tw = Math.max(1, Math.round(Math.min(sw, tw)));
    th = Math.max(1, Math.round(Math.min(sh, th)));
    var cur = src, cw = sw, ch = sh, step = 0, c, x;
    while (cw / 2 >= tw && ch / 2 >= th && step < 5) {
      var nw = Math.round(cw / 2), nh = Math.round(ch / 2);
      c = pooled(cache, 'pre' + step, nw, nh); x = c.getContext('2d');
      x.clearRect(0, 0, nw, nh);
      x.imageSmoothingQuality = 'high';
      x.drawImage(cur, 0, 0, nw, nh);
      cur = c; cw = nw; ch = nh; step++;
    }
    c = pooled(cache, 'pre-final', tw, th); x = c.getContext('2d');
    x.clearRect(0, 0, tw, th);
    x.imageSmoothingQuality = 'high';
    x.drawImage(cur, 0, 0, tw, th);
    return { img: c, w: tw, h: th };
  }

  /* ---------- WebGL warp ---------- */

  /* One shared WebGL canvas draws the design as a single perspective-correct
     quad: exact, with antialiased edges and no seams even where the design is
     see-through. Where WebGL is missing the triangle mesh below takes over. */
  var GL = null;
  function glSetup() {
    if (GL !== null) return GL;
    GL = false;
    try {
      var c = document.createElement('canvas');
      var gl = c.getContext('webgl', { alpha: true, premultipliedAlpha: true, antialias: true, preserveDrawingBuffer: true });
      if (!gl) return GL;
      var shader = function (type, src) {
        var s = gl.createShader(type);
        gl.shaderSource(s, src);
        gl.compileShader(s);
        return gl.getShaderParameter(s, gl.COMPILE_STATUS) ? s : null;
      };
      /* u*w, v*w, w are affine in screen space, so interpolating them and
         dividing per pixel gives the true projective mapping. */
      var vs = shader(gl.VERTEX_SHADER,
        'attribute vec2 p; attribute vec3 t; uniform vec2 s; varying vec3 v;' +
        'void main(){ v = t; gl_Position = vec4(p.x / s.x * 2.0 - 1.0, 1.0 - p.y / s.y * 2.0, 0.0, 1.0); }');
      var fs = shader(gl.FRAGMENT_SHADER,
        '#ifdef GL_FRAGMENT_PRECISION_HIGH\nprecision highp float;\n#else\nprecision mediump float;\n#endif\n' +
        'varying vec3 v; uniform sampler2D tex;' +
        'void main(){ gl_FragColor = texture2D(tex, clamp(v.xy / v.z, 0.0, 1.0)); }');
      if (!vs || !fs) return GL;
      var prog = gl.createProgram();
      gl.attachShader(prog, vs);
      gl.attachShader(prog, fs);
      gl.linkProgram(prog);
      if (!gl.getProgramParameter(prog, gl.LINK_STATUS)) return GL;
      var tex = gl.createTexture();
      gl.bindTexture(gl.TEXTURE_2D, tex);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);
      c.addEventListener('webglcontextlost', function () { GL = false; });
      GL = { c: c, gl: gl, prog: prog, tex: tex, buf: gl.createBuffer(),
             p: gl.getAttribLocation(prog, 'p'), t: gl.getAttribLocation(prog, 't'), s: gl.getUniformLocation(prog, 's'),
             max: gl.getParameter(gl.MAX_TEXTURE_SIZE) };
    } catch (e) { GL = false; }
    return GL;
  }

  function glWarp(img, w, h, corners, bx, by, bw, bh) {
    var g = glSetup();
    if (!g || w > g.max || h > g.max || bw > g.max || bh > g.max) return null;
    var gl = g.gl;
    if (g.c.width !== bw || g.c.height !== bh) { g.c.width = bw; g.c.height = bh; }
    gl.viewport(0, 0, bw, bh);
    gl.clearColor(0, 0, 0, 0);
    gl.clear(gl.COLOR_BUFFER_BIT);
    gl.useProgram(g.prog);
    gl.bindTexture(gl.TEXTURE_2D, g.tex);
    gl.pixelStorei(gl.UNPACK_PREMULTIPLY_ALPHA_WEBGL, true);
    /* A picture from another site taints the design; fall back then. */
    try { gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, gl.RGBA, gl.UNSIGNED_BYTE, img); } catch (e) { return null; }

    var M = quadToSquare(corners), uv = [[0, 0], [1, 0], [1, 1], [0, 1]], v = [], big = 0, i;
    for (i = 0; i < 4; i++) big = Math.max(big, Math.abs(M[6] * corners[i][0] + M[7] * corners[i][1] + M[8]));
    if (!big) return null;
    [0, 1, 2, 0, 2, 3].forEach(function (k) {
      var x = corners[k][0], y = corners[k][1];
      var s = (M[6] * x + M[7] * y + M[8]) / big;
      v.push(x - bx, y - by, uv[k][0] * s, uv[k][1] * s, s);
    });
    gl.bindBuffer(gl.ARRAY_BUFFER, g.buf);
    gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(v), gl.DYNAMIC_DRAW);
    gl.enableVertexAttribArray(g.p);
    gl.vertexAttribPointer(g.p, 2, gl.FLOAT, false, 20, 0);
    gl.enableVertexAttribArray(g.t);
    gl.vertexAttribPointer(g.t, 3, gl.FLOAT, false, 20, 8);
    gl.uniform2f(g.s, bw, bh);
    gl.drawArrays(gl.TRIANGLES, 0, 6);
    return gl.isContextLost() ? null : g.c;
  }

  /* Warps src onto the quad; returns {canvas, x, y} to draw at x, y. */
  function warp(src, corners, cache, key) {
    if (!ready(src)) return null;
    var s = sizeOf(src);
    var xs = corners.map(function (p) { return p[0]; }), ys = corners.map(function (p) { return p[1]; });
    var bx = Math.floor(Math.min.apply(null, xs)) - 2, by = Math.floor(Math.min.apply(null, ys)) - 2;
    var bw = Math.ceil(Math.max.apply(null, xs)) + 2 - bx, bh = Math.ceil(Math.max.apply(null, ys)) + 2 - by;
    if (bw < 2 || bh < 2 || bw * bh > 36e6) return null;

    var faceW = Math.max(dist(corners[0], corners[1]), dist(corners[3], corners[2]));
    var faceH = Math.max(dist(corners[0], corners[3]), dist(corners[1], corners[2]));
    var p = prescale(src, s.w, s.h, faceW * 1.15, faceH * 1.15, cache);
    var img = p.img, w = p.w, h = p.h;

    var viaGL = glWarp(img, w, h, corners, bx, by, bw, bh);
    if (viaGL) return { canvas: viaGL, x: bx, y: by };

    var out = pooled(cache, key, bw, bh), x = out.getContext('2d');
    x.setTransform(1, 0, 0, 1, 0, 0);
    x.globalCompositeOperation = 'source-over';
    x.clearRect(0, 0, bw, bh);
    x.imageSmoothingEnabled = true;
    x.imageSmoothingQuality = 'high';
    var H = squareToQuad(corners);

    if (H[6] === 0 && H[7] === 0) {
      /* A parallelogram needs no mesh. */
      x.setTransform(H[0] / w, H[3] / w, H[1] / h, H[4] / h, H[2] - bx, H[5] - by);
      x.drawImage(img, 0, 0);
    } else {
      var cols = Math.max(2, Math.min(24, Math.ceil(faceW / 70)));
      var rows = Math.max(2, Math.min(24, Math.ceil(faceH / 70)));
      var P = [], S = [], r, c;
      for (r = 0; r <= rows; r++) {
        P.push([]); S.push([]);
        for (c = 0; c <= cols; c++) {
          var q = project(H, c / cols, r / rows);
          P[r].push([q[0] - bx, q[1] - by]);
          S[r].push([c / cols * w, r / rows * h]);
        }
      }
      for (r = 0; r < rows; r++) {
        for (c = 0; c < cols; c++) {
          tri(x, img, S[r][c], S[r][c + 1], S[r + 1][c + 1], P[r][c], P[r][c + 1], P[r + 1][c + 1]);
          tri(x, img, S[r][c], S[r + 1][c + 1], S[r + 1][c], P[r][c], P[r + 1][c + 1], P[r + 1][c]);
        }
      }
    }

    /* Triangles are grown a little so no seam shows between them; trim the
       outer edge back to the exact quad. */
    x.setTransform(1, 0, 0, 1, 0, 0);
    x.globalCompositeOperation = 'destination-in';
    quadPath(x, corners, bx, by);
    x.fillStyle = '#000';
    x.fill();
    x.globalCompositeOperation = 'source-over';
    return { canvas: out, x: bx, y: by };
  }

  function tri(x, img, s0, s1, s2, d0, d1, d2) {
    var m = triTransform(s0, s1, s2, d0, d1, d2);
    if (!m) return;
    var cx = (d0[0] + d1[0] + d2[0]) / 3, cy = (d0[1] + d1[1] + d2[1]) / 3;
    function grow(p) {
      var dx = p[0] - cx, dy = p[1] - cy, l = Math.hypot(dx, dy) || 1;
      return [p[0] + dx / l * 0.9, p[1] + dy / l * 0.9];
    }
    var e0 = grow(d0), e1 = grow(d1), e2 = grow(d2);
    x.save();
    x.beginPath();
    x.moveTo(e0[0], e0[1]); x.lineTo(e1[0], e1[1]); x.lineTo(e2[0], e2[1]);
    x.closePath();
    x.clip();
    x.transform(m[0], m[1], m[2], m[3], m[4], m[5]);
    x.drawImage(img, 0, 0);
    x.restore();
  }

  /* ---------- light & texture ---------- */

  function drawImageEl(c, img, el, plain, cache) {
    if (!ready(img)) return;
    if (!plain && el.tint) { drawTinted(c, img, el, cache || {}); return; }
    c.save();
    if (!plain) {
      c.globalAlpha = (el.opacity == null ? 100 : el.opacity) / 100;
      if (el.blend && el.blend !== 'source-over') c.globalCompositeOperation = el.blend;
    }
    c.translate(el.x + el.width / 2, el.y + el.height / 2);
    if (el.rotation) c.rotate(el.rotation * Math.PI / 180);
    if (el.flip_x || el.flip_y) c.scale(el.flip_x ? -1 : 1, el.flip_y ? -1 : 1);
    c.drawImage(img, -el.width / 2, -el.height / 2, el.width, el.height);
    c.restore();
  }

  /* A white box render recoloured: the colour is multiplied in, so the
     render's light and shadow stay; `sheen` screens some of the render back
     over it, which keeps the edges readable on a dark box. Only the picture's
     own pixels change. */
  function drawTinted(c, img, el, cache) {
    var pts = rectCorners(el.x + el.width / 2, el.y + el.height / 2, el.width, el.height, el.rotation || 0);
    var xs = pts.map(function (p) { return p[0]; }), ys = pts.map(function (p) { return p[1]; });
    var bx = Math.floor(Math.min.apply(null, xs)), by = Math.floor(Math.min.apply(null, ys));
    var bw = Math.ceil(Math.max.apply(null, xs)) - bx, bh = Math.ceil(Math.max.apply(null, ys)) - by;
    if (bw < 1 || bh < 1 || bw * bh > 36e6) return;
    var off = pooled(cache, 'tint:' + el.id, bw, bh), x = off.getContext('2d');
    function paint(op, alpha) {
      x.save();
      x.globalCompositeOperation = op;
      x.globalAlpha = alpha;
      x.translate(-bx, -by);
      drawImageEl(x, img, el, true);
      x.restore();
    }
    x.setTransform(1, 0, 0, 1, 0, 0);
    x.globalCompositeOperation = 'source-over';
    x.globalAlpha = 1;
    x.clearRect(0, 0, bw, bh);
    paint('source-over', 1);
    /* At full strength the dye looks painted on; by default 70 % of it goes
       in and the render's own white shows through the rest. */
    x.globalCompositeOperation = 'multiply';
    x.globalAlpha = (el.tint_strength == null ? TINT_STRENGTH : el.tint_strength) / 100;
    x.fillStyle = el.tint;
    x.fillRect(0, 0, bw, bh);
    x.globalAlpha = 1;
    if (el.sheen) paint('screen', Math.min(1, el.sheen / 100));
    paint('destination-in', 1);
    /* Unless told to dye everything, only the render's white paper takes the
       colour: chocolate and props rendered with the box keep theirs. */
    var mask = el.tint_all ? null : whiteMask(img);
    if (mask) {
      x.save();
      x.globalCompositeOperation = 'destination-in';
      x.translate(-bx, -by);
      drawImageEl(x, mask, el, true);
      x.restore();
    }
    x.globalCompositeOperation = 'source-over';

    if (mask) {
      var bare = {}, k;
      for (k in el) bare[k] = el[k];
      bare.tint = null;
      drawImageEl(c, img, bare);
    }
    c.save();
    c.globalAlpha = (el.opacity == null ? 100 : el.opacity) / 100;
    if (el.blend && el.blend !== 'source-over') c.globalCompositeOperation = el.blend;
    c.drawImage(off, bx, by);
    c.restore();
  }

  /* Where a render is white-ish paper (light and unsaturated), as alpha. */
  var masks = typeof WeakMap !== 'undefined' ? new WeakMap() : null;
  function whiteMask(img) {
    if (!masks) return null;
    if (masks.has(img)) return masks.get(img);
    var s = sizeOf(img), c = document.createElement('canvas');
    c.width = s.w; c.height = s.h;
    var x = c.getContext('2d', { willReadFrequently: true });
    x.drawImage(img, 0, 0);
    var d;
    try { d = x.getImageData(0, 0, s.w, s.h); } catch (e) { masks.set(img, null); return null; }
    var p = d.data;
    for (var i = 0; i < p.length; i += 4) {
      var r = p[i], g = p[i + 1], b = p[i + 2];
      var max = Math.max(r, g, b), min = Math.min(r, g, b);
      var lum = 0.299 * r + 0.587 * g + 0.114 * b, sat = max ? (max - min) / max : 0;
      var wl = Math.min(1, Math.max(0, (lum - 90) / 50));
      var ws = Math.min(1, Math.max(0, 1 - (sat - 0.12) / 0.13));
      p[i] = p[i + 1] = p[i + 2] = 0;
      p[i + 3] = p[i + 3] * wl * ws;
    }
    x.putImageData(d, 0, 0);
    masks.set(img, c);
    return c;
  }

  /* The rendered box's own shading under the design's quad, as a grey map
     normalised so its brightest paper is white: multiplied over the design it
     brings back the render's light falloff and paper grain without dimming. */
  function shadeMap(srcEl, img, corners, cache, key) {
    if (!ready(img)) return null;
    var sig = JSON.stringify([srcEl.x, srcEl.y, srcEl.width, srcEl.height, srcEl.rotation, srcEl.flip_x, srcEl.flip_y, corners, img.src || '']);
    var hit = cache[key + ':sig'];
    if (hit === sig) return cache[key + ':map'];

    var xs = corners.map(function (p) { return p[0]; }), ys = corners.map(function (p) { return p[1]; });
    var bx = Math.floor(Math.min.apply(null, xs)), by = Math.floor(Math.min.apply(null, ys));
    var bw = Math.ceil(Math.max.apply(null, xs)) - bx, bh = Math.ceil(Math.max.apply(null, ys)) - by;
    if (bw < 2 || bh < 2 || bw * bh > 16e6) return null;

    var c = document.createElement('canvas');
    c.width = bw; c.height = bh;
    var x = c.getContext('2d', { willReadFrequently: true });
    x.translate(-bx, -by);
    drawImageEl(x, img, srcEl, true);
    var data;
    try { data = x.getImageData(0, 0, bw, bh); } catch (e) { return null; }
    var px = data.data, n = bw * bh, i, hist = new Uint32Array(256), count = 0;
    var lum = new Uint8ClampedArray(n);
    for (i = 0; i < n; i++) {
      var k = i * 4;
      var l = (px[k] * 299 + px[k + 1] * 587 + px[k + 2] * 114) / 1000;
      lum[i] = l;
      if (px[k + 3] > 200 && pointInQuad(corners, bx + (i % bw) + 0.5, by + Math.floor(i / bw) + 0.5)) { hist[lum[i]]++; count++; }
    }
    var ref = 255;
    if (count) {
      var want = count * 0.95, acc = 0;
      for (i = 0; i < 256; i++) { acc += hist[i]; if (acc >= want) { ref = Math.max(i, 1); break; } }
    }
    for (i = 0; i < n; i++) {
      var o = i * 4, a = px[o + 3] / 255;
      var g = Math.min(255, lum[i] * 255 / ref);
      g = 255 - (255 - g) * a;
      px[o] = px[o + 1] = px[o + 2] = g;
      px[o + 3] = 255;
    }
    x.putImageData(data, 0, 0);
    var map = { canvas: c, x: bx, y: by };
    cache[key + ':sig'] = sig;
    cache[key + ':map'] = map;
    return map;
  }

  function drawDesignEl(c, scene, el, design, getImage, cache, opts) {
    if (!design || !el.corners) return;
    var w = warp(design, el.corners, cache, 'warp:' + el.id);
    if (!w) return;
    var alpha = (el.opacity == null ? 100 : el.opacity) / 100;
    if (opts.designAlpha != null) alpha *= opts.designAlpha;
    c.save();
    c.globalAlpha = alpha;
    if (el.blend === 'multiply') c.globalCompositeOperation = 'multiply';
    c.drawImage(w.canvas, w.x, w.y);
    c.restore();

    var k = (el.shade || 0) / 100;
    if (!k || !el.shade_from || opts.fast) return;
    var src = null;
    scene.elements.forEach(function (e) { if (e.id === el.shade_from && e.type === 'image') src = e; });
    if (!src) return;
    var map = shadeMap(src, getImage(src.url), el.corners, cache, 'shade:' + el.id);
    if (!map) return;
    c.save();
    quadPath(c, el.corners);
    c.clip();
    c.globalCompositeOperation = 'multiply';
    c.globalAlpha = Math.min(1, k) * alpha;
    c.drawImage(map.canvas, map.x, map.y);
    if (k > 1) {
      c.globalAlpha = (k - 1) * alpha;
      c.drawImage(map.canvas, map.x, map.y);
    }
    c.restore();
  }

  /*
   * scene     {w, h, bgColor, bg, elements}
   * design    the flat design (a canvas or a loaded image)
   * getImage  url -> HTMLImageElement (loading is the caller's business)
   * cache     an object this file keeps its scratch canvases in
   * opts      {fast: skip the shading pass, designAlpha: 0..1, skip: element id,
   *            boxColor: the product's box colour, for renders marked recolor}
   */
  function drawScene(c, scene, design, getImage, cache, opts) {
    opts = opts || {};
    c.save();
    if (scene.bgColor) {
      c.fillStyle = scene.bgColor;
      c.fillRect(0, 0, scene.w, scene.h);
    }
    var bg = scene.bg ? getImage(scene.bg) : null;
    if (ready(bg)) c.drawImage(bg, 0, 0, scene.w, scene.h);
    (scene.elements || []).forEach(function (el) {
      if (el.hidden || el.id === opts.skip) return;
      if (el.type === 'image' && el.recolor && opts.boxColor) {
        /* A render marked "follows the box colour" takes the product's. */
        var own = {}, k;
        for (k in el) own[k] = el[k];
        own.tint = opts.boxColor;
        /* A dark box with no highlights reads as a flat cut-out. */
        if (!own.sheen && luminance(opts.boxColor) < 0.3) own.sheen = 12;
        drawImageEl(c, getImage(el.url), own, false, cache);
      } else if (el.type === 'image') drawImageEl(c, getImage(el.url), el, false, cache);
      else if (el.type === 'design') drawDesignEl(c, scene, el, design, getImage, cache, opts);
    });
    c.restore();
  }

  /* Where a scene point falls on the flat design, in design pixels, or null. */
  function designPoint(scene, x, y, dw, dh) {
    var els = scene.elements || [];
    for (var i = els.length - 1; i >= 0; i--) {
      var el = els[i];
      if (el.type !== 'design' || el.hidden || !pointInQuad(el.corners, x, y)) continue;
      var uv = toUV(el.corners, x, y);
      if (uv) return { x: uv.u * dw, y: uv.v * dh };
    }
    return null;
  }

  /* The same scene at another size, for thumbnails: warping at the small size
     is far cheaper than warping full size and shrinking the result. */
  function scaled(scene, s) {
    return {
      w: scene.w * s, h: scene.h * s, bgColor: scene.bgColor, bg: scene.bg,
      elements: (scene.elements || []).map(function (el) {
        var o = {}, k;
        for (k in el) o[k] = el[k];
        if (el.type === 'image') { o.x *= s; o.y *= s; o.width *= s; o.height *= s; }
        else if (el.corners) o.corners = el.corners.map(function (p) { return [p[0] * s, p[1] * s]; });
        return o;
      })
    };
  }

  global.NefisScene = {
    drawScene: drawScene,
    scaled: scaled,
    drawImageEl: drawImageEl,
    designPoint: designPoint,
    squareToQuad: squareToQuad,
    project: project,
    toUV: toUV,
    pointInQuad: pointInQuad,
    quadPath: quadPath,
    rectCorners: rectCorners,
    ready: ready
  };
})(window);
