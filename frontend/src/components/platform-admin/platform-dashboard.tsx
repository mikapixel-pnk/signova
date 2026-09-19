"use client";

import {
  ShieldCheck,
} from "lucide-react";

import {
  usePlatformAccess,
} from "./platform-session-gate";

import styles from "./platform-admin.module.css";

export function PlatformDashboard() {
  const {
    user,
    capabilities,
  } =
    usePlatformAccess();

  return (
    <div
      className={
        styles.pageStack
      }
    >
      <section
        className={
          styles.hero
        }
      >
        <div>
          <span
            className={
              styles.eyebrow
            }
          >
            Control Plane SIGNOVA
          </span>

          <h2>
            Selamat datang,
            {" "}
            {user.name}
          </h2>

          <p>
            Kelola platform SaaS
            SIGNOVA dari satu panel
            yang terpisah dari
            operasional tenant.
          </p>
        </div>

        <div
          className={
            styles.heroIcon
          }
        >
          <ShieldCheck
            size={30}
          />
        </div>
      </section>

      <section
        className={
          styles.summaryGrid
        }
      >
        <article
          className={
            styles.summaryCard
          }
        >
          <span>
            Konteks aktif
          </span>

          <strong>
            Platform
          </strong>

          <small>
            Tanpa TenantContext dan
            BusinessContext.
          </small>
        </article>

        <article
          className={
            styles.summaryCard
          }
        >
          <span>
            Hak akses aktif
          </span>

          <strong>
            {capabilities.length}
          </strong>

          <small>
            Capability platform pada
            akun ini.
          </small>
        </article>

        <article
          className={
            styles.summaryCard
          }
        >
          <span>
            Status panel
          </span>

          <strong>
            Aktif
          </strong>

          <small>
            Session platform berhasil
            diverifikasi.
          </small>
        </article>
      </section>

      <section
        className={
          styles.infoPanel
        }
      >
        <h3>
          Platform Super Admin
        </h3>

        <p>
          Modul control plane akan
          diaktifkan bertahap sesuai
          capability dan kontrak API
          SIGNOVA. Data operasional
          tenant tidak dibuka langsung
          dari halaman ini.
        </p>
      </section>
    </div>
  );
}
