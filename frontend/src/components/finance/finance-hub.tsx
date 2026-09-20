"use client";

import Link from "next/link";

import {
  useEffect,
  useState,
} from "react";

import {
  ChevronRight,
} from "lucide-react";

import {
  FinanceAttention,
} from "@/components/finance/finance-attention";

import {
  FinanceSummaryPanel,
} from "@/components/finance/finance-summary";

import {
  ModuleHero,
} from "@/components/module/module-hero";

import type {
  ModuleDefinition,
} from "@/config/module-types";

import {
  getActiveCapabilities,
} from "@/lib/auth/active-capabilities-service";

import {
  getModule,
} from "@/lib/module/registry";

import styles from "./finance-hub.module.css";


function FinanceCard({
  moduleDef,
}: {
  moduleDef: ModuleDefinition;
}) {
  const Icon =
    moduleDef.icon;

  return (
    <Link
      href={moduleDef.href}
      className={styles.moduleCard}
      data-tone={moduleDef.tone}
    >
      <span
        className={styles.moduleIcon}
      >
        <Icon
          size={21}
          strokeWidth={1.9}
        />
      </span>

      <span
        className={styles.moduleCopy}
      >
        <strong>
          {moduleDef.label}
        </strong>

        <span>
          {moduleDef.description}
        </span>
      </span>

      <ChevronRight
        className={
          styles.moduleArrow
        }
        size={18}
        strokeWidth={1.9}
      />
    </Link>
  );
}


export function FinanceHub() {
  const [
    canViewPayables,
    setCanViewPayables,
  ] = useState(false);

  useEffect(() => {
    let cancelled = false;

    void getActiveCapabilities()
      .then((response) => {
        if (cancelled) {
          return;
        }

        setCanViewPayables(
          response.data
            .capability_codes
            .includes(
              "finance.payable.view",
            ),
        );
      })
      .catch(() => {
        if (!cancelled) {
          setCanViewPayables(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, []);

  const finance =
    getModule("finance");

  const income =
    getModule("income");

  const expense =
    getModule("expense");

  const cashBank =
    getModule("cash-bank");

  const receivables =
    getModule("receivables");

  const payables =
    getModule("payables");

  const FinanceIcon =
    finance.icon;

  return (
    <section
      className={styles.page}
    >
      <ModuleHero
        eyebrow="Keuangan"
        title={finance.label}
        description={
          finance.description
        }
        icon={FinanceIcon}
        tone="green"
        insightTitle={
          finance.insight.title
        }
        insightDescription={
          finance.insight
            .description
        }
      />

      <FinanceSummaryPanel />

      <FinanceAttention />

      <section
        className={styles.section}
      >
        <header
          className={
            styles.sectionHeader
          }
        >
          <div>
            <span
              className={
                styles.eyebrow
              }
            >
              TRANSAKSI
            </span>

            <h2>
              Arus uang usaha
            </h2>

            <p>
              Pantau uang yang masuk
              dan keluar dari usaha.
            </p>
          </div>
        </header>

        <div
          className={
            styles.moduleGrid
          }
        >
          <FinanceCard
            moduleDef={income}
          />

          <FinanceCard
            moduleDef={expense}
          />
        </div>
      </section>

      <section
        className={styles.section}
      >
        <header
          className={
            styles.sectionHeader
          }
        >
          <div>
            <span
              className={
                styles.eyebrow
              }
            >
              POSISI KEUANGAN
            </span>

            <h2>
              Posisi uang, piutang & utang
            </h2>

            <p>
              Ketahui posisi Kas &
              Bank, tagihan pelanggan,
              serta kewajiban kepada
              pemasok.
            </p>
          </div>
        </header>

        <div
          className={
            styles.moduleGrid
          }
        >
          <FinanceCard
            moduleDef={cashBank}
          />

          <FinanceCard
            moduleDef={
              receivables
            }
          />

          {canViewPayables ? (
            <FinanceCard
              moduleDef={payables}
            />
          ) : null}
        </div>
      </section>
    </section>
  );
}
