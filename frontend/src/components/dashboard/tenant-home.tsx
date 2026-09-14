"use client";

import {
  ArrowDownRight,
  ArrowRight,
  ArrowUpRight,
  FilePlus2,
  Plus,
  ReceiptText,
  UserPlus,
  WalletCards,
} from "lucide-react";

import {
  useEffect,
  useState,
} from "react";

import {
  getSelectedContext,
} from "@/lib/auth/active-context";

import {
  getAuthContext,
} from "@/lib/auth/context";

import styles from "./tenant-home.module.css";

const summaryItems = [
  {
    label: "Piutang",
    value: "Rp0",
    note: "Belum ada tagihan berjalan",
    icon: ReceiptText,
  },
  {
    label: "Kas & Bank",
    value: "Rp0",
    note: "Saldo tercatat",
    icon: WalletCards,
  },
  {
    label: "Pemasukan",
    value: "Rp0",
    note: "Bulan ini",
    icon: ArrowUpRight,
  },
  {
    label: "Pengeluaran",
    value: "Rp0",
    note: "Bulan ini",
    icon: ArrowDownRight,
  },
];

const quickActions = [
  {
    label: "Buat Tagihan",
    icon: FilePlus2,
  },
  {
    label: "Tambah Pelanggan",
    icon: UserPlus,
  },
  {
    label: "Catat Pemasukan",
    icon: Plus,
  },
  {
    label: "Catat Pengeluaran",
    icon: ArrowDownRight,
  },
];

function firstName(
  value:
    | string
    | null
    | undefined,
): string | null {
  const normalized =
    value?.trim();

  if (!normalized) {
    return null;
  }

  return (
    normalized.split(/\s+/)[0] ??
    null
  );
}

export function TenantHome() {
  const [
    userName,
    setUserName,
  ] = useState<
    string | null
  >(null);

  const [
    tenantName,
    setTenantName,
  ] = useState<
    string | null
  >(null);

  useEffect(() => {
    let cancelled =
      false;

    void getAuthContext()
      .then((response) => {
        if (cancelled) {
          return;
        }

        const data =
          response.data;

        setUserName(
          firstName(
            data.user.name,
          ),
        );

        const selected =
          getSelectedContext();

        if (
          selected?.type ===
          "TENANT"
        ) {
          const tenant =
            data.access.tenants.find(
              (item) =>
                item.id ===
                selected.tenantId,
            );

          if (tenant) {
            setTenantName(
              tenant.name,
            );

            return;
          }
        }

        if (
          data.default_context
            ?.type ===
          "TENANT"
        ) {
          const tenant =
            data.access.tenants.find(
              (item) =>
                item.id ===
                data.default_context
                  ?.tenant_id,
            );

          if (tenant) {
            setTenantName(
              tenant.name,
            );
          }
        }
      })
      .catch(() => {
        /*
         * Session gate adalah sumber
         * otorisasi utama.
         *
         * Sapaan Beranda bersifat
         * enhancement visual saja.
         */
      });

    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <div className={styles.page}>
      <section className={styles.hero}>
        <div>
          <p className={styles.eyebrow}>
            Beranda
          </p>

          <p className={styles.greeting}>
            {userName
              ? `Halo, ${userName}`
              : "Halo"}
          </p>

          <h1 className={styles.title}>
            Selamat datang di SIGNOVA
          </h1>

          <p className={styles.description}>
            {tenantName
              ? (
                  <>
                    Pantau kondisi{" "}
                    <strong>
                      {tenantName}
                    </strong>{" "}
                    dan lanjutkan pekerjaan
                    penting dari satu tempat.
                  </>
                )
              : (
                  <>
                    Pantau kondisi usaha dan
                    lanjutkan pekerjaan penting
                    dari satu tempat.
                  </>
                )}
          </p>
        </div>

        <button
          className={styles.primaryAction}
          type="button"
        >
          <FilePlus2 size={18} />
          Buat Tagihan
        </button>
      </section>

      <section>
        <div className={styles.sectionHeading}>
          <div>
            <h2>Ringkasan Usaha</h2>
            <p>Kondisi usaha Anda saat ini.</p>
          </div>
        </div>

        <div className={styles.summaryGrid}>
          {summaryItems.map((item) => {
            const Icon = item.icon;

            return (
              <article
                key={item.label}
                className={styles.summaryCard}
              >
                <div className={styles.summaryIcon}>
                  <Icon size={19} />
                </div>

                <span className={styles.summaryLabel}>
                  {item.label}
                </span>

                <strong className={styles.summaryValue}>
                  {item.value}
                </strong>

                <span className={styles.summaryNote}>
                  {item.note}
                </span>
              </article>
            );
          })}
        </div>
      </section>

      <section>
        <div className={styles.sectionHeading}>
          <div>
            <h2>Aksi Cepat</h2>
            <p>
              Kerjakan aktivitas yang paling
              sering digunakan.
            </p>
          </div>
        </div>

        <div className={styles.quickGrid}>
          {quickActions.map((item) => {
            const Icon = item.icon;

            return (
              <button
                key={item.label}
                className={styles.quickAction}
                type="button"
              >
                <span className={styles.quickIcon}>
                  <Icon size={19} />
                </span>

                <span>{item.label}</span>

                <ArrowRight
                  className={styles.quickArrow}
                  size={17}
                />
              </button>
            );
          })}
        </div>
      </section>

      <section className={styles.activityCard}>
        <div className={styles.sectionHeading}>
          <div>
            <h2>Aktivitas Terbaru</h2>
            <p>
              Perubahan terakhir dalam usaha Anda.
            </p>
          </div>
        </div>

        <div className={styles.emptyState}>
          <div className={styles.emptyIcon}>
            <ReceiptText size={24} />
          </div>

          <strong>Belum ada aktivitas</strong>

          <p>
            Mulai dengan membuat pelanggan atau
            tagihan pertama Anda.
          </p>
        </div>
      </section>
    </div>
  );
}
