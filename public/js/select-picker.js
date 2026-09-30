/**
 * The shop's own dropdown.
 *
 * A browser's own <select> panel belongs to the browser: grey, square, in the
 * system font, and on a long list of towns there is nothing to search with.
 * Beside the site's calendar it looks like a hole in the page.
 *
 * So the real field is kept for the form — same name, same value, same events,
 * so nothing on the server or in the page's own scripts changes — and what the
 * customer sees is ours, built the way the calendar is: the same paper, the
 * same corners, the chosen line filled in cocoa.
 *
 * Attaches itself to every <select data-fancy> on the page.
 */
(function () {
  'use strict';

  /* Below this many choices a search box is more in the way than it is help. */
  var SEARCH_FROM = 10;

  var WORDS = {
    az: { search: 'Axtarın…', empty: 'Tapılmadı' },
    ru: { search: 'Поиск…', empty: 'Ничего не найдено' },
    en: { search: 'Search…', empty: 'Nothing found' },
  };

  function words() {
    var lang = (document.documentElement.lang || 'az').slice(0, 2);

    return WORDS[lang] || WORDS.az;
  }

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
    });
  }

  function attach(select) {
    if (select.dataset.nsReady) {
      return;
    }
    select.dataset.nsReady = '1';

    var W = words();
    var options = Array.prototype.map.call(select.options, function (o) {
      return { value: o.value, label: o.textContent.trim(), disabled: o.disabled };
    });
    var searchable = options.length >= SEARCH_FROM;

    var wrap = document.createElement('div');
    wrap.className = 'ns';
    select.parentNode.insertBefore(wrap, select);
    wrap.appendChild(select);
    /* The field itself stays in the form and keeps its name and value; it
       simply stops being the thing that is looked at. */
    select.classList.add('ns-real');

    var field = document.createElement('button');
    field.type = 'button';
    field.className = 'ns-field';
    field.setAttribute('aria-haspopup', 'listbox');
    field.innerHTML = '<span class="ns-value"></span><span class="ns-caret" aria-hidden="true">▾</span>';
    wrap.appendChild(field);

    var pop = document.createElement('div');
    pop.className = 'ns-pop';
    pop.hidden = true;
    pop.setAttribute('role', 'listbox');
    pop.innerHTML = (searchable
      ? '<div class="ns-head"><input type="text" class="ns-search" placeholder="' + esc(W.search) + '" autocomplete="off"></div>'
      : '') + '<div class="ns-list"></div>';
    wrap.appendChild(pop);

    var list = pop.querySelector('.ns-list');
    var search = pop.querySelector('.ns-search');

    function label() {
      var picked = options.filter(function (o) { return o.value === select.value; })[0];
      field.querySelector('.ns-value').textContent = picked ? picked.label : (options[0] ? options[0].label : '');
    }

    function draw(filter) {
      var needle = (filter || '').toLowerCase();
      var shown = options.filter(function (o) {
        return ! needle || o.label.toLowerCase().indexOf(needle) >= 0;
      });

      list.innerHTML = shown.length
        ? shown.map(function (o) {
            return '<button type="button" class="ns-opt' + (o.value === select.value ? ' is-on' : '') + '"'
              + (o.disabled ? ' disabled' : '') + ' role="option" data-value="' + esc(o.value) + '">'
              + esc(o.label) + '</button>';
          }).join('')
        : '<p class="ns-empty">' + esc(W.empty) + '</p>';
    }

    function choose(v) {
      select.value = v;
      label();
      close();
      /* Everything already listening to the field — the star map's preview,
         the page's own checks — hears it as an ordinary change. */
      select.dispatchEvent(new Event('input', { bubbles: true }));
      select.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function open() {
      draw('');
      pop.hidden = false;
      wrap.classList.add('is-open');
      document.addEventListener('click', away, true);
      if (search) {
        search.value = '';
        search.focus();
      }
      var on = list.querySelector('.is-on');
      if (on) {
        on.scrollIntoView({ block: 'nearest' });
      }
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

    field.addEventListener('click', function () {
      pop.hidden ? open() : close();
    });

    list.addEventListener('click', function (e) {
      var opt = e.target.closest('.ns-opt');
      if (opt && ! opt.disabled) {
        choose(opt.dataset.value);
      }
    });

    if (search) {
      search.addEventListener('input', function () { draw(search.value); });
    }

    wrap.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && ! pop.hidden) {
        e.preventDefault();
        close();
        field.focus();

        return;
      }
      if (pop.hidden) {
        return;
      }
      if (e.key === 'Enter') {
        var first = list.querySelector('.ns-opt');
        if (first) {
          e.preventDefault();
          choose(first.dataset.value);
        }
      }
    });

    /* Something else may set the value — the page, or the browser restoring a
       form; the field then says what the form says. */
    select.addEventListener('change', label);

    label();
  }

  function scan(root) {
    (root || document).querySelectorAll('select[data-fancy]').forEach(attach);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { scan(); });
  } else {
    scan();
  }

  window.NefisSelect = { attach: attach, scan: scan };
})();
