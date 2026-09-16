"use client";

import {
  useRouter,
} from "next/navigation";

import {
  useCallback,
  useEffect,
  useState,
  type ReactNode,
} from "react";

import {
  ActionFeedback,
} from "@/components/feedback/action-feedback";

import {
  Button,
} from "@/components/ui/button";

import {
  ApiClientError,
} from "@/lib/api/types";

import {
  setPendingAccessSelection,
} from "@/lib/auth/access-selection";

import {
  clearSelectedContext,
  getSelectedContext,
  setSelectedContext,
} from "@/lib/auth/active-context";

import {
  getAuthContext,
} from "@/lib/auth/context";

import {
  apiErrorMessage,
  apiRequestId,
} from "@/lib/api/error-message";

import styles from "./tenant-session-gate.module.css";

type TenantSessionGateProps = {
  children: ReactNode;

  onBusinessResolved?: (
    businessName: string,
  ) => void;
};

export function TenantSessionGate({
  children,
  onBusinessResolved,
}: TenantSessionGateProps) {
  const router =
    useRouter();

  const [
    ready,
    setReady,
  ] = useState(false);

  const [
    error,
    setError,
  ] = useState<unknown>(
    null,
  );

  const bootstrap =
    useCallback(
      async () => {
        setError(null);

        try {
          const response =
            await getAuthContext();

          const data =
            response.data;

          if (!data.has_access) {
            clearSelectedContext();

            router.replace(
              "/login",
            );

            return;
          }

          const stored =
            getSelectedContext();

          if (
            stored?.type ===
              "TENANT"
          ) {
            const tenant =
              data.access.tenants.find(
                (item) =>
                  item.id ===
                  stored.tenantId,
              );

            const business =
              tenant?.businesses.find(
                (item) =>
                  item.id ===
                  stored.businessId,
              );

            if (
              tenant &&
              business
            ) {
              onBusinessResolved?.(
                business.name,
              );

              setReady(true);

              return;
            }

            clearSelectedContext();
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
            const defaultContext =
              data.default_context;

            setSelectedContext({
              type: "TENANT",
              tenantId:
                defaultContext.tenant_id,
              businessId:
                defaultContext.business_id,
            });

            const tenant =
              data.access.tenants.find(
                (item) =>
                  item.id ===
                  defaultContext.tenant_id,
              );

            const business =
              tenant?.businesses.find(
                (item) =>
                  item.id ===
                  defaultContext.business_id,
              );

            if (business) {
              onBusinessResolved?.(
                business.name,
              );
            }

            setReady(true);

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

          setError(
            new Error(
              "Usaha aktif belum dapat ditentukan.",
            ),
          );
        } catch (caught) {
          if (
            caught instanceof
              ApiClientError &&
            caught.status === 401
          ) {
            clearSelectedContext();

            router.replace(
              "/login",
            );

            return;
          }

          setError(caught);
        }
      },
      [
        onBusinessResolved,
        router,
      ],
    );

  useEffect(() => {
    /*
     * Jalankan bootstrap pada microtask berikutnya
     * agar state update tidak terjadi sinkron
     * di body effect.
     */
    window.queueMicrotask(
      () => {
        void bootstrap();
      },
    );
  }, [bootstrap]);

  if (ready) {
    return children;
  }

  if (error) {
    return (
      <div
        className={
          styles.state
        }
      >
        <ActionFeedback
          tone="error"
          title="Aplikasi belum dapat disiapkan"
          message={
            apiErrorMessage(
              error,
              "Konteks usaha belum dapat dimuat. Silakan coba lagi.",
            )
          }
          requestId={
            apiRequestId(
              error,
            )
          }
        />

        <Button
          type="button"
          variant="secondary"
          onClick={() =>
            void bootstrap()
          }
        >
          Coba Lagi
        </Button>
      </div>
    );
  }

  return (
    <div
      className={
        styles.loading
      }
      role="status"
      aria-live="polite"
    >
      <span
        className={
          styles.spinner
        }
      />

      <div>
        <strong>
          Menyiapkan SIGNOVA
        </strong>

        <p>
          Memeriksa sesi dan usaha aktif...
        </p>
      </div>
    </div>
  );
}
