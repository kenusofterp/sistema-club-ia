/*
 * Service Worker del club.
 * - Assets compilados (/build) e imágenes: cache-first.
 * - Navegación: network-first con respaldo en caché y página /offline.
 * - Nunca se cachean peticiones POST, Livewire, API ni rutas de administración.
 * - Notificaciones push: se muestran y al tocarlas abren la pantalla correspondiente.
 */
const VERSION = 'club-v2';
const STATIC_CACHE = `${VERSION}-static`;
const PAGES_CACHE = `${VERSION}-pages`;
const OFFLINE_URL = '/offline';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(PAGES_CACHE).then((cache) => cache.addAll([OFFLINE_URL])).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => !k.startsWith(VERSION)).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

const isExcluded = (url) =>
    url.pathname.startsWith('/livewire') ||
    url.pathname.startsWith('/api') ||
    url.pathname.startsWith('/admin') ||
    url.pathname.startsWith('/salir') ||
    url.pathname.startsWith('/ingresar');

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin || isExcluded(url)) {
        return;
    }

    // Assets versionados por Vite e imágenes subidas: cache-first.
    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/storage/') || url.pathname.startsWith('/pwa-icon/')) {
        event.respondWith(
            caches.match(request).then((cached) =>
                cached ||
                fetch(request).then((response) => {
                    if (response.ok) {
                        const copy = response.clone();
                        caches.open(STATIC_CACHE).then((cache) => cache.put(request, copy));
                    }
                    return response;
                })
            )
        );
        return;
    }

    // Páginas: red primero; si no hay conexión, la última versión guardada o la página offline.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    if (response.ok && (url.pathname.startsWith('/portal') || url.pathname === '/')) {
                        const copy = response.clone();
                        caches.open(PAGES_CACHE).then((cache) => cache.put(request, copy));
                    }
                    return response;
                })
                .catch(() => caches.match(request).then((cached) => cached || caches.match(OFFLINE_URL)))
        );
    }
});

// ---- Notificaciones push ----
self.addEventListener('push', (event) => {
    if (!event.data) {
        return;
    }

    let payload;
    try {
        payload = event.data.json();
    } catch {
        payload = { title: 'Aviso', body: event.data.text() };
    }

    event.waitUntil(
        self.registration.showNotification(payload.title || 'Aviso', {
            body: payload.body,
            icon: payload.icon,
            badge: payload.badge,
            tag: payload.tag,
            data: payload.data || {},
            renotify: Boolean(payload.tag),
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = (event.notification.data && event.notification.data.url) || '/';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
            for (const client of windows) {
                if (new URL(client.url).origin === self.location.origin && 'focus' in client) {
                    client.navigate(url);
                    return client.focus();
                }
            }
            return self.clients.openWindow(url);
        })
    );
});
