"use client";

import {
  ArrowLeft,
  Ban,
  CalendarDays,
  FileDown,
  FileText,
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
  useRouter,
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

import {
  listInvoicePayments,
  listPaymentCashAccounts,
  recordInvoicePayment,
} from "@/lib/payment/service";

import type {
  Invoice,
} from "@/types/invoice";

import type {
  InvoicePaymentHistoryItem,
  PaymentCashAccount,
  PaymentMethod,
} from "@/types/payment";

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

function paymentMethodLabel(
  method: PaymentMethod,
): string {
  switch (method) {
    case "BANK_TRANSFER":
      return "Transfer Bank";

    case "STATIC_QR":
      return "QR Statis";

    default:
      return method;
  }
}

function paymentStatusLabel(
  status: string,
): string {
  switch (status) {
    case "VERIFIED":
      return "Terverifikasi";

    case "PENDING":
      return "Menunggu verifikasi";

    case "REJECTED":
      return "Ditolak";

    case "REVERSED":
      return "Dibatalkan";

    default:
      return status;
  }
}

function localDateTimeInput(
  date = new Date(),
): string {
  const local =
    new Date(
      date.getTime() -
      date.getTimezoneOffset() *
        60_000,
    );

  return local
    .toISOString()
    .slice(
      0,
      16,
    );
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

  const router =
    useRouter();

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

  const [
    payments,
    setPayments,
  ] = useState<
    InvoicePaymentHistoryItem[]
  >([]);

  const [
    paymentsLoading,
    setPaymentsLoading,
  ] = useState(true);

  const [
    paymentsError,
    setPaymentsError,
  ] = useState<unknown>(
    null,
  );

  const [
    paymentOpen,
    setPaymentOpen,
  ] = useState(false);

  const [
    paymentSaving,
    setPaymentSaving,
  ] = useState(false);

  const [
    paymentError,
    setPaymentError,
  ] = useState<unknown>(
    null,
  );

  const [
    cashAccounts,
    setCashAccounts,
  ] = useState<
    PaymentCashAccount[]
  >([]);

  const [
    cashAccountsLoading,
    setCashAccountsLoading,
  ] = useState(false);

  const [
    cashAccountsError,
    setCashAccountsError,
  ] = useState<unknown>(
    null,
  );

  const [
    paymentCashAccountId,
    setPaymentCashAccountId,
  ] = useState("");

  const [
    paymentAmount,
    setPaymentAmount,
  ] = useState("");

  const [
    paymentPaidAt,
    setPaymentPaidAt,
  ] = useState(
    localDateTimeInput(),
  );

  const [
    paymentMethod,
    setPaymentMethod,
  ] = useState<PaymentMethod>(
    "BANK_TRANSFER",
  );

  const [
    paymentReference,
    setPaymentReference,
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
    let cancelled =
      false;

    async function loadPayments() {
      setPaymentsLoading(
        true,
      );

      setPaymentsError(
        null,
      );

      try {
        const response =
          await listInvoicePayments(
            params.id,
          );

        if (!cancelled) {
          setPayments(
            response.data,
          );
        }
      } catch (caught) {
        if (!cancelled) {
          setPaymentsError(
            caught,
          );
        }
      } finally {
        if (!cancelled) {
          setPaymentsLoading(
            false,
          );
        }
      }
    }

    void loadPayments();

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


  async function refreshPayments() {
    setPaymentsLoading(
      true,
    );

    setPaymentsError(
      null,
    );

    try {
      const response =
        await listInvoicePayments(
          params.id,
        );

      setPayments(
        response.data,
      );
    } catch (caught) {
      setPaymentsError(
        caught,
      );
    } finally {
      setPaymentsLoading(
        false,
      );
    }
  }

  async function loadCashAccounts() {
    setCashAccountsLoading(
      true,
    );

    setCashAccountsError(
      null,
    );

    try {
      const response =
        await listPaymentCashAccounts();

      const sorted =
        [...response.data]
          .sort(
            (a, b) =>
              Number(
                b.is_default,
              ) -
              Number(
                a.is_default,
              ),
          );

      setCashAccounts(
        sorted,
      );

      setPaymentCashAccountId(
        (
          current
        ) =>
          current ||
          sorted[0]?.id ||
          "",
      );
    } catch (caught) {
      setCashAccountsError(
        caught,
      );
    } finally {
      setCashAccountsLoading(
        false,
      );
    }
  }

  async function openPaymentSheet() {
    if (!invoice) {
      return;
    }

    setPaymentError(
      null,
    );

    setPaymentAmount(
      String(
        invoice.outstanding_amount,
      ),
    );

    setPaymentPaidAt(
      localDateTimeInput(),
    );

    setPaymentMethod(
      "BANK_TRANSFER",
    );

    setPaymentReference(
      "",
    );

    setPaymentOpen(
      true,
    );

    if (
      cashAccounts.length ===
      0
    ) {
      await loadCashAccounts();
    }
  }

  async function handleRecordPayment() {
    if (!invoice) {
      return;
    }

    const amount =
      Number(
        paymentAmount,
      );

    const outstanding =
      Number(
        invoice.outstanding_amount,
      );

    if (
      !Number.isFinite(amount) ||
      amount <= 0
    ) {
      setPaymentError(
        new Error(
          "Nominal pembayaran harus lebih dari 0.",
        ),
      );

      return;
    }

    if (
      amount >
      outstanding
    ) {
      setPaymentError(
        new Error(
          "Nominal pembayaran tidak boleh melebihi sisa tagihan.",
        ),
      );

      return;
    }

    if (
      !paymentCashAccountId
    ) {
      setPaymentError(
        new Error(
          "Pilih akun Kas & Bank tujuan.",
        ),
      );

      return;
    }

    const paidAt =
      new Date(
        paymentPaidAt,
      );

    if (
      Number.isNaN(
        paidAt.getTime(),
      )
    ) {
      setPaymentError(
        new Error(
          "Tanggal pembayaran belum valid.",
        ),
      );

      return;
    }

    setPaymentSaving(
      true,
    );

    setPaymentError(
      null,
    );

    setActionError(
      null,
    );

    try {
      const response =
        await recordInvoicePayment(
          invoice.id,
          {
            cash_account_id:
              paymentCashAccountId,

            amount:
              paymentAmount,

            paid_at:
              paidAt.toISOString(),

            method:
              paymentMethod,

            reference:
              paymentReference.trim() ||
              null,
          },
        );

      setInvoice(
        response.data.invoice,
      );

      setPaymentOpen(
        false,
      );

      setActionSuccess(
        response.message ??
        "Pembayaran berhasil dicatat.",
      );

      await refreshPayments();
    } catch (caught) {
      setPaymentError(
        caught,
      );
    } finally {
      setPaymentSaving(
        false,
      );
    }
  }

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

  const canEdit =
    invoice.status ===
      "DRAFT" &&
    invoice.source_quotation_id ===
      null &&
    invoice.source_quotation_version_id ===
      null;

  const canVoid =
    invoice.status ===
      "DRAFT" ||
    invoice.status ===
      "ISSUED";

  const canRecordPayment =
    [
      "ISSUED",
      "PARTIALLY_PAID",
    ].includes(
      invoice.status,
    ) &&
    Number(
      invoice.outstanding_amount,
    ) > 0;

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
          {canEdit ? (
            <Button
              type="button"
              variant="secondary"
              disabled={
                actionLoading
              }
              leadingIcon={
                <Pencil
                  size={17}
                />
              }
              onClick={
                () =>
                  router.push(
                    `/app/tagihan?edit=${invoice.id}`
                  )
              }
            >
              Ubah Tagihan
            </Button>
          ) : null}

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

                    {item.pricing_display
                      ?.formula ? (
                      <div
                        className={
                          styles.pricingDetail
                        }
                      >
                        <span>
                          {
                            item
                              .pricing_display
                              .formula
                          }
                        </span>

                        {item
                          .pricing_display
                          .result_text ? (
                          <strong>
                            {
                              item
                                .pricing_display
                                .result_text
                            }
                          </strong>
                        ) : null}
                      </div>
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
                      {item.pricing_display
                        ?.display_quantity ??
                        item.quantity}{" "}
                      {item.pricing_display
                        ?.display_unit ??
                        item.unit_symbol ??
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
              Pembayaran
            </strong>

            <span>
              {payments.length > 0
                ? `${payments.length} pembayaran tercatat`
                : "Belum ada pembayaran"}
            </span>
          </div>

          {canRecordPayment ? (
            <Button
              type="button"
              disabled={
                paymentSaving
              }
              leadingIcon={
                <ReceiptText
                  size={17}
                />
              }
              onClick={
                () =>
                  void openPaymentSheet()
              }
            >
              Catat Pembayaran
            </Button>
          ) : null}
        </header>

        <div
          className={
            styles.paymentOverview
          }
        >
          <div>
            <span>
              Total Tagihan
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
              styles.paymentOutstanding
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

        <div
          className={
            styles.paymentList
          }
        >
          {paymentsLoading ? (
            <p
              className={
                styles.paymentEmpty
              }
            >
              Memuat riwayat pembayaran...
            </p>
          ) : paymentsError ? (
            <p
              className={
                styles.paymentEmpty
              }
            >
              Riwayat pembayaran belum dapat dimuat.{" "}
              {apiErrorMessage(
                paymentsError,
              )}
            </p>
          ) : payments.length ===
            0 ? (
            <p
              className={
                styles.paymentEmpty
              }
            >
              Belum ada pembayaran untuk tagihan ini.
            </p>
          ) : (
            payments.map(
              (
                payment,
              ) => (
                <article
                  key={
                    payment.allocation_id
                  }
                  className={
                    styles.paymentItem
                  }
                >
                  <div
                    className={
                      styles.paymentItemTop
                    }
                  >
                    <div>
                      <strong>
                        {money(
                          payment.allocated_amount,
                          invoice.currency,
                        )}
                      </strong>

                      <span>
                        {paymentMethodLabel(
                          payment.method,
                        )}
                      </span>
                    </div>

                    <span
                      className={
                        styles.paymentStatus
                      }
                    >
                      {paymentStatusLabel(
                        payment.status,
                      )}
                    </span>
                  </div>

                  <div
                    className={
                      styles.paymentMeta
                    }
                  >
                    <span>
                      {localDateTime(
                        payment.paid_at,
                      )}
                    </span>

                    {payment.reference ? (
                      <span>
                        Ref.{" "}
                        {payment.reference}
                      </span>
                    ) : null}

                    <span>
                      {payment.has_evidence
                        ? "Ada bukti pembayaran"
                        : "Tanpa bukti pembayaran"}
                    </span>
                  </div>
                </article>
              ),
            )
          )}
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

      {paymentOpen ? (
        <div
          className={
            styles.sheetOverlay
          }
          onMouseDown={
            () => {
              if (
                !paymentSaving
              ) {
                setPaymentOpen(
                  false,
                );
              }
            }
          }
        >
          <form
            className={
              styles.paymentSheet
            }
            role="dialog"
            aria-modal="true"
            aria-labelledby="payment-title"
            onMouseDown={
              (
                event,
              ) => {
                event.stopPropagation();
              }
            }
            onSubmit={
              (
                event,
              ) => {
                event.preventDefault();

                void handleRecordPayment();
              }
            }
          >
            <header
              className={
                styles.dialogHeader
              }
            >
              <div>
                <span>
                  Pembayaran
                </span>

                <h2
                  id="payment-title"
                >
                  Catat Pembayaran
                </h2>

                <p>
                  Pembayaran akan langsung diverifikasi dan dialokasikan ke tagihan ini.
                </p>
              </div>

              <button
                type="button"
                className={
                  styles.close
                }
                aria-label="Tutup"
                disabled={
                  paymentSaving
                }
                onClick={
                  () =>
                    setPaymentOpen(
                      false,
                    )
                }
              >
                <X
                  size={19}
                />
              </button>
            </header>

            <div
              className={
                styles.paymentSheetBody
              }
            >
              <div
                className={
                  styles.paymentSheetSummary
                }
              >
                <span>
                  Sisa tagihan
                </span>

                <strong>
                  {money(
                    invoice.outstanding_amount,
                    invoice.currency,
                  )}
                </strong>
              </div>

              <div
                className={
                  styles.paymentForm
                }
              >
                <label
                  className={
                    styles.field
                  }
                >
                  <span>
                    Nominal
                  </span>

                  <input
                    type="number"
                    inputMode="decimal"
                    min="0.01"
                    step="0.01"
                    value={
                      paymentAmount
                    }
                    disabled={
                      paymentSaving
                    }
                    onChange={
                      (
                        event,
                      ) =>
                        setPaymentAmount(
                          event.target.value,
                        )
                    }
                  />

                  <small>
                    Maksimal{" "}
                    {money(
                      invoice.outstanding_amount,
                      invoice.currency,
                    )}
                  </small>
                </label>

                <label
                  className={
                    styles.field
                  }
                >
                  <span>
                    Tanggal Pembayaran
                  </span>

                  <input
                    type="datetime-local"
                    value={
                      paymentPaidAt
                    }
                    disabled={
                      paymentSaving
                    }
                    onChange={
                      (
                        event,
                      ) =>
                        setPaymentPaidAt(
                          event.target.value,
                        )
                    }
                  />
                </label>

                <label
                  className={
                    styles.field
                  }
                >
                  <span>
                    Metode
                  </span>

                  <select
                    value={
                      paymentMethod
                    }
                    disabled={
                      paymentSaving
                    }
                    onChange={
                      (
                        event,
                      ) =>
                        setPaymentMethod(
                          event.target
                            .value as
                            PaymentMethod,
                        )
                    }
                  >
                    <option
                      value="BANK_TRANSFER"
                    >
                      Transfer Bank
                    </option>

                    <option
                      value="STATIC_QR"
                    >
                      QR Statis
                    </option>
                  </select>
                </label>

                <label
                  className={
                    styles.field
                  }
                >
                  <span>
                    Akun Kas / Bank Tujuan
                  </span>

                  <select
                    value={
                      paymentCashAccountId
                    }
                    disabled={
                      paymentSaving ||
                      cashAccountsLoading
                    }
                    onChange={
                      (
                        event,
                      ) =>
                        setPaymentCashAccountId(
                          event.target.value,
                        )
                    }
                  >
                    <option
                      value=""
                    >
                      {cashAccountsLoading
                        ? "Memuat akun..."
                        : "Pilih akun"}
                    </option>

                    {cashAccounts.map(
                      (
                        account,
                      ) => (
                        <option
                          key={
                            account.id
                          }
                          value={
                            account.id
                          }
                        >
                          {account.name}
                          {account.is_default
                            ? " • Default"
                            : ""}
                        </option>
                      ),
                    )}
                  </select>

                  {cashAccountsError ? (
                    <small
                      className={
                        styles.fieldError
                      }
                    >
                      {apiErrorMessage(
                        cashAccountsError,
                      )}
                    </small>
                  ) : cashAccounts.length ===
                      0 &&
                    !cashAccountsLoading ? (
                    <small>
                      Belum ada akun Kas & Bank aktif.
                    </small>
                  ) : null}
                </label>

                <label
                  className={
                    styles.field
                  }
                >
                  <span>
                    Referensi
                  </span>

                  <input
                    type="text"
                    maxLength={190}
                    placeholder="Opsional, mis. nomor transfer"
                    value={
                      paymentReference
                    }
                    disabled={
                      paymentSaving
                    }
                    onChange={
                      (
                        event,
                      ) =>
                        setPaymentReference(
                          event.target.value,
                        )
                    }
                  />
                </label>

                {paymentError ? (
                  <div
                    className={
                      styles.formError
                    }
                    role="alert"
                  >
                    {apiErrorMessage(
                      paymentError,
                    )}
                  </div>
                ) : null}

                <div
                  className={
                    styles.paymentEvidenceHint
                  }
                >
                  Bukti transfer bersifat opsional dan dapat ditambahkan setelah pembayaran tersimpan.
                </div>
              </div>
            </div>

            <footer
              className={
                styles.dialogFooter
              }
            >
              <Button
                type="button"
                variant="secondary"
                disabled={
                  paymentSaving
                }
                onClick={
                  () =>
                    setPaymentOpen(
                      false,
                    )
                }
              >
                Batal
              </Button>

              <Button
                type="submit"
                loading={
                  paymentSaving
                }
                loadingLabel="Menyimpan..."
                disabled={
                  cashAccounts.length ===
                    0
                }
              >
                Simpan Pembayaran
              </Button>
            </footer>
          </form>
        </div>
      ) : null}

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
