/* The shop as an app on the phone.
 *
 * Two jobs, both small. The first is to hand the worker to the browser, so a
 * page already seen opens with no signal. The second is the bar that offers to
 * put the shop on the home screen — on Android through the browser's own
 * install dialog, on iPhone by saying where Apple hid it.
 *
 * The bar is deliberately shy: never on the first page of a visit, never to
 * somebody who is already in the app, and once closed not again for a month.
 */
(function () {
  'use strict';

  /* ---------------------------------------------------------- the worker */

  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(function () {
        /* An old browser, a private window, a hosting without https: the shop
           works exactly as before, only without the shelf. */
      });
    });
  }

  /* ------------------------------------------------------------- the bar */

  var bar = document.getElementById('pwa-bar');
  if (!bar) { return; }

  var KEY = bar.dataset.seenKey || 'nefis-install-hint';
  var MONTH = 30 * 24 * 60 * 60 * 1000;

  function standalone() {
    return window.matchMedia('(display-mode: standalone)').matches
      || window.navigator.standalone === true;
  }

  function stored(key) {
    try { return window.localStorage.getItem(key); } catch (e) { return null; }
  }

  function keep(key, value) {
    try { window.localStorage.setItem(key, value); } catch (e) {}
  }

  // Already installed, or closed recently: say nothing at all.
  if (standalone()) { return; }
  var hidden = parseInt(stored(KEY) || '0', 10);
  if (hidden && Date.now() - hidden < MONTH) { return; }

  // Not on the first page of a visit — an offer before the shop itself is
  // just something in the way.
  var seen = parseInt(stored(KEY + '-pages') || '0', 10) + 1;
  keep(KEY + '-pages', String(seen));
  if (seen < 2) { return; }

  var apple = /iphone|ipad|ipod/i.test(navigator.userAgent)
    && !/crios|fxios|edgios/i.test(navigator.userAgent);
  var prompt = null;

  function show() {
    bar.hidden = false;
    // A moment later, so it slides in rather than appearing mid-paint.
    requestAnimationFrame(function () { bar.classList.add('on'); });
  }

  function close() {
    bar.classList.remove('on');
    keep(KEY, String(Date.now()));
    setTimeout(function () { bar.hidden = true; }, 300);
  }

  bar.querySelector('.pwa-x').addEventListener('click', close);

  if (apple) {
    bar.classList.add('is-ios');
    show();
    return;
  }

  /* Android and desktop Chrome: the browser tells us when it is willing to
     install, and that moment is the only one its dialog may be opened from. */
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    prompt = e;
    var go = bar.querySelector('.pwa-go');
    go.hidden = false;
    go.addEventListener('click', function () {
      if (!prompt) { return; }
      prompt.prompt();
      prompt.userChoice.then(function () {
        prompt = null;
        close();
      });
    });
    show();
  });

  window.addEventListener('appinstalled', function () {
    keep(KEY, String(Date.now()));
    bar.hidden = true;
  });
})();
