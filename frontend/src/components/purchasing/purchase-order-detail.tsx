"use client";

import {
  ArrowLeft,
  CalendarDays,
  CheckCircle2,
  ClipboardCheck,
  FileText,
  Pencil,
  ReceiptText,
  WalletCards,
  XCircle,
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
  Button,
} from "@/components/ui/button";

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
  procurementTypeLabel,
  purchaseOrderStatusDescription,
  purchaseOrderStatusLabel,
} from "@/lib/purchasing/labels";

import {
  cancelPurchaseOrder,
  getPurchaseOrder,
  issuePurchaseOrder,
} from "@/lib/purchasing/service";

import type {
  PurchaseOrder,
} from "@/types/purchasing";

import {
  formatDecimalDisplay,
} from "@/lib/format/decimal";

import styles from "./purchase-order.module.css";


type PurchaseOrderDetailProps = {
  purchaseOrderId: string;
};


export function PurchaseOrderDetail({
  purchaseOrderId,
}: PurchaseOrderDetailProps) {
  const moduleDef =
    getModule(
      "purchase-orders",
    );

  const [
    data,
    setData,
  ] = useState<
    PurchaseOrder | null
  >(null);

  const [
    loading,
    setLoading,
  ] = useState(true);

  const [
    error,
    setError,
  ] = useState<unknown>(
    null,
  );

  const [
    feedback,
    setFeedback,
  ] = useState<
    string | null
  >(null);

  const [
    busy,
    setBusy,
  ] = useState<
    "ISSUE" |
    "CANCEL" |
    null
  >(null);

  const [
    canManage,
    setCanManage,
  ] = useState(false);

  const [
    canIssue,
    setCanIssue,
  ] = useState(false);

  const [
    canCancel,
    setCanCancel,
  ] = useState(false);

  const [
    cancelOpen,
    setCancelOpen,
  ] = useState(false);

  const [
    reason,
    setReason,
  ] = useState("");


  useEffect(() => {
    let cancelled = false;

    void getActiveCapabilities()
      .then((response) => {
        if (cancelled) {
          return;
        }

        const codes =
          response.data
            .capability_codes;

        setCanManage(
          codes.includes(
            "purchasing.create_po",
          ),
        );

        setCanIssue(
          codes.includes(
            "purchasing.approve_po",
          ),
        );

        setCanCancel(
          codes.includes(
            "purchasing.cancel_po",
          ),
        );
      })
      .catch(() => {
        if (!cancelled) {
          setCanManage(false);
          setCanIssue(false);
          setCanCancel(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, []);


  useEffect(() => {
    let cancelled = false;

    void getPurchaseOrder(
      purchaseOrderId,
    )
      .then((response) => {
        if (!cancelled) {
          setData(
            response.data,
          );
        }
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
    purchaseOrderId,
  ]);


  async function issue() {
    if (
      !data ||
      busy ||
      !canIssue ||
      data.status !== "DRAFT"
    ) {
      return;
    }

    setBusy("ISSUE");
    setError(null);
    setFeedback(null);

    try {
      const response =
        await issuePurchaseOrder(
          data.id,
        );

      setData(
        response.data,
      );

      setFeedback(
        response.message ??
        "Pesanan Pembelian berhasil diterbitkan.",
      );
    } catch (caught) {
      setError(caught);
    } finally {
      setBusy(null);
    }
  }


  async function cancel() {
    if (
      !data ||
      busy ||
      !canCancel ||
      ![
        "DRAFT",
        "ISSUED",
      ].includes(
        data.status,
      ) ||
      reason.trim().length < 3
    ) {
      return;
    }

    setBusy("CANCEL");
    setError(null);
    setFeedback(null);

    try {
      const response =
        await cancelPurchaseOrder(
          data.id,
          reason.trim(),
        );

      setData(
        response.data,
      );

      setReason("");
      setCancelOpen(false);

      setFeedback(
        response.message ??
        "Pesanan Pembelian berhasil dibatalkan.",
      );
    } catch (caught) {
      setError(caught);
    } finally {
      setBusy(null);
    }
  }


  if (loading) {
    return (
      <section
        className={
          styles.page
        }
      >
        <div
          className={
            styles.formLoading
          }
        >
          Memuat Pesanan Pembelian...
        </div>
      </section>
    );
  }


  if (
    error &&
    !data
  ) {
    return (
      <section
        className={
          styles.page
        }
      >
        <Link
          href="/app/operasional/pesanan-pembelian"
          className={
            styles.backLink
          }
        >
          <ArrowLeft
            size={17}
          />
          Pesanan Pembelian
        </Link>

        <ActionFeedback
          tone="error"
          title="Pesanan belum dapat dibuka"
          message={
            apiErrorMessage(
              error,
              "Pesanan Pembelian tidak ditemukan atau tidak dapat diakses.",
            )
          }
          requestId={
            apiRequestId(
              error,
            )
          }
        />
      </section>
    );
  }


  if (!data) {
    return null;
  }


  const items =
    data.items ??
    [];

  const cancellable =
    [
      "DRAFT",
      "ISSUED",
    ].includes(
      data.status,
    );


  return (
    <section
      className={
        styles.page
      }
    >
      <div
        className={
          styles.detailTopBar
        }
      >
        <Link
          href="/app/operasional/pesanan-pembelian"
          className={
            styles.backLink
          }
        >
          <ArrowLeft
            size={17}
          />
          Pesanan Pembelian
        </Link>

        {data.status ===
          "DRAFT" &&
        canManage ? (
          <Link
            href={
              `/app/operasional/pesanan-pembelian/${data.id}/ubah`
            }
            className={
              styles.secondaryLink
            }
          >
            <Pencil
              size={17}
            />
            Ubah Draf
          </Link>
        ) : null}
      </div>

      <ModuleHero
        eyebrow="Pesanan Pembelian"
        title={
          data.order_number
        }
        description={
          purchaseOrderStatusDescription(
            data.status,
          )
        }
        icon={
          ReceiptText
        }
        tone={
          moduleDef.tone
        }
        insightTitle={
          purchaseOrderStatusLabel(
            data.status,
          )
        }
        insightDescription={
          data.supplier?.name ??
          "Pemasok tidak tersedia."
        }
      />

      <section
        className={
          styles.nextActionCard
        }
      >
        <div
          className={
            styles.nextActionCopy
          }
        >
          <span>
            LANGKAH BERIKUTNYA
          </span>

          {data.status ===
          "DRAFT" ? (
            <>
              <h2>
                Periksa dan Terbitkan Pesanan
              </h2>

              <p>
                Pastikan pemasok, jumlah,
                harga, diskon, pajak, dan
                tanggal penerimaan sudah benar.
              </p>
            </>
          ) : null}

          {data.status ===
          "ISSUED" ? (
            <>
              <h2>
                Catat Penerimaan
              </h2>

              <p>
                Pesanan sudah diterbitkan.
                Tahap berikutnya adalah mencatat
                barang atau jasa yang benar-benar
                diterima.
              </p>
            </>
          ) : null}

          {data.status ===
          "PARTIALLY_RECEIVED" ? (
            <>
              <h2>
                Lanjutkan Penerimaan
              </h2>

              <p>
                Masih ada jumlah pesanan
                yang belum diterima lengkap.
              </p>
            </>
          ) : null}

          {data.status ===
          "RECEIVED" ? (
            <>
              <h2>
                Catat Tagihan Pemasok
              </h2>

              <p>
                Penerimaan sudah lengkap.
                Tahap keuangan berikutnya
                adalah mencatat tagihan pemasok.
              </p>
            </>
          ) : null}

          {data.status ===
          "CANCELLED" ? (
            <>
              <h2>
                Proses Selesai
              </h2>

              <p>
                Pesanan telah dibatalkan
                dan tidak dapat dilanjutkan.
              </p>
            </>
          ) : null}
        </div>

        {feedback ? (
          <ActionFeedback
            tone="success"
            title="Berhasil"
            message={
              feedback
            }
          />
        ) : null}

        {error ? (
          <ActionFeedback
            tone="error"
            title="Tindakan belum dapat diproses"
            message={
              apiErrorMessage(
                error,
                "Silakan coba kembali.",
              )
            }
            requestId={
              apiRequestId(
                error,
              )
            }
          />
        ) : null}

        {data.status ===
        "DRAFT" ? (
          <div
            className={
              styles.workflowActions
            }
          >
            {canIssue ? (
              <Button
                type="button"
                leadingIcon={
                  <CheckCircle2
                    size={18}
                  />
                }
                loading={
                  busy ===
                  "ISSUE"
                }
                loadingLabel="Menerbitkan..."
                disabled={
                  Boolean(busy)
                }
                onClick={() =>
                  void issue()
                }
              >
                Terbitkan Pesanan
              </Button>
            ) : null}

            {canManage ? (
              <Link
                href={
                  `/app/operasional/pesanan-pembelian/${data.id}/ubah`
                }
                className={
                  styles.secondaryLink
                }
              >
                <Pencil
                  size={17}
                />
                Ubah Draf
              </Link>
            ) : null}

            {canCancel ? (
              <Button
                type="button"
                variant="ghost"
                leadingIcon={
                  <XCircle
                    size={18}
                  />
                }
                disabled={
                  Boolean(busy)
                }
                onClick={() => {
                  setReason("");
                  setCancelOpen(
                    true,
                  );
                }}
              >
                Batalkan Pesanan
              </Button>
            ) : null}
          </div>
        ) : null}

        {data.status ===
          "ISSUED" &&
        canCancel ? (
          <div
            className={
              styles.workflowActions
            }
          >
            <Button
              type="button"
              variant="ghost"
              leadingIcon={
                <XCircle
                  size={18}
                />
              }
              disabled={
                Boolean(busy)
              }
              onClick={() => {
                setReason("");
                setCancelOpen(
                  true,
                );
              }}
            >
              Batalkan Pesanan
            </Button>
          </div>
        ) : null}

        {cancelOpen &&
        cancellable &&
        canCancel ? (
          <section
            className={
              styles.reasonCard
            }
          >
            <label
              className={
                styles.field
              }
            >
              <span>
                Alasan Pembatalan *
              </span>

              <textarea
                value={
                  reason
                }
                rows={3}
                maxLength={2000}
                placeholder="Jelaskan alasan pembatalan pesanan"
                onChange={
                  (event) =>
                    setReason(
                      event.target.value,
                    )
                }
                disabled={
                  Boolean(busy)
                }
              />
            </label>

            <div
              className={
                styles.reasonActions
              }
            >
              <Button
                type="button"
                variant="secondary"
                disabled={
                  Boolean(busy)
                }
                onClick={() => {
                  setReason("");
                  setCancelOpen(
                    false,
                  );
                }}
              >
                Kembali
              </Button>

              <Button
                type="button"
                loading={
                  busy ===
                  "CANCEL"
                }
                loadingLabel="Membatalkan..."
                disabled={
                  reason
                    .trim()
                    .length < 3
                }
                onClick={() =>
                  void cancel()
                }
              >
                Konfirmasi Pembatalan
              </Button>
            </div>
          </section>
        ) : null}
      </section>

      <div
        className={
          styles.detailGrid
        }
      >
        <section
          className={
            styles.detailCard
          }
        >
          <header
            className={
              styles.detailCardHeader
            }
          >
            <ClipboardCheck
              size={20}
            />

            <div>
              <h2>
                Informasi Pesanan
              </h2>

              <p>
                Ringkasan dokumen resmi
                kepada pemasok.
              </p>
            </div>
          </header>

          <dl>
            <div>
              <dt>Status</dt>

              <dd>
                <span
                  className={
                    styles.statusBadge
                  }
                  data-status={
                    data.status
                  }
                >
                  {purchaseOrderStatusLabel(
                    data.status,
                  )}
                </span>
              </dd>
            </div>

            <div>
              <dt>Pemasok</dt>

              <dd>
                {
                  data.supplier?.name ??
                  "-"
                }
              </dd>
            </div>

            <div>
              <dt>
                Perkiraan Diterima
              </dt>

              <dd>
                <CalendarDays
                  size={15}
                />

                {formatPurchaseDate(
                  data.expected_at,
                )}
              </dd>
            </div>

            <div>
              <dt>Total Pesanan</dt>

              <dd>
                <WalletCards
                  size={15}
                />

                {formatPurchaseMoney(
                  data.total,
                )}
              </dd>
            </div>

            <div>
              <dt>Subtotal</dt>

              <dd>
                {formatPurchaseMoney(
                  data.subtotal,
                )}
              </dd>
            </div>

            <div>
              <dt>Total Diskon</dt>

              <dd>
                {formatPurchaseMoney(
                  data.discount_total,
                )}
              </dd>
            </div>

            <div>
              <dt>Total Pajak</dt>

              <dd>
                {formatPurchaseMoney(
                  data.tax_total,
                )}
              </dd>
            </div>

            {data.issued_at ? (
              <div>
                <dt>Diterbitkan</dt>

                <dd>
                  {formatPurchaseDate(
                    data.issued_at,
                  )}
                </dd>
              </div>
            ) : null}
          </dl>
        </section>

        <section
          className={
            styles.detailCard
          }
        >
          <header
            className={
              styles.detailCardHeader
            }
          >
            <FileText
              size={20}
            />

            <div>
              <h2>
                Sumber & Catatan
              </h2>

              <p>
                Referensi kebutuhan dan
                catatan internal pesanan.
              </p>
            </div>
          </header>

          {data
            .source_purchase_request ? (
            <Link
              href={
                `/app/operasional/permintaan-pembelian/${data.source_purchase_request.id}`
              }
              className={
                styles.sourceDetailLink
              }
            >
              <FileText
                size={18}
              />

              <div>
                <span>
                  SUMBER PERMINTAAN
                </span>

                <strong>
                  {
                    data
                      .source_purchase_request
                      .request_number
                  }
                </strong>
              </div>
            </Link>
          ) : (
            <p
              className={
                styles.mutedText
              }
            >
              Pesanan dibuat langsung,
              tanpa Permintaan Pembelian.
            </p>
          )}

          <div
            className={
              styles.notesBox
            }
          >
            <span>CATATAN</span>

            <p>
              {data.notes?.trim() ||
                "Tidak ada catatan tambahan."}
            </p>
          </div>

          {data.status ===
            "CANCELLED" &&
          data.cancellation_reason ? (
            <div
              className={
                styles.cancelBox
              }
            >
              <span>
                ALASAN PEMBATALAN
              </span>

              <p>
                {
                  data
                    .cancellation_reason
                }
              </p>
            </div>
          ) : null}
        </section>
      </div>

      <section
        className={
          styles.detailCard
        }
      >
        <header
          className={
            styles.detailCardHeader
          }
        >
          <ReceiptText
            size={20}
          />

          <div>
            <h2>
              Item Pesanan
            </h2>

            <p>
              Snapshot item dan harga
              pada Pesanan Pembelian ini.
            </p>
          </div>
        </header>

        <div
          className={
            styles.detailItemList
          }
        >
          {items.map(
            (
              item,
              index,
            ) => (
              <article
                key={
                  item.id
                }
                className={
                  styles.detailItem
                }
              >
                <div
                  className={
                    styles.detailItemTop
                  }
                >
                  <div>
                    <span>
                      ITEM {index + 1}
                    </span>

                    <strong>
                      {item.name}
                    </strong>
                  </div>

                  <span
                    className={
                      styles.procurementBadge
                    }
                  >
                    {procurementTypeLabel(
                      item.procurement_type,
                    )}
                  </span>
                </div>

                <div
                  className={
                    styles.detailItemGrid
                  }
                >
                  <div>
                    <span>Jumlah</span>

                    <strong>
                      {formatDecimalDisplay(item.quantity)}{" "}
                      {item.unit_symbol ??
                        ""}
                    </strong>
                  </div>

                  <div>
                    <span>
                      Harga Satuan
                    </span>

                    <strong>
                      {formatPurchaseMoney(
                        item.unit_price,
                      )}
                    </strong>
                  </div>

                  <div>
                    <span>Diskon</span>

                    <strong>
                      {formatPurchaseMoney(
                        item.discount_amount,
                      )}
                    </strong>
                  </div>

                  <div>
                    <span>Pajak</span>

                    <strong>
                      {formatPurchaseMoney(
                        item.tax_amount,
                      )}
                    </strong>
                  </div>

                  <div>
                    <span>Jumlah Akhir</span>

                    <strong>
                      {formatPurchaseMoney(
                        item.amount,
                      )}
                    </strong>
                  </div>
                </div>

                {item.description ? (
                  <p
                    className={
                      styles.itemDescription
                    }
                  >
                    {item.description}
                  </p>
                ) : null}
              </article>
            ),
          )}
        </div>
      </section>
    </section>
  );
}
