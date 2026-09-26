/* The eraser over a cut-out head.
 *
 * The models get the background off on their own, but a corner of a wall or
 * a piece of shoulder survives often enough that the customer needs a way to
 * wipe it himself — here, with a finger, not in Photoshop.
 *
 * Two things make it usable: the circle that follows the hand, so nobody has
 * to guess how much the brush takes, and a stroke drawn as a line rather than
 * a row of dots, so a quick swipe leaves no gaps. What is wiped is the alpha
 * channel only; the pixels stay, which is why "restore" can bring them back
 * exactly as they were.
 */
window.NefisBrush = (function () {
  var root, stage, canvas, ctx, ring, sizeInput, sizeOut, undoBtn, resetBtn, saveBtn;
  var source = null;      // the picture as it arrived, for restoring
  var scale = 1;          // css pixels per canvas pixel
  var tool = 'erase';
  var painting = false;
  var lastPoint = null;
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
    ring = el('brush-ring');
    sizeInput = el('brush-size');
    sizeOut = el('brush-size-out');
    undoBtn = el('brush-undo');
    resetBtn = el('brush-reset');
    saveBtn = el('brush-save');

    root.querySelectorAll('[data-close]').forEach(function (b) {
      b.addEventListener('click', close);
    });
    root.querySelectorAll('[data-tool]').forEach(function (b) {
      b.addEventListener('click', function () {
        tool = b.dataset.tool;
        root.querySelectorAll('[data-tool]').forEach(function (o) { o.classList.toggle('on', o === b); });
        root.classList.toggle('restoring', tool === 'restore');
      });
    });

    sizeInput.addEventListener('input', function () {
      sizeOut.textContent = sizeInput.value;
      drawRing();
    });
    undoBtn.addEventListener('click', undo);
    resetBtn.addEventListener('click', reset);
    saveBtn.addEventListener('click', save);

    canvas.addEventListener('pointerdown', down);
    canvas.addEventListener('pointermove', move);
    canvas.addEventListener('pointerenter', function () { ring.hidden = false; });
    canvas.addEventListener('pointerleave', function () { ring.hidden = true; up(); });
    window.addEventListener('pointerup', up);

    /* The square brackets are where every drawing program keeps brush size. */
    document.addEventListener('keydown', function (e) {
      if (root.hidden) return;
      if (e.key === 'Escape') { close(); return; }
      if (e.key === '[' || e.key === ']') {
        var step = e.key === '[' ? -8 : 8;
        sizeInput.value = Math.max(Number(sizeInput.min), Math.min(Number(sizeInput.max), Number(sizeInput.value) + step));
        sizeOut.textContent = sizeInput.value;
        drawRing();
      }
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z') { e.preventDefault(); undo(); }
    });

    return true;
  }

  /* The stage is a fixed box; the picture sits in the middle of it. */
  function fit(img) {
    var box = stage.getBoundingClientRect();
    var room = (box.width || 520) - 24;
    var tall = (box.height || 420) - 24;
    scale = Math.min(room / img.width, tall / img.height, 1);
    canvas.width = img.width;
    canvas.height = img.height;
    canvas.style.width = Math.round(img.width * scale) + 'px';
    canvas.style.height = Math.round(img.height * scale) + 'px';
  }

  function drawRing(x, y) {
    var d = Number(sizeInput.value);
    ring.style.width = d + 'px';
    ring.style.height = d + 'px';
    if (x !== undefined) {
      ring.style.left = x + 'px';
      ring.style.top = y + 'px';
    }
  }

  function remember() {
    history.push(ctx.getImageData(0, 0, canvas.width, canvas.height));
    if (history.length > 10) history.shift();
    undoBtn.disabled = false;
  }

  function undo() {
    var last = history.pop();
    if (!last) return;
    ctx.putImageData(last, 0, 0);
    undoBtn.disabled = history.length === 0;
  }

  function reset() {
    if (!source) return;
    remember();
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(source, 0, 0);
  }

  function at(e) {
    var box = canvas.getBoundingClientRect();
    return {
      x: (e.clientX - box.left) / box.width * canvas.width,
      y: (e.clientY - box.top) / box.height * canvas.height,
      sx: e.clientX - stage.getBoundingClientRect().left,
      sy: e.clientY - stage.getBoundingClientRect().top,
    };
  }

  /* A swipe is a line, not a handful of dots: at speed the dots leave holes. */
  function stroke(from, to) {
    var r = Number(sizeInput.value) / 2 / scale;

    ctx.save();
    ctx.lineWidth = r * 2;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';

    if (tool === 'erase') {
      ctx.globalCompositeOperation = 'destination-out';
      ctx.strokeStyle = 'rgba(0,0,0,1)';
      ctx.beginPath();
      ctx.moveTo(from.x, from.y);
      ctx.lineTo(to.x, to.y);
      ctx.stroke();
      ctx.restore();
      return;
    }

    /* Restoring paints the original photo back along the same line: the
       brush itself is filled with the untouched picture, anchored at the
       canvas origin, so the band the brush covers comes back pixel for
       pixel. Stroking the line and then clipping to it does not work —
       clip() takes a path's fill, and a line encloses no area, so the clip
       would be empty and the stroke would stay as plain black paint. */
    ctx.strokeStyle = ctx.createPattern(source, 'no-repeat');
    ctx.beginPath();
    ctx.moveTo(from.x, from.y);
    ctx.lineTo(to.x, to.y);
    ctx.stroke();
    ctx.restore();
  }

  function down(e) {
    var p = at(e);
    painting = true;
    lastPoint = p;
    try { canvas.setPointerCapture(e.pointerId); } catch (err) {}
    remember();
    stroke(p, p);
    ring.hidden = false;
    drawRing(p.sx, p.sy);
    e.preventDefault();
  }

  function move(e) {
    var p = at(e);
    drawRing(p.sx, p.sy);
    if (!painting) return;
    stroke(lastPoint || p, p);
    lastPoint = p;
    e.preventDefault();
  }

  function up() {
    painting = false;
    lastPoint = null;
  }

  function shut() {
    root.hidden = true;
    root.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    ring.hidden = true;
  }

  function close() {
    shut();
    if (done) { done(null); done = null; }
  }

  function save() {
    canvas.toBlob(function (blob) {
      var give = done;
      done = null;
      shut();
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
        undoBtn.disabled = true;
        tool = 'erase';
        root.classList.remove('restoring');
        root.querySelectorAll('[data-tool]').forEach(function (o) {
          o.classList.toggle('on', o.dataset.tool === 'erase');
        });

        root.hidden = false;
        root.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        fit(img);
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(img, 0, 0);
        sizeOut.textContent = sizeInput.value;
        drawRing();

        done = resolve;
      };
      img.onerror = function () { URL.revokeObjectURL(url); resolve(null); };
      img.src = url;
    });
  }

  return { open: open };
})();
