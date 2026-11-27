// Service worker minimal -- SENGAJA cuma cache app-shell statis (logo,
// manifest) dengan strategi network-first, BUKAN cache seluruh halaman.
// Tujuannya cuma satu: penuhi syarat teknis Chrome untuk prompt "Tambah ke
// Layar Utama" otomatis muncul (butuh service worker terdaftar + fetch
// handler). Data kos/booking/dsb selalu diambil fresh dari jaringan --
// caching agresif justru berbahaya untuk data yang berubah-ubah begini.
const CACHE_NAME = 'koskita-shell-v1';
const SHELL_ASSETS = ['/images/logo_icon.png', '/manifest.webmanifest'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(SHELL_ASSETS))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key)))
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    // Cuma tangani GET ke app-shell assets kita sendiri -- semua request
    // lain (halaman, API) lewat langsung ke jaringan seperti biasa, tidak
    // pernah disajikan dari cache.
    const url = new URL(event.request.url);
    if (event.request.method !== 'GET' || !SHELL_ASSETS.includes(url.pathname)) {
        return;
    }

    event.respondWith(
        fetch(event.request)
            .then((response) => {
                const clone = response.clone();
                caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
                return response;
            })
            .catch(() => caches.match(event.request))
    );
});
