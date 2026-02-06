const STATIC_CACHE = 'coffee-static-v3';
const HTML_CACHE = 'coffee-html-v3';
const STATIC_ASSETS = ['/', '/profile', '/assets/css/app.css', '/assets/js/app.js', '/manifest.json', '/offline.html'];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(STATIC_CACHE).then((cache) => cache.addAll(STATIC_ASSETS)));
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(keys.filter((k) => ![STATIC_CACHE, HTML_CACHE].includes(k)).map((k) => caches.delete(k)));
    await self.clients.claim();
  })());
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;
  const accept = req.headers.get('accept') || '';

  if (accept.includes('text/html')) {
    event.respondWith((async () => {
      const cache = await caches.open(HTML_CACHE);
      const cached = await cache.match(req);
      const network = fetch(req)
        .then((res) => {
          cache.put(req, res.clone());
          return res;
        })
        .catch(async () => cached || caches.match('/offline.html'));
      return cached || network;
    })());
    return;
  }

  event.respondWith(
    caches.match(req).then((cached) =>
      cached ||
      fetch(req).then((res) => {
        const copy = res.clone();
        caches.open(STATIC_CACHE).then((cache) => cache.put(req, copy));
        return res;
      })
    )
  );
});

self.addEventListener('push', (event) => {
  let data = {};
  try {
    data = event.data ? event.data.json() : {};
  } catch {
    data = { title: 'Kapouch', body: event.data ? event.data.text() : '' };
  }

  const title = data.title || 'Kapouch';
  const body = data.body || 'Новое уведомление';
  const url = data.url || '/profile';

  event.waitUntil(self.registration.showNotification(title, {
    body,
    data: { url },
    icon: '/assets/icons/icon-192.svg',
    badge: '/assets/icons/icon-192.svg'
  }));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const targetUrl = event.notification.data?.url || '/profile';

  event.waitUntil((async () => {
    const allClients = await clients.matchAll({ type: 'window', includeUncontrolled: true });
    for (const client of allClients) {
      if ('focus' in client) {
        client.navigate(targetUrl);
        return client.focus();
      }
    }
    if (clients.openWindow) return clients.openWindow(targetUrl);
  })());
});
