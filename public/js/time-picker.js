/**
 * The shop's own clock, built the way the calendar is.
 *
 * A browser's time field brings its own panel with it, and that panel belongs
 * to the browser: a black wheel of hours with AM and PM beside it, in a shop
 * that writes 21:00 on cream paper. None of it can be styled.
 *
 * So the real field is kept for the form — same name, same HH:MM value, so
 * nothing on the server changes — and what the customer sees and clicks is
 * ours: the same panel, the same colours and the same rounding as the
 * calendar next to it, on a twenty-four hour clock.
 *
 * Attaches itself to every <input type="time"> on the page. Opt out with
 * data-native on the input. The minutes step from data-step, five by default.
 */
(function () {
  'use strict';

  var WORDS = {
    az: { title: 'Saat seçin', hour: 'Saat', minute: 'Dəqiqə' },
    ru: { title: 'Выберите время', hour: 'Часы', minute: 'Минуты' },
    en: { title: 'Pick a time', hour: 'Hour', minute: 'Minute' },
  };

  function words() {
    var lang = (document.documentElement.lang || 'az').slice(0, 2);

    return WORDS[lang] || WORDS.az;
  }

  function two(n) {
    return (n < 10 ? '0' : '') + n;
  }

  /** A time as the form carries it: 21:00, never the browser's own wording. */
  function parse(value) {
    var m = /^(\d{1,2}):(\d{2})/.exec(String(value || ''));
    if (!m) {
      return null;
    }
    var h = +m[1], min = +m[2];

    return h >= 0 && h < 24 && min >= 0 && min < 60 ? { h: h, m: min } : null;
  }

  function attach(input) {
    if (input.dataset.npReady || input.hasAttribute('data-native')) {
      return;
    }
    input.dataset.npReady = '1';

    var W = words();
    var step = Math.max(1, Math.min(30, +input.dataset.step || 5));
    var chosen = parse(input.value);

    /* The real field stays in the form, carrying the same name and the same
       value; it simply stops being something to look at. */
    var wrap = document.createElement('div');
    wrap.className = 'np';
    input.parentNode.insertBefore(wrap, input);
    input.type = 'hidden';
    wrap.appendChild(input);

    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'np-field';
    button.setAttribute('aria-haspopup', 'dialog');
    wrap.appendChild(button);

    var pop = document.createElement('div');
    pop.className = 'np-pop';
    pop.hidden = true;
    pop.setAttribute('role', 'dialog');
    wrap.appendChild(pop);

    function label() {
      /* An empty field says what it is waiting for; a dash alone reads as
         something broken. */
      button.textContent = chosen
        ? two(chosen.h) + ':' + two(chosen.m)
        : (input.dataset.placeholder || '—');
      button.classList.toggle('is-empty', ! chosen);
    }

    function choose(h, m) {
      chosen = { h: h, m: m };
      input.value = two(h) + ':' + two(m);
      label();
      draw();
      /* Everything already listening to the field — the star map's preview,
         the place on the box — hears it as an ordinary change. */
      input.dispatchEvent(new Event('input', { bubbles: true }));
      input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function draw() {
      var head = '<div class="np-head"><span class="np-title">' + W.title + '</span></div>';

      var hours = '';
      for (var h = 0; h < 24; h++) {
        hours += '<button type="button" class="np-day' + (chosen && chosen.h === h ? ' is-on' : '')
          + '" data-hour="' + h + '">' + two(h) + '</button>';
      }

      var minutes = '';
      for (var m = 0; m < 60; m += step) {
        minutes += '<button type="button" class="np-day' + (chosen && chosen.m === m ? ' is-on' : '')
          + '" data-minute="' + m + '">' + two(m) + '</button>';
      }

      pop.innerHTML = head
        + '<div class="np-body">'
        + '<p class="np-label">' + W.hour + '</p>'
        + '<div class="np-grid is-hours">' + hours + '</div>'
        + '<p class="np-label">' + W.minute + '</p>'
        + '<div class="np-grid is-minutes">' + minutes + '</div>'
        + '</div>';
    }

    function open() {
      draw();
      pop.hidden = false;
      wrap.classList.add('is-open');
      document.addEventListener('click', away, true);
    }

    function close() {
      pop.hidden = true;
      wrap.classList.remove('is-open');
      document.removeEventListener('click', away, true);
    }

    function away(e) {
      if (! wrap.contains(e.target)) {
        close();
      }
    }

    button.addEventListener('click', function () {
      pop.hidden ? open() : close();
    });

    pop.addEventListener('click', function (e) {
      var hour = e.target.closest('[data-hour]');
      if (hour) {
        /* Picking the hour first leaves the minutes where they were, so a
           customer correcting 21:30 to 22:30 touches one button. */
        choose(Number(hour.dataset.hour), chosen ? chosen.m : 0);

        return;
      }
      var minute = e.target.closest('[data-minute]');
      if (minute) {
        choose(chosen ? chosen.h : 21, Number(minute.dataset.minute));
        /* The minute is the last thing asked for, so the panel closes on it. */
        close();
      }
    });

    wrap.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && ! pop.hidden) {
        close();
        button.focus();
      }
    });

    label();
  }

  function scan(root) {
    (root || document).querySelectorAll('input[type="time"]').forEach(attach);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { scan(); });
  } else {
    scan();
  }

  window.NefisTime = { attach: attach, scan: scan };
})();
