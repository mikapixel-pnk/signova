"use client";

import {
  CalendarDays,
  ClipboardList,
  FileText,
  Plus,
  Search,
  WalletCards,
} from "lucide-react";

import Link from "next/link";

import {
  useEffect,
  useMemo,
  useState,
} from "react";

import {
  ActionFeedback,
} from "@/components/feedback/action-feedback";

import {
  ModuleHero,
} from "@/components/module/module-hero";

import {
  apiErrorMessage,
  apiRequestId,
} from "@/lib/api/error-message";

import {
  getActiveCapabilities,
} from "@/lib/auth/active-capabilities-service";

import {
  getModule,
} from "@/lib/module/registry";

import {
  formatPurchaseDate,
  formatPurchaseMoney,
  purchaseRequestStatusLabel,
} from "@/lib/purchasing/labels";

import {
  listPurchaseRequests,
} from "@/lib/purchasing/service";

import type {
  PurchaseRequest,
  PurchaseRequestStatus,
} from "@/types/purchasing";

import styles from "./purchase-request.module.css";


type StatusFilter =
  | "ALL"
  | PurchaseRequestStatus;


const statusOptions:
  readonly [
    StatusFilter,
    string,
  ][] = [
  ["ALL", "Semua"],
  ["DRAFT", "Draf"],
  ["SUBMITTED", "Diajukan"],
  ["APPROVED", "Disetujui"],
  ["REJECTED", "Ditolak"],
  ["CANCELLED", "Dibatalkan"],
];


export function PurchaseRequestList() {
  const moduleDef =
    getModule(
      "purchase-requests",
    );

  const ModuleIcon =
    moduleDef.icon;

  const [
    rows,
    setRows,
  ] = useState<
    PurchaseRequest[]
  >([]);

  const [
    search,
    setSearch,
  ] = useState("");

  const [
    status,
    setStatus,
  ] = useState<StatusFilter>(
    "ALL",
  );

  const [
    total,
    setTotal,
  ] = useState(0);

  const [
    loading,
    setLoading,
  ] = useState(true);

  const [
    error,
    setError,
  ] = useState<unknown>(null);

  const [
    canManage,
    setCanManage,
  ] = useState(false);

  const effectiveStatus =
    useMemo(
      () =>
        status === "ALL"
          ? undefined
          : status,
      [status],
    );

  useEffect(() => {
    let cancelled = false;

    void getActiveCapabilities()
      .then((response) => {
        if (cancelled) {
          return;
        }

        setCanManage(
          response.data
            .capability_codes
            .includes(
              "purchasing.request",
            ),
        );
      })
      .catch(() => {
        if (!cancelled) {
          setCanManage(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, []);

  useEffect(() => {
    let cancelled = false;

    const timer =
      window.setTimeout(
        () => {
          setLoading(true);
          setError(null);

          void listPurchaseRequests({
            search,
            status:
              effectiveStatus,
            page: 1,
            perPage: 50,
          })
            .then((response) => {
              if (cancelled) {
                return;
              }

              setRows(
                response.data,
              );

              setTotal(
                response.meta.total,
              );
            })
            .catch((caught) => {
              if (!cancelled) {
                setError(caught);
              }
            })
            .finally(() => {
              if (!cancelled) {
                setLoading(false);
              }
            });
        },
        280,
      );

    return () => {
      cancelled = true;

      window.clearTimeout(
        timer,
      );
    };
  }, [
    search,
    effectiveStatus,
  ]);

  return (
    <section
      className={styles.page}
    >
      <ModuleHero
        eyebrow="Operasional"
        title={moduleDef.label}
        description={
          moduleDef.description
        }
        icon={ModuleIcon}
        tone={moduleDef.tone}
        insightTitle={
          moduleDef.insight.title
        }
        insightDescription={
          moduleDef.insight
            .description
        }
        actions={
          canManage ? (
            <Link
              href="/app/operasional/permintaan-pembelian/tambah"
              className={
                styles.primaryLink
              }
            >
              <Plus size={18} />
              Buat Permintaan
            </Link>
          ) : undefined
        }
      />

      <section
        className={styles.toolbar}
      >
        <label
          className={
            styles.searchBox
          }
        >
          <Search
            size={18}
            strokeWidth={1.9}
          />

          <input
            type="search"
            value={search}
            onChange={(event) =>
              setSearch(
                event.target.value,
              )
            }
            placeholder="Cari nomor atau catatan permintaan..."
            aria-label="Cari Permintaan Pembelian"
          />
        </label>

        <div
          className={styles.filters}
        >
          {statusOptions.map(
            ([
              value,
              label,
            ]) => (
              <button
                key={value}
                type="button"
                className={
                  status === value
                    ? styles.filterActive
                    : styles.filter
                }
                onClick={() =>
                  setStatus(value)
                }
              >
                {label}
              </button>
            ),
          )}
        </div>
      </section>

      <div
        className={
          styles.resultMeta
        }
      >
        {loading
          ? "Memuat Permintaan Pembelian..."
          : `${total} permintaan`}
      </div>

      {error ? (
        <ActionFeedback
          tone="error"
          title="Permintaan Pembelian belum dapat dimuat"
          message={
            apiErrorMessage(
              error,
              "Data Permintaan Pembelian belum berhasil dimuat.",
            )
          }
          requestId={
            apiRequestId(
              error,
            )
          }
        />
      ) : null}

      {!error &&
      !loading &&
      rows.length === 0 ? (
        <section
          className={styles.empty}
        >
          <ClipboardList
            size={34}
            strokeWidth={1.7}
          />

          <h2>
            Belum ada Permintaan Pembelian
          </h2>

          <p>
            Catat kebutuhan barang
            atau jasa sebelum proses
            pembelian dilanjutkan.
          </p>

          {canManage ? (
            <Link
              href="/app/operasional/permintaan-pembelian/tambah"
              className={
                styles.primaryLink
              }
            >
              <Plus size={18} />
              Buat Permintaan
            </Link>
          ) : null}
        </section>
      ) : null}

      {!error &&
      rows.length > 0 ? (
        <div
          className={styles.grid}
        >
          {rows.map(
            (row) => (
              <Link
                key={row.id}
                href={
                  `/app/operasional/permintaan-pembelian/${row.id}`
                }
                className={
                  styles.listCard
                }
              >
                <div
                  className={
                    styles.cardHeader
                  }
                >
                  <span
                    className={
                      styles.cardIcon
                    }
                  >
                    <FileText
                      size={20}
                    />
                  </span>

                  <div
                    className={
                      styles.cardTitle
                    }
                  >
                    <strong>
                      {row.request_number}
                    </strong>

                    <span>
                      {row.notes?.trim() ||
                        "Tanpa catatan"}
                    </span>
                  </div>

                  <span
                    className={
                      styles.statusBadge
                    }
                    data-status={
                      row.status
                    }
                  >
                    {purchaseRequestStatusLabel(
                      row.status,
                    )}
                  </span>
                </div>

                <div
                  className={
                    styles.cardDetails
                  }
                >
                  <span>
                    <CalendarDays
                      size={15}
                    />
                    Dibutuhkan{" "}
                    {formatPurchaseDate(
                      row.needed_at,
                    )}
                  </span>

                  <span>
                    <WalletCards
                      size={15}
                    />
                    {formatPurchaseMoney(
                      row.estimated_total,
                    )}
                  </span>
                </div>
              </Link>
            ),
          )}
        </div>
      ) : null}
    </section>
  );
}
