/*
 * Draws a box's artwork layers and captions onto a 2D canvas.
 *
 * The customer's page and the admin box editor both draw through this file,
 * so what the owner lays out in the editor is exactly what a customer sees.
 *
 * A caption is described in canvas pixels:
 *   x, y        anchor point (left/centre/right edge by `align`, vertical middle)
 *   maxWidth    wrap width; longer text wraps, then shrinks to fit maxLines
 *   fontSize, fontFamily, fontWeight, color, align, rotation, maxLines
 *   strokeColor, strokeWidth   outside stroke; null width = legacy soft outline
 *   shadowColor, shadowBlur, shadowX, shadowY
 */
(function (global) {
  'use strict';

  function fontStack(t) {
    return '"' + (t.fontFamily || 'Inter') + '", Inter, sans-serif';
  }

  /* A line the writer broke by hand (Shift+Enter) always stays its own line;
     what is too long for the slot still wraps within it. */
  function wrapLines(c, text, maxWidth) {
    var lines = [];
    String(text).split('\n').forEach(function (paragraph) {
      var words = paragraph.split(' ');
      var line = '';
      for (var i = 0; i < words.length; i++) {
        var test = line ? line + ' ' + words[i] : words[i];
        if (c.measureText(test).width > maxWidth && line) {
          lines.push(line);
          line = words[i];
        } else {
          line = test;
        }
      }
      lines.push(line);
    });

    return lines;
  }

  /* Text longer than the designer's own wording must not grow into whatever
     sits below it, so it shrinks until it fits the slot's line budget. */
  /* The letters set apart, in the thousandths of the size the design was
     drawn in — Photoshop's own unit. Applied to measuring as well as to
     drawing, or a tracked caption would be shrunk against the wrong width. */
  function spacing(c, t, size) {
    var px = size * (t.tracking || 0) / 1000;
    try { c.letterSpacing = px ? px + 'px' : '0px'; } catch (e) {}
    try { c.fontVariantCaps = t.textCase === 'small' ? 'small-caps' : 'normal'; } catch (e) {}

    return px;
  }

  /** Printed as written, in capitals, or left to the font's small capitals. */
  function cased(text, t) {
    /* The Azerbaijani i has its own capital, İ, and the plain uppercase rule
       would turn it into a Latin I — a name misspelt on someone's present. */
    return t.textCase === 'upper' ? String(text).toLocaleUpperCase('az') : String(text);
  }

  function fitText(c, text, t) {
    /* Lines the writer asked for are never shrunk away: they are the layout,
       not text overrunning the slot. */
    var maxLines = Math.max(1, t.maxLines || 1, String(text).split('\n').length);
    /* Captions carry the weight their font file really has; asking for more
       makes the browser smear a fake bold on top. */
    var weight = t.fontWeight || 600;
    var size = t.fontSize;
    var lines = [];
    var widest = 0;

    for (var attempt = 0; attempt < 40; attempt++) {
      c.font = weight + ' ' + size + 'px ' + fontStack(t);
      spacing(c, t, size);
      lines = wrapLines(c, text, t.maxWidth);
      widest = 0;
      for (var i = 0; i < lines.length; i++) widest = Math.max(widest, c.measureText(lines[i]).width);
      if (lines.length <= maxLines && widest <= t.maxWidth) break;
      if (size <= t.fontSize * 0.45 || size <= 8) break;
      size -= Math.max(1, size * 0.04);
    }

    var lineHeight = size * ((t.lineHeight || 120) / 100);
    return { lines: lines, size: size, weight: weight, lineHeight: lineHeight,
             width: widest, height: lines.length * lineHeight };
  }

  function drawText(c, text, t) {
    if (!text) return null;

    c.save();
    c.fillStyle = t.color || '#000';
    c.textAlign = t.align || 'center';
    c.textBaseline = 'middle';

    /* Everything happens about the caption's own anchor: it turns there, and
       it is stretched there, so a narrowed or tilted caption stays where the
       designer put it instead of sliding across the box. */
    c.translate(t.x, t.y);
    if (t.rotation) {
      c.rotate(t.rotation * Math.PI / 180);
    }
    var sx = (t.scaleX || 100) / 100, sy = (t.scaleY || 100) / 100;
    if (sx !== 1 || sy !== 1) {
      c.scale(sx, sy);
    }
    var x = 0, y = 0;

    text = cased(text, t);
    var layout = fitText(c, text, t);
    var size = layout.size;
    var shrink = size / t.fontSize;
    /* Positive lifts the line, as the panel's baseline shift does. */
    var startY = y - ((layout.lines.length - 1) * layout.lineHeight) / 2 - (t.baselineShift || 0) * shrink;
    /* Letter spacing is added after the last letter too, so a centred caption
       would sit half a gap to the right of where it belongs. */
    var track = size * (t.tracking || 0) / 1000;
    var nudge = t.align === 'center' ? -track / 2 : (t.align === 'right' ? -track : 0);

    /* Styling scales with any shrink-to-fit. Slots with no stroke recorded
       keep the soft dark outline they always had. */
    var legacy = t.strokeWidth === null || t.strokeWidth === undefined;
    var strokeWidth = legacy ? size * 0.08 : t.strokeWidth * shrink;
    var strokeColor = legacy ? 'rgba(0,0,0,.5)' : t.strokeColor;

    function shadow(on) {
      c.shadowColor = on && t.shadowColor ? t.shadowColor : 'transparent';
      c.shadowBlur = on ? (t.shadowBlur || 0) * shrink : 0;
      c.shadowOffsetX = on ? (t.shadowX || 0) * shrink : 0;
      c.shadowOffsetY = on ? (t.shadowY || 0) * shrink : 0;
    }

    for (var i = 0; i < layout.lines.length; i++) {
      var line = layout.lines[i];
      var lx = x + nudge;
      var ly = startY + i * layout.lineHeight;
      /* The shadow is cast once, by whichever layer is outermost. */
      shadow(true);
      if (strokeWidth > 0 && strokeColor) {
        /* Photoshop's outside stroke: twice as wide, drawn under the fill. */
        c.lineWidth = legacy ? strokeWidth : strokeWidth * 2;
        c.strokeStyle = strokeColor;
        c.lineJoin = 'round';
        c.strokeText(line, lx, ly);
        shadow(false);
      }
      c.fillText(line, lx, ly);
    }

    c.restore();
    return layout;
  }

  /* Measures a caption the way drawText would lay it out, without drawing. */
  function measureText(c, text, t) {
    c.save();
    var layout = fitText(c, text || ' ', t);
    c.restore();
    return layout;
  }

  function drawLayer(c, img, l) {
    if (!img || !img.complete || !img.naturalWidth) return;
    c.save();
    c.globalAlpha = (l.opacity == null ? 100 : l.opacity) / 100;
    c.translate(l.x + l.width / 2, l.y + l.height / 2);
    if (l.rotation) c.rotate(l.rotation * Math.PI / 180);
    c.drawImage(img, -l.width / 2, -l.height / 2, l.width, l.height);
    c.restore();
  }

  /* The outline of a shape, drawn about its own centre. */
  function shapePath(c, s) {
    var w = s.width, h = s.height, i;
    c.beginPath();

    if (s.kind === 'ellipse') {
      c.ellipse(0, 0, w / 2, h / 2, 0, 0, Math.PI * 2);

      return;
    }
    if (s.kind === 'line') {
      c.moveTo(-w / 2, 0);
      c.lineTo(w / 2, 0);

      return;
    }
    if (s.kind === 'triangle') {
      c.moveTo(0, -h / 2);
      c.lineTo(w / 2, h / 2);
      c.lineTo(-w / 2, h / 2);
      c.closePath();

      return;
    }
    if (s.kind === 'heart') {
      /* The same curve the star map's heart uses, lifted onto its own centre
         so the point is not cut off. */
      var kx = w / 32, ky = h / 22;
      for (i = 0; i <= 200; i++) {
        var t = (i / 200) * Math.PI * 2;
        var hx = 16 * Math.pow(Math.sin(t), 3);
        var hy = 13 * Math.cos(t) - 5 * Math.cos(2 * t) - 2 * Math.cos(3 * t) - Math.cos(4 * t);
        var px = hx * kx, py = -(hy + 6) * ky;
        i ? c.lineTo(px, py) : c.moveTo(px, py);
      }
      c.closePath();

      return;
    }
    if (s.kind === 'star') {
      for (i = 0; i < 10; i++) {
        var a = (Math.PI / 5) * i - Math.PI / 2;
        var r = i % 2 ? 0.382 : 1;                 // the classic five-pointed ratio
        var sx = Math.cos(a) * (w / 2) * r, sy = Math.sin(a) * (h / 2) * r;
        i ? c.lineTo(sx, sy) : c.moveTo(sx, sy);
      }
      c.closePath();

      return;
    }

    var rad = Math.max(0, Math.min(s.radius || 0, Math.min(w, h) / 2));
    if (!rad) {
      c.rect(-w / 2, -h / 2, w, h);

      return;
    }
    c.moveTo(-w / 2 + rad, -h / 2);
    c.arcTo(w / 2, -h / 2, w / 2, h / 2, rad);
    c.arcTo(w / 2, h / 2, -w / 2, h / 2, rad);
    c.arcTo(-w / 2, h / 2, -w / 2, -h / 2, rad);
    c.arcTo(-w / 2, -h / 2, w / 2, -h / 2, rad);
    c.closePath();
  }

  function drawShape(c, s) {
    if (!s || !s.width || !s.height) return;
    c.save();
    c.globalAlpha = (s.opacity == null ? 100 : s.opacity) / 100;
    c.translate(s.x + s.width / 2, s.y + s.height / 2);
    if (s.rotation) c.rotate(s.rotation * Math.PI / 180);
    shapePath(c, s);
    /* A line has nothing to fill; everything else is filled first and outlined
       after, so the outline sits on top of its own colour. */
    if (s.fill && s.kind !== 'line') {
      c.fillStyle = s.fill;
      c.fill();
    }
    var width = +s.strokeWidth || 0;
    if (width > 0 && s.strokeColor) {
      c.lineWidth = width;
      c.strokeStyle = s.strokeColor;
      c.lineJoin = 'round';
      c.lineCap = 'round';
      c.stroke();
    }
    c.restore();
  }

  global.NefisBox = {
    drawShape: drawShape,
    fontStack: fontStack,
    wrapLines: wrapLines,
    fitText: fitText,
    measureText: measureText,
    drawText: drawText,
    drawLayer: drawLayer
  };
})(window);
