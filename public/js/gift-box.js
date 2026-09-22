/*
 * The 3D gift boxes (resources/views/partials/gift-box.blade.php): the face
 * is drawn by wrap-render.js, the side and top show the same paper, darker.
 *
 *   NefisGift.paint(el, { pattern, ribbon, color, scale })
 *   NefisGift.paintAll(root)   — every .gift[data-pattern] inside root
 */
(function (global) {
  'use strict';

  var images = {};
  function load(src, done) {
    var img = images[src];
    if (!img) {
      img = images[src] = new Image();
      img.src = src;
    }
    if (img.complete && img.naturalWidth) done(img);
    else img.addEventListener('load', function () { done(img); }, { once: true });
  }

  function paint(el, o) {
    var front = el.querySelector('.gift-front');
    var faces = el.querySelectorAll('.gift-side, .gift-top');
    var color = o.color || '#F3D3B4';
    el.style.setProperty('--rb', o.ribbon === 'none' ? 'transparent' : color);
    el.classList.toggle('twine', o.ribbon === 'twine');

    /* The paper round the sides, at the size it is printed on the face. */
    var tile = Math.max(16, Math.round((front.getBoundingClientRect().width || 150) * (o.scale || 0.5)));
    faces.forEach(function (f) {
      f.style.backgroundImage = o.pattern ? 'url("' + o.pattern + '")' : 'none';
      f.style.backgroundSize = tile + 'px auto';
    });

    el._giftSrc = o.pattern;
    var ctx = front.getContext('2d');
    NefisWrap.draw(ctx, front.width, front.height, null, o);
    if (o.pattern) {
      load(o.pattern, function (img) {
        if (el._giftSrc === o.pattern) NefisWrap.draw(ctx, front.width, front.height, img, o);
      });
    }
  }

  function paintAll(root) {
    (root || document).querySelectorAll('.gift[data-pattern]').forEach(function (el) {
      paint(el, {
        pattern: el.dataset.pattern,
        ribbon: el.dataset.ribbon,
        color: el.dataset.color,
        scale: parseFloat(el.dataset.scale) || 0.5,
      });
    });
  }

  /* ---------- the viewer: one box, all six sides, turned by hand ---------- */
  var VIEWS = [
    ['Ön', -30, 8], ['Yan', -72, 6], ['Arxa', 150, 8], ['Üst', -20, -55],
  ];

  function face(cls, w, h, transform, dpr) {
    var c = document.createElement('canvas');
    c.className = 'gv-face ' + cls;
    c.width = Math.round(w * dpr);
    c.height = Math.round(h * dpr);
    c.style.width = w + 'px';
    c.style.height = h + 'px';
    c.style.transform = transform;
    return c;
  }

  function open(o) {
    var dpr = Math.min(2, global.devicePixelRatio || 1);
    var H = Math.round(Math.min(global.innerHeight * 0.56, 520)), W = Math.round(H * 969 / 1895), D = Math.round(W * 0.15);

    var root = document.createElement('div');
    root.className = 'gv';
    root.setAttribute('role', 'dialog');
    root.setAttribute('aria-modal', 'true');
    root.setAttribute('aria-label', o.name || 'Qablaşdırma');
    root.innerHTML =
      '<div class="gv-backdrop"></div>' +
      '<div class="gv-panel">' +
        '<button type="button" class="gv-close" aria-label="Bağla">×</button>' +
        '<div class="gv-stage"><div class="gv-box"></div><div class="gv-floor"></div></div>' +
        '<div class="gv-info"><h3></h3><p></p></div>' +
        '<div class="gv-views"></div>' +
        '<p class="gv-hint">Qutunu fırlatmaq üçün barmağınızla və ya siçanla sürüşdürün</p>' +
      '</div>';
    root.querySelector('h3').textContent = o.name || '';
    root.querySelector('.gv-info p').textContent = [o.price, o.ribbonLabel].filter(Boolean).join(' · ');

    var box = root.querySelector('.gv-box');
    box.style.width = W + 'px';
    box.style.height = H + 'px';
    var half = D / 2;
    var faces = {
      front: face('gv-front', W, H, 'translateZ(' + half + 'px)', dpr),
      back: face('gv-back', W, H, 'rotateY(180deg) translateZ(' + half + 'px)', dpr),
      right: face('gv-right', D, H, 'rotateY(90deg) translateZ(' + (W / 2) + 'px)', dpr),
      left: face('gv-left', D, H, 'rotateY(-90deg) translateZ(' + (W / 2) + 'px)', dpr),
      top: face('gv-top', W, D, 'rotateX(90deg) translateZ(' + (H / 2) + 'px)', dpr),
      bottom: face('gv-bottom', W, D, 'rotateX(-90deg) translateZ(' + (H / 2) + 'px)', dpr),
    };
    faces.right.style.left = faces.left.style.left = ((W - D) / 2) + 'px';
    faces.top.style.top = faces.bottom.style.top = ((H - D) / 2) + 'px';
    Object.keys(faces).forEach(function (k) { box.appendChild(faces[k]); });

    function paintFaces(img) {
      var fw = W * dpr, fh = H * dpr, fd = D * dpr;
      NefisWrap.draw(faces.front.getContext('2d'), fw, fh, img, o);
      NefisWrap.draw(faces.back.getContext('2d'), fw, fh, img, Object.assign({}, o, { back: true }));
      NefisWrap.drawSide(faces.right.getContext('2d'), fd, fh, img, o, fw);
      NefisWrap.drawSide(faces.left.getContext('2d'), fd, fh, img, o, fw);
      NefisWrap.drawEnd(faces.top.getContext('2d'), fw, fd, img, o);
      NefisWrap.drawEnd(faces.bottom.getContext('2d'), fw, fd, img, o);
    }
    paintFaces(null);
    if (o.pattern) load(o.pattern, paintFaces);

    /* turning */
    var ry = VIEWS[0][1], rx = VIEWS[0][2], spin = null;
    function apply(smooth) {
      box.style.transition = smooth ? 'transform .8s cubic-bezier(.16,1,.3,1)' : 'none';
      box.style.transform = 'rotateX(' + rx + 'deg) rotateY(' + ry + 'deg)';
    }
    apply(false);

    var views = root.querySelector('.gv-views');
    VIEWS.forEach(function (v, i) {
      var b = document.createElement('button');
      b.type = 'button';
      b.textContent = v[0];
      if (i === 0) b.classList.add('on');
      b.addEventListener('click', function () {
        stopSpin();
        views.querySelectorAll('button').forEach(function (x) { x.classList.remove('on'); });
        b.classList.add('on');
        /* the short way round from wherever the box is now */
        var target = v[1];
        while (target - ry > 180) target -= 360;
        while (ry - target > 180) target += 360;
        ry = target; rx = v[2];
        apply(true);
      });
      views.appendChild(b);
    });
    var spinBtn = document.createElement('button');
    spinBtn.type = 'button';
    spinBtn.textContent = '⟳ Fırlat';
    spinBtn.addEventListener('click', function () { spin ? stopSpin() : startSpin(); });
    views.appendChild(spinBtn);

    function startSpin() {
      spinBtn.classList.add('on');
      var last = performance.now();
      (function step(t) {
        ry += (t - last) * 0.04;
        last = t;
        apply(false);
        spin = requestAnimationFrame(step);
      })(last);
    }
    function stopSpin() {
      if (spin) cancelAnimationFrame(spin);
      spin = null;
      spinBtn.classList.remove('on');
    }

    var stage = root.querySelector('.gv-stage'), drag = null;
    stage.addEventListener('pointerdown', function (e) {
      stopSpin();
      drag = { x: e.clientX, y: e.clientY, ry: ry, rx: rx };
      stage.setPointerCapture(e.pointerId);
      views.querySelectorAll('button').forEach(function (x) { x.classList.remove('on'); });
    });
    stage.addEventListener('pointermove', function (e) {
      if (!drag) return;
      ry = drag.ry + (e.clientX - drag.x) * 0.5;
      rx = Math.max(-80, Math.min(80, drag.rx - (e.clientY - drag.y) * 0.4));
      apply(false);
    });
    var endDrag = function () { drag = null; };
    stage.addEventListener('pointerup', endDrag);
    stage.addEventListener('pointercancel', endDrag);

    /* closing */
    var before = document.activeElement;
    function close() {
      stopSpin();
      document.removeEventListener('keydown', onKey);
      root.remove();
      document.documentElement.classList.remove('gv-open');
      if (before && before.focus) before.focus();
    }
    function onKey(e) {
      if (e.key === 'Escape') close();
      if (e.key === 'ArrowLeft') { stopSpin(); ry -= 30; apply(true); }
      if (e.key === 'ArrowRight') { stopSpin(); ry += 30; apply(true); }
    }
    root.querySelector('.gv-close').addEventListener('click', close);
    root.querySelector('.gv-backdrop').addEventListener('click', close);
    document.addEventListener('keydown', onKey);

    document.body.appendChild(root);
    document.documentElement.classList.add('gv-open');
    root.querySelector('.gv-close').focus();
  }

  /* Any element with data-gift-open (and the wrap's data-*) opens the viewer. */
  document.addEventListener('click', function (e) {
    var el = e.target.closest && e.target.closest('[data-gift-open]');
    if (!el) return;
    var d = el.dataset.pattern ? el.dataset : (el.querySelector('.gift[data-pattern]') || {}).dataset;
    if (!d) return;
    e.preventDefault();
    open({
      pattern: d.pattern, ribbon: d.ribbon, color: d.color, scale: parseFloat(d.scale) || 0.5,
      name: el.dataset.name || d.name, price: el.dataset.price || d.price, ribbonLabel: el.dataset.ribbonLabel || d.ribbonLabel,
    });
  });

  global.NefisGift = { paint: paint, paintAll: paintAll, open: open };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { paintAll(); });
  else paintAll();
})(window);
