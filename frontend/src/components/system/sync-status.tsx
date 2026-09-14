"use client";

import {
  CloudOff,
  CloudUpload,
  RefreshCw,
  Wifi,
} from "lucide-react";
import {
  useEffect,
  useState,
} from "react";

import styles from "./sync-status.module.css";

type NetworkState =
  | "online"
  | "offline"
  | "syncing";

export function SyncStatus() {
  const [state, setState] =
    useState<NetworkState>(() =>
      typeof navigator !== "undefined" &&
      !navigator.onLine
        ? "offline"
        : "online",
    );

  useEffect(() => {
    function handleOnline() {
      setState("syncing");

      window.setTimeout(() => {
        setState("online");
      }, 900);
    }

    function handleOffline() {
      setState("offline");
    }

    window.addEventListener(
      "online",
      handleOnline,
    );

    window.addEventListener(
      "offline",
      handleOffline,
    );

    return () => {
      window.removeEventListener(
        "online",
        handleOnline,
      );

      window.removeEventListener(
        "offline",
        handleOffline,
      );
    };
  }, []);

  if (state === "offline") {
    return (
      <div
        className={styles.offline}
        title="Aplikasi sedang dalam mode offline"
      >
        <CloudOff size={15} />
        <span>Offline</span>
      </div>
    );
  }

  if (state === "syncing") {
    return (
      <div
        className={styles.syncing}
        title="Menyinkronkan data"
      >
        <RefreshCw
          className={styles.spinning}
          size={15}
        />
        <span>Sinkron</span>
      </div>
    );
  }

  return (
    <div
      className={styles.online}
      title="Terhubung ke SIGNOVA"
    >
      <Wifi size={15} />
      <span>Online</span>
      <CloudUpload
        className={styles.cloud}
        size={14}
      />
    </div>
  );
}
