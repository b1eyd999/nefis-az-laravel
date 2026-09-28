/* Cutting a face out of whatever the customer sends.
 *
 * Some designs need a head without its background: a joke box where the
 * customer's face sits on a baby's body, a cut-out board at a fair. Asking
 * people to do that themselves in Photoshop is asking them not to order, so
 * the browser does it: it finds the person, keeps only them, finds the face,
 * crops around the head and hands back a PNG with a transparent background.
 *
 * Everything runs on the visitor's own machine — Google's MediaPipe models
 * are fetched once and cached by the browser, nothing is sent anywhere and
 * the hosting does no work. When anything fails (an old browser, no network,
 * no face in the picture) the original photo is kept and the customer frames
 * it by hand, the way he always could.
 */
window.NefisCutout = (function () {
  var VISION = 'https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.14';
  var WASM = 'https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.14/wasm';
  var SEGMENTER = 'https://storage.googleapis.com/mediapipe-models/image_segmenter/selfie_segmenter/float16/latest/selfie_segmenter.tflite';
  var DETECTOR = 'https://storage.googleapis.com/mediapipe-models/face_detector/blaze_face_short_range/float16/1/blaze_face_short_range.tflite';
  var MAX_SIDE = 1400;

  var tools = null;

  /* The models are heavy enough to fetch once and keep for the page's life. */
  function load() {
    if (tools) return tools;

    tools = import(/* webpackIgnore: true */ VISION).then(function (vision) {
      return vision.FilesetResolver.forVisionTasks(WASM).then(function (files) {
        return Promise.all([
          vision.ImageSegmenter.createFromOptions(files, {
            baseOptions: { modelAssetPath: SEGMENTER, delegate: 'GPU' },
            runningMode: 'IMAGE',
            outputCategoryMask: true,
            outputConfidenceMasks: false,
          }),
          vision.FaceDetector.createFromOptions(files, {
            baseOptions: { modelAssetPath: DETECTOR, delegate: 'GPU' },
            runningMode: 'IMAGE',
          }),
        ]).then(function (pair) {
          return { segmenter: pair[0], detector: pair[1] };
        });
      });
    }).catch(function (e) {
      tools = null;
      throw e;
    });

    return tools;
  }

  /* Warms the models up while the customer is still reading the page. */
  function warm() {
    try { load().catch(function () {}); } catch (e) {}
  }

  /**
   * The photograph at working size. NefisPhoto asks the browser to decode it
   * straight to MAX_SIDE where it can, so a 48 MP picture off a phone never
   * becomes a 190 MB bitmap on the way in — that peak is what used to throw
   * the page away on iOS in the middle of an order.
   */
  function readImage(file) {
    if (window.NefisPhoto) {
      return window.NefisPhoto.load(file, MAX_SIDE);
    }

    return new Promise(function (resolve, reject) {
      var url = URL.createObjectURL(file);
      var img = new Image();
      img.onload = function () { URL.revokeObjectURL(url); resolve(img); };
      img.onerror = function () { URL.revokeObjectURL(url); reject(new Error('not an image')); };
      img.src = url;
    });
  }

  function toCanvas(img) {
    var w = img.naturalWidth || img.width, h = img.naturalHeight || img.height;
    var k = Math.min(1, MAX_SIDE / Math.max(w, h));
    if (k === 1 && img.getContext) {
      return img;                 // already a canvas at working size
    }
    var c = document.createElement('canvas');
    c.width = Math.round(w * k);
    c.height = Math.round(h * k);
    c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
    return c;
  }

  /**
   * The mask comes back as one byte per pixel, one value for the person and
   * another for the background — but which is which differs between model
   * builds, and getting it backwards erases the person and keeps the room.
   * So the edge of the picture decides: whatever fills the border is the
   * background, because nobody photographs themselves in the corners.
   */
  function alphaFrom(mask, w, h) {
    var data = mask.getAsUint8Array();
    var border = 0;
    var zeros = 0;
    var step = Math.max(1, Math.round(w / 64));

    for (var x = 0; x < w; x += step) {
      var top = data[x];
      var bottom = data[(h - 1) * w + x];
      border += 2;
      if (top === 0) zeros++;
      if (bottom === 0) zeros++;
    }
    for (var y = 0; y < h; y += step) {
      var left = data[y * w];
      var right = data[y * w + (w - 1)];
      border += 2;
      if (left === 0) zeros++;
      if (right === 0) zeros++;
    }

    /* If zero is what sits around the edges, zero is the background. */
    var personIsZero = border > 0 && zeros / border < 0.5;

    var a = new Uint8ClampedArray(w * h);
    for (var i = 0; i < a.length; i++) {
      var isPerson = personIsZero ? data[i] === 0 : data[i] !== 0;
      a[i] = isPerson ? 255 : 0;
    }
    return a;
  }

  /** How much of the picture survived: all of it means nothing was cut. */
  function keptShare(alpha) {
    var kept = 0;
    for (var i = 0; i < alpha.length; i += 7) {
      if (alpha[i] > 128) kept++;
    }
    return kept / Math.ceil(alpha.length / 7);
  }

  /* A hard edge looks cut with scissors; a couple of blurred pixels do not. */
  function feather(alpha, w, h) {
    var c = document.createElement('canvas');
    c.width = w;
    c.height = h;
    var ctx = c.getContext('2d');
    var img = ctx.createImageData(w, h);
    for (var i = 0; i < alpha.length; i++) {
      img.data[i * 4] = img.data[i * 4 + 1] = img.data[i * 4 + 2] = 255;
      img.data[i * 4 + 3] = alpha[i];
    }
    ctx.putImageData(img, 0, 0);

    var r = Math.max(1, Math.round(Math.min(w, h) / 220));
    var soft = document.createElement('canvas');
    soft.width = w;
    soft.height = h;
    var sctx = soft.getContext('2d');
    sctx.filter = 'blur(' + r + 'px)';
    sctx.drawImage(c, 0, 0);

    /* The blur softened the edge in both directions, and the outward half is
       the wall behind the person: raising the floor pulls the matte back in,
       so no grey rim of somebody's room travels onto the box. */
    sctx.filter = 'none';
    var band = sctx.getImageData(0, 0, w, h);
    for (var i = 3; i < band.data.length; i += 4) {
      var v = band.data[i] / 255;
      v = (v - 0.42) / 0.5;
      band.data[i] = v <= 0 ? 0 : (v >= 1 ? 255 : Math.round(v * 255));
    }
    sctx.putImageData(band, 0, 0);

    return soft;
  }

  /* Around the face: room for hair above, for the chin and neck below. */
  function headBox(face, w, h) {
    var cx = face.originX + face.width / 2;
    var cy = face.originY + face.height / 2;
    var bw = face.width * 2.0;
    var bh = face.height * 2.2;
    var x = cx - bw / 2;
    var y = cy - bh * 0.56;

    x = Math.max(0, Math.min(x, w - 1));
    y = Math.max(0, Math.min(y, h - 1));

    return {
      x: Math.round(x),
      y: Math.round(y),
      w: Math.round(Math.min(bw, w - x)),
      h: Math.round(Math.min(bh, h - y)),
    };
  }

  /**
   * Gives back a PNG of the head alone, or null when the picture cannot be
   * cut (then the caller keeps what the customer chose).
   */
  function prepare(file) {
    if (!file || !/^image\//.test(file.type) || typeof window.createImageBitmap !== 'function') {
      return Promise.resolve(null);
    }

    return Promise.all([load(), readImage(file)]).then(function (both) {
      var kit = both[0];
      var canvas = toCanvas(both[1]);
      var w = canvas.width;
      var h = canvas.height;

      var found = kit.detector.detect(canvas);
      var face = found && found.detections && found.detections.length
        ? found.detections.map(function (d) { return d.boundingBox; })
            .sort(function (a, b) { return b.width * b.height - a.width * a.height; })[0]
        : null;

      var cut = kit.segmenter.segment(canvas);
      var mask = cut.categoryMask;
      if (!mask) {
        return null;
      }

      var alpha = alphaFrom(mask, w, h);
      mask.close();

      /* Nothing separated: a drawing, a crowd, a picture of a wall. Better to
         hand the photo back untouched than to send a half-erased one. */
      var kept = keptShare(alpha);
      if (kept > 0.97 || kept < 0.03) {
        return null;
      }

      var soft = feather(alpha, w, h);

      var box = face ? headBox(face, w, h) : { x: 0, y: 0, w: w, h: h };

      var out = document.createElement('canvas');
      out.width = box.w;
      out.height = box.h;
      var ctx = out.getContext('2d');
      ctx.drawImage(canvas, box.x, box.y, box.w, box.h, 0, 0, box.w, box.h);
      ctx.globalCompositeOperation = 'destination-in';
      ctx.drawImage(soft, box.x, box.y, box.w, box.h, 0, 0, box.w, box.h);
      ctx.globalCompositeOperation = 'source-over';

      return new Promise(function (resolve) {
        out.toBlob(function (blob) {
          if (!blob) { resolve(null); return; }
          resolve(new File([blob], 'uz.png', { type: 'image/png' }));
        }, 'image/png');
      });
    }).catch(function () {
      return null;
    });
  }

  return { prepare: prepare, warm: warm };
})();
