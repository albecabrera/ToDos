/* MenuBarTasks — Service Worker */
const VERSION = 'v1';
const STATIC_CACHE = `static-${VERSION}`;
const SHELL = [
    '/',
    '/css/style.css',
    '/js/app.js',
    '/manifest.webmanifest',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
];

self.addEventListener('install', e => {
    e.waitUntil(
        caches.open(STATIC_CACHE)
            .then(c => c.addAll(SHELL))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', e => {
    e.waitUntil(
        caches.keys()
            .then(keys => Promise.all(keys.filter(k => k !== STATIC_CACHE).map(k => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', e => {
    const url = new URL(e.request.url);
    if (e.request.method !== 'GET' || url.origin !== location.origin) return;

    // API: network-first, fallback caché (última respuesta conocida offline)
    if (url.pathname.startsWith('/api/')) {
        e.respondWith(
            fetch(e.request)
                .then(r => {
                    const copy = r.clone();
                    caches.open(STATIC_CACHE).then(c => c.put(e.request, copy));
                    return r;
                })
                .catch(() => caches.match(e.request))
        );
        return;
    }

    // HTML (/, /detail, /overview): network-first, fallback caché
    if (e.request.mode === 'navigate') {
        e.respondWith(
            fetch(e.request)
                .then(r => {
                    const copy = r.clone();
                    caches.open(STATIC_CACHE).then(c => c.put(e.request, copy));
                    return r;
                })
                .catch(() => caches.match(e.request).then(r => r || caches.match('/')))
        );
        return;
    }

    // Estáticos: cache-first
    e.respondWith(
        caches.match(e.request).then(hit => hit || fetch(e.request).then(r => {
            const copy = r.clone();
            caches.open(STATIC_CACHE).then(c => c.put(e.request, copy));
            return r;
        }))
    );
});
