/*
 * Draws a product's catalogue cover — its visual corner-pinned into the scene
 * the owner picked — and uploads it. Runs in the admin's browser (the cover
 * page, the box editor, the scene editor), through the same scene renderer the
 * customer page uses.
 *
 * A job is {id, name, visual, boxColor, scene, upload}, from Product::coverJob().
 */
(function (global) {
  'use strict';

  var WIDTH = 800;

  function load(url) {
    return new Promise(function (resolve) {
      if (!url) return resolve(null);
      var img = new Image();
      img.onload = function () { resolve(img); };
      img.onerror = function () { resolve(null); };
      img.src = url;
    });
  }

  function make(job) {
    var s = job.scene, pics = {};
    var urls = [s.bg].concat((s.elements || []).filter(function (e) { return e.type === 'image'; }).map(function (e) { return e.url; }));
    return Promise.all(urls.filter(Boolean).map(function (u) { return load(u).then(function (img) { pics[u] = img; }); }))
      .then(function () { return load(job.visual); })
      .then(function (visual) {
        if (!visual) throw new Error('vizual yüklənmədi');
        var k = WIDTH / s.w, c = document.createElement('canvas');
        c.width = WIDTH;
        c.height = Math.round(s.h * k);
        var x = c.getContext('2d');
        x.fillStyle = '#ffffff';
        x.fillRect(0, 0, c.width, c.height);
        NefisScene.drawScene(x, NefisScene.scaled(s, k), visual, function (u) { return pics[u] || null; }, {}, { boxColor: job.boxColor });
        return new Promise(function (resolve, reject) {
          c.toBlob(function (b) { if (b) resolve(b); else reject(new Error('şəkil alınmadı')); }, 'image/jpeg', 0.88);
        });
      });
  }

  function upload(job, blob, csrf) {
    var fd = new FormData();
    fd.append('file', blob, 'cover.jpg');
    return fetch(job.upload, { method: 'POST', body: fd, credentials: 'same-origin',
      headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } })
      .then(function (r) {
        return r.json().catch(function () { return {}; }).then(function (res) {
          if (!r.ok) throw new Error(res.message || ('Xəta ' + r.status));
          return res;
        });
      });
  }

  /* Draws and uploads jobs one after another; onStep(i, job, error) reports each. */
  function runAll(jobs, csrf, onStep) {
    var failed = 0;
    return jobs.reduce(function (chain, job, i) {
      return chain.then(function () {
        return make(job).then(function (b) { return upload(job, b, csrf); })
          .then(function () { if (onStep) onStep(i, job, null); })
          .catch(function (err) { failed++; if (onStep) onStep(i, job, err); });
      });
    }, Promise.resolve()).then(function () { return failed; });
  }

  global.NefisCover = { make: make, upload: upload, runAll: runAll };
})(window);
