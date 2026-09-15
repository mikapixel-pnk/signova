"use client";

import {
  ArrowLeft,
  CalendarDays,
  Eye,
  FileDown,
  FileText,
  ReceiptText,
  UserRound,
  X,
} from "lucide-react";

import Link from "next/link";

import {
  useEffect,
  useState,
} from "react";

import {
  useParams,
} from "next/navigation";

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
  formatCurrency,
} from "@/lib/format/currency";

import {
  getModule,
} from "@/lib/module/registry";

import {
  getQuotation,
  quotationPdfUrl,
} from "@/lib/quotation/service";

import type {
  Quotation,
  QuotationStatus,
} from "@/types/quotation";

import styles from "./quotation-detail.module.css";

function statusLabel(
  status: QuotationStatus,
): string {
  switch (status) {
    case "DRAFT":
      return "Draf";
    case "SENT":
      return "Menunggu Keputusan";
    case "VIEWED":
      return "Sudah Dilihat";
    case "APPROVED":
      return "Disetujui";
    case "REJECTED":
      return "Perlu Revisi";
    case "EXPIRED":
      return "Kedaluwarsa";
    case "CANCELLED":
      return "Dibatalkan";
    default:
      return status;
  }
}

function nextStep(
  status: QuotationStatus,
): string {
  switch (status) {
    case "DRAFT":
      return "Periksa isi penawaran sebelum dikirim ke pelanggan.";
    case "SENT":
      return "Penawaran sedang menunggu keputusan pelanggan.";
    case "VIEWED":
      return "Pelanggan sudah membuka penawaran dan belum memberi keputusan.";
    case "APPROVED":
      return "Penawaran sudah disetujui dan siap dilanjutkan menjadi Tagihan.";
    case "REJECTED":
      return "Buat revisi baru tanpa menghapus versi sebelumnya.";
    case "EXPIRED":
      return "Masa berlaku penawaran sudah berakhir.";
    case "CANCELLED":
      return "Penawaran ini sudah dibatalkan.";
    default:
      return "";
  }
}

function localDate(
  value: string | null,
): string {
  if (!value) {
    return "Tanpa batas waktu";
  }

  return new Intl.DateTimeFormat(
    "id-ID",
    {
      day: "numeric",
      month: "long",
      year: "numeric",
    },
  ).format(
    new Date(
      `${value}T00:00:00`,
    ),
  );
}

function textOrDash(
  value:
    | string
    | null
    | undefined,
): string {
  return value?.trim()
    ? value
    : "-";
}

