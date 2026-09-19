/*
 * The checkout map where a customer marks the spot a door delivery goes to.
 *
 * Runs on Google Maps when the owner has set a key in the admin, and on
 * OpenStreetMap (via Leaflet) otherwise. Both are coloured to
 * match the site — a warm dark brown map in the dark theme, a cream one in
 * the light theme — with a gold pin, and both keep to Baku.
 *
 *   NefisMapPicker.mount(el, {
 *     google: 'KEY' | null, bounds: {south, west, north, east}, center: {lat, lng},
 *     value: {lat, lng} | null, reverseUrl, searchUrl,
 *     onPick(lat, lng)            a point was set (click, drag, search, locate)
 *   }) -> Promise<{ place(lat, lng, pan), refresh(), reverse(lat, lng) -> Promise<string|null>,
 *                   search(q) -> Promise<[{lat, lng, label}]> }>
 */
(function (global) {
  'use strict';

  var PIN = '<svg xmlns="http://www.w3.org/2000/svg" width="36" height="46" viewBox="0 0 36 46">'
    + '<path d="M18 45C18 45 3 27.6 3 17.5a15 15 0 0 1 30 0C33 27.6 18 45 18 45z" fill="#D6A35A" stroke="#3A2617" stroke-width="2"/>'
    + '<circle cx="18" cy="17.5" r="6" fill="#1F1712"/></svg>';

  function isDark() {
    var t = document.documentElement.getAttribute('data-theme');
    if (t) return t === 'dark';
    return !!(global.matchMedia && global.matchMedia('(prefers-color-scheme: dark)').matches);
  }

  /* Re-colour the map whenever the site's theme changes. */
  function onThemeChange(fn) {
    new MutationObserver(fn).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
    if (global.matchMedia) {
      var mq = global.matchMedia('(prefers-color-scheme: dark)');
      if (mq.addEventListener) mq.addEventListener('change', fn);
    }
  }

  function getJSON(url) {
    return fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : {}; })
      .catch(function () { return {}; });
  }

  /* ---------------- OpenStreetMap (Leaflet) ---------------- */

  function loadLeaflet() {
    if (global.L) return Promise.resolve();
    return new Promise(function (resolve, reject) {
      var css = document.createElement('link');
      css.rel = 'stylesheet';
      css.href = 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css';
      document.head.appendChild(css);
      var s = document.createElement('script');
      s.src = 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js';
      s.onload = function () { resolve(); };
      s.onerror = reject;
      document.head.appendChild(s);
    });
  }

  function osm(el, o) {
    return loadLeaflet().then(function () {
      var L = global.L, b = o.bounds;
      var box = L.latLngBounds([b.south, b.west], [b.north, b.east]);
      var map = L.map(el, { center: [o.center.lat, o.center.lng], zoom: 12, minZoom: 10, maxBounds: box.pad(0.05), maxBoundsViscosity: 0.9 });
      /* OpenStreetMap's own tiles (free, no key; their policy wants the
         site's origin sent along and the credit shown). The page's CSS turns
         them into the site's dark brown or cream. */
      L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19, referrerPolicy: 'strict-origin-when-cross-origin',
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>'
      }).addTo(map);
      function paint() { el.classList.toggle('map-dark', isDark()); }
      paint();
      onThemeChange(paint);

      var icon = L.divIcon({ className: 'map-pin', html: PIN, iconSize: [36, 46], iconAnchor: [18, 44] });
      var marker = null;
      function place(lat, lng, pan) {
        if (!marker) {
          marker = L.marker([lat, lng], { draggable: true, icon: icon, keyboard: false }).addTo(map);
          marker.on('dragend', function () { var p = marker.getLatLng(); o.onPick(p.lat, p.lng); });
        } else {
          marker.setLatLng([lat, lng]);
        }
        if (pan) map.setView([lat, lng], Math.max(map.getZoom(), 16));
      }
      map.on('click', function (e) { place(e.latlng.lat, e.latlng.lng); o.onPick(e.latlng.lat, e.latlng.lng); });
      if (o.value) place(o.value.lat, o.value.lng, true);

      return {
        place: place,
        refresh: function () { map.invalidateSize(); },
        reverse: function (lat, lng) {
          return getJSON(o.reverseUrl + '?lat=' + lat + '&lng=' + lng).then(function (d) { return d.address || null; });
        },
        search: function (q) {
          return getJSON(o.searchUrl + '?q=' + encodeURIComponent(q)).then(function (d) { return d.results || []; });
        }
      };
    });
  }

  /* ---------------- Google Maps ---------------- */

  /* The site's palette as map styles. */
  var GOOGLE_DARK = [
    { elementType: 'geometry', stylers: [{ color: '#1f1712' }] },
    { elementType: 'labels.text.fill', stylers: [{ color: '#cbb59b' }] },
    { elementType: 'labels.text.stroke', stylers: [{ color: '#17110d' }] },
    { featureType: 'administrative', elementType: 'geometry.stroke', stylers: [{ color: '#4a3a2c' }] },
    { featureType: 'poi', stylers: [{ visibility: 'off' }] },
    { featureType: 'poi.park', elementType: 'geometry', stylers: [{ visibility: 'on' }, { color: '#243021' }] },
    { featureType: 'road', elementType: 'geometry', stylers: [{ color: '#3a2c20' }] },
    { featureType: 'road', elementType: 'geometry.stroke', stylers: [{ color: '#2a2018' }] },
    { featureType: 'road.highway', elementType: 'geometry', stylers: [{ color: '#6b4e2b' }] },
    { featureType: 'road.highway', elementType: 'labels.text.fill', stylers: [{ color: '#e3b56e' }] },
    { featureType: 'transit', elementType: 'geometry', stylers: [{ color: '#2e241b' }] },
    { featureType: 'transit.station', elementType: 'labels.text.fill', stylers: [{ color: '#d6a35a' }] },
    { featureType: 'water', elementType: 'geometry', stylers: [{ color: '#0f1418' }] },
    { featureType: 'water', elementType: 'labels.text.fill', stylers: [{ color: '#5c6b73' }] }
  ];
  var GOOGLE_LIGHT = [
    { elementType: 'geometry', stylers: [{ color: '#f7efe3' }] },
    { elementType: 'labels.text.fill', stylers: [{ color: '#5c4330' }] },
    { elementType: 'labels.text.stroke', stylers: [{ color: '#fffdf9' }] },
    { featureType: 'poi', stylers: [{ visibility: 'off' }] },
    { featureType: 'poi.park', elementType: 'geometry', stylers: [{ visibility: 'on' }, { color: '#dfe6cf' }] },
    { featureType: 'road', elementType: 'geometry', stylers: [{ color: '#ffffff' }] },
    { featureType: 'road.highway', elementType: 'geometry', stylers: [{ color: '#ecd3a8' }] },
    { featureType: 'road.highway', elementType: 'geometry.stroke', stylers: [{ color: '#d6a35a' }] },
    { featureType: 'transit.station', elementType: 'labels.text.fill', stylers: [{ color: '#9c6c2a' }] },
    { featureType: 'water', elementType: 'geometry', stylers: [{ color: '#c9dde4' }] }
  ];

  var googleLoading = null;
  function loadGoogle(key) {
    if (global.google && global.google.maps) return Promise.resolve();
    if (googleLoading) return googleLoading;
    googleLoading = new Promise(function (resolve, reject) {
      global.__nefisMapReady = function () { resolve(); };
      var s = document.createElement('script');
      s.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(key) + '&language=az&region=AZ&callback=__nefisMapReady&loading=async';
      s.async = true;
      s.onerror = reject;
      document.head.appendChild(s);
    });
    return googleLoading;
  }

  function googleMap(el, o) {
    return loadGoogle(o.google).then(function () {
      var gm = global.google.maps, b = o.bounds;
      var map = new gm.Map(el, {
        center: o.center, zoom: 12, minZoom: 10,
        restriction: { latLngBounds: b, strictBounds: false },
        styles: isDark() ? GOOGLE_DARK : GOOGLE_LIGHT,
        disableDefaultUI: true, zoomControl: true, clickableIcons: false, gestureHandling: 'cooperative',
        backgroundColor: isDark() ? '#1f1712' : '#f7efe3'
      });
      onThemeChange(function () { map.setOptions({ styles: isDark() ? GOOGLE_DARK : GOOGLE_LIGHT }); });

      var icon = { url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(PIN), scaledSize: new gm.Size(36, 46), anchor: new gm.Point(18, 44) };
      var marker = null, geocoder = new gm.Geocoder();
      function place(lat, lng, pan) {
        var pos = { lat: lat, lng: lng };
        if (!marker) {
          marker = new gm.Marker({ map: map, position: pos, draggable: true, icon: icon });
          marker.addListener('dragend', function () { var p = marker.getPosition(); o.onPick(p.lat(), p.lng()); });
        } else {
          marker.setPosition(pos);
        }
        if (pan) { map.panTo(pos); if (map.getZoom() < 16) map.setZoom(16); }
      }
      map.addListener('click', function (e) { place(e.latLng.lat(), e.latLng.lng()); o.onPick(e.latLng.lat(), e.latLng.lng()); });
      if (o.value) place(o.value.lat, o.value.lng, true);

      return {
        place: place,
        refresh: function () { gm.event.trigger(map, 'resize'); },
        reverse: function (lat, lng) {
          return geocoder.geocode({ location: { lat: lat, lng: lng }, language: 'az' })
            .then(function (r) { return r.results && r.results[0] ? r.results[0].formatted_address : null; })
            .catch(function () { return null; });
        },
        search: function (q) {
          return geocoder.geocode({ address: q, bounds: b, componentRestrictions: { country: 'AZ' }, language: 'az' })
            .then(function (r) {
              return (r.results || []).slice(0, 6).map(function (x) {
                return { lat: x.geometry.location.lat(), lng: x.geometry.location.lng(), label: x.formatted_address };
              });
            })
            .catch(function () { return []; });
        }
      };
    });
  }

  /* Google when there is a key; OpenStreetMap otherwise, or if Google refuses
     it — which it may do only after the map has already loaded. */
  function mount(el, o) {
    var impl = null;
    var api = {
      place: function (lat, lng, pan) { if (impl) impl.place(lat, lng, pan); },
      refresh: function () { if (impl) impl.refresh(); },
      reverse: function (lat, lng) { return impl ? impl.reverse(lat, lng) : Promise.resolve(null); },
      search: function (q) { return impl ? impl.search(q) : Promise.resolve([]); }
    };
    function useOsm() {
      el.innerHTML = '';
      el.className = el.className.replace(/\bgm-style\b/g, '');
      return osm(el, o).then(function (m) { impl = m; return api; });
    }
    if (!o.google) return useOsm();
    global.gm_authFailure = useOsm;
    return googleMap(el, o).then(function (m) { impl = m; return api; }).catch(useOsm);
  }

  global.NefisMapPicker = { mount: mount };
})(window);
