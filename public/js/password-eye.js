/* Showing a password that is typed only once.
 *
 * The sign-up form used to ask for the password twice, which is how a typo
 * was caught. Asking twice is also two fields of work on a phone, and this
 * shop loses people at the sign-up, so the second field went. The eye takes
 * its place: whoever is unsure looks at what he typed instead of typing it
 * again. There is a password reset by e-mail behind all this anyway. */
(function () {
  'use strict';

  document.addEventListener('click', function (event) {
    var eye = event.target.closest ? event.target.closest('.pw-eye') : null;
    if (!eye) return;

    var field = document.getElementById(eye.getAttribute('data-for'));
    if (!field) return;

    var showing = field.type === 'text';
    field.type = showing ? 'password' : 'text';
    eye.classList.toggle('on', !showing);
    eye.setAttribute('aria-pressed', String(!showing));
    // The label changes with it, for anyone listening rather than looking.
    eye.setAttribute('aria-label', eye.getAttribute(showing ? 'data-show' : 'data-hide') || '');
    field.focus();
  });
})();
