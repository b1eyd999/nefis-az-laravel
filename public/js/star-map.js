/**
 * The sky as it stood over one place at one moment, drawn round.
 *
 * Everything is worked out here, in the browser: no service to call, nothing
 * to pay for, and the same picture comes out years later from the same four
 * numbers — date, time, latitude, longitude — which is what the order keeps.
 *
 * The maths is the ordinary astronomy of it. A star's catalogue position
 * (right ascension, declination) is fixed; what changes with the hour and the
 * place is where that position sits over the horizon. Sidereal time gives the
 * rotation of the earth under the sky; from it the star's height above the
 * horizon and its compass bearing follow. The visible half of the sky is then
 * laid flat inside a circle — the zenith at the middle, the horizon at the
 * rim — the way a star chart has always been drawn.
 *
 * East is on the LEFT. That is not a mistake: the reader is looking up, not
 * down at a map, and the chart is held overhead.
 *
 *   NefisStarMap.draw(canvas, {
 *     date: '2026-02-14', time: '21:30', tzOffset: 4,
 *     lat: 40.3777, lon: 49.8920,
 *     shape: 'circle', style: 'night', ring: true, lines: true
 *   });
 *
 * Needs star-data.js on the page (window.NefisSky).
 */
(function () {
  'use strict';

  var RAD = Math.PI / 180;

  /* The look of the thing. Each entry is one of the printed styles: the card
     behind, the disc of sky, the stars on it, the figures joining them, and
     the graduated ring with its lettering. */
  var STYLES = {
    night:   { page: '#0b0b0d', disc: '#0b0b0d', star: '#ffffff', line: 'rgba(255,255,255,.55)', ring: '#ffffff', text: '#ffffff' },
    ink:     { page: '#ffffff', disc: '#111114', star: '#ffffff', line: 'rgba(255,255,255,.5)',  ring: '#111114', text: '#111114' },
    paper:   { page: '#ffffff', disc: '#ffffff', star: '#111114', line: 'rgba(17,17,20,.45)',    ring: '#111114', text: '#111114' },
    navy:    { page: '#1b3a5c', disc: '#1b3a5c', star: '#ffffff', line: 'rgba(159,196,232,.6)',  ring: '#9fc4e8', text: '#e8f1fa' },
    crimson: { page: '#8c1626', disc: '#8c1626', star: '#ffffff', line: 'rgba(240,184,192,.55)', ring: '#f0b8c0', text: '#fbe9ec' },
    cream:   { page: '#f4e6cd', disc: '#4a3520', star: '#f7ecd8', line: 'rgba(247,236,216,.45)', ring: '#4a3520', text: '#4a3520' },
    sky:     { page: '#dcecf5', disc: '#dcecf5', star: '#2b4c63', line: 'rgba(43,76,99,.45)',    ring: '#2b4c63', text: '#2b4c63' },
    /* The two painted ones: the sky is not a disc on a card, it is the card,
       with the Milky Way glowing in colour and the page fading in underneath
       so the wording has somewhere quiet to sit. */
    cosmos:  { page: '#ffffff', disc: '#0b0a1f', star: '#ffffff', line: 'rgba(255,255,255,.4)',  ring: '#ffffff', text: '#ffffff',
               glow: ['rgba(96,72,200,.55)', 'rgba(38,26,92,0)'], fade: '#ffffff' },
    moss:    { page: '#ffffff', disc: '#0d1410', star: '#ffffff', line: 'rgba(255,255,255,.38)', ring: '#ffffff', text: '#ffffff',
               glow: ['rgba(92,132,96,.5)', 'rgba(20,40,26,0)'], fade: '#ffffff' },
  };

  var POINTS = ['North', 'NNE', 'NE', 'ENE', 'East', 'ESE', 'SE', 'SSE',
                'South', 'SSW', 'SW', 'WSW', 'West', 'WNW', 'NW', 'NNW'];

  function styleOf(name) { return STYLES[name] || STYLES.night; }

  /** Days since the epoch astronomers count from, for a moment in UTC. */
  function julianDay(ms) {
    return ms / 86400000 + 2440587.5;
  }

  /** How far the earth has turned, in degrees, at Greenwich. */
  function siderealAtGreenwich(jd) {
    var d = jd - 2451545.0;
    var t = d / 36525;
    var g = 280.46061837 + 360.98564736629 * d + 0.000387933 * t * t - t * t * t / 38710000;

    return ((g % 360) + 360) % 360;
  }

  /**
   * The moment the customer means, as UTC milliseconds.
   *
   * He writes the time on the clock that hung on the wall in that place, so it
   * is read with that place's offset — not the browser's, which is usually a
   * different country by the time an order is printed.
   */
  function momentOf(opts) {
    var date = String(opts.date || '').split('-');
    var time = String(opts.time || '00:00').split(':');
    var offset = opts.tzOffset == null ? 4 : Number(opts.tzOffset);

    return Date.UTC(
      Number(date[0]) || 2000, (Number(date[1]) || 1) - 1, Number(date[2]) || 1,
      (Number(time[0]) || 0) - offset, Number(time[1]) || 0, 0
    );
  }

  /**
   * Where one catalogue position falls on the paper, or null when it is under
   * the horizon and cannot be seen at all.
   *
   * @param  {number} lst  local sidereal time, degrees
   */
  function place(raDeg, decDeg, lst, sinLat, cosLat, clamp) {
    var h = (lst - raDeg) * RAD;
    var dec = decDeg * RAD;
    var sinDec = Math.sin(dec), cosDec = Math.cos(dec);
    var sinAlt = sinDec * sinLat + cosDec * cosLat * Math.cos(h);

    if (sinAlt <= 0 && ! clamp) {
      return null;                       // below the horizon: not in the sky that night
    }
    if (sinAlt <= 0) {
      /* For a shape that has to stay closed — the Milky Way's outline — the
         part under the horizon is laid on the rim rather than dropped, and
         the disc's own edge then cuts it. */
      var azOut = Math.atan2(-cosDec * Math.sin(h), sinDec * cosLat - cosDec * sinLat * Math.cos(h));

      return { x: -Math.sin(azOut), y: -Math.cos(azOut), alt: 0 };
    }

    var alt = Math.asin(sinAlt);
    var az = Math.atan2(-cosDec * Math.sin(h), sinDec * cosLat - cosDec * sinLat * Math.cos(h));

    /* Stereographic: the zenith at the centre, the horizon on the rim. It is
       the projection star charts are drawn in because it keeps the shape of a
       constellation recognisable instead of smearing it near the edge. */
    var r = Math.tan((Math.PI / 2 - alt) / 2);

    return { x: -r * Math.sin(az), y: -r * Math.cos(az), alt: alt };
  }

  /** The outline the sky is poured into. */
  function clipShape(ctx, shape, cx, cy, r, box) {
    ctx.beginPath();

    if (shape === 'full' && box) {
      /* Not a disc at all: the sky covers the whole card. */
      ctx.rect(box.x, box.y, box.w, box.h);

      return;
    }

    if (shape === 'heart') {
      /* The usual heart curve. It is not symmetric about its own origin — the
         point hangs to -17 while the lobes only reach +5 — so it is both
         scaled to fit the circle it replaces and lifted onto that circle's
         centre, or the point would be cut off by the edge of the card. */
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

  /**
   * The ring around the sky.
   *
   *  'simple'  one thin circle, nothing written — the quiet one;
   *  'degrees' the graduated band with the compass points;
   *  'double'  the same with a second circle outside it, the old printed look.
   */
  function drawRing(ctx, colours, cx, cy, r, size, kind) {
    var thin = Math.max(1, size / 900);
    var band = r * 0.085;

    ctx.save();
    ctx.strokeStyle = colours.ring;
    ctx.fillStyle = colours.text;
    ctx.lineWidth = thin;

    if (kind === 'simple') {
      ctx.beginPath();
      ctx.arc(cx, cy, r + band * 0.5, 0, Math.PI * 2);
      ctx.stroke();
      ctx.restore();

      return;
    }

    ctx.beginPath();
    ctx.arc(cx, cy, r + band * 0.15, 0, Math.PI * 2);
    ctx.stroke();
    ctx.beginPath();
    ctx.arc(cx, cy, r + band, 0, Math.PI * 2);
    ctx.stroke();
    if (kind === 'double') {
      ctx.beginPath();
      ctx.arc(cx, cy, r + band * 2.1, 0, Math.PI * 2);
      ctx.stroke();
    }

    for (var deg = 0; deg < 360; deg += 2) {
      var a = (deg - 90) * RAD;                  // 0° at the top, growing clockwise on paper
      var long = deg % 10 === 0;
      var from = r + band * (long ? 0.15 : 0.45);
      ctx.beginPath();
      ctx.moveTo(cx + Math.cos(a) * from, cy + Math.sin(a) * from);
      ctx.lineTo(cx + Math.cos(a) * (r + band), cy + Math.sin(a) * (r + band));
      ctx.stroke();
    }

    /* The numbers sit inside the band, the compass names outside it, both
       turned so they are read from the middle of the card. */
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.font = (size / 90 | 0) + 'px "Helvetica Neue", Arial, sans-serif';

    for (var d = 0; d < 360; d += 10) {
      if (d % 90 === 0) {
        continue;                                 // the four cardinals are spelled out instead
      }
      var ang = (d - 90) * RAD;
      var rx = cx + Math.cos(ang) * (r + band * 0.62);
      var ry = cy + Math.sin(ang) * (r + band * 0.62);
      ctx.save();
      ctx.translate(rx, ry);
      ctx.rotate(ang + Math.PI / 2);
      ctx.fillText(String(d), 0, 0);
      ctx.restore();
    }

    ctx.font = (size / 62 | 0) + 'px "Helvetica Neue", Arial, sans-serif';
    var out = kind === 'double' ? 2.65 : 1.55;
    for (var p = 0; p < POINTS.length; p++) {
      var pa = (p * 22.5 - 90) * RAD;
      var px = cx + Math.cos(pa) * (r + band * out);
      var py = cy + Math.sin(pa) * (r + band * out);
      ctx.save();
      ctx.translate(px, py);
      ctx.rotate(pa + Math.PI / 2);
      ctx.fillText(POINTS[p], 0, 0);
      ctx.restore();
    }

    ctx.restore();
  }

  /**
   * Draws the sky onto a canvas (or any 2d context) and answers the circle it
   * used, so a caller can put its own wording underneath.
   */
  function draw(target, opts) {
    opts = opts || {};
    var sky = window.NefisSky;
    if (!sky) {
      return null;
    }

    var canvas = target.getContext ? target : null;
    var ctx = canvas ? canvas.getContext('2d') : target;
    var size = opts.size || (canvas ? Math.min(canvas.width, canvas.height) : 1000);
    var colours = styleOf(opts.style);
    var full = opts.shape === 'full';
    /* `ring` was once simply on or off; the three named strengths came later. */
    var ringKind = opts.ring === true ? 'degrees' : (opts.ring === false ? 'none' : (opts.ring || 'degrees'));
    if (full || opts.shape === 'heart') {
      ringKind = 'none';
    }
    var withRing = ringKind !== 'none';
    var cx = opts.cx == null ? (canvas ? canvas.width / 2 : size / 2) : opts.cx;
    var cy = opts.cy == null ? (canvas ? canvas.height / 2 : size / 2) : opts.cy;
    /* Room for the graduated band, when there is one. */
    var r = (opts.radius || size / 2) * (withRing ? (ringKind === 'double' ? 0.78 : 0.86) : 0.98);
    /* A full-bleed sky is drawn across the whole window, and the circle it
       would have had still sets how much of the sky is shown. */
    var box = opts.box || { x: cx - size / 2, y: cy - size / 2, w: size, h: size };
    if (full) {
      r = Math.max(box.w, box.h) * 0.62;
      cy = box.y + box.h * 0.34;
      cx = box.x + box.w / 2;
    }

    var jd = julianDay(momentOf(opts));
    var lst = siderealAtGreenwich(jd) + Number(opts.lon || 0);
    var lat = Number(opts.lat || 0) * RAD;
    var sinLat = Math.sin(lat), cosLat = Math.cos(lat);

    if (opts.page !== false && canvas) {
      ctx.fillStyle = colours.page;
      ctx.fillRect(0, 0, canvas.width, canvas.height);
    }


    ctx.save();
    clipShape(ctx, opts.shape, cx, cy, r, box);
    ctx.fillStyle = colours.disc;
    ctx.fill();
    ctx.clip();

    /* The painted styles glow where the galaxy runs, before anything is on it. */
    if (colours.glow) {
      var glow = ctx.createRadialGradient(cx, cy, 0, cx, cy, r);
      glow.addColorStop(0, colours.glow[0]);
      glow.addColorStop(1, colours.glow[1]);
      ctx.fillStyle = glow;
      ctx.fillRect(box.x, box.y, box.w, box.h);
    }

    /* The Milky Way first, under everything: a soft wash, not a shape with an
       edge. The three outlines are nested, so stacking them thickens the band
       towards its middle the way it looks. */
    if (opts.milkyWay && window.NefisSkyExtra) {
      ctx.save();
      ctx.fillStyle = colours.star;
      ctx.globalAlpha = 0.07;
      var rings = window.NefisSkyExtra.milkyWay;
      for (var m = 0; m < rings.length; m++) {
        var ring = rings[m];
        ctx.beginPath();
        for (var q = 0; q < ring.length; q += 2) {
          var mp = place(ring[q], ring[q + 1], lst, sinLat, cosLat, true);
          var mx = cx + mp.x * r, my = cy + mp.y * r;
          q ? ctx.lineTo(mx, my) : ctx.moveTo(mx, my);
        }
        ctx.closePath();
        ctx.fill();
      }
      ctx.restore();
    }

    if (opts.lines !== false) {
      ctx.strokeStyle = colours.line;
      ctx.lineWidth = Math.max(0.6, size / 1300);
      ctx.lineJoin = 'round';

      for (var s = 0; s < sky.lines.length; s++) {
        var seg = sky.lines[s];
        var drawing = false;
        ctx.beginPath();
        for (var c = 0; c < seg.length; c += 2) {
          var pt = place(seg[c], seg[c + 1], lst, sinLat, cosLat);
          if (!pt) {
            drawing = false;               // the figure walks out of the sky here
            continue;
          }
          var lx = cx + pt.x * r, ly = cy + pt.y * r;
          drawing ? ctx.lineTo(lx, ly) : ctx.moveTo(lx, ly);
          drawing = true;
        }
        ctx.stroke();
      }
    }

    ctx.fillStyle = colours.star;
    var stars = sky.stars;
    for (var i = 0; i < stars.length; i += 3) {
      var st = place(stars[i], stars[i + 1], lst, sinLat, cosLat);
      if (!st) {
        continue;
      }
      /* A bright star is drawn bigger, the way the eye reports it; the scale
         is the one printers have used for a century, not a physical one. */
      var mag = stars[i + 2];
      var dot = (size / 1000) * Math.max(0.35, Math.pow(6.4 - mag, 1.35) * 0.42);
      ctx.beginPath();
      ctx.arc(cx + st.x * r, cy + st.y * r, dot, 0, Math.PI * 2);
      ctx.fill();
    }

    /* The names, where the reader can see which figure is which. Latin is
       what star charts have always used; the other languages are there for
       a customer who would rather read his own. */
    if (opts.labels && window.NefisSkyExtra) {
      var col = { la: 2, ru: 3, en: 4, tr: 5 }[opts.labelLang] || 2;
      ctx.save();
      ctx.fillStyle = colours.text;
      ctx.globalAlpha = 0.75;
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.font = Math.max(7, size / 75 | 0) + 'px "Helvetica Neue", Arial, sans-serif';
      try { ctx.letterSpacing = Math.max(0.5, size / 900) + 'px'; } catch (e) {}
      var names = window.NefisSkyExtra.names;
      for (var n = 0; n < names.length; n++) {
        var np = place(names[n][0], names[n][1], lst, sinLat, cosLat);
        if (! np || np.alt < 0.12) {
          continue;                       // right on the horizon it would sit half outside
        }
        var label = names[n][col] || names[n][2];
        ctx.fillText(String(label).toUpperCase(), cx + np.x * r, cy + np.y * r);
      }
      ctx.restore();
    }

    /* A heart over the middle of the sky, for the customers who ask for one. */
    if (opts.heart) {
      var hr = r * 0.07;
      ctx.save();
      ctx.fillStyle = opts.heartColour || '#e2455a';
      ctx.beginPath();
      for (var t2 = 0; t2 <= 120; t2++) {
        var a2 = (t2 / 120) * Math.PI * 2;
        var hx = 16 * Math.pow(Math.sin(a2), 3);
        var hy = 13 * Math.cos(a2) - 5 * Math.cos(2 * a2) - 2 * Math.cos(3 * a2) - Math.cos(4 * a2);
        var pxh = cx + hx * (hr / 16), pyh = cy - (hy + 6) * (hr / 11);
        t2 ? ctx.lineTo(pxh, pyh) : ctx.moveTo(pxh, pyh);
      }
      ctx.closePath();
      ctx.fill();
      ctx.restore();
    }

    ctx.restore();

    /* Underneath, the page washes in, so the wording has somewhere quiet to
       sit — the whole point of the painted styles. */
    if (full) {
      var fade = ctx.createLinearGradient(0, box.y + box.h * 0.36, 0, box.y + box.h * 0.78);
      fade.addColorStop(0, 'rgba(255,255,255,0)');
      fade.addColorStop(1, colours.fade || colours.page);
      ctx.fillStyle = fade;
      ctx.fillRect(box.x, box.y, box.w, box.h);
      ctx.fillStyle = colours.fade || colours.page;
      ctx.fillRect(box.x, box.y + box.h * 0.78, box.w, box.h * 0.22);
    }

    /* The rim of the sky itself, so the disc keeps an edge on a page of the
       same colour. */
    if (! full) {
      ctx.save();
      ctx.strokeStyle = colours.ring;
      ctx.lineWidth = Math.max(1, size / 700);
      clipShape(ctx, opts.shape, cx, cy, r, box);
      ctx.stroke();
      ctx.restore();
    }

    if (withRing) {
      drawRing(ctx, colours, cx, cy, r, size, ringKind);
    }

    return { cx: cx, cy: cy, r: r, colours: colours };
  }

  /** Degrees as a printed coordinate: 38°47'33"N. */
  function coordinate(value, positive, negative) {
    var v = Math.abs(Number(value) || 0);
    var d = Math.floor(v);
    var m = Math.floor((v - d) * 60);
    var s = Math.round((((v - d) * 60) - m) * 60);
    if (s === 60) { s = 0; m += 1; }
    if (m === 60) { m = 0; d += 1; }

    return d + '°' + (m < 10 ? '0' : '') + m + "'" + (s < 10 ? '0' : '') + s + '"' + (Number(value) < 0 ? negative : positive);
  }

  window.NefisStarMap = {
    draw: draw,
    styles: STYLES,
    coordinate: coordinate,
    /** "38°47'33"N 48°28'47"E", the way it is printed under the sky. */
    coordinates: function (lat, lon) {
      return coordinate(lat, 'N', 'S') + ' ' + coordinate(lon, 'E', 'W');
    },
  };
})();
