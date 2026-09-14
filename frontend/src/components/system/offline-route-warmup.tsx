"use client";

import {
  useEffect,
} from "react";

function collectRuntimeAssets():
  string[] {
  const urls =
    new Set<string>();

  document
    .querySelectorAll<
      HTMLScriptElement
    >(
      "script[src]",
    )
    .forEach(
      (element) => {
        if (
          element.src.startsWith(
            window.location.origin,
          )
        ) {
          urls.add(
            element.src,
          );
        }
      },
    );

  document
    .querySelectorAll<
      HTMLLinkElement
    >(
      'link[rel="stylesheet"][href]',
    )
    .forEach(
      (element) => {
        if (
          element.href.startsWith(
            window.location.origin,
          )
        ) {
          urls.add(
            element.href,
          );
        }
      },
    );

  document
    .querySelectorAll<
      HTMLImageElement
    >(
      "img[src]",
    )
    .forEach(
      (element) => {
        if (
          element.src.startsWith(
            window.location.origin,
          )
        ) {
          urls.add(
            element.src,
          );
        }
      },
    );

  performance
    .getEntriesByType(
      "resource",
    )
    .forEach(
      (entry) => {
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
          urls.add(
            entry.name,
          );
        }
      },
    );

  return [
    ...urls,
  ];
}

export function OfflineRouteWarmup() {
  useEffect(() => {
    if (
      !navigator.onLine ||
      !(
        "serviceWorker"
        in navigator
      )
    ) {
      return;
    }

    async function warmup() {
      try {
        const registration =
          await navigator
            .serviceWorker
            .ready;

        const assets =
          collectRuntimeAssets();

        registration.active
          ?.postMessage({
            type:
              "SIGNOVA_WARM_ASSETS",

            urls:
              assets,
          });
      } catch {
        /*
         * Warmup statis bersifat
         * best effort.
         */
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
      window.clearTimeout(
        timer,
      );
    };
  }, []);

  return null;
}
