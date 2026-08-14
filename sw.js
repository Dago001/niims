const CACHE_NAME = "niims-v2";

const urlsToCache = [
  "/build/css/custom.min.css",
  "/vendors/bootstrap/dist/css/bootstrap.min.css",
  "/vendors/font-awesome/css/font-awesome.min.css"
];

self.addEventListener("install", event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        // Cache files individually so that a single failed asset doesn't break the installation
        return Promise.allSettled(
          urlsToCache.map(url => cache.add(url).catch(err => console.log('Failed to cache:', url, err)))
        );
      })
  );
});

self.addEventListener("activate", event => {
  event.waitUntil(
    caches.keys().then(keys => {
      return Promise.all(
        keys.map(key => {
          if (key !== CACHE_NAME) {
            return caches.delete(key);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

self.addEventListener("fetch", event => {
  // Always bypass service worker cache for POST requests and PHP pages to ensure real-time dynamic behavior
  if (event.request.method === "POST" || event.request.url.includes(".php") || event.request.url.endsWith("/") || event.request.url.includes("index.php")) {
    event.respondWith(fetch(event.request));
    return;
  }

  event.respondWith(
    caches.match(event.request)
      .then(response => response || fetch(event.request))
  );
});