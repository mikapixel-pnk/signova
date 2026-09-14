const SHELL_CACHE =
  "signova-shell-v7";

const RUNTIME_CACHE =
  "signova-runtime-v7";

const OFFLINE_URL =
  "/offline.html";

const SAFE_PRECACHE = [
  OFFLINE_URL,
  "/brand/signova-mark.png",
];

function isSignovaCache(key) {
  return (
    key.startsWith("signova-shell-") ||
    key.startsWith("signova-runtime-")
  );
}

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
    url.pathname.startsWith(path)
  );
}

function isSafeStaticAsset(url) {
  return (
    url.pathname.startsWith("/_next/static/") ||
    url.pathname.startsWith("/brand/") ||
    /\.(?:png|jpg|jpeg|webp|svg|ico|woff2?)$/i.test(
      url.pathname,
    )
  );
}

async function precacheShell() {
  const cache =
    await caches.open(SHELL_CACHE);

  for (const path of SAFE_PRECACHE) {
    try {
      const response =
        await fetch(path, {
          cache: "reload",
          credentials: "same-origin",
        });

      if (response.ok) {
        await cache.put(
          path,
          response.clone(),
        );
      }
    } catch {
      // Best effort.
    }
  }
}

self.addEventListener(
  "install",
  (event) => {
    event.waitUntil(
      precacheShell().then(() =>
        self.skipWaiting(),
      ),
    );
  },
);

self.addEventListener(
  "activate",
  (event) => {
    event.waitUntil(
      (async () => {
        const keys =
          await caches.keys();

        await Promise.all(
          keys
            .filter(
              (key) =>
                isSignovaCache(key) &&
                key !== SHELL_CACHE &&
                key !== RUNTIME_CACHE,
            )
            .map((key) =>
              caches.delete(key),
            ),
        );

        await self.clients.claim();
      })(),
    );
  },
);

async function offlineResponse() {
  const cache =
    await caches.open(SHELL_CACHE);

  const cached =
    await cache.match(OFFLINE_URL);

  if (cached) {
    return cached;
  }

  return new Response(
    "SIGNOVA sedang offline.",
    {
      status: 503,
      headers: {
        "Content-Type":
          "text/plain; charset=utf-8",
      },
    },
  );
}

async function networkOnlyNavigation(
  request,
) {
  try {
    return await fetch(
      request,
      {
        cache: "no-store",
      },
    );
  } catch {
    return offlineResponse();
  }
}

async function staleWhileRevalidateAsset(
  request,
) {
  const cache =
    await caches.open(
      RUNTIME_CACHE,
    );

  const cached =
    await cache.match(request);

  const networkPromise =
    fetch(request)
      .then(async (response) => {
        if (
          response.ok &&
          response.type !== "opaque"
        ) {
          await cache.put(
            request,
            response.clone(),
          );
        }

        return response;
      })
      .catch(() => null);

  if (cached) {
    void networkPromise;

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

    if (
      isApiRequest(url) ||
      isAuthRoute(url)
    ) {
      return;
    }

    if (
      request.mode === "navigate"
    ) {
      event.respondWith(
        networkOnlyNavigation(
          request,
        ),
      );

      return;
    }

    if (
      isSafeStaticAsset(url)
    ) {
      event.respondWith(
        staleWhileRevalidateAsset(
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
      data.urls.filter((value) => {
        if (
          typeof value !== "string"
        ) {
          return false;
        }

        try {
          const url =
            new URL(value);

          return (
            url.origin ===
              self.location.origin &&
            isSafeStaticAsset(url)
          );
        } catch {
          return false;
        }
      });

    event.waitUntil(
      (async () => {
        const cache =
          await caches.open(
            RUNTIME_CACHE,
          );

        for (const value of urls) {
          try {
            const response =
              await fetch(
                value,
                {
                  credentials:
                    "same-origin",
                  cache: "reload",
                },
              );

            if (
              response.ok &&
              response.type !==
                "opaque"
            ) {
              await cache.put(
                value,
                response.clone(),
              );
            }
          } catch {
            // Best effort.
          }
        }
      })(),
    );
  },
);
