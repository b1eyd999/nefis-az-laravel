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

  function bind(fig, fileInput, textInput) {
    var photo = null;
    function redraw() { update(fig, photo, textInput ? textInput.value.trim() : ''); }
    if (fileInput) {
      fileInput.addEventListener('change', function () {
        var f = fileInput.files && fileInput.files[0];
        if (!f) { photo = null; redraw(); return; }
        var r = new FileReader();
        r.onload = function (e) { photo = e.target.result; redraw(); };
        r.readAsDataURL(f);
      });
    }
    if (textInput) textInput.addEventListener('input', redraw);
    redraw();
    return {
      clearPhoto: function () { photo = null; if (fileInput) fileInput.value = ''; redraw(); },
    };
  }

  global.NefisPolaroid = { update: update, bind: bind };
})(window);
