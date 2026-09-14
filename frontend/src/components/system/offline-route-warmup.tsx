"use client";

import {
  useEffect,
} from "react";

const routes = [
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

function collectRuntimeAssets():
  string[] {
  const urls =
    new Set<string>();

  document
    .querySelectorAll<
      HTMLScriptElement
    >("script[src]")
    .forEach((element) => {
      if (element.src) {
        urls.add(element.src);
      }
    });

  document
    .querySelectorAll<
      HTMLLinkElement
    >(
      'link[rel="stylesheet"][href]',
    )
    .forEach((element) => {
      if (element.href) {
        urls.add(element.href);
      }
    });

  document
    .querySelectorAll<
      HTMLImageElement
    >("img[src]")
    .forEach((element) => {
      if (element.src) {
        urls.add(element.src);
      }
    });

  performance
    .getEntriesByType("resource")
    .forEach((entry) => {
      if (
        entry.name.startsWith(
          window.location.origin,
        ) &&
        (
          entry.name.includes(
            "/_next/static/",
          ) ||
          entry.name.includes(
            "/brand/",
          )
        )
      ) {
        urls.add(entry.name);
      }
    });

  return [...urls];
}

export function OfflineRouteWarmup() {
  useEffect(() => {
    if (
      !navigator.onLine ||
      !("serviceWorker" in navigator)
    ) {
      return;
    }

    async function warmup() {
      try {
        const registration =
          await navigator
            .serviceWorker
            .ready;

        for (const route of routes) {
          try {
            await fetch(
              route,
              {
                method: "GET",
                credentials:
                  "same-origin",
                cache: "reload",
                headers: {
                  "X-Signova-Prefetch":
                    "offline-shell",
                },
              },
            );
          } catch {
            // Best effort.
          }
        }

        const assets =
          collectRuntimeAssets();

        registration.active
          ?.postMessage({
            type:
              "SIGNOVA_WARM_ASSETS",
            urls: assets,
          });
      } catch {
        // Offline warmup
        // tidak boleh mengganggu app.
      }
    }

    const timer =
      window.setTimeout(
        () => {
          void warmup();
        },
        1500,
      );

    return () => {
      window.clearTimeout(timer);
    };
  }, []);

  return null;
}
