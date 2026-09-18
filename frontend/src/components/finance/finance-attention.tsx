"use client";

import Link from "next/link";

import {
  ArrowRight,
  TriangleAlert,
} from "lucide-react";

import {
  useEffect,
  useState,
} from "react";

import {
  listPayments,
} from "@/lib/payment/service";

import styles from "./finance-attention.module.css";


function countLabel(
  value: number,
): string {
  return value > 99
    ? "99+"
    : String(value);
}


function paymentCopy(
  value: number,
): string {
  return value === 1
    ? "Ada 1 pembayaran pelanggan yang menunggu konfirmasi."
    : `Ada ${value} pembayaran pelanggan yang menunggu konfirmasi.`;
}


export function FinanceAttention() {
  const [
    pendingCount,
    setPendingCount,
  ] = useState(0);

  const [
    loaded,
    setLoaded,
  ] = useState(false);


  useEffect(() => {
    let active = true;

    async function load() {
      try {
        const response =
          await listPayments({
            status: "PENDING",
            page: 1,
            per_page: 1,
          });

        if (!active) {
          return;
        }

        setPendingCount(
          response.meta.total,
        );
      } catch {
        /*
         * Attention bersifat read model tambahan.
         * Bila gagal dimuat, Finance Hub utama
         * tetap boleh digunakan.
         */
      } finally {
        if (active) {
          setLoaded(true);
        }
      }
    }

    void load();

    return () => {
      active = false;
    };
  }, []);


  if (!loaded) {
    return null;
  }

  const hasPending =
    pendingCount > 0;


  return (
    <section
      className={styles.section}
      data-state={
        hasPending
          ? "warning"
          : "clear"
      }
      aria-label={
        hasPending
          ? "Perlu perhatian"
          : "Konfirmasi pembayaran"
      }
    >
      <header
        className={
          styles.header
        }
      >
        <span
          className={
            styles.alertIcon
          }
        >
          <TriangleAlert
            size={20}
            strokeWidth={2}
          />
        </span>

        <div>
          <span
            className={
              styles.eyebrow
            }
          >
            {hasPending
              ? "PERLU PERHATIAN"
              : "KONFIRMASI PEMBAYARAN"}
          </span>

          <h2>
            {hasPending
              ? "Ada yang perlu ditindaklanjuti"
              : "Pembayaran pelanggan"}
          </h2>

          <p>
            {hasPending
              ? paymentCopy(
                  pendingCount,
                )
              : "Tidak ada pembayaran yang menunggu konfirmasi saat ini."}
          </p>
        </div>
      </header>


      <Link
        href="/app/pembayaran"
        className={
          styles.paymentCard
        }
      >
        <span
          className={
            styles.cardCopy
          }
        >
          <span
            className={
              styles.cardHeading
            }
          >
            <strong>
              Konfirmasi Pembayaran
            </strong>

            {hasPending ? (
              <span
                className={
                  styles.badge
                }
                aria-label={`${pendingCount} pembayaran menunggu konfirmasi`}
              >
                {countLabel(
                  pendingCount,
                )}
              </span>
            ) : null}
          </span>

          <span
            className={
              styles.description
            }
          >
            Periksa bukti
            pembayaran pelanggan,
            lalu verifikasi atau
            tolak sesuai hasil
            pengecekan.
          </span>

          <span
            className={
              styles.action
            }
          >
            {hasPending
              ? "Periksa Sekarang"
              : "Buka Pembayaran"}

            <ArrowRight
              size={16}
              strokeWidth={2}
            />
          </span>
        </span>
      </Link>
    </section>
  );
}
