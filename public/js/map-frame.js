/**
 * The window the customer frames his place in.
 *
 * A picture of the streets cannot be nudged, and a place is never exactly
 * where its address says: the corner someone means is half a street from the
 * pin. So the streets are laid out here as a map he can drag and zoom, cut to
 * the shape of the window on the box, with the mark standing still in the
 * middle — the map moves under it, the way a photograph moves under a frame.
 *
 * What he leaves in the frame is the centre and the zoom, and those are the
 * two numbers the printed sheet is drawn from, so what he framed is what the
 * press makes.
 */
(function () {
  'use strict';

  var LEAFLET = 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/';
  var pending = null;

  /** Leaflet, fetched once however many windows ask for it. */
  function leaflet(then) {
    if (window.L && window.L.map) {
      then();

      return;
    }
    if (pending) {
      pending.push(then);

      return;
    }
    pending = [then];

    var css = document.createElement('link');
    css.rel = 'stylesheet';
    css.href = LEAFLET + 'leaflet.css';
    document.head.appendChild(css);

    var s = document.createElement('script');
    s.src = LEAFLET + 'leaflet.js';
    s.onload = function () {
      var waiting = pending;
      pending = null;
      waiting.forEach(function (fn) { fn(); });
    };
    s.onerror = function () { pending = null; };
    document.head.appendChild(s);
  }

  /**
   * Mount a framing window into `el`.
   *
   * opts: lat, lon, zoom, style, shape, marker, minZoom, maxZoom, canZoom,
   *       tiles (a URL template), onMove(lat, lon, zoom).
   */
  function mount(el, opts) {
    opts = opts || {};
    var held = null;

    leaflet(function () {
      if (!window.L) {
        return;
      }

      var map = L.map(el, {
        center: [Number(opts.lat), Number(opts.lon)],
        zoom: Number(opts.zoom) || 15,
        minZoom: opts.canZoom ? (opts.minZoom || 11) : (Number(opts.zoom) || 15),
        maxZoom: opts.canZoom ? (opts.maxZoom || 18) : (Number(opts.zoom) || 15),
        /* The printed sheet is drawn at a whole zoom level, so the frame may
           only stop at one — otherwise the press would round the customer's
           view and show him streets he never framed. */
        zoomSnap: 1,
        zoomDelta: 1,
        attributionControl: false,
        zoomControl: !!opts.canZoom,
        scrollWheelZoom: !!opts.canZoom,
        doubleClickZoom: !!opts.canZoom,
        touchZoom: !!opts.canZoom,
        keyboard: false,
      });

      L.tileLayer(opts.tiles, {
        minZoom: opts.minZoom || 11,
        maxZoom: opts.maxZoom || 18,
        tileSize: 256,
        /* The window on the box is drawn from a picture of the same width and
           the same zoom, so one tile here is one tile there. */
        detectRetina: false,
      }).addTo(map);

      function tell() {
        var c = map.getCenter();
        if (opts.onMove) {
          opts.onMove(c.lat, c.lng, map.getZoom());
        }
      }

      map.on('moveend', tell);
      map.on('zoomend', tell);

      /* A window that was hidden while it was built has no size yet. */
      setTimeout(function () { map.invalidateSize(); }, 60);

      held = map;
    });

    return {
      /** Point the frame somewhere else — a new place from the search. */
      go: function (lat, lon, zoom) {
        if (held) {
          held.setView([Number(lat), Number(lon)], Number(zoom) || held.getZoom());
        } else {
          opts.lat = lat;
          opts.lon = lon;
          if (zoom) {
            opts.zoom = zoom;
          }
        }
      },
      resize: function () {
        if (held) {
          held.invalidateSize();
        }
      },
    };
  }

  window.NefisMapFrame = { mount: mount };
})();
