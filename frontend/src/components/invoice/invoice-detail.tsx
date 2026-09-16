"use client";

import {
  ArrowLeft,
  Ban,
  CalendarDays,
  FileDown,
  FileText,
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
  getInvoice,
  getInvoicePdf,
  issueInvoice,
  voidInvoice,
} from "@/lib/invoice/service";

import type {
  Invoice,
} from "@/types/invoice";

import styles from "./invoice-detail.module.css";

function money(
  value:
    | number
    | string,
  currency = "IDR",
): string {
  return new Intl.NumberFormat(
    "id-ID",
    {
      style: "currency",
      currency,
      maximumFractionDigits: 0,
    },
  ).format(
    Number(value) || 0,
  );
}

function localDate(
  value:
    | string
    | null,
): string {
  if (!value) {
    return "-";
  }

  const date =
    new Date(value);

  if (
    Number.isNaN(
      date.getTime(),
    )
  ) {
    return value;
  }

  return new Intl.DateTimeFormat(
    "id-ID",
    {
      day: "numeric",
      month: "long",
      year: "numeric",
    },
  ).format(date);
}

function localDateTime(
  value:
    | string
    | null,
): string {
  if (!value) {
    return "-";
  }

  const date =
    new Date(value);

  if (
    Number.isNaN(
      date.getTime(),
    )
  ) {
    return value;
  }

  return new Intl.DateTimeFormat(
    "id-ID",
    {
      day: "numeric",
      month: "short",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    },
  ).format(date);
}

function nextStep(
  status: string,
): string {
  switch (status) {
    case "DRAFT":
      return "Periksa rincian tagihan sebelum diterbitkan.";

    case "ISSUED":
      return "Tagihan sudah diterbitkan dan menunggu pembayaran pelanggan.";

    case "PARTIALLY_PAID":
      return "Sebagian pembayaran sudah diterima. Pantau sisa tagihan.";

    case "PAID":
      return "Tagihan sudah lunas.";

    case "VOID":
      return "Tagihan ini sudah dibatalkan dan tidak lagi memiliki nilai piutang.";

    default:
      return "";
  }
}

function canDownloadPdf(
  status: string,
): boolean {
  return [
    "ISSUED",
    "PARTIALLY_PAID",
    "PAID",
  ].includes(status);
}