export function QuotationDetail() {
  const params =
    useParams<{
      id: string;
    }>();

  const moduleDef =
    getModule("quotations");

  const [
    quotation,
    setQuotation,
  ] = useState<Quotation | null>(
    null,
  );

  const [
    loading,
    setLoading,
  ] = useState(true);

  const [
    error,
    setError,
  ] = useState<unknown>(null);

  const [
    previewOpen,
    setPreviewOpen,
  ] = useState(false);

  useEffect(() => {
    let cancelled = false;

    async function load() {
      setLoading(true);
      setError(null);

      try {
        const response =
          await getQuotation(
            params.id,
          );

        if (!cancelled) {
          setQuotation(
            response.data,
          );
        }
      } catch (caught) {
        if (!cancelled) {
          setError(caught);
        }
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    }

    void load();

    return () => {
      cancelled = true;
    };
  }, [
    params.id,
  ]);

  if (loading) {
    return (
      <section
        className={styles.page}
      >
        <div
          className={
            styles.loadingCard
          }
        >
          Memuat penawaran...
        </div>
      </section>
    );
  }

  if (
    error ||
    !quotation
  ) {
    return (
      <section
        className={styles.page}
      >
        <Link
          href="/app/penawaran"
          className={
            styles.backLink
          }
        >
          <ArrowLeft size={17} />
          Penawaran
        </Link>

        <ActionFeedback
          tone="error"
          title="Penawaran belum dapat dibuka"
          message={
            error
              ? apiErrorMessage(
                  error,
                )
              : "Penawaran tidak ditemukan."
          }
          requestId={
            error
              ? apiRequestId(
                  error,
                )
              : null
          }
        />
      </section>
    );
  }

  const version =
    quotation.current_version;

  return (
    <section
      className={styles.page}
    >
      <div
        className={styles.topBar}
      >
        <Link
          href="/app/penawaran"
          className={
            styles.backLink
          }
        >
          <ArrowLeft size={17} />
          Penawaran
        </Link>

        <div
          className={
            styles.actions
          }
        >
          <Button
            type="button"
            variant="secondary"
            leadingIcon={
              <Eye size={17} />
            }
            onClick={
              () =>
                setPreviewOpen(
                  true,
                )
            }
          >
            Preview
          </Button>

          <Button
            type="button"
            variant="secondary"
            leadingIcon={
              <FileDown
                size={17}
              />
            }
            onClick={
              () => {
                window.open(
                  quotationPdfUrl(
                    quotation.id,
                  ),
                  "_blank",
                  "noopener,noreferrer",
                );
              }
            }
          >
            PDF
          </Button>
        </div>
      </div>

      <ModuleHero
        eyebrow="Penjualan"
        title={
          quotation.quotation_number
        }
        description={
          quotation.customer?.name
            ? `Penawaran untuk ${quotation.customer.name}`
            : moduleDef.description
        }
        icon={FileText}
        tone="teal"
        insightTitle={
          statusLabel(
            quotation.status,
          )
        }
        insightDescription={
          nextStep(
            quotation.status,
          )
        }
      />

      <div
        className={styles.grid}
      >
        <section
          className={styles.card}
        >
          <header
            className={
              styles.cardHeader
            }
          >
            <UserRound size={20} />

            <div>
              <h2>
                Informasi Penawaran
              </h2>
              <p>
                Pelanggan, masa berlaku,
                status dan revisi aktif.
              </p>
            </div>
          </header>

          <dl
            className={
              styles.detailList
            }
          >
            <div>
              <dt>Pelanggan</dt>
              <dd>
                {quotation.customer
                  ?.name ?? "-"}
              </dd>
            </div>

            <div>
              <dt>Berlaku sampai</dt>
              <dd>
                <CalendarDays
                  size={15}
                />
                {localDate(
                  quotation.valid_until,
                )}
              </dd>
            </div>

            <div>
              <dt>Status</dt>
              <dd>
                <span
                  className={
                    styles.statusBadge
                  }
                  data-status={
                    quotation.status
                  }
                >
                  {statusLabel(
                    quotation.status,
                  )}
                </span>
              </dd>
            </div>

            <div>
              <dt>Revisi</dt>
              <dd>
                REV-
                {String(
                  version
                    ?.revision_no ??
                    1,
                ).padStart(
                  2,
                  "0",
                )}
              </dd>
            </div>
          </dl>
        </section>

        <section
          className={styles.card}
        >
          <header
            className={
              styles.cardHeader
            }
          >
            <ReceiptText
              size={20}
            />

            <div>
              <h2>
                Ringkasan Nilai
              </h2>
              <p>
                Nilai authoritative dari
                perhitungan server.
              </p>
            </div>
          </header>

          <dl
            className={
              styles.detailList
            }
          >
            <div>
              <dt>Subtotal</dt>
              <dd>
                {formatCurrency(
                  version?.subtotal,
                  version?.currency ??
                    "IDR",
                )}
              </dd>
            </div>

            <div>
              <dt>Diskon</dt>
              <dd>
                {formatCurrency(
                  version
                    ?.discount_total,
                  version?.currency ??
                    "IDR",
                )}
              </dd>
            </div>

            <div>
              <dt>Pajak</dt>
              <dd>
                {formatCurrency(
                  version?.tax_total,
                  version?.currency ??
                    "IDR",
                )}
              </dd>
            </div>

            <div
              className={
                styles.totalRow
              }
            >
              <dt>Total</dt>
              <dd>
                {formatCurrency(
                  version?.total,
                  version?.currency ??
                    "IDR",
                )}
              </dd>
            </div>
          </dl>
        </section>
      </div>

      <section
        className={styles.card}
      >
        <header
          className={
            styles.cardHeader
          }
        >
          <FileText size={20} />

          <div>
            <h2>
              Item Penawaran
            </h2>
            <p>
              Snapshot Barang & Jasa
              pada revisi aktif.
            </p>
          </div>
        </header>

        <div
          className={
            styles.items
          }
        >
          {version?.items.map(
            (
              item,
              index,
            ) => (
              <article
                key={item.id}
                className={
                  styles.item
                }
              >
                <div>
                  <span>
                    {index + 1}
                  </span>

                  <div>
                    <strong>
                      {item.name}
                    </strong>

                    <small>
                      {item.code ??
                        "Tanpa kode"}
                      {" • "}
                      {item.unit_symbol ??
                        item.unit_name ??
                        "unit"}
                    </small>
                  </div>
                </div>

                <dl>
                  <div>
                    <dt>Qty</dt>
                    <dd>
                      {item.quantity}
                    </dd>
                  </div>

                  <div>
                    <dt>Harga</dt>
                    <dd>
                      {formatCurrency(
                        item.unit_price,
                        version.currency,
                      )}
                    </dd>
                  </div>

                  <div>
                    <dt>Diskon</dt>
                    <dd>
                      {formatCurrency(
                        item.discount_amount,
                        version.currency,
                      )}
                    </dd>
                  </div>

                  <div>
                    <dt>Jumlah</dt>
                    <dd>
                      {formatCurrency(
                        item.amount,
                        version.currency,
                      )}
                    </dd>
                  </div>
                </dl>
              </article>
            ),
          )}

          {!version ||
          version.items.length ===
            0 ? (
            <p
              className={
                styles.muted
              }
            >
              Tidak ada item.
            </p>
          ) : null}
        </div>
      </section>

      <div
        className={styles.grid}
      >
        <section
          className={styles.card}
        >
          <header
            className={
              styles.cardHeader
            }
          >
            <FileText size={20} />

            <div>
              <h2>Catatan</h2>
              <p>
                Informasi tambahan pada
                revisi aktif.
              </p>
            </div>
          </header>

          <p className={styles.notes}>
            {textOrDash(
              version?.notes,
            )}
          </p>
        </section>

        <section
          className={styles.card}
        >
          <header
            className={
              styles.cardHeader
            }
          >
            <ReceiptText
              size={20}
            />

            <div>
              <h2>
                Syarat & Ketentuan
              </h2>
              <p>
                Ketentuan yang diberikan
                kepada pelanggan.
              </p>
            </div>
          </header>

          <p className={styles.notes}>
            {textOrDash(
              version?.terms,
            )}
          </p>
        </section>
      </div>

      {previewOpen ? (
        <div
          className={
            styles.previewBackdrop
          }
          role="presentation"
        >
          <section
            className={
              styles.preview
            }
            role="dialog"
            aria-modal="true"
            aria-labelledby="quotation-preview-title"
          >
            <header
              className={
                styles.previewHeader
              }
            >
              <div>
                <span>
                  PREVIEW — Belum dikirim
                  ke pelanggan
                </span>

                <h2
                  id="quotation-preview-title"
                >
                  {
                    quotation.quotation_number
                  }
                </h2>
              </div>

              <button
                type="button"
                className={
                  styles.closeButton
                }
                onClick={
                  () =>
                    setPreviewOpen(
                      false,
                    )
                }
                aria-label="Tutup preview"
              >
                <X size={20} />
              </button>
            </header>

            <div
              className={
                styles.previewBody
              }
            >
              <div
                className={
                  styles.previewMeta
                }
              >
                <div>
                  <span>
                    Pelanggan
                  </span>
                  <strong>
                    {quotation.customer
                      ?.name ?? "-"}
                  </strong>
                </div>

                <div>
                  <span>
                    Berlaku Sampai
                  </span>
                  <strong>
                    {localDate(
                      quotation.valid_until,
                    )}
                  </strong>
                </div>
              </div>

              <div
                className={
                  styles.previewItems
                }
              >
                {version?.items.map(
                  (
                    item,
                    index,
                  ) => (
                    <div
                      key={item.id}
                    >
                      <span>
                        {index + 1}.
                      </span>

                      <div>
                        <strong>
                          {item.name}
                        </strong>
                        <small>
                          {item.quantity} ×{" "}
                          {formatCurrency(
                            item.unit_price,
                            version.currency,
                          )}
                        </small>
                      </div>

                      <strong>
                        {formatCurrency(
                          item.amount,
                          version.currency,
                        )}
                      </strong>
                    </div>
                  ),
                )}
              </div>

              <div
                className={
                  styles.previewTotal
                }
              >
                <span>Total</span>
                <strong>
                  {formatCurrency(
                    version?.total,
                    version?.currency ??
                      "IDR",
                  )}
                </strong>
              </div>

              {version?.notes ? (
                <section>
                  <h3>Catatan</h3>
                  <p>
                    {version.notes}
                  </p>
                </section>
              ) : null}

              {version?.terms ? (
                <section>
                  <h3>
                    Syarat & Ketentuan
                  </h3>
                  <p>
                    {version.terms}
                  </p>
                </section>
              ) : null}
            </div>
          </section>
        </div>
      ) : null}
    </section>
  );
}
