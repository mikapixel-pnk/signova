"use client";

import {
  CalendarDays,
  ClipboardList,
  FileText,
  ReceiptText,
  WalletCards,
} from "lucide-react";

import Link from "next/link";

import {
  useEffect,
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
  purchaseOrderStatusLabel,
} from "@/lib/purchasing/labels";

import {
  listPurchaseOrders,
} from "@/lib/purchasing/service";

import type {
  PurchaseOrder,
} from "@/types/purchasing";

import styles from "./purchase-order.module.css";


export function PurchaseOrderList() {
  const moduleDef =
    getModule(
      "purchase-orders",
    );

  const ModuleIcon =
    moduleDef.icon;

  const [
    rows,
    setRows,
  ] = useState<
    PurchaseOrder[]
  >([]);

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
    canAccess,
    setCanAccess,
  ] = useState<
    boolean | null
  >(null);


  useEffect(() => {
    let cancelled = false;

    void getActiveCapabilities()
      .then((response) => {
        if (cancelled) {
          return;
        }

        setCanAccess(
          response.data
            .capability_codes
            .includes(
              "purchasing.create_po",
            ),
        );
      })
      .catch((caught) => {
        if (!cancelled) {
          setCanAccess(false);
          setError(caught);
          setLoading(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, []);


  useEffect(() => {
    if (canAccess !== true) {
      return;
    }

    let cancelled = false;

    void listPurchaseOrders()
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

    return () => {
      cancelled = true;
    };
  }, [
    canAccess,
  ]);


  return (
    <section
      className={
        styles.page
      }
    >
      <ModuleHero
        eyebrow="Operasional"
        title={
          moduleDef.label
        }
        description={
          moduleDef.description
        }
        icon={
          ModuleIcon
        }
        tone={
          moduleDef.tone
        }
        insightTitle="Alur Pembelian"
        insightDescription="Pesanan menjadi acuan resmi sebelum barang atau jasa dicatat sebagai diterima."
      />

      {canAccess === false ? (
        <ActionFeedback
          tone="warning"
          title="Akses terbatas"
          message="Anda tidak memiliki hak untuk mengelola Pesanan Pembelian."
        />
      ) : null}

      {error &&
      canAccess !== false ? (
        <ActionFeedback
          tone="error"
          title="Pesanan Pembelian belum dapat dimuat"
          message={
            apiErrorMessage(
              error,
              "Data Pesanan Pembelian belum berhasil dimuat.",
            )
          }
          requestId={
            apiRequestId(
              error,
            )
          }
        />
      ) : null}

      {canAccess === true ? (
        <>
          <section
            className={
              styles.summaryCard
            }
          >
            <div>
              <span>
                PESANAN PEMBELIAN
              </span>

              <strong>
                {loading
                  ? "Memuat..."
                  : `${total} pesanan`}
              </strong>
            </div>

            <p>
              Pantau pesanan resmi ke pemasok
              sebelum dilanjutkan ke proses
              penerimaan.
            </p>
          </section>

          {!error &&
          !loading &&
          rows.length === 0 ? (
            <section
              className={
                styles.empty
              }
            >
              <ReceiptText
                size={36}
                strokeWidth={1.7}
              />

              <h2>
                Belum ada Pesanan Pembelian
              </h2>

              <p>
                Mulai dari Permintaan Pembelian
                yang sudah disetujui agar
                kebutuhan tetap dapat ditelusuri.
              </p>

              <Link
                href="/app/operasional/permintaan-pembelian"
                className={
                  styles.primaryLink
                }
              >
                <ClipboardList
                  size={18}
                />
                Lihat Permintaan Pembelian
              </Link>
            </section>
          ) : null}

          {!error &&
          rows.length > 0 ? (
            <div
              className={
                styles.grid
              }
            >
              {rows.map(
                (row) => (
                  <article
                    key={row.id}
                    className={
                      styles.card
                    }
                  >
                    <header
                      className={
                        styles.cardHeader
                      }
                    >
                      <div
                        className={
                          styles.cardIcon
                        }
                      >
                        <ReceiptText
                          size={20}
                        />
                      </div>

                      <div
                        className={
                          styles.cardTitle
                        }
                      >
                        <strong>
                          {row.order_number}
                        </strong>

                        <span>
                          {row.supplier?.name ??
                            "Pemasok belum tersedia"}
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
                        {purchaseOrderStatusLabel(
                          row.status,
                        )}
                      </span>
                    </header>

                    <div
                      className={
                        styles.cardDetails
                      }
                    >
                      <span>
                        <WalletCards
                          size={15}
                        />

                        {formatPurchaseMoney(
                          row.total,
                        )}
                      </span>

                      <span>
                        <CalendarDays
                          size={15}
                        />

                        Perkiraan diterima:{" "}
                        {formatPurchaseDate(
                          row.expected_at,
                        )}
                      </span>

                      {row
                        .source_purchase_request
                        ?.request_number ? (
                        <span>
                          <FileText
                            size={15}
                          />

                          Dari{" "}
                          {
                            row
                              .source_purchase_request
                              .request_number
                          }
                        </span>
                      ) : (
                        <span>
                          <FileText
                            size={15}
                          />

                          Pesanan langsung
                        </span>
                      )}
                    </div>

                    <div
                      className={
                        styles.cardActions
                      }
                    >
                      <Link
                        href={
                          `/app/operasional/pesanan-pembelian/${row.id}`
                        }
                        className={
                          styles.primaryLink
                        }
                      >
                        Buka Pesanan
                      </Link>

                      {row
                        .source_purchase_request_id ? (
                        <Link
                          href={
                            `/app/operasional/permintaan-pembelian/${row.source_purchase_request_id}`
                          }
                          className={
                            styles.secondaryLink
                          }
                        >
                          Lihat Permintaan Asal
                        </Link>
                      ) : null}
                    </div>
                  </article>
                ),
              )}
            </div>
          ) : null}

          {!error &&
          !loading &&
          rows.length > 0 &&
          rows.length < total ? (
            <p
              className={
                styles.paginationNote
              }
            >
              Menampilkan {rows.length} dari{" "}
              {total} pesanan. Navigasi halaman
              akan ditambahkan bersama penyempurnaan
              daftar Pesanan Pembelian.
            </p>
          ) : null}
        </>
      ) : null}
    </section>
  );
}