export function InvoiceDetail() {
  const params =
    useParams<{
      id: string;
    }>();

  const [
    invoice,
    setInvoice,
  ] = useState<
    Invoice | null
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
    actionLoading,
    setActionLoading,
  ] = useState(false);

  const [
    actionError,
    setActionError,
  ] = useState<unknown>(
    null,
  );

  const [
    actionSuccess,
    setActionSuccess,
  ] = useState<
    string | null
  >(null);

  const [
    voidOpen,
    setVoidOpen,
  ] = useState(false);

  const [
    voidReason,
    setVoidReason,
  ] = useState("");

  useEffect(() => {
    let cancelled =
      false;

    async function load() {
      setLoading(true);
      setError(null);

      try {
        const response =
          await getInvoice(
            params.id,
          );

        if (!cancelled) {
          setInvoice(
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

  useEffect(() => {
    if (!actionSuccess) {
      return;
    }

    const timer =
      window.setTimeout(
        () => {
          setActionSuccess(
            null,
          );
        },
        4000,
      );

    return () => {
      window.clearTimeout(
        timer,
      );
    };
  }, [
    actionSuccess,
  ]);


  async function handleIssue() {
    if (!invoice) {
      return;
    }

    setActionLoading(true);
    setActionError(null);

    try {
      const response =
        await issueInvoice(
          invoice.id,
        );

      setInvoice(
        response.data,
      );

      setActionSuccess(
        response.message ??
          "Tagihan berhasil diterbitkan.",
      );
    } catch (caught) {
      setActionError(caught);
    } finally {
      setActionLoading(false);
    }
  }

  async function handleVoid() {
    if (
      !invoice ||
      !voidReason.trim()
    ) {
      setActionError(
        new Error(
          "Alasan pembatalan wajib diisi.",
        ),
      );
      return;
    }

    setActionLoading(true);
    setActionError(null);

    try {
      const response =
        await voidInvoice(
          invoice.id,
          {
            reason:
              voidReason.trim(),
          },
        );

      setInvoice(
        response.data,
      );

      setVoidOpen(false);
      setVoidReason("");

      setActionSuccess(
        response.message ??
          "Tagihan berhasil dibatalkan.",
      );
    } catch (caught) {
      setActionError(caught);
    } finally {
      setActionLoading(false);
    }
  }

  async function handlePdf() {
    if (!invoice) {
      return;
    }

    const pdfWindow =
      window.open(
        "",
        "_blank",
      );

    if (pdfWindow) {
      try {
        pdfWindow.opener =
          null;

        pdfWindow.document.title =
          "Memuat PDF...";
      } catch {
        // Browser dapat membatasi akses ke tab baru.
      }
    }

    setActionLoading(true);
    setActionError(null);

    try {
      const blob =
        await getInvoicePdf(
          invoice.id,
        );

      const objectUrl =
        URL.createObjectURL(
          blob,
        );

      if (pdfWindow) {
        pdfWindow.location.href =
          objectUrl;
      } else {
        const link =
          document.createElement(
            "a",
          );

        link.href =
          objectUrl;

        link.target =
          "_blank";

        link.rel =
          "noopener noreferrer";

        document.body.appendChild(
          link,
        );

        link.click();
        link.remove();
      }

      window.setTimeout(
        () => {
          URL.revokeObjectURL(
            objectUrl,
          );
        },
        60_000,
      );
    } catch (caught) {
      if (
        pdfWindow &&
        !pdfWindow.closed
      ) {
        pdfWindow.close();
      }

      setActionError(caught);
    } finally {
      setActionLoading(false);
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
            styles.loadingCard
          }
        >
          Memuat tagihan...
        </div>
      </section>
    );
  }

  if (
    error ||
    !invoice
  ) {
    return (
      <section
        className={
          styles.page
        }
      >
        <Link
          href="/app/tagihan"
          className={
            styles.backLink
          }
        >
          <ArrowLeft
            size={17}
          />
          Tagihan
        </Link>

        <ActionFeedback
          tone="error"
          title="Tagihan belum dapat dibuka"
          message={
            error
              ? apiErrorMessage(
                  error,
                )
              : "Tagihan tidak ditemukan."
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

  const canVoid =
    invoice.status ===
      "DRAFT" ||
    invoice.status ===
      "ISSUED";

  return (
    <section
      className={
        styles.page
      }
    >
      <div
        className={
          styles.topBar
        }
      >
        <Link
          href="/app/tagihan"
          className={
            styles.backLink
          }
        >
          <ArrowLeft
            size={17}
          />
          Tagihan
        </Link>

        {canDownloadPdf(
          invoice.status,
        ) ? (
          <Button
            type="button"
            variant="secondary"
            disabled={
              actionLoading
            }
            leadingIcon={
              <FileDown
                size={17}
              />
            }
            onClick={
              () =>
                void handlePdf()
            }
          >
            PDF
          </Button>
        ) : null}
      </div>

      <ModuleHero
        eyebrow="Penjualan"
        title={
          invoice.invoice_number
        }
        description={
          invoice.customer?.name
            ? `Tagihan untuk ${invoice.customer.name}`
            : "Rincian tagihan pelanggan."
        }
        icon={FileText}
        tone="amber"
        insightTitle={
          invoice.status_label
        }
        insightDescription={
          nextStep(
            invoice.status,
          )
        }
      />

      {actionSuccess ? (
        <ActionFeedback
          tone="success"
          title="Berhasil"
          message={
            actionSuccess
          }
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
              invoice.status,
            )}
          </p>
        </div>

        <div
          className={
            styles.actions
          }
        >
          {invoice.status ===
          "DRAFT" ? (
            <Button
              type="button"
              loading={
                actionLoading
              }
              loadingLabel="Menerbitkan..."
              leadingIcon={
                <Send
                  size={17}
                />
              }
              onClick={
                () =>
                  void handleIssue()
              }
            >
              Terbitkan
            </Button>
          ) : null}

          {canVoid ? (
            <Button
              type="button"
              variant="secondary"
              disabled={
                actionLoading
              }
              leadingIcon={
                <Ban
                  size={17}
                />
              }
              onClick={
                () => {
                  setActionError(
                    null,
                  );
                  setVoidOpen(
                    true,
                  );
                }
              }
            >
              Batalkan
            </Button>
          ) : null}
        </div>
      </section>

      <div
        className={
          styles.infoGrid
        }
      >
        <article
          className={
            styles.infoCard
          }
        >
          <span
            className={
              styles.infoIcon
            }
          >
            <UserRound
              size={19}
            />
          </span>

          <div>
            <span>
              Pelanggan
            </span>

            <strong>
              {invoice.customer
                ?.name ??
                "-"}
            </strong>

            {invoice.customer
              ?.code ? (
              <small>
                {
                  invoice.customer
                    .code
                }
              </small>
            ) : null}
          </div>
        </article>

        <article
          className={
            styles.infoCard
          }
        >
          <span
            className={
              styles.infoIcon
            }
          >
            <CalendarDays
              size={19}
            />
          </span>

          <div>
            <span>
              Diterbitkan
            </span>

            <strong>
              {localDate(
                invoice.issued_at,
              )}
            </strong>

            <small>
              Jatuh tempo{" "}
              {localDate(
                invoice.due_at,
              )}
            </small>
          </div>
        </article>

        <article
          className={
            styles.infoCard
          }
        >
          <span
            className={
              styles.infoIcon
            }
          >
            <ReceiptText
              size={19}
            />
          </span>

          <div>
            <span>
              Sisa Tagihan
            </span>

            <strong>
              {money(
                invoice.outstanding_amount,
                invoice.currency,
              )}
            </strong>

            <small>
              Dibayar{" "}
              {money(
                invoice.paid_amount,
                invoice.currency,
              )}
            </small>
          </div>
        </article>
      </div>

      <section
        className={
          styles.card
        }
      >
        <header
          className={
            styles.cardHeader
          }
        >
          <div>
            <strong>
              Rincian Tagihan
            </strong>

            <span>
              {invoice.items
                ?.length ??
                0}{" "}
              item
            </span>
          </div>
        </header>

        <div
          className={
            styles.itemList
          }
        >
          {invoice.items?.map(
            (
              item,
              index,
            ) => (
              <article
                className={
                  styles.item
                }
                key={
                  item.id
                }
              >
                <div
                  className={
                    styles.itemMain
                  }
                >
                  <div
                    className={
                      styles.itemNumber
                    }
                  >
                    {index + 1}
                  </div>

                  <div>
                    <strong>
                      {item.name}
                    </strong>

                    <span>
                      {item.code ??
                        "Tanpa kode"}
                    </span>

                    {item.description ? (
                      <p>
                        {
                          item.description
                        }
                      </p>
                    ) : null}
                  </div>
                </div>

                <div
                  className={
                    styles.itemValues
                  }
                >
                  <span>
                    Jumlah
                    <strong>
                      {item.quantity}{" "}
                      {item.unit_symbol ??
                        item.unit_name ??
                        ""}
                    </strong>
                  </span>

                  <span>
                    Harga
                    <strong>
                      {money(
                        item.unit_price,
                        invoice.currency,
                      )}
                    </strong>
                  </span>

                  <span>
                    Diskon
                    <strong>
                      {money(
                        item.discount_amount,
                        invoice.currency,
                      )}
                    </strong>
                  </span>

                  <span>
                    Pajak
                    <strong>
                      {money(
                        item.tax_amount,
                        invoice.currency,
                      )}
                    </strong>
                  </span>

                  <span
                    className={
                      styles.itemAmount
                    }
                  >
                    Total
                    <strong>
                      {money(
                        item.amount,
                        invoice.currency,
                      )}
                    </strong>
                  </span>
                </div>
              </article>
            ),
          )}
        </div>

        <div
          className={
            styles.summary
          }
        >
          <div>
            <span>
              Subtotal
            </span>

            <strong>
              {money(
                invoice.subtotal,
                invoice.currency,
              )}
            </strong>
          </div>

          <div>
            <span>
              Diskon
            </span>

            <strong>
              -{" "}
              {money(
                invoice.discount_total,
                invoice.currency,
              )}
            </strong>
          </div>

          <div>
            <span>
              Pajak
            </span>

            <strong>
              {money(
                invoice.tax_total,
                invoice.currency,
              )}
            </strong>
          </div>

          <div
            className={
              styles.grandTotal
            }
          >
            <span>
              Total
            </span>

            <strong>
              {money(
                invoice.total,
                invoice.currency,
              )}
            </strong>
          </div>

          <div>
            <span>
              Sudah Dibayar
            </span>

            <strong>
              {money(
                invoice.paid_amount,
                invoice.currency,
              )}
            </strong>
          </div>

          <div
            className={
              styles.outstanding
            }
          >
            <span>
              Sisa Tagihan
            </span>

            <strong>
              {money(
                invoice.outstanding_amount,
                invoice.currency,
              )}
            </strong>
          </div>
        </div>
      </section>

      {invoice.notes ? (
        <section
          className={
            styles.card
          }
        >
          <header
            className={
              styles.cardHeader
            }
          >
            <strong>
              Catatan
            </strong>
          </header>

          <p
            className={
              styles.notes
            }
          >
            {invoice.notes}
          </p>
        </section>
      ) : null}

      <section
        className={
          styles.card
        }
      >
        <header
          className={
            styles.cardHeader
          }
        >
          <div>
            <strong>
              Riwayat Status
            </strong>

            <span>
              Perubahan lifecycle
              tagihan
            </span>
          </div>
        </header>

        <div
          className={
            styles.timeline
          }
        >
          {invoice.status_history
            ?.length ? (
            invoice.status_history.map(
              (history) => (
                <article
                  className={
                    styles.timelineItem
                  }
                  key={
                    history.id
                  }
                >
                  <span
                    className={
                      styles.timelineDot
                    }
                  />

                  <div>
                    <strong>
                      {
                        history.to_state
                      }
                    </strong>

                    <span>
                      {localDateTime(
                        history.occurred_at,
                      )}
                    </span>

                    {history.reason ? (
                      <p>
                        {
                          history.reason
                        }
                      </p>
                    ) : null}
                  </div>
                </article>
              ),
            )
          ) : (
            <p
              className={
                styles.emptyTimeline
              }
            >
              Belum ada riwayat
              status.
            </p>
          )}
        </div>
      </section>

      {voidOpen ? (
        <div
          className={
            styles.overlay
          }
          role="presentation"
        >
          <section
            className={
              styles.dialog
            }
            role="dialog"
            aria-modal="true"
            aria-labelledby="void-invoice-title"
          >
            <header
              className={
                styles.dialogHeader
              }
            >
              <div>
                <span>
                  Pembatalan
                </span>

                <h2
                  id="void-invoice-title"
                >
                  Batalkan Tagihan?
                </h2>

                <p>
                  Tagihan yang
                  dibatalkan tidak
                  dapat diterbitkan
                  kembali.
                </p>
              </div>

              <button
                type="button"
                className={
                  styles.close
                }
                aria-label="Tutup"
                disabled={
                  actionLoading
                }
                onClick={
                  () =>
                    setVoidOpen(
                      false,
                    )
                }
              >
                <X
                  size={21}
                />
              </button>
            </header>

            <div
              className={
                styles.dialogBody
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
                  required
                  rows={4}
                  maxLength={1000}
                  value={
                    voidReason
                  }
                  placeholder="Contoh: pesanan dibatalkan pelanggan."
                  onChange={
                    (event) =>
                      setVoidReason(
                        event.target.value,
                      )
                  }
                />

                <small>
                  {voidReason.length}
                  /1000
                </small>
              </label>
            </div>

            <footer
              className={
                styles.dialogFooter
              }
            >
              <Button
                type="button"
                variant="ghost"
                disabled={
                  actionLoading
                }
                onClick={
                  () =>
                    setVoidOpen(
                      false,
                    )
                }
              >
                Kembali
              </Button>

              <Button
                type="button"
                loading={
                  actionLoading
                }
                loadingLabel="Membatalkan..."
                leadingIcon={
                  <Ban
                    size={17}
                  />
                }
                onClick={
                  () =>
                    void handleVoid()
                }
              >
                Batalkan Tagihan
              </Button>
            </footer>
          </section>
        </div>
      ) : null}
    </section>
  );
}
