/**
 * The shop's own calendar.
 *
 * A browser's date field brings its own panel with it, and that panel belongs
 * to the browser, not to us: black on a cream page, English weekday names, the
 * American month-first order, and a week that starts on Sunday — which is not
 * how anyone in Baku reads a date. None of it can be styled.
 *
 * So the real field is kept for the form — same name, same ISO value, so
 * nothing on the server changes — and what the customer sees and clicks is
 * ours: the site's own colours, the month written out in his language, and a
 * week that starts on Monday.
 *
 * Attaches itself to every <input type="date"> on the page. Opt out with
 * data-native on the input.
 */
(function () {
  'use strict';

  var WORDS = {
    az: {
      months: ['Yanvar', 'Fevral', 'Mart', 'Aprel', 'May', 'İyun', 'İyul', 'Avqust', 'Sentyabr', 'Oktyabr', 'Noyabr', 'Dekabr'],
      days: ['B.e', 'Ç.a', 'Ç', 'C.a', 'C', 'Ş', 'B'],
    },
    ru: {
      months: ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'],
      days: ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'],
    },
    en: {
      months: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
      days: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
    },
  };

  function words() {
    var lang = (document.documentElement.lang || 'az').slice(0, 2);

    return WORDS[lang] || WORDS.az;
  }

  /** A date as the form carries it: 2026-09-30, never the browser's own order. */
  function iso(d) {
    var m = d.getMonth() + 1, day = d.getDate();

    return d.getFullYear() + '-' + (m < 10 ? '0' : '') + m + '-' + (day < 10 ? '0' : '') + day;
  }

  function parse(value) {
    var p = String(value || '').split('-');
    if (p.length !== 3) {
      return null;
    }
    var d = new Date(+p[0], +p[1] - 1, +p[2]);

    return isNaN(d.getTime()) ? null : d;
  }

  function sameDay(a, b) {
    return a && b && a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
  }

  /** Monday first, the way the week runs here. */
  function weekday(d) {
    return (d.getDay() + 6) % 7;
  }

  function attach(input) {
    if (input.dataset.npReady || input.hasAttribute('data-native')) {
      return;
    }
    input.dataset.npReady = '1';

    var W = words();
    var min = parse(input.getAttribute('min'));
    var max = parse(input.getAttribute('max'));
    var chosen = parse(input.value);

    /* The real field stays in the form, carrying the same name and the same
       ISO value; it simply stops being something to look at. */
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

    /* Opens on the month the customer is living in, not on the earliest one
       the field allows: a "from" of a year ago used to open the panel a year
       in the past, with every day greyed out. */
    function opening() {
      var at = chosen || new Date();
      if (min && at < min) {
        at = min;
      }
      if (max && at > max) {
        at = max;
      }

      return new Date(at.getTime());
    }

    var shown = opening();
    shown.setDate(1);

    function label() {
      /* An empty field says what it is waiting for; a dash alone reads as
         something broken. */
      button.textContent = chosen
        ? chosen.getDate() + ' ' + W.months[chosen.getMonth()] + ' ' + chosen.getFullYear()
        : (input.dataset.placeholder || '—');
      button.classList.toggle('is-empty', ! chosen);
    }

    function blocked(d) {
      return (min && d < min && ! sameDay(d, min)) || (max && d > max && ! sameDay(d, max));
    }

    function choose(d) {
      chosen = d;
      input.value = iso(d);
      label();
      close();
      /* Everything already listening to the field — the star map's preview,
         the checkout's own checks — hears it as an ordinary change. */
      input.dispatchEvent(new Event('input', { bubbles: true }));
      input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function draw() {
      var first = new Date(shown.getFullYear(), shown.getMonth(), 1);
      var lead = weekday(first);
      var days = new Date(shown.getFullYear(), shown.getMonth() + 1, 0).getDate();
      var before = new Date(shown.getFullYear(), shown.getMonth(), 0).getDate();
      var today = new Date();
      today.setHours(0, 0, 0, 0);

      var head = '<div class="np-head">'
        + '<button type="button" class="np-nav" data-step="-1" aria-label="&#8592;">‹</button>'
        + '<span class="np-title">' + W.months[shown.getMonth()] + ', ' + shown.getFullYear() + '</span>'
        + '<button type="button" class="np-nav" data-step="1" aria-label="&#8594;">›</button>'
        + '</div>';

      var names = '<div class="np-week">' + W.days.map(function (d) {
        return '<span>' + d + '</span>';
      }).join('') + '</div>';

      /* The days either side are shown greyed rather than left blank, so the
         weeks read as whole rows the way a paper calendar does. */
      var cells = '';
      for (var i = lead; i > 0; i--) {
        cells += '<span class="np-day is-other">' + (before - i + 1) + '</span>';
      }
      for (var day = 1; day <= days; day++) {
        var d = new Date(shown.getFullYear(), shown.getMonth(), day);
        var cls = 'np-day';
        if (sameDay(d, chosen)) cls += ' is-on';
        else if (sameDay(d, today)) cls += ' is-today';
        cells += blocked(d)
          ? '<span class="np-day is-off">' + day + '</span>'
          : '<button type="button" class="' + cls + '" data-day="' + day + '">' + day + '</button>';
      }
      var tail = (7 - ((lead + days) % 7)) % 7;
      for (var t = 1; t <= tail; t++) {
        cells += '<span class="np-day is-other">' + t + '</span>';
      }

      pop.innerHTML = head + '<div class="np-body">' + names + '<div class="np-grid">' + cells + '</div></div>';
    }

    function open() {
      shown = opening();
      shown.setDate(1);
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
      var step = e.target.closest('.np-nav');
      if (step) {
        shown.setMonth(shown.getMonth() + Number(step.dataset.step));
        draw();

        return;
      }
      var day = e.target.closest('.np-day[data-day]');
      if (day) {
        choose(new Date(shown.getFullYear(), shown.getMonth(), Number(day.dataset.day)));

        return;
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
    (root || document).querySelectorAll('input[type="date"]').forEach(attach);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { scan(); });
  } else {
    scan();
  }

  window.NefisDate = { attach: attach, scan: scan };
})();
