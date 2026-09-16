"use client";

import {
  useEffect,
} from "react";
import {
  useRouter,
} from "next/navigation";

import {
  SplashScreen,
} from "@/components/feedback/splash-screen";

import {
  setPendingAccessSelection,
} from "@/lib/auth/access-selection";

import {
  getSelectedContext,
  setSelectedContext,
} from "@/lib/auth/active-context";

import {
  getAuthContext,
} from "@/lib/auth/context";

import {
  hasCompletedOnboarding,
} from "@/lib/auth/onboarding";

export default function Home() {
  const router = useRouter();

  useEffect(() => {
    let cancelled = false;

    window.queueMicrotask(
      () => {
        void getAuthContext()
          .then((response) => {
            if (cancelled) {
              return;
            }

            const data =
              response.data;

            const stored =
              getSelectedContext();

            if (
              stored?.type ===
                "TENANT" &&
              data.access.tenants.some(
                (tenant) =>
                  tenant.id ===
                    stored.tenantId &&
                  tenant.businesses.some(
                    (business) =>
                      business.id ===
                      stored.businessId,
                  ),
              )
            ) {
              router.replace(
                "/app",
              );

              return;
            }

            if (
              stored?.type ===
                "PLATFORM" &&
              data.access.platform
                .available
            ) {
              router.replace(
                "/admin",
              );

              return;
            }

            if (
              data.default_context
                ?.type ===
              "TENANT"
            ) {
              setSelectedContext({
                type: "TENANT",
                tenantId:
                  data.default_context
                    .tenant_id,
                businessId:
                  data.default_context
                    .business_id,
              });

              router.replace(
                "/app",
              );

              return;
            }

            if (
              data.default_context
                ?.type ===
              "PLATFORM"
            ) {
              setSelectedContext({
                type: "PLATFORM",
                tenantId: null,
                businessId: null,
              });

              router.replace(
                "/admin",
              );

              return;
            }

            if (
              data.requires_context_selection
            ) {
              setPendingAccessSelection(
                data.access,
              );

              router.replace(
                "/select-context",
              );

              return;
            }

            router.replace(
              "/login",
            );
          })
          .catch(() => {
            if (cancelled) {
              return;
            }

            const desktop =
              window.matchMedia(
                "(min-width: 900px)",
              ).matches;

            if (desktop) {
              router.replace(
                "/login",
              );

              return;
            }

            router.replace(
              hasCompletedOnboarding()
                ? "/login"
                : "/welcome",
            );
          });
      },
    );

    return () => {
      cancelled = true;
    };
  }, [router]);

  return <SplashScreen />;
}
