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

    function draw(couriers) {
      var list = document.getElementById('ku-list');
      var points = [];
      layer.clearLayers();

      if (!couriers.length) {
        list.innerHTML = '<div class="ku-empty"><b>Yolda kuryer yoxdur.</b><br>'
          + 'Sifarişi kuryerə verəndə və o lokasiyanı yandıranda burada görünəcək.</div>';
        return;
      }

      var html = '';
      couriers.forEach(function (c) {
        html += '<div class="ku-card"><h3>'
          + (c.sharing ? '<span class="ku-live"></span>' : '<span class="ku-off"></span>')
          + esc(c.name) + '</h3><div class="ku-meta">'
          + (c.sharing ? 'canlı' : 'söndürülüb')
          + (c.at ? ' · son siqnal ' + esc(c.at) : ' · lokasiya yoxdur')
          + (c.phone ? ' · <a href="tel:' + esc(c.phone) + '">' + esc(c.phone) + '</a>' : '')
          + '</div>';

        (c.orders || []).forEach(function (o) {
          html += '<div class="ku-ord"><a href="' + esc(o.url) + '">#' + esc(o.id) + '</a> — '
            + esc(o.status) + (o.on_the_way ? ' · yolda' : '')
            + (o.slot ? ' · ' + esc(o.slot) : '')
            + '<br><span class="ku-meta">' + esc(o.address || '—') + '</span></div>';
          /* Where the box has to end up, so the owner sees the gap between the
             courier and the door rather than only the courier. */
          if (o.lat && o.lng) {
            points.push([o.lat, o.lng]);
            L.marker([o.lat, o.lng], { icon: icon('📍') })
              .bindPopup('<b>#' + esc(o.id) + '</b><br>' + esc(o.address || ''))
              .addTo(layer);
          }
        });
        html += '</div>';

        if (c.lat && c.lng) {
          points.push([c.lat, c.lng]);
          L.marker([c.lat, c.lng], { icon: icon('🚴'), zIndexOffset: 500 })
            .bindPopup('<b>' + esc(c.name) + '</b><br>' + (c.sharing ? 'canlı' : 'son yer')
              + (c.at ? ' · ' + esc(c.at) : ''))
            .addTo(layer);
          if (c.accuracy) {
            L.circle([c.lat, c.lng], { radius: Math.min(c.accuracy, 500), weight: 0,
              fillColor: '#16a34a', fillOpacity: .12 }).addTo(layer);
          }
          if ((c.trail || []).length > 1) {
            L.polyline(c.trail, { color: '#d97706', weight: 3, opacity: .65 }).addTo(layer);
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