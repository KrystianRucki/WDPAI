const REEVIO_CACHE_VERSION = "reevio-offline-v1";
const REEVIO_OFFLINE_URL = "/offline-page";
const REEVIO_SHELL_ASSETS = [
  REEVIO_OFFLINE_URL,
  "/public/assets/css/reevio.css",
  "/public/assets/js/reevio.js"
];

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches
      .open(REEVIO_CACHE_VERSION)
      .then((cache) => cache.addAll(REEVIO_SHELL_ASSETS))
      .then(() => self.skipWaiting())
      .catch(() => self.skipWaiting())
  );
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) =>
        Promise.all(
          keys
            .filter((key) => key.startsWith("reevio-offline-") && key !== REEVIO_CACHE_VERSION)
            .map((key) => caches.delete(key))
        )
      )
      .then(() => self.clients.claim())
  );
});

self.addEventListener("fetch", (event) => {
  const request = event.request;

  if (request.method !== "GET") {
    return;
  }

  if (request.mode === "navigate") {
    event.respondWith(
      fetch(request).catch(() =>
        caches.match(REEVIO_OFFLINE_URL).then((response) => {
          if (response) {
            return response;
          }

          return new Response(
            "<!doctype html><title>Reevio Offline</title><h1>Offline</h1><p>Reevio cannot reach the server.</p>",
            {
              status: 503,
              headers: { "Content-Type": "text/html; charset=utf-8" }
            }
          );
        })
      )
    );
    return;
  }

  const url = new URL(request.url);
  const isLocalStaticAsset =
    url.origin === self.location.origin &&
    (url.pathname.startsWith("/public/assets/") ||
      url.pathname.startsWith("/public/uploads/") ||
      url.pathname === "/favicon.ico");

  if (!isLocalStaticAsset) {
    return;
  }

  event.respondWith(
    caches.match(request).then((cached) => {
      const networkRequest = fetch(request)
        .then((response) => {
          if (response && response.ok) {
            const copy = response.clone();
            caches.open(REEVIO_CACHE_VERSION).then((cache) => cache.put(request, copy));
          }

          return response;
        })
        .catch(() => cached);

      return cached || networkRequest;
    })
  );
});
