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

  function readImage(file) {
    return new Promise(function (resolve, reject) {
      var url = URL.createObjectURL(file);
      var img = new Image();
      img.onload = function () { URL.revokeObjectURL(url); resolve(img); };
      img.onerror = function () { URL.revokeObjectURL(url); reject(new Error('not an image')); };
      img.src = url;
    });
  }

  function toCanvas(img) {
    var k = Math.min(1, MAX_SIDE / Math.max(img.width, img.height));
    var c = document.createElement('canvas');
    c.width = Math.round(img.width * k);
    c.height = Math.round(img.height * k);
    c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
    return c;
  }

  /* The mask comes back as one byte per pixel: 0 where the person is. */
  function alphaFrom(mask, w, h) {
    var data = mask.getAsUint8Array();
    var a = new Uint8ClampedArray(w * h);
    for (var i = 0; i < a.length; i++) {
      a[i] = data[i] === 0 ? 255 : 0;
    }
    return a;
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

    var soft = document.createElement('canvas');
    soft.width = w;
    soft.height = h;
    var sctx = soft.getContext('2d');
    sctx.filter = 'blur(' + Math.max(1, Math.round(Math.min(w, h) / 220)) + 'px)';
    sctx.drawImage(c, 0, 0);

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

      var soft = feather(alphaFrom(mask, w, h), w, h);
      mask.close();

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
