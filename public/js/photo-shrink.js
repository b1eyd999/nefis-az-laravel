/**
 * Opening a photograph without choking on it.
 *
 * A picture off a phone is 12 to 48 megapixels. Handed to `new Image()` the
 * browser decodes every one of those pixels — 48 MP is a 190 MB bitmap — and
 * iOS Safari answers a peak like that by throwing the page away and loading
 * it again, seconds after the customer chose the picture.
 *
 * So we ask the browser to decode straight to the size we actually need.
 * `createImageBitmap` can do that; the full bitmap is then never built. The
 * size comes from the file's own header (a few bytes, no decoding), because
 * without it a small photo would be blown up instead of left alone.
 *
 * Where any of that is missing, the old path still runs: the picture opens
 * as before and is shrunk afterwards. Nothing depends on this working.
 *
 *   NefisPhoto.load(file, 1600).then(function (picture) { … })
 *
 * The answer is a canvas (or an <img> on the fallback path) no larger than
 * the side asked for, ready for drawImage.
 */
(function () {
  'use strict';

  /** Width and height straight out of the file's header, or null. */
  function headerSize(file) {
    return file.slice(0, 64 * 1024).arrayBuffer().then(function (buf) {
      var v = new DataView(buf), n = v.byteLength;

      // PNG: IHDR is always the first chunk.
      if (n > 24 && v.getUint32(0) === 0x89504e47) {
        return { w: v.getUint32(16), h: v.getUint32(20) };
      }

      // JPEG: walk the markers to the frame header that carries the size.
      if (n > 4 && v.getUint16(0) === 0xffd8) {
        var i = 2;
        while (i + 9 < n) {
          if (v.getUint8(i) !== 0xff) { i++; continue; }
          var marker = v.getUint8(i + 1);
          if (marker === 0xd8 || marker === 0x01 || (marker >= 0xd0 && marker <= 0xd7)) { i += 2; continue; }
          var len = v.getUint16(i + 2);
          // SOF0…SOF15, minus the four that are not frame headers
          if (marker >= 0xc0 && marker <= 0xcf && marker !== 0xc4 && marker !== 0xc8 && marker !== 0xcc) {
            return { h: v.getUint16(i + 5), w: v.getUint16(i + 7) };
          }
          i += 2 + len;
        }
      }

      // WebP (VP8X carries the canvas size).
      if (n > 30 && v.getUint32(0) === 0x52494646 && v.getUint32(8) === 0x57454250 && v.getUint32(12) === 0x56503858) {
        return {
          w: 1 + (v.getUint8(24) | (v.getUint8(25) << 8) | (v.getUint8(26) << 16)),
          h: 1 + (v.getUint8(27) | (v.getUint8(28) << 8) | (v.getUint8(29) << 16)),
        };
      }

      return null;
    }).catch(function () { return null; });
  }

  function viaImage(file, max) {
    return new Promise(function (resolve, reject) {
      var url = URL.createObjectURL(file);
      var img = new Image();
      img.onload = function () {
        URL.revokeObjectURL(url);
        var w = img.naturalWidth || img.width, h = img.naturalHeight || img.height;
        var k = Math.min(1, max / Math.max(w, h));
        if (k === 1) { resolve(img); return; }
        var c = document.createElement('canvas');
        c.width = Math.max(1, Math.round(w * k));
        c.height = Math.max(1, Math.round(h * k));
        c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
        img.src = '';                       // let the big one go at once
        resolve(c);
      };
      img.onerror = function () { URL.revokeObjectURL(url); reject(new Error('not an image')); };
      img.src = url;
    });
  }

  function load(file, max) {
    max = max || 1600;

    if (!file || typeof window.createImageBitmap !== 'function' || !file.slice || !File.prototype.arrayBuffer) {
      return viaImage(file, max);
    }

    return headerSize(file).then(function (size) {
      if (!size || !size.w || !size.h) {
        return viaImage(file, max);              // unknown format: the old way
      }
      if (Math.max(size.w, size.h) <= max) {
        return viaImage(file, max);              // already small: nothing to save
      }

      var k = max / Math.max(size.w, size.h);
      var w = Math.max(1, Math.round(size.w * k));
      var h = Math.max(1, Math.round(size.h * k));

      return createImageBitmap(file, { resizeWidth: w, resizeHeight: h, resizeQuality: 'high' })
        .then(function (bitmap) {
          var c = document.createElement('canvas');
          c.width = bitmap.width;
          c.height = bitmap.height;
          c.getContext('2d').drawImage(bitmap, 0, 0);
          if (bitmap.close) bitmap.close();
          return c;
        })
        .catch(function () { return viaImage(file, max); });
    });
  }

  window.NefisPhoto = { load: load, headerSize: headerSize };
})();
