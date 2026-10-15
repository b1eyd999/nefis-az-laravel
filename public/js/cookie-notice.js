/* The cookie strip, and the switch that decides whether Google Analytics
   ever loads.
 *
 * The answer is kept in localStorage rather than in a cookie of its own:
 * asking permission to set a cookie by setting a cookie is a poor joke, and
 * localStorage is per-visitor and never travels to the server.
 *
 * Nothing Google-owned is fetched before a yes. The page has already opened
 * the dataLayer queue, so whatever the shop measured on the way here either
 * flushes the moment consent arrives, or is never sent at all. */
(function () {
  'use strict';

  var bar = document.getElementById('cookie-bar');
  if (!bar) return;

  var KEY = bar.dataset.key || 'nefis-cookies';

  function remembered() {
    try { return localStorage.getItem(KEY); } catch (e) { return null; }
  }

  function remember(answer) {
    try { localStorage.setItem(KEY, answer); } catch (e) { /* private window */ }
  }

  /* Loads Google's tag, once. Called on a yes — now, or on a later page
     because the yes was given before. */
  var loaded = false;
  function startAnalytics() {
    if (loaded) return;
    var id = window.NefisGA;
    if (!id) return;
    loaded = true;

    var s = document.createElement('script');
    s.async = true;
    s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(id);
    document.head.appendChild(s);

    window.dataLayer = window.dataLayer || [];
    window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
    window.gtag('js', new Date());
    window.gtag('config', id);
  }

  function answer(value) {
    remember(value);
    bar.hidden = true;
    if (value === 'all') startAnalytics();
  }

  var said = remembered();
  if (said === 'all') {
    startAnalytics();
    return;                     // decided already; the strip stays away
  }
  if (said === 'essential') {
    return;                     // decided already, and the answer was no
  }

  /* Not asked yet. The strip is written into the page hidden so it cannot
     flash before this runs; show it now. */
  bar.hidden = false;

  bar.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-cookie]');
    if (!btn) return;
    e.preventDefault();
    answer(btn.dataset.cookie === 'all' ? 'all' : 'essential');
  });

  /* The same decision is offered again on the privacy page, so somebody who
     said no once is not stuck with it for good. */
  document.addEventListener('click', function (e) {
    var again = e.target.closest('[data-cookie-reset]');
    if (!again) return;
    e.preventDefault();
    try { localStorage.removeItem(KEY); } catch (err) { /* nothing to undo */ }
    bar.hidden = false;
    bar.scrollIntoView({ block: 'end', behavior: 'smooth' });
  });
})();
