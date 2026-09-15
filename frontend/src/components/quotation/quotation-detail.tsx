"use client";

import {
  ArrowLeft,
  CalendarDays,
  CheckCircle2,
  Copy,
  Eye,
  FileDown,
  FileText,
  Link2,
  Pencil,
  ReceiptText,
  Send,
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
  listCustomers,
} from "@/lib/customer/service";

import {
  getModule,
} from "@/lib/module/registry";

import {
  createInvoiceFromQuotation,
  getQuotation,
  issueQuotationPublicLink,
  quotationPdfUrl,
  recordQuotationManualDecision,
  sendQuotation,
  updateQuotationHeader,
} from "@/lib/quotation/service";

import type {
  Customer,
} from "@/types/customer";

import type {
  Quotation,
  QuotationManualDecisionMethod,
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

  const [
    actionLoading,
    setActionLoading,
  ] = useState(false);

  const [
    actionError,
    setActionError,
  ] = useState<unknown>(null);

  const [
    actionSuccess,
    setActionSuccess,
  ] = useState<string | null>(
    null,
  );

  const [
    publicUrl,
    setPublicUrl,
  ] = useState<string | null>(
    null,
  );

  const [
    decisionOpen,
    setDecisionOpen,
  ] = useState(false);

  const [
    decision,
    setDecision,
  ] = useState<
    "APPROVE" | "REJECT"
  >("APPROVE");

  const [
    decisionMethod,
    setDecisionMethod,
  ] = useState<
    QuotationManualDecisionMethod
  >("SIGNATURE");

  const [
    decisionReason,
    setDecisionReason,
  ] = useState("");

  const [
    decisionNote,
    setDecisionNote,
  ] = useState("");

  const [
    headerOpen,
    setHeaderOpen,
  ] = useState(false);

  const [
    headerCustomers,
    setHeaderCustomers,
  ] = useState<Customer[]>([]);

  const [
    headerCustomerId,
    setHeaderCustomerId,
  ] = useState("");

  const [
    headerValidUntil,
    setHeaderValidUntil,
  ] = useState("");

  useEffect(() => {
    if (!actionSuccess) {
      return;
    }

    const timeoutId =
      window.setTimeout(
        () => {
          setActionSuccess(null);
        },
        4000,
      );

    return () => {
      window.clearTimeout(
        timeoutId,
      );
    };
  }, [
    actionSuccess,
  ]);

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

  async function openHeaderEditor() {
    if (!quotation) {
      return;
    }

    setActionError(null);
    setHeaderCustomerId(
      quotation.customer_id,
    );
    setHeaderValidUntil(
      quotation.valid_until ?? "",
    );

    try {
      const response =
        await listCustomers({
          status: "ACTIVE",
          page: 1,
        });

      const options =
        [...response.data];

      if (
        quotation.customer &&
        !options.some(
          (customer) =>
            customer.id ===
            quotation.customer_id,
        )
      ) {
        options.unshift({
          id:
            quotation.customer.id,
          type: "COMPANY",
          code:
            quotation.customer.code,
          name:
            quotation.customer.name,
          phone: null,
          email: null,
          tax_id: null,
          payment_terms_days:
            null,
          notes: null,
          status: "INACTIVE",
          created_at: null,
          updated_at: null,
        });
      }

      setHeaderCustomers(
        options,
      );

      setHeaderOpen(true);
    } catch (caught) {
      setActionError(caught);
    }
  }

  async function handleHeaderSave() {
    if (!quotation) {
      return;
    }

    if (!headerCustomerId) {
      setActionError(
        new Error(
          "Pilih pelanggan terlebih dahulu.",
        ),
      );
      return;
    }

    setActionLoading(true);
    setActionError(null);

    try {
      const response =
        await updateQuotationHeader(
          quotation.id,
          {
            customer_id:
              headerCustomerId,
            valid_until:
              headerValidUntil ||
              null,
          },
        );

      setQuotation(
        response.data,
      );

      setHeaderOpen(false);

      setActionSuccess(
        "Informasi penawaran berhasil diperbarui.",
      );
    } catch (caught) {
      setActionError(caught);
    } finally {
      setActionLoading(false);
    }
  }

  async function reloadQuotation() {
    const response =
      await getQuotation(
        params.id,
      );

    setQuotation(
      response.data,
    );

    return response.data;
  }

  async function handleSend() {
    if (!quotation) {
      return;
    }

    setActionLoading(true);
    setActionError(null);

    try {
      const response =
        await sendQuotation(
          quotation.id,
        );

      setQuotation(
        response.data,
      );

      setActionSuccess(
        "Penawaran berhasil ditandai sebagai dikirim.",
      );
    } catch (caught) {
      setActionError(caught);
    } finally {
      setActionLoading(false);
    }
  }

  async function handlePublicLink() {
    if (!quotation) {
      return;
    }

    setActionLoading(true);
    setActionError(null);

    try {
      const response =
        await issueQuotationPublicLink(
          quotation.id,
        );

      setPublicUrl(
        response.data.public_url,
      );

      setActionSuccess(
        "Link pelanggan berhasil dibuat. Link lama otomatis tidak berlaku jika sebelumnya sudah ada.",
      );
    } catch (caught) {
      setActionError(caught);
    } finally {
      setActionLoading(false);
    }
  }

  async function copyPublicLink() {
    if (!publicUrl) {
      return;
    }

    try {
      await navigator.clipboard.writeText(
        publicUrl,
      );

      setActionSuccess(
        "Link pelanggan berhasil disalin.",
      );
    } catch {
      setActionError(
        new Error(
          "Link belum berhasil disalin. Silakan salin secara manual.",
        ),
      );
    }
  }

  async function handleManualDecision() {
    if (!quotation) {
      return;
    }

    if (
      decision === "REJECT" &&
      !decisionReason.trim()
    ) {
      setActionError(
        new Error(
          "Alasan perubahan wajib diisi.",
        ),
      );
      return;
    }

    setActionLoading(true);
    setActionError(null);

    try {
      const response =
        await recordQuotationManualDecision(
          quotation.id,
          {
            decision,
            method:
              decisionMethod,
            reason:
              decision === "REJECT"
                ? decisionReason.trim()
                : null,
            note:
              decisionNote.trim() ||
              null,
          },
        );

      setQuotation(
        response.data,
      );

      setDecisionOpen(false);
      setDecisionReason("");
      setDecisionNote("");

      setActionSuccess(
        decision === "APPROVE"
          ? "Persetujuan manual berhasil dicatat."
          : "Permintaan revisi manual berhasil dicatat.",
      );
    } catch (caught) {
      setActionError(caught);
    } finally {
      setActionLoading(false);
    }
  }

  async function handleCreateInvoice() {
    if (!quotation) {
      return;
    }

    setActionLoading(true);
    setActionError(null);

    try {
      const response =
        await createInvoiceFromQuotation(
          quotation.id,
        );

      setActionSuccess(
        response.message ??
          "Tagihan berhasil dibuat dari Penawaran.",
      );

      await reloadQuotation();
    } catch (caught) {
      setActionError(caught);
    } finally {
      setActionLoading(false);
    }
  }

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

      {actionSuccess ? (
        <ActionFeedback
          tone="success"
          title="Berhasil"
          message={actionSuccess}
          placement="viewport"
        />
      ) : null}

      {actionError ? (
        <ActionFeedback
          tone="error"
          title="Aksi belum berhasil"
          message={
            apiErrorMessage(
              actionError,
            )
          }
          requestId={
            apiRequestId(
              actionError,
            )
          }
        />
      ) : null}

      <section
        className={
          styles.actionCard
        }
      >
        <div>
          <strong>
            Tindakan Berikutnya
          </strong>

          <p>
            {nextStep(
              quotation.status,
            )}
          </p>
        </div>

        <div
          className={
            styles.lifecycleActions
          }
        >
          {quotation.status ===
          "DRAFT" ? (
            <Button
              type="button"
              variant="secondary"
              disabled={
                actionLoading
              }
              leadingIcon={
                <Pencil size={17} />
              }
              onClick={
                () =>
                  void openHeaderEditor()
              }
            >
              Edit Informasi
            </Button>
          ) : null}

          {quotation.status ===
          "DRAFT" ? (
            <Button
              type="button"
              loading={
                actionLoading
              }
              loadingLabel="Mengirim..."
              leadingIcon={
                <Send size={17} />
              }
              onClick={
                () =>
                  void handleSend()
              }
            >
              Kirim Penawaran
            </Button>
          ) : null}

          {quotation.status ===
            "SENT" ||
          quotation.status ===
            "VIEWED" ? (
            <>
              <Button
                type="button"
                variant="secondary"
                disabled={
                  actionLoading
                }
                leadingIcon={
                  <Link2
                    size={17}
                  />
                }
                onClick={
                  () =>
                    void handlePublicLink()
                }
              >
                Buat / Perbarui Link
              </Button>

              <Button
                type="button"
                variant="secondary"
                disabled={
                  actionLoading
                }
                leadingIcon={
                  <CheckCircle2
                    size={17}
                  />
                }
                onClick={
                  () =>
                    setDecisionOpen(
                      true,
                    )
                }
              >
                Keputusan Manual
              </Button>
            </>
          ) : null}

          {quotation.status ===
          "APPROVED" ? (
            <Button
              type="button"
              loading={
                actionLoading
              }
              loadingLabel="Membuat Tagihan..."
              leadingIcon={
                <ReceiptText
                  size={17}
                />
              }
              onClick={
                () =>
                  void handleCreateInvoice()
              }
            >
              Buat Tagihan
            </Button>
          ) : null}
        </div>

        {publicUrl ? (
          <div
            className={
              styles.publicLinkBox
            }
          >
            <div>
              <span>
                Link pelanggan aktif
              </span>

              <code>
                {publicUrl}
              </code>
            </div>

            <Button
              type="button"
              variant="secondary"
              leadingIcon={
                <Copy size={16} />
              }
              onClick={
                () =>
                  void copyPublicLink()
              }
            >
              Salin Link
            </Button>
          </div>
        ) : null}
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

      {headerOpen ? (
        <div
          className={
            styles.previewBackdrop
          }
          role="presentation"
        >
          <section
            className={
              styles.decisionModal
            }
            role="dialog"
            aria-modal="true"
            aria-labelledby="quotation-header-title"
          >
            <header
              className={
                styles.previewHeader
              }
            >
              <div>
                <span>
                  EDIT INFORMASI
                </span>

                <h2
                  id="quotation-header-title"
                >
                  Informasi Penawaran
                </h2>
              </div>

              <button
                type="button"
                className={
                  styles.closeButton
                }
                disabled={
                  actionLoading
                }
                onClick={
                  () =>
                    setHeaderOpen(
                      false,
                    )
                }
                aria-label="Tutup"
              >
                <X size={20} />
              </button>
            </header>

            <div
              className={
                styles.decisionBody
              }
            >
              <ActionFeedback
                tone="info"
                title="Hanya informasi utama"
                message="Barang, harga, catatan dan syarat tidak diubah di sini. Perubahan isi harus dibuat sebagai revisi baru."
              />

              <label>
                <span>
                  Pelanggan *
                </span>

                <select
                  value={
                    headerCustomerId
                  }
                  onChange={
                    (event) =>
                      setHeaderCustomerId(
                        event.target
                          .value,
                      )
                  }
                >
                  <option value="">
                    Pilih pelanggan
                  </option>

                  {headerCustomers.map(
                    (customer) => (
                      <option
                        key={
                          customer.id
                        }
                        value={
                          customer.id
                        }
                      >
                        {customer.name}
                      </option>
                    ),
                  )}
                </select>
              </label>

              <label>
                <span>
                  Berlaku Sampai
                </span>

                <input
                  type="date"
                  value={
                    headerValidUntil
                  }
                  onChange={
                    (event) =>
                      setHeaderValidUntil(
                        event.target
                          .value,
                      )
                  }
                />
              </label>
            </div>

            <footer
              className={
                styles.decisionFooter
              }
            >
              <Button
                type="button"
                variant="secondary"
                disabled={
                  actionLoading
                }
                onClick={
                  () =>
                    setHeaderOpen(
                      false,
                    )
                }
              >
                Batal
              </Button>

              <Button
                type="button"
                loading={
                  actionLoading
                }
                loadingLabel="Menyimpan..."
                onClick={
                  () =>
                    void handleHeaderSave()
                }
              >
                Simpan Perubahan
              </Button>
            </footer>
          </section>
        </div>
      ) : null}

      {decisionOpen ? (
        <div
          className={
            styles.previewBackdrop
          }
          role="presentation"
        >
          <section
            className={
              styles.decisionModal
            }
            role="dialog"
            aria-modal="true"
            aria-labelledby="manual-decision-title"
          >
            <header
              className={
                styles.previewHeader
              }
            >
              <div>
                <span>
                  KEPUTUSAN MANUAL
                </span>

                <h2
                  id="manual-decision-title"
                >
                  Catat keputusan pelanggan
                </h2>
              </div>

              <button
                type="button"
                className={
                  styles.closeButton
                }
                disabled={
                  actionLoading
                }
                onClick={
                  () =>
                    setDecisionOpen(
                      false,
                    )
                }
                aria-label="Tutup"
              >
                <X size={20} />
              </button>
            </header>

            <div
              className={
                styles.decisionBody
              }
            >
              <label>
                <span>
                  Keputusan
                </span>

                <select
                  value={decision}
                  onChange={
                    (event) =>
                      setDecision(
                        event.target
                          .value as
                          | "APPROVE"
                          | "REJECT",
                      )
                  }
                >
                  <option value="APPROVE">
                    Disetujui
                  </option>

                  <option value="REJECT">
                    Ajukan Perubahan
                  </option>
                </select>
              </label>

              <label>
                <span>
                  Metode Konfirmasi
                </span>

                <select
                  value={
                    decisionMethod
                  }
                  onChange={
                    (event) =>
                      setDecisionMethod(
                        event.target
                          .value as
                          QuotationManualDecisionMethod,
                      )
                  }
                >
                  <option value="SIGNATURE">
                    Tanda Tangan
                  </option>
                  <option value="WHATSAPP">
                    WhatsApp
                  </option>
                  <option value="EMAIL">
                    Email
                  </option>
                  <option value="PHONE">
                    Telepon
                  </option>
                  <option value="MEETING">
                    Pertemuan
                  </option>
                  <option value="OTHER">
                    Lainnya
                  </option>
                </select>
              </label>

              {decision ===
              "REJECT" ? (
                <label>
                  <span>
                    Alasan Perubahan *
                  </span>

                  <textarea
                    rows={4}
                    value={
                      decisionReason
                    }
                    onChange={
                      (event) =>
                        setDecisionReason(
                          event.target
                            .value,
                        )
                    }
                  />
                </label>
              ) : null}

              <label>
                <span>
                  Catatan Internal
                </span>

                <textarea
                  rows={4}
                  value={
                    decisionNote
                  }
                  onChange={
                    (event) =>
                      setDecisionNote(
                        event.target
                          .value,
                      )
                  }
                />
              </label>
            </div>

            <footer
              className={
                styles.decisionFooter
              }
            >
              <Button
                type="button"
                variant="secondary"
                disabled={
                  actionLoading
                }
                onClick={
                  () =>
                    setDecisionOpen(
                      false,
                    )
                }
              >
                Batal
              </Button>

              <Button
                type="button"
                loading={
                  actionLoading
                }
                loadingLabel="Menyimpan..."
                onClick={
                  () =>
                    void handleManualDecision()
                }
              >
                Simpan Keputusan
              </Button>
            </footer>
          </section>
        </div>
      ) : null}

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
