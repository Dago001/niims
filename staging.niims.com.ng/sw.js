const CACHE_NAME = "niims-cache-v4";

const STATIC_ASSETS = [
  "build/css/custom.min.css",
  "vendors/bootstrap/dist/css/bootstrap.min.css",
  "vendors/font-awesome/css/font-awesome.min.css",
  "vendors/jquery/dist/jquery.min.js",
  "vendors/bootstrap/dist/js/bootstrap.min.js",
  "vendors/fastclick/lib/fastclick.js",
  "vendors/nprogress/nprogress.js",
  "vendors/Chart.js/dist/Chart.min.js",
  "build/js/custom.min.js",
  "assets/extra-libs/DataTables/datatables.min.js",
  "js/lga.min.js"
];

// Install Event
self.addEventListener("install", event => {
  self.skipWaiting();
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => {
      return Promise.allSettled(
        STATIC_ASSETS.map(url => {
          return cache.add(url).catch(err => {
            console.warn("Failed to pre-cache asset during install:", url, err);
          });
        })
      );
    })
  );
});

// Activate Event
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

// Fetch Event
self.addEventListener("fetch", event => {
  const request = event.request;
  const url = new URL(request.url);

  // Exclude POST and other non-GET requests
  if (request.method !== "GET") {
    return;
  }

  // Exclude real-time check API endpoints and checks
  if (url.pathname.includes("check_service_no") || url.pathname.includes("ajax-log-print") || url.pathname.includes("check_indexes")) {
    return;
  }

  // Determine if it is a static asset (CSS, JS, Fonts, Images)
  const isStaticAsset = (
    request.destination === "style" ||
    request.destination === "script" ||
    request.destination === "font" ||
    request.destination === "image" ||
    url.pathname.match(/\.(css|js|woff|woff2|ttf|png|jpg|jpeg|gif|svg|ico)$/i)
  );

  if (isStaticAsset) {
    // Stale-While-Revalidate: serve the cached copy immediately for speed,
    // but always kick off a background fetch that refreshes the cache for
    // next time. Plain Cache-First here meant a stale, even broken, JS/CSS
    // file could sit in every visitor's cache indefinitely — until the
    // browser happened to notice sw.js itself had changed — since a normal
    // reload never rechecks the network once something is cached. This way
    // a deploy is picked up within one extra load, with no manual cache
    // version bump and no hard refresh required.
    event.respondWith(
      caches.open(CACHE_NAME).then(cache => {
        return cache.match(request).then(cachedResponse => {
          const networkFetch = fetch(request).then(networkResponse => {
            if (networkResponse && networkResponse.status === 200) {
              cache.put(request, networkResponse.clone());
            }
            return networkResponse;
          }).catch(() => cachedResponse);
          return cachedResponse || networkFetch;
        });
      })
    );
  } else {
    // Network-First (Network falling back to Cache) for pages and routes
    event.respondWith(
      fetch(request).then(networkResponse => {
        if (networkResponse && networkResponse.status === 200 && networkResponse.type === "basic") {
          const responseToCache = networkResponse.clone();
          caches.open(CACHE_NAME).then(cache => {
            cache.put(request, responseToCache);
          });
        }
        return networkResponse;
      }).catch(() => {
        // Fallback to cache if network fails (offline mode)
        return caches.match(request).then(cachedResponse => {
          if (cachedResponse) {
            return cachedResponse;
          }
          // If offline and not in cache, return a friendly offline HTML response
          return new Response(
            `<!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Offline | NIIMS</title>
                <link rel="stylesheet" href="vendors/bootstrap/dist/css/bootstrap.min.css">
                <style>
                    body { background: #f8fafc; font-family: system-ui, sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; color: #334155; }
                    .card { max-width: 480px; width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
                    .btn-retry { background-color: #059669; color: white; font-weight: 600; border: none; padding: 10px 20px; border-radius: 6px; }
                    .btn-retry:hover { background-color: #047857; color: white; }
                </style>
            </head>
            <body>
                <div class="card p-4 text-center m-3">
                    <div style="font-size: 48px; color: #64748b; margin-bottom: 15px;">🔌</div>
                    <h3 class="font-weight-bold" style="color: #1e293b;">You are Offline</h3>
                    <p class="text-muted mt-2">The Nigeria Immigration Identity Management System is currently unable to reach the server. Please check your internet connection.</p>
                    <button class="btn btn-retry mt-3" onclick="window.location.reload()">Retry Connection</button>
                </div>
            </body>
            </html>`,
            {
              headers: { "Content-Type": "text/html" }
            }
          );
        });
      })
    );
  }
});