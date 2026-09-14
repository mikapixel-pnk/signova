const VERSION = "signova-shell-v5";

const SHELL_CACHE =
  `${VERSION}-shell`;

const RUNTIME_CACHE =
  `${VERSION}-runtime`;

const APP_ROUTES = [
  "/app",
  "/app/pelanggan",
  "/app/barang-jasa",
  "/app/tagihan",
  "/app/keuangan",
  "/app/pembayaran",
  "/app/penawaran",
  "/app/pengaturan",
  "/app/menu",
  "/app/aksi",
  "/app/keuangan/kas-bank",
  "/app/keuangan/pemasukan",
  "/app/keuangan/pengeluaran",
  "/app/keuangan/piutang",
];

function isApiRequest(url) {
  return url.pathname.startsWith("/api/");
}

function isAuthRoute(url) {
  return [
    "/login",
    "/register",
    "/forgot-password",
    "/otp-login",
    "/ana-login",
    "/select-context",
  ].some((path) =>
    url.pathname.startsWith(path),
  );
}

function isStaticAsset(url) {
  return (
    url.pathname.startsWith("/_next/static/") ||
    url.pathname.startsWith("/brand/") ||
    /\.(?:png|jpg|jpeg|webp|svg|ico|woff2?)$/i.test(
      url.pathname,
    )
  );
}

async function cacheAppRoute(path) {
  try {
    const response = await fetch(path, {
      credentials: "same-origin",
      cache: "reload",
    });

    if (!response.ok) {
      return;
    }

    const cache =
      await caches.open(SHELL_CACHE);

    await cache.put(
      path,
      response.clone(),
    );
  } catch {
    // Install must not fail only because
    // one route is temporarily unavailable.
  }
}

self.addEventListener(
  "install",
  (event) => {
    event.waitUntil(
      Promise.all(
        APP_ROUTES.map(
          cacheAppRoute,
        ),
      ).then(() =>
        self.skipWaiting(),
      ),
    );
  },
);

self.addEventListener(
  "activate",
  (event) => {
    event.waitUntil(
      caches
        .keys()
        .then((keys) =>
          Promise.all(
            keys
              .filter(
                (key) =>
                  !key.startsWith(VERSION),
              )
              .map((key) =>
                caches.delete(key),
              ),
          ),
        )
        .then(() =>
          self.clients.claim(),
        ),
    );
  },
);

async function networkFirstNavigation(
  request,
) {
  const cache =
    await caches.open(SHELL_CACHE);

  try {
    const response =
      await fetch(request);

    if (
      response.ok &&
      request.method === "GET"
    ) {
      await cache.put(
        request.url,
        response.clone(),
      );
    }

    return response;
  } catch {
    const exact =
      await cache.match(request);

    if (exact) {
      return exact;
    }

    const url =
      new URL(request.url);

    const route =
      await cache.match(
        url.pathname,
      );

    if (route) {
      return route;
    }

    const home =
      await cache.match("/app");

    if (home) {
      return home;
    }

    return new Response(
      `<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta
    name="viewport"
    content="width=device-width,initial-scale=1"
  >
  <title>SIGNOVA • Offline</title>
</head>
<body
  style="
    font-family:system-ui,sans-serif;
    padding:32px;
    background:#f7f9fc;
    color:#172033;
  "
>
  <h1>SIGNOVA</h1>
  <p>Mode Offline</p>
  <p>
    Halaman ini belum tersimpan di perangkat.
    Sambungkan internet lalu buka halaman tersebut
    sekali agar tersedia saat offline.
  </p>
</body>
</html>`,
      {
        status: 503,
        headers: {
          "Content-Type":
            "text/html; charset=utf-8",
        },
      },
    );
  }
}

async function staleWhileRevalidate(
  request,
) {
  const cache =
    await caches.open(RUNTIME_CACHE);

  const cached =
    await cache.match(request);

  const networkPromise =
    fetch(request)
      .then(async (response) => {
        if (response.ok) {
          await cache.put(
            request,
            response.clone(),
          );
        }

        return response;
      })
      .catch(() => null);

  if (cached) {
    networkPromise.catch(
      () => null,
    );

    return cached;
  }

  const network =
    await networkPromise;

  if (network) {
    return network;
  }

  return Response.error();
}

self.addEventListener(
  "fetch",
  (event) => {
    const request =
      event.request;

    if (request.method !== "GET") {
      return;
    }

    const url =
      new URL(request.url);

    if (
      url.origin !==
      self.location.origin
    ) {
      return;
    }

    // API responses, auth endpoints, OTP,
    // finance mutations, and private user data
    // are intentionally not cached here.
    if (
      isApiRequest(url) ||
      isAuthRoute(url)
    ) {
      return;
    }

    if (
      url.pathname === "/app" ||
      url.pathname.startsWith("/app/")
    ) {
      event.respondWith(
        networkFirstNavigation(
          request,
        ),
      );

      return;
    }

    if (
      request.mode === "navigate"
    ) {
      event.respondWith(
        networkFirstNavigation(
          request,
        ),
      );

      return;
    }

    if (isStaticAsset(url)) {
      event.respondWith(
        staleWhileRevalidate(
          request,
        ),
      );
    }
  },
);

self.addEventListener(
  "message",
  (event) => {
    const data =
      event.data;

    if (
      !data ||
      data.type !==
        "SIGNOVA_WARM_ASSETS" ||
      !Array.isArray(data.urls)
    ) {
      return;
    }

    const urls =
      data.urls.filter(
        (value) =>
          typeof value === "string" &&
          value.startsWith(
            self.location.origin,
          ),
      );

    event.waitUntil(
      (async () => {
        const cache =
          await caches.open(
            RUNTIME_CACHE,
          );

        for (const url of urls) {
          try {
            const response =
              await fetch(
                url,
                {
                  credentials:
                    "same-origin",
                  cache: "reload",
                },
              );

            if (response.ok) {
              await cache.put(
                url,
                response.clone(),
              );
            }
          } catch {
            // Asset tertentu boleh gagal
            // tanpa menggagalkan warmup.
          }
        }
      })(),
    );
  },
);
