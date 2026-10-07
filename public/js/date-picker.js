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

    /* Which of the three the panel is showing. A birthday is a hundred and
       twenty years back from here, and stepping to it a month at a time is
       four hundred taps; the title zooms out instead — days to months,
       months to years — and the years come twenty-four to a page. */
    var view = 'days';
    var YEARS_PER_PAGE = 24;

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

    /** A month nobody can reach: it ends before the earliest day, or starts after the last. */
    function monthBlocked(year, month) {
      var last = new Date(year, month + 1, 0);
      var first = new Date(year, month, 1);

      return (min && last < min) || (max && first > max);
    }

    function yearBlocked(year) {
      return (min && new Date(year, 11, 31) < min) || (max && new Date(year, 0, 1) > max);
    }

    /* The first year of the page the given year sits on, kept inside the
       field's own range so the arrows never walk off into empty pages. */
    function yearPage(year) {
      var base = Math.floor(year / YEARS_PER_PAGE) * YEARS_PER_PAGE;
      if (min && base < min.getFullYear()) {
        base = Math.floor(min.getFullYear() / YEARS_PER_PAGE) * YEARS_PER_PAGE;
      }

      return base;
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

    function monthCells() {
      var cells = '';
      for (var m = 0; m < 12; m++) {
        var cls = 'np-cell';
        if (m === shown.getMonth()) cls += ' is-on';
        else if (chosen && chosen.getFullYear() === shown.getFullYear() && chosen.getMonth() === m) cls += ' is-today';
        cells += monthBlocked(shown.getFullYear(), m)
          ? '<span class="np-cell is-off">' + W.months[m].slice(0, 3) + '</span>'
          : '<button type="button" class="' + cls + '" data-month="' + m + '">' + W.months[m].slice(0, 3) + '</button>';
      }

      return '<div class="np-grid is-months">' + cells + '</div>';
    }

    function yearCells() {
      var base = yearPage(shown.getFullYear());
      var cells = '';
      for (var i = 0; i < YEARS_PER_PAGE; i++) {
        var y = base + i;
        var cls = 'np-cell';
        if (y === shown.getFullYear()) cls += ' is-on';
        else if (chosen && chosen.getFullYear() === y) cls += ' is-today';
        cells += yearBlocked(y)
          ? '<span class="np-cell is-off">' + y + '</span>'
          : '<button type="button" class="' + cls + '" data-year="' + y + '">' + y + '</button>';
      }

      return '<div class="np-grid is-years">' + cells + '</div>';
    }

    function draw() {
      var first = new Date(shown.getFullYear(), shown.getMonth(), 1);
      var lead = weekday(first);
      var days = new Date(shown.getFullYear(), shown.getMonth() + 1, 0).getDate();
      var before = new Date(shown.getFullYear(), shown.getMonth(), 0).getDate();
      var today = new Date();
      today.setHours(0, 0, 0, 0);

      var title = view === 'days' ? W.months[shown.getMonth()] + ', ' + shown.getFullYear()
        : view === 'months' ? String(shown.getFullYear())
        : yearPage(shown.getFullYear()) + ' – ' + (yearPage(shown.getFullYear()) + YEARS_PER_PAGE - 1);

      var head = '<div class="np-head">'
        + '<button type="button" class="np-nav" data-step="-1" aria-label="&#8592;">‹</button>'
        + (view === 'years'
          ? '<span class="np-title">' + title + '</span>'
          : '<button type="button" class="np-title np-zoom">' + title + '</button>')
        + '<button type="button" class="np-nav" data-step="1" aria-label="&#8594;">›</button>'
        + '</div>';

      if (view !== 'days') {
        pop.innerHTML = head + '<div class="np-body">' + (view === 'months' ? monthCells() : yearCells()) + '</div>';

        return;
      }

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
      view = 'days';
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
        var by = Number(step.dataset.step);
        if (view === 'days') shown.setMonth(shown.getMonth() + by);
        else if (view === 'months') shown.setFullYear(shown.getFullYear() + by);
        else shown.setFullYear(yearPage(shown.getFullYear()) + by * YEARS_PER_PAGE);
        draw();

        return;
      }

      // The title zooms out: the month opens its year, the year opens the page.
      if (e.target.closest('.np-zoom')) {
        view = view === 'days' ? 'months' : 'years';
        draw();

        return;
      }

      var year = e.target.closest('.np-cell[data-year]');
      if (year) {
        shown.setDate(1);
        shown.setFullYear(Number(year.dataset.year));
        view = 'months';
        draw();

        return;
      }

      var month = e.target.closest('.np-cell[data-month]');
      if (month) {
        shown.setDate(1);
        shown.setMonth(Number(month.dataset.month));
        view = 'days';
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
      if (e.key !== 'Escape' || pop.hidden) {
        return;
      }
      /* One step back at a time: out of the years to the months, out of the
         months to the days, and only then out of the panel. */
      if (view === 'years') { view = 'months'; draw(); return; }
      if (view === 'months') { view = 'days'; draw(); return; }
      close();
      button.focus();
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
