"use client";

import {
  useEffect,
} from "react";

export function ServiceWorkerRegistration() {
  useEffect(() => {
    if (
      !(
        "serviceWorker"
        in navigator
      )
    ) {
      return;
    }

    async function register() {
      try {
        const registration =
          await navigator
            .serviceWorker
            .register(
              "/sw.js",
              {
                scope: "/",
                updateViaCache:
                  "none",
              },
            );

        await registration
          .update();
      } catch (error) {
        console.error(
          "SIGNOVA service worker gagal didaftarkan.",
          error,
        );
      }
    }

    if (
      document.readyState ===
      "complete"
    ) {
      void register();

      return;
    }

    window.addEventListener(
      "load",
      register,
      {
        once: true,
      },
    );

    return () => {
      window.removeEventListener(
        "load",
        register,
      );
    };
  }, []);

  return null;
}
