/*
 * Live previews of a Polaroid letter (partials/polaroid.blade.php): the photo
 * picked and the words typed show as they will be printed.
 *
 *   NefisPolaroid.bind(figure, fileInput, textInput)
 */
(function (global) {
  'use strict';

  function sizeClass(n) { return n <= 40 ? 'pol-s' : (n <= 110 ? 'pol-m' : 'pol-l'); }

  function update(fig, photo, text) {
    var img = fig.querySelector('img'), note = fig.querySelector('.pol-note'), cap = fig.querySelector('.pol-text');
    fig.classList.remove('pol-s', 'pol-m', 'pol-l');
    fig.classList.add(sizeClass((text || '').length));
    if (photo) {
      img.src = photo;
      img.hidden = false;
      fig.classList.remove('no-photo');
      note.textContent = '';
      note.classList.remove('is-placeholder');
      cap.textContent = text || '';
    } else {
      img.hidden = true;
      img.removeAttribute('src');
      fig.classList.add('no-photo');
      note.textContent = text || fig.dataset.placeholder || '';
      note.classList.toggle('is-placeholder', !text);
      cap.textContent = '';
    }
  }

  /** Whatever NefisPhoto hands back, as a small JPEG the <img> can wear. */
  function small(pic) {
    if (pic.toDataURL) {
      return pic.toDataURL('image/jpeg', 0.85);
    }
    var c = document.createElement('canvas');
    c.width = pic.naturalWidth || pic.width;
    c.height = pic.naturalHeight || pic.height;
    c.getContext('2d').drawImage(pic, 0, 0);
    return c.toDataURL('image/jpeg', 0.85);
  }

  function bind(fig, fileInput, textInput) {
    var photo = null;
    function redraw() { update(fig, photo, textInput ? textInput.value.trim() : ''); }
    if (fileInput) {
      fileInput.addEventListener('change', function () {
        var f = fileInput.files && fileInput.files[0];
        if (!f) { photo = null; redraw(); return; }

        /* A phone photograph read as a data URL is megabytes of base64 and a
           bitmap of tens of megabytes behind it — enough for iOS to throw the
           page away mid-order. The preview is a few centimetres wide, so the
           picture is decoded small and kept small. */
        if (global.NefisPhoto) {
          global.NefisPhoto.load(f, 900).then(function (pic) {
            photo = small(pic);
            redraw();
          }).catch(function () { readWhole(f); });
          return;
        }
        readWhole(f);
      });
    }

    function readWhole(f) {
      var r = new FileReader();
      r.onload = function (e) { photo = e.target.result; redraw(); };
      r.readAsDataURL(f);
    }
    if (textInput) textInput.addEventListener('input', redraw);
    redraw();
    return {
      clearPhoto: function () { photo = null; if (fileInput) fileInput.value = ''; redraw(); },
    };
  }

  global.NefisPolaroid = { update: update, bind: bind };
})(window);
