/* The shop, working without a signal.
 *
 * Installed on a phone's home screen, nefis.az runs in its own window with no
 * browser bar — and the window has to open even in a lift. This worker keeps
 * what it is safe to keep and nothing else:
 *
 *   • pages are always fetched from the network first, so a price is never a
 *     day old; a page that was opened before is served from the shelf only
 *     when the network fails,
 *   • only the pages anyone may read are kept. The basket, the checkout, the
 *     customer's own orders and the account pages are never written down —
 *     a phone is lent, and so is a browser,
 *   • the shop's own styles, scripts and pictures are served from the shelf
 *     and refreshed behind the page: they already carry a stamp in the
 *     address, so a new release is a new address and never a stale file,
 *   • the admin, the courier's screen, the payment gateway, the chat and the
 *     map lookups are left alone entirely.
 */

var VERSION = 'nefis-2026-10-05b';
var PAGES = 'pages-' + VERSION;
var ASSETS = 'assets-' + VERSION;

/* The three ways of saying "no signal", one per language. Put on the shelf
   when the worker installs, which is the one moment there is a network. */
var OFFLINE = ['/oflayn', '/ru/oflayn', '/en/oflayn'];

/* Nothing under these is this worker's business. */
var NEVER = [
  '/admin', '/admin-phone', '/admin-fayl', '/admin-ulduz', '/admin-lokasiya',
  '/admin-kuryerler', '/kuryer', '/epoint', '/telegram', '/sohbet', '/xerite',
  '/lokasiya', '/livewire', '/i/', '/canli', '/sitemap.xml', '/feed.xml'
];

/* Pages that belong to one person rather than to everybody, by address.
   Fetched, shown, never kept. These are this shop's real paths — the sign-in
   and the password pages included, because each carries a form token that is
   only good for as long as the session behind it.

   This list is the belt; the braces is the header below, which catches every
   page drawn for somebody who is signed in, whatever its address. */
var PRIVATE = [
  '/cart', '/checkout', '/orders', '/sifaris',
  '/login', '/register', '/sifre-unutdum', '/sifre-yenile', '/canli-hazirla'
];

/* Set by the server on anything drawn for a signed-in visitor. A phone gets
   lent, and a form token kept past its session is how pressing "Çıxış" ends
   at a page saying "Page Expired". */
var PRIVATE_HEADER = 'X-Nefis-Private';

/* How much is worth keeping. A phone's storage is not ours to fill. */
var MAX_PAGES = 40;
var MAX_ASSETS = 150;

self.addEventListener('install', function (event) {
  event.waitUntil(
    caches.open(PAGES).then(function (cache) {
      // One at a time: a single missing address must not fail the install.
      return Promise.all(OFFLINE.map(function (url) {
        return cache.add(new Request(url, { cache: 'reload' })).catch(function () {});
      }));
    }).then(function () { return self.skipWaiting(); })
  );
});

self.addEventListener('activate', function (event) {
  event.waitUntil(
    caches.keys().then(function (names) {
      return Promise.all(names.map(function (name) {
        // Everything from an older release goes; the stamp is in the name.
        return (name !== PAGES && name !== ASSETS) ? caches.delete(name) : null;
      }));
    }).then(function () { return self.clients.claim(); })
  );
});

/* The worker can be told to step aside — the page does that when somebody
   signs out, so nothing of theirs is left on the shelf. */
self.addEventListener('message', function (event) {
  if (event.data === 'forget-pages') {
    caches.delete(PAGES);
  }
});

function startsWithAny(path, list) {
  for (var i = 0; i < list.length; i++) {
    if (path === list[i] || path.indexOf(list[i] + '/') === 0 || path.indexOf(list[i]) === 0) {
      return true;
    }
  }
  return false;
}

/** Without the language in front of it, so one list covers all three. */
function bare(path) {
  var m = path.match(/^\/(ru|en)(\/|$)/);
  return m ? path.slice(3) || '/' : path;
}

function offlineFor(path) {
  var m = path.match(/^\/(ru|en)(\/|$)/);
  return m ? '/' + m[1] + '/oflayn' : '/oflayn';
}

/** Keeps a cache down to size, oldest first. */
function trim(name, max) {
  caches.open(name).then(function (cache) {
    cache.keys().then(function (keys) {
      for (var i = 0; i < keys.length - max; i++) {
        cache.delete(keys[i]);
      }
    });
  });
}

function isAsset(path) {
  return /^\/(css|js|build|fonts|images)\//.test(path)
    || /\.(css|js|woff2?|ttf|otf|png|jpe?g|webp|svg|ico)$/i.test(path);
}

self.addEventListener('fetch', function (event) {
  var request = event.request;

  // Only ordinary reads of this site's own addresses.
  if (request.method !== 'GET') { return; }

  var url;
  try { url = new URL(request.url); } catch (e) { return; }
  if (url.origin !== self.location.origin) { return; }

  var path = url.pathname;
  if (startsWithAny(path, NEVER)) { return; }

  if (request.mode === 'navigate' || (request.headers.get('accept') || '').indexOf('text/html') !== -1) {
    var keepIt = !startsWithAny(bare(path), PRIVATE) && !url.search;
    event.respondWith(
      fetch(request).then(function (response) {
        if (keepIt && response && response.ok && response.type === 'basic'
            && !response.headers.get(PRIVATE_HEADER)) {
          var copy = response.clone();
          caches.open(PAGES).then(function (cache) {
            cache.put(request, copy);
            trim(PAGES, MAX_PAGES);
          });
        }
        return response;
      }).catch(function () {
        return caches.match(request).then(function (hit) {
          return hit || caches.match(offlineFor(path)).then(function (page) {
            return page || new Response('', { status: 503, statusText: 'Offline' });
          });
        });
      })
    );
    return;
  }

  if (!isAsset(path)) { return; }

  // The shelf first, and a quiet refresh behind the page.
  event.respondWith(
    caches.match(request).then(function (hit) {
      var fresh = fetch(request).then(function (response) {
        if (response && response.ok && response.type === 'basic') {
          var copy = response.clone();
          caches.open(ASSETS).then(function (cache) {
            cache.put(request, copy);
            trim(ASSETS, MAX_ASSETS);
          });
        }
        return response;
      }).catch(function () { return hit; });

      return hit || fresh;
    })
  );
});
