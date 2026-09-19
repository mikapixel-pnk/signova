"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";

import {
  useRouter,
} from "next/navigation";

import {
  ApiClientError,
} from "@/lib/api/types";

import {
  clearSelectedContext,
  setSelectedContext,
} from "@/lib/auth/active-context";

import {
  getAuthContext,
} from "@/lib/auth/context";

import type {
  AuthUser,
} from "@/types/auth";

import styles from "./platform-admin.module.css";

type PlatformAccessState = {
  user: AuthUser;
  capabilities: string[];
};

const PlatformAccessContext =
  createContext<
    PlatformAccessState | null
  >(null);

export function usePlatformAccess():
  PlatformAccessState {
  const context =
    useContext(
      PlatformAccessContext,
    );

  if (!context) {
    throw new Error(
      "Platform access context belum tersedia.",
    );
  }

  return context;
}

type PlatformSessionGateProps = {
  children: ReactNode;
};

export function PlatformSessionGate({
  children,
}: PlatformSessionGateProps) {
  const router =
    useRouter();

  const [
    access,
    setAccess,
  ] =
    useState<
      PlatformAccessState | null
    >(null);

  const [
    error,
    setError,
  ] =
    useState<string | null>(
      null,
    );

  const bootstrap =
    useCallback(
      async (
        force = false,
      ) => {
        setError(null);

        try {
          const response =
            await getAuthContext(
              force,
            );

          const data =
            response.data;

          if (
            !data.has_access ||
            !data.access.platform
              .available
          ) {
            clearSelectedContext();

            router.replace("/");

            return;
          }

          setSelectedContext({
            type: "PLATFORM",
            tenantId: null,
            businessId: null,
          });

          setAccess({
            user: data.user,
            capabilities:
              data.access.platform
                .capabilities,
          });
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

          setError(
            "Panel Super Admin belum dapat disiapkan. Silakan coba lagi.",
          );
        }
      },
      [router],
    );

  useEffect(() => {
    window.queueMicrotask(
      () => {
        void bootstrap();
      },
    );
  }, [bootstrap]);

  const value =
    useMemo(
      () => access,
      [access],
    );

  if (error) {
    return (
      <main
        className={
          styles.gateState
        }
      >
        <div
          className={
            styles.gateCard
          }
        >
          <span
            className={
              styles.eyebrow
            }
          >
            SIGNOVA Platform
          </span>

          <h1>
            Panel belum dapat dimuat
          </h1>

          <p>{error}</p>

          <button
            type="button"
            className={
              styles.primaryButton
            }
            onClick={() =>
              void bootstrap(true)
            }
          >
            Coba lagi
          </button>
        </div>
      </main>
    );
  }

  if (!value) {
    return (
      <main
        className={
          styles.gateState
        }
        aria-busy="true"
      >
        <div
          className={
            styles.loadingCard
          }
        >
          <span
            className={
              styles.loadingDot
            }
          />

          <span>
            Memeriksa akses
            Platform SIGNOVA…
          </span>
        </div>
      </main>
    );
  }

  return (
    <PlatformAccessContext.Provider
      value={value}
    >
      {children}
    </PlatformAccessContext.Provider>
  );
}
