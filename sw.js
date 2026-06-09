// D18 Notes — Service Worker
const CACHE_VER  = 'd18notes-v1';
const OFFLINE    = './offline.html';

// Pre-cache these on install
const PRE_CACHE = [
  './offline.html',
  './icon.svg',
  './manifest.json',
  'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
];

// ── Install: pre-cache static assets ─────────────────────────────────────────
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_VER)
      .then(cache => cache.addAll(PRE_CACHE))
      .then(() => self.skipWaiting())
  );
});

// ── Activate: clean up old cache versions ─────────────────────────────────────
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(
        keys.filter(k => k !== CACHE_VER).map(k => caches.delete(k))
      )
    ).then(() => self.clients.claim())
  );
});

// ── Fetch strategy ────────────────────────────────────────────────────────────
self.addEventListener('fetch', event => {
  // Only handle GET requests
  if (event.request.method !== 'GET') return;

  const url = new URL(event.request.url);

  // ── PHP pages: Network First (always fresh notes) ─────────────────────────
  if (url.pathname.endsWith('.php')) {
    event.respondWith(
      fetch(event.request)
        .catch(() => caches.match(OFFLINE))
    );
    return;
  }

  // ── Google Fonts: Cache First ─────────────────────────────────────────────
  if (url.hostname === 'fonts.googleapis.com' || url.hostname === 'fonts.gstatic.com') {
    event.respondWith(
      caches.match(event.request).then(cached => {
        if (cached) return cached;
        return fetch(event.request).then(response => {
          if (response && response.ok) {
            const clone = response.clone();
            caches.open(CACHE_VER).then(cache => cache.put(event.request, clone));
          }
          return response;
        });
      })
    );
    return;
  }

  // ── Static assets: Cache First, then network ──────────────────────────────
  event.respondWith(
    caches.match(event.request).then(cached => {
      if (cached) return cached;
      return fetch(event.request).then(response => {
        if (response && response.ok && url.origin === location.origin) {
          const clone = response.clone();
          caches.open(CACHE_VER).then(cache => cache.put(event.request, clone));
        }
        return response;
      }).catch(() => caches.match(OFFLINE));
    })
  );
});
