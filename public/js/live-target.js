/* Live photos (AR): prepares a picture for the phone's camera right in the
   customer's browser (MindAR's compiler), and hands the result to a form.
   The library is big, so it is fetched only when a live photo is wanted. */
window.NefisLive = (function(){
  var LIB = 'https://cdn.jsdelivr.net/npm/mind-ar@1.2.5/dist/mindar-image.prod.js';
  var loading = null;

  function preload(){
    if (window.MINDAR && window.MINDAR.IMAGE) return Promise.resolve();
    if (!loading) loading = import(LIB).catch(function(e){ loading = null; throw e; });
    return loading;
  }

  /* A copy of the picture no bigger than `max` px a side, on white (a
     transparent design would otherwise turn black). */
  function flatten(src, max){
    var w = src.naturalWidth || src.width, h = src.naturalHeight || src.height;
    var k = Math.min(1, max / Math.max(w, h));
    var c = document.createElement('canvas');
    c.width = Math.max(1, Math.round(w * k));
    c.height = Math.max(1, Math.round(h * k));
    var x = c.getContext('2d');
    x.fillStyle = '#fff';
    x.fillRect(0, 0, c.width, c.height);
    x.drawImage(src, 0, 0, c.width, c.height);
    return c;
  }

  function toBlob(canvas, type, quality){
    return new Promise(function(ok, bad){
      canvas.toBlob(function(b){ b ? ok(b) : bad(new Error('blob')); }, type, quality);
    });
  }

  function loadFile(file){
    return new Promise(function(ok, bad){
      var url = URL.createObjectURL(file);
      var img = new Image();
      img.onload = function(){ ok(img); };
      img.onerror = function(){ URL.revokeObjectURL(url); bad(new Error('image')); };
      img.src = url;
    });
  }

  /* The camera's tracking data (.mind) made from a picture (image or canvas).
     onProgress gets 0–100. About 1000 px is all the camera needs. */
  function compile(src, onProgress){
    return preload().then(function(){
      var c = flatten(src, 1000);
      var compiler = new window.MINDAR.IMAGE.Compiler();
      return compiler.compileImageTargets([c], function(p){ if (onProgress) onProgress(Math.min(100, Math.round(p))); })
        .then(function(){ return compiler.exportData(); })
        .then(function(data){ return new Blob([data], { type: 'application/octet-stream' }); });
    });
  }

  /* Puts a made file into a form's file field. False where the browser cannot. */
  function attach(input, blob, name){
    try {
      var dt = new DataTransfer();
      dt.items.add(new File([blob], name, { type: blob.type || 'application/octet-stream' }));
      input.files = dt.files;
      return input.files.length === 1;
    } catch (e) {
      return false;
    }
  }

  return { preload: preload, flatten: flatten, toBlob: toBlob, loadFile: loadFile, compile: compile, attach: attach };
})();
