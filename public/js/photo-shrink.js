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

  /* The one format the browsers still refuse.
   *
   * Every iPhone photographs in HEIC. Safari shows one because the system
   * decodes it, but no browser will draw it into a <canvas>, which is what
   * every design on this site needs. The customer picks his picture, the box
   * stays empty, and nothing tells him why.
   *
   * So it is turned into a JPEG first, here, in his own browser. The decoder
   * is heavy (a wasm build of libheif), so it is fetched only when a HEIC
   * actually turns up, and once per page. */
  var HEIC_LIB = 'https://cdn.jsdelivr.net/npm/heic2any@0.0.4/dist/heic2any.min.js';
  var heicLib = null;

  /** Does this file look like one, by what the picker said and by its name? */
  function isHeic(file) {
    if (!file) return false;
    var type = (file.type || '').toLowerCase();
    if (type.indexOf('heic') !== -1 || type.indexOf('heif') !== -1) return true;

    return /\.(heic|heif)$/i.test(file.name || '');
  }

  /** And by what is actually inside it: the ISO box brand, bytes 8…12. */
  function heicInside(file) {
    if (!file || !file.slice) return Promise.resolve(false);

    return file.slice(0, 16).arrayBuffer().then(function (buf) {
      var v = new DataView(buf);
      if (v.byteLength < 12 || v.getUint32(4) !== 0x66747970) return false;   // 'ftyp'
      var brand = String.fromCharCode(v.getUint8(8), v.getUint8(9), v.getUint8(10), v.getUint8(11));

      return ['heic', 'heix', 'heim', 'heis', 'hevc', 'hevx', 'mif1', 'msf1'].indexOf(brand) !== -1;
    }).catch(function () { return false; });
  }

  function decoder() {
    if (heicLib) return heicLib;

    heicLib = new Promise(function (resolve, reject) {
      if (window.heic2any) { resolve(window.heic2any); return; }
      var s = document.createElement('script');
      s.src = HEIC_LIB;
      s.onload = function () {
        window.heic2any ? resolve(window.heic2any) : reject(new Error('heic2any'));
      };
      s.onerror = function () { reject(new Error('heic2any')); };
      document.head.appendChild(s);
    });

    // A failed fetch must not poison every later attempt.
    heicLib.catch(function () { heicLib = null; });

    return heicLib;
  }

  /** The same picture as a JPEG the browser can draw. */
  function toJpeg(file) {
    return decoder().then(function (convert) {
      return convert({ blob: file, toType: 'image/jpeg', quality: 0.92 });
    }).then(function (out) {
      var blob = Array.isArray(out) ? out[0] : out;
      var name = (file.name || 'photo').replace(/\.[^.]+$/, '') + '.jpg';

      return new File([blob], name, { type: 'image/jpeg', lastModified: Date.now() });
    });
  }

  /**
   * The file in a form the rest of the site can use: a HEIC comes back as a
   * JPEG, everything else comes back untouched. If the conversion fails the
   * original is handed back, so the caller's own error is what the customer
   * reads rather than one of ours.
   */
  function usable(file) {
    if (!file) return Promise.resolve(file);
    if (isHeic(file)) return toJpeg(file).catch(function () { return file; });

    return heicInside(file).then(function (yes) {
      return yes ? toJpeg(file).catch(function () { return file; }) : file;
    });
  }

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
        var w = img.naturalWidth || img.width, h = img.naturalHeight || img.height;
        var k = Math.min(1, max / Math.max(w, h));
        if (k === 1) {
          /* Small enough already, so the <img> itself is the answer — and its
             address has to stay alive with it. Revoking here left a picture
             that still draws but can no longer be handed to another <img>,
             which is how the live-photo page's preview went blank for every
             photograph that did not need shrinking. The browser frees the
             address with the page. */
          resolve(img);
          return;
        }
        URL.revokeObjectURL(url);
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

    // An iPhone's own format, before anything tries to decode it.
    if (isHeic(file)) {
      return toJpeg(file).then(function (jpeg) { return decode(jpeg, max); });
    }

    return decode(file, max).catch(function (e) {
      /* Nothing could open it. One explanation is left: a HEIC the picker
         described as something else, or did not describe at all. */
      return heicInside(file).then(function (yes) {
        if (!yes) throw e;

        return toJpeg(file).then(function (jpeg) { return decode(jpeg, max); });
      });
    });
  }

  function decode(file, max) {
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

  window.NefisPhoto = {
    load: load,
    headerSize: headerSize,
    isHeic: isHeic,
    toJpeg: toJpeg,
    usable: usable,
  };
})();
