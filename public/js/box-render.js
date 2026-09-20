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
      lines = wrapLines(c, text, t.maxWidth);
      widest = 0;
      for (var i = 0; i < lines.length; i++) widest = Math.max(widest, c.measureText(lines[i]).width);
      if (lines.length <= maxLines && widest <= t.maxWidth) break;
      if (size <= t.fontSize * 0.45 || size <= 8) break;
      size -= Math.max(1, size * 0.04);
    }

    var lineHeight = size * 1.2;
    return { lines: lines, size: size, weight: weight, lineHeight: lineHeight,
             width: widest, height: lines.length * lineHeight };
  }

  function drawText(c, text, t) {
    if (!text) return null;

    c.save();
    c.fillStyle = t.color || '#000';
    c.textAlign = t.align || 'center';
    c.textBaseline = 'middle';

    var x = t.x, y = t.y;
    if (t.rotation) {
      /* Tilted captions turn about their own anchor point. */
      c.translate(t.x, t.y);
      c.rotate(t.rotation * Math.PI / 180);
      x = 0;
      y = 0;
    }

    var layout = fitText(c, text, t);
    var size = layout.size;
    var shrink = size / t.fontSize;
    var startY = y - ((layout.lines.length - 1) * layout.lineHeight) / 2;

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
      var ly = startY + i * layout.lineHeight;
      /* The shadow is cast once, by whichever layer is outermost. */
      shadow(true);
      if (strokeWidth > 0 && strokeColor) {
        /* Photoshop's outside stroke: twice as wide, drawn under the fill. */
        c.lineWidth = legacy ? strokeWidth : strokeWidth * 2;
        c.strokeStyle = strokeColor;
        c.lineJoin = 'round';
        c.strokeText(line, x, ly);
        shadow(false);
      }
      c.fillText(line, x, ly);
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

  global.NefisBox = {
    fontStack: fontStack,
    wrapLines: wrapLines,
    fitText: fitText,
    measureText: measureText,
    drawText: drawText,
    drawLayer: drawLayer
  };
})(window);
