/* The eraser over a cut-out head.
 *
 * The models get the background off on their own, but a corner of a wall or
 * a piece of shoulder survives often enough that the customer needs a way to
 * wipe it himself — here, with a finger, not in Photoshop. What he wipes is
 * the alpha channel only: the picture keeps its pixels, so "restore" brings
 * them back exactly as they were.
 */
window.NefisBrush = (function () {
  var root, stage, canvas, ctx, sizeInput, undoBtn, saveBtn;
  var source = null;      // the photo as it came, for restoring
  var scale = 1;
  var tool = 'erase';
  var painting = false;
  var history = [];
  var done = null;

  function el(id) { return document.getElementById(id); }

  function setup() {
    if (root) return true;
    root = el('brush');
    if (!root) return false;

    stage = el('brush-stage');
    canvas = el('brush-canvas');
    ctx = canvas.getContext('2d', { willReadFrequently: true });
    sizeInput = el('brush-size');
    undoBtn = el('brush-undo');
    saveBtn = el('brush-save');

    root.querySelectorAll('[data-close]').forEach(function (b) {
      b.addEventListener('click', close);
    });
    root.querySelectorAll('[data-tool]').forEach(function (b) {
      b.addEventListener('click', function () {
        tool = b.dataset.tool;
        root.querySelectorAll('[data-tool]').forEach(function (o) { o.classList.toggle('on', o === b); });
      });
    });

    undoBtn.addEventListener('click', undo);
    saveBtn.addEventListener('click', save);

    canvas.addEventListener('pointerdown', down);
    canvas.addEventListener('pointermove', move);
    window.addEventListener('pointerup', up);
    canvas.addEventListener('pointerleave', up);

    return true;
  }

  function fit(img) {
    var box = stage.getBoundingClientRect();
    var room = Math.min(box.width || 520, 520);
    var tall = Math.min(box.height || 420, 420);
    scale = Math.min(room / img.width, tall / img.height, 1);
    canvas.width = img.width;
    canvas.height = img.height;
    canvas.style.width = Math.round(img.width * scale) + 'px';
    canvas.style.height = Math.round(img.height * scale) + 'px';
  }

  function remember() {
    /* Three steps back is enough for a finger; more would eat memory on a phone. */
    history.push(ctx.getImageData(0, 0, canvas.width, canvas.height));
    if (history.length > 8) history.shift();
  }

  function undo() {
    var last = history.pop();
    if (last) ctx.putImageData(last, 0, 0);
  }

  function at(e) {
    var box = canvas.getBoundingClientRect();
    return {
      x: (e.clientX - box.left) / box.width * canvas.width,
      y: (e.clientY - box.top) / box.height * canvas.height,
    };
  }

  function dab(p) {
    var r = Number(sizeInput.value) / 2 / scale;

    if (tool === 'erase') {
      ctx.save();
      ctx.globalCompositeOperation = 'destination-out';
      ctx.beginPath();
      ctx.arc(p.x, p.y, r, 0, Math.PI * 2);
      ctx.fillStyle = 'rgba(0,0,0,1)';
      ctx.fill();
      ctx.restore();
      return;
    }

    /* Restoring paints the original photo back inside the circle. */
    ctx.save();
    ctx.beginPath();
    ctx.arc(p.x, p.y, r, 0, Math.PI * 2);
    ctx.clip();
    ctx.drawImage(source, 0, 0);
    ctx.restore();
  }

  function down(e) {
    painting = true;
    /* Keeping the pointer is a nicety; a browser that refuses must not stop
       the stroke. */
    try { canvas.setPointerCapture(e.pointerId); } catch (err) {}
    remember();
    dab(at(e));
    e.preventDefault();
  }

  function move(e) {
    if (!painting) return;
    dab(at(e));
    e.preventDefault();
  }

  function up() { painting = false; }

  function close() {
    root.hidden = true;
    root.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    if (done) { done(null); done = null; }
  }

  function save() {
    canvas.toBlob(function (blob) {
      var give = done;
      done = null;
      root.hidden = true;
      root.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
      if (give) give(blob ? new File([blob], 'uz.png', { type: 'image/png' }) : null);
    }, 'image/png');
  }

  /**
   * Opens the eraser over this file and resolves with the touched-up PNG,
   * or with null when the customer closes it without saving.
   */
  function open(file) {
    if (!setup()) return Promise.resolve(null);

    return new Promise(function (resolve) {
      var url = URL.createObjectURL(file);
      var img = new Image();
      img.onload = function () {
        URL.revokeObjectURL(url);
        source = img;
        history = [];
        tool = 'erase';
        root.querySelectorAll('[data-tool]').forEach(function (o) {
          o.classList.toggle('on', o.dataset.tool === 'erase');
        });

        root.hidden = false;
        root.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        fit(img);
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(img, 0, 0);

        done = resolve;
      };
      img.onerror = function () { URL.revokeObjectURL(url); resolve(null); };
      img.src = url;
    });
  }

  return { open: open };
})();
