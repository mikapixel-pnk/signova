"use client";

import {
  Eye,
  MoreVertical,
  Pencil,
  Trash2,
} from "lucide-react";

import Link from "next/link";

import {
  useEffect,
  useRef,
  useState,
} from "react";

import styles from "./customer-actions-menu.module.css";

type CustomerActionsMenuProps = {
  customerId: string;
};

export function CustomerActionsMenu({
  customerId,
}: CustomerActionsMenuProps) {
  const [
    open,
    setOpen,
  ] = useState(false);

  const containerRef =
    useRef<HTMLDivElement>(
      null,
    );

  useEffect(() => {
    function handlePointerDown(
      event: PointerEvent,
    ) {
      if (
        containerRef.current &&
        !containerRef.current.contains(
          event.target as Node,
        )
      ) {
        setOpen(false);
      }
    }

    function handleKeyDown(
      event: KeyboardEvent,
    ) {
      if (
        event.key === "Escape"
      ) {
        setOpen(false);
      }
    }

    document.addEventListener(
      "pointerdown",
      handlePointerDown,
    );

    document.addEventListener(
      "keydown",
      handleKeyDown,
    );

    return () => {
      document.removeEventListener(
        "pointerdown",
        handlePointerDown,
      );

      document.removeEventListener(
        "keydown",
        handleKeyDown,
      );
    };
  }, []);

  return (
    <div
      ref={containerRef}
      className={
        styles.root
      }
    >
      <button
        type="button"
        className={
          styles.trigger
        }
        aria-label="Buka aksi pelanggan"
        aria-haspopup="menu"
        aria-expanded={open}
        onClick={() =>
          setOpen(
            (current) =>
              !current,
          )
        }
      >
        <MoreVertical
          size={18}
          strokeWidth={2}
        />
      </button>

      {open ? (
        <div
          className={
            styles.menu
          }
          role="menu"
        >
          <Link
            href={
              `/app/pelanggan/${customerId}`
            }
            className={
              styles.item
            }
            role="menuitem"
            onClick={() =>
              setOpen(false)
            }
          >
            <Eye
              size={16}
            />

            <span>
              Lihat
            </span>
          </Link>

          <Link
            href={
              `/app/pelanggan/${customerId}/ubah`
            }
            className={
              styles.item
            }
            role="menuitem"
            onClick={() =>
              setOpen(false)
            }
          >
            <Pencil
              size={16}
            />

            <span>
              Ubah
            </span>
          </Link>

          <div
            className={
              styles.separator
            }
          />

          <button
            type="button"
            className={
              styles.dangerItemDisabled
            }
            role="menuitem"
            disabled
            title="Penghapusan akan diaktifkan setelah pemeriksaan riwayat aktivitas tersedia."
          >
            <Trash2
              size={16}
            />

            <span>
              Hapus
            </span>

            <small>
              Segera
            </small>
          </button>
        </div>
      ) : null}
    </div>
  );
}
