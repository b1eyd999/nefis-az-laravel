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

  global.NefisGift = { paint: paint, paintAll: paintAll };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { paintAll(); });
  else paintAll();
})(window);
