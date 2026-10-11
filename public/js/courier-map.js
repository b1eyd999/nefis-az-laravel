/* Where the couriers are, on the owner's map.
 *
 * A file of its own rather than a <script> in the page: the page is a Livewire
 * component, and Livewire counts the root elements of what a component renders
 * — an inline script of this size ends up reading as a second root and the
 * panel answers 500. It also means the browser caches it.
 *
 * The map is built once and fed by fetch every fifteen seconds. Everything it
 * draws comes from one address, which the page writes on the map element.
 */
(function () {
    var URL = (document.getElementById('ku-map') || {}).dataset ? document.getElementById('ku-map').dataset.url : null;
    if (!URL) { return; }
    /* Once per page, whatever Livewire does around it: a second run would
       mean a second map and two timers asking the same question. */
    if (window.__nefisCouriers) { return; }
    window.__nefisCouriers = true;

    var LEAFLET = 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/';
    var BAKU = [40.4093, 49.8671];
    var map = null, layer = null, first = true;

    function load() {
      if (window.L) return Promise.resolve();
      return new Promise(function (resolve, reject) {
        var css = document.createElement('link');
        css.rel = 'stylesheet'; css.href = LEAFLET + 'leaflet.css';
        document.head.appendChild(css);
        var s = document.createElement('script');
        s.src = LEAFLET + 'leaflet.js';
        s.onload = resolve; s.onerror = reject;
        document.head.appendChild(s);
      });
    }

    function icon(text) {
      return L.divIcon({ className: '', html: '<div class="ku-pin">' + text + '</div>',
        iconSize: [26, 26], iconAnchor: [13, 24] });
    }

    function esc(v) {
      return String(v === null || v === undefined ? '' : v)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    /* One line of small print out of whatever is actually known: a courier
       with no number must not leave a dangling « · » behind him. */
    function bits() {
      var out = [];
      for (var i = 0; i < arguments.length; i++) { if (arguments[i]) out.push(arguments[i]); }
      return out.join(' · ');
    }

    function when(o) {
      return o.day ? esc(o.day) + (o.slot ? ', ' + esc(o.slot) : '') : (o.slot ? esc(o.slot) : '');
    }

    /* The door he is headed for, told in one block: the address first, because
       that is the question the owner is asking the page. */
    function heading(h) {
      return '<div class="ku-head' + (h.on_the_way ? ' live' : '') + '">'
        + '<div class="ku-head-to">' + (h.on_the_way ? 'Yolda →' : 'Növbəti →')
        + ' <b>' + esc(h.where || '—') + '</b></div>'
        + '<div class="ku-meta">'
        + bits('#' + esc(h.id), esc(h.status), esc(h.how), when(h),
               (h.km !== null && h.km !== undefined) ? esc(h.km) + ' km qalıb' : '',
               (h.on_the_way && h.since) ? 'yola düşdü ' + esc(h.since) : '')
        + '</div><div class="ku-go">'
        + '<a href="' + esc(h.url) + '">Sifarişi aç</a>'
        + (h.map ? '<a href="' + esc(h.map) + '" target="_blank" rel="noopener">Ünvan xəritədə</a>' : '')
        + (h.phone ? '<a href="tel:' + esc(h.phone) + '">Müştəriyə zəng</a>' : '')
        + '</div></div>';
    }

    function draw(couriers) {
      var list = document.getElementById('ku-list');
      var points = [];
      layer.clearLayers();

      /* Empty now means one thing only — the shop has no couriers at all. A
         man who is in but idle is listed below with his name and his number,
         because «nobody is moving» and «nobody is hired» are not the same
         thing to look at. */
      if (!couriers.length) {
        list.innerHTML = '<div class="ku-empty"><b>Kuryer yoxdur.</b><br>'
          + 'İstifadəçilər səhifəsində bir nəfərə «Kuryer» rolu verin — adı burada görünəcək.</div>';
        return;
      }

      var html = '';
      couriers.forEach(function (c) {
        var h = c.heading;

        html += '<div class="ku-card' + (c.out ? '' : ' ku-free') + '"><h3>'
          + (c.sharing ? '<span class="ku-live"></span>' : '<span class="ku-off"></span>')
          + esc(c.name) + '</h3><div class="ku-meta">'
          + bits(c.sharing ? 'canlı' : 'lokasiya söndürülüb',
                 c.at ? 'son siqnal ' + esc(c.at) : 'lokasiya yoxdur',
                 c.phone ? '<a href="tel:' + esc(c.phone) + '">' + esc(c.phone) + '</a>' : '')
          + '</div>';

        html += h ? heading(h) : '<div class="ku-none">Əlində sifariş yoxdur — boşdur.</div>';

        var rest = (c.orders || []).filter(function (o) { return !o.heading; });
        if (rest.length) {
          html += '<div class="ku-more">Əlində daha ' + rest.length + ' sifariş:</div>';
          rest.forEach(function (o) {
            html += '<div class="ku-ord"><a href="' + esc(o.url) + '">#' + esc(o.id) + '</a> — '
              + esc(o.where || '—') + '<br><span class="ku-meta">'
              + bits(esc(o.status), when(o)) + '</span></div>';
          });
        }
        html += '</div>';

        /* Where the boxes have to end up, so the owner sees the gap between
           the courier and the door rather than only the courier. The one he is
           on is flagged, and a dashed line says which way he is going. */
        (c.orders || []).forEach(function (o) {
          if (!o.lat || !o.lng) return;
          points.push([o.lat, o.lng]);
          L.marker([o.lat, o.lng], { icon: icon(o.heading ? '🏁' : '📍'), zIndexOffset: o.heading ? 200 : 0 })
            .bindPopup('<b>#' + esc(o.id) + '</b><br>' + esc(o.where || '')
              + (o.heading ? '<br>' + esc(c.name) + ' bu ünvana gedir' : ''))
            .addTo(layer);
        });

        if (c.lat && c.lng) {
          points.push([c.lat, c.lng]);
          L.marker([c.lat, c.lng], { icon: icon('🚴'), zIndexOffset: 500 })
            .bindPopup('<b>' + esc(c.name) + '</b><br>' + (c.sharing ? 'canlı' : 'son yer')
              + (c.at ? ' · ' + esc(c.at) : '')
              + (h ? '<br>→ ' + esc(h.where || '') : ''))
            .addTo(layer);
          if (c.accuracy) {
            L.circle([c.lat, c.lng], { radius: Math.min(c.accuracy, 500), weight: 0,
              fillColor: '#16a34a', fillOpacity: .12 }).addTo(layer);
          }
          if ((c.trail || []).length > 1) {
            L.polyline(c.trail, { color: '#d97706', weight: 3, opacity: .65 }).addTo(layer);
          }
          if (h && h.lat && h.lng) {
            L.polyline([[c.lat, c.lng], [h.lat, h.lng]],
              { color: '#2563eb', weight: 2, opacity: .85, dashArray: '6 8' }).addTo(layer);
          }
        }
      });
      list.innerHTML = html;

      // The view is fitted once; after that it is the owner's to move.
      if (first && points.length) {
        map.fitBounds(L.latLngBounds(points).pad(0.25), { maxZoom: 15 });
        first = false;
      }
    }

    function poll() {
      fetch(URL, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.json() : { couriers: [] }; })
        .then(function (d) { draw(d.couriers || []); })
        .catch(function () {});
    }

    load().then(function () {
      var el = document.getElementById('ku-map');
      if (!el || el.dataset.ready === '1') return;
      el.dataset.ready = '1';
      map = L.map(el, { center: BAKU, zoom: 12 });
      L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19, referrerPolicy: 'strict-origin-when-cross-origin',
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>'
      }).addTo(map);
      layer = L.layerGroup().addTo(map);
      poll();
      setInterval(poll, 15000);
    }).catch(function () {
      document.getElementById('ku-list').innerHTML =
        '<div class="ku-empty">Xəritə yüklənmədi. İnternet bağlantısını yoxlayın.</div>';
    });
  })();
