"use client";

import Image from "next/image";

import {
  AlertCircle,
  Building2,
  CalendarDays,
  Check,
  CheckCircle2,
  Clipboard,
  CreditCard,
  FileDown,
  FileText,
  Landmark,
  QrCode,
  RefreshCcw,
  ShieldCheck,
  UserRound,
} from "lucide-react";

import {
  type FormEvent,
  useEffect,
  useState,
} from "react";

import {
  useParams,
} from "next/navigation";

import {
  getPublicInvoice,
  PublicInvoiceApiError,
  submitPublicInvoicePayment,
} from "@/lib/invoice/public-service";

import {
  SIGNOVA_MARKETING_URL,
} from "@/lib/signova-marketing";

import type {
  PublicInvoice,
  PublicInvoicePaymentResult,
  PublicInvoiceStatus,
} from "@/types/public-invoice";

import styles from "./public-invoice.module.css";


const STATUS_LABELS:
  Record<
    PublicInvoiceStatus,
    string
  > = {
  DRAFT:
    "Draf",

  ISSUED:
    "Diterbitkan",

  PARTIALLY_PAID:
    "Dibayar Sebagian",

  PAID:
    "Lunas",

  OVERDUE:
    "Jatuh Tempo",

  VOID:
    "Dibatalkan",
};


function money(
  value:
    | number
    | string,
  currency:
    string,
): string {
  const number =
    Number(value);

  return new Intl.NumberFormat(
    "id-ID",
    {
      style:
        "currency",

      currency:
        currency || "IDR",

      maximumFractionDigits:
        2,
    },
  ).format(
    Number.isFinite(number)
      ? number
      : 0,
  );
}


function dateLabel(
  value:
    | string
    | null,
): string {
  if (!value) {
    return "-";
  }

  const parsed =
    new Date(
      `${value}T00:00:00`,
    );

  if (
    Number.isNaN(
      parsed.getTime(),
    )
  ) {
    return value;
  }

  return new Intl.DateTimeFormat(
    "id-ID",
    {
      day:
        "2-digit",

      month:
        "long",

      year:
        "numeric",
    },
  ).format(parsed);
}


function quantityLabel(
  quantity:
    | string
    | number,
): string {
  const number =
    Number(quantity);

  return Number.isFinite(number)
    ? new Intl.NumberFormat(
        "id-ID",
        {
          maximumFractionDigits:
            4,
        },
      ).format(number)
    : String(quantity);
}


function todayInputValue(): string {
  const now =
    new Date();

  const offset =
    now.getTimezoneOffset() *
    60_000;

  return new Date(
    now.getTime() - offset,
  )
    .toISOString()
    .slice(
      0,
      10,
    );
}


function paymentFormDefaults(
  invoice: PublicInvoice,
) {
  const accounts =
    invoice
      .payment_options
      .bank_accounts;

  const defaultAccount =
    accounts.find(
      (account) =>
        account.is_default,
    ) ??
    accounts[0];

  return {
    amount:
      invoice.payment_allowed
        ? String(
            invoice
              .outstanding_amount ??
              "",
          )
        : "",

    accountToken:
      invoice.payment_allowed
        ? defaultAccount
            ?.payment_account_token ??
          ""
        : "",

    date:
      invoice.payment_allowed
        ? todayInputValue()
        : "",
  };
}


function brandInitial(
  value:
    | string
    | null,
): string {
  const normalized =
    value?.trim();

  return normalized
    ? normalized
        .charAt(0)
        .toUpperCase()
    : "S";
}


export function PublicInvoiceView() {
  const params =
    useParams<{
      token:
        string;
    }>();

  const token =
    typeof params.token ===
      "string"
      ? params.token
      : "";

  const [
    invoice,
    setInvoice,
  ] = useState<
    PublicInvoice | null
  >(null);

  const [
    loading,
    setLoading,
  ] = useState(true);

  const [
    error,
    setError,
  ] = useState<
    string | null
  >(null);

  const [
    unavailable,
    setUnavailable,
  ] = useState(false);

  const [
    copiedAccount,
    setCopiedAccount,
  ] = useState<
    string | null
  >(null);

  const [
    paymentAmount,
    setPaymentAmount,
  ] = useState("");

  const [
    paymentAccountToken,
    setPaymentAccountToken,
  ] = useState("");

  const [
    paymentDate,
    setPaymentDate,
  ] = useState("");

  const [
    paymentReference,
    setPaymentReference,
  ] = useState("");

  const [
    paymentEvidence,
    setPaymentEvidence,
  ] = useState<
    File | null
  >(null);

  const [
    paymentSubmitting,
    setPaymentSubmitting,
  ] = useState(false);

  const [
    paymentSubmitError,
    setPaymentSubmitError,
  ] = useState<
    string | null
  >(null);

  const [
    paymentSubmitted,
    setPaymentSubmitted,
  ] = useState<
    PublicInvoicePaymentResult | null
  >(null);


  async function loadInvoice() {
    if (!token) {
      setUnavailable(true);

      setError(
        "Link Tagihan tidak valid.",
      );

      setLoading(false);

      return;
    }

    setLoading(true);
    setError(null);
    setUnavailable(false);

    try {
      const response =
        await getPublicInvoice(
          token,
        );

      const nextInvoice =
        response.data;

      const defaults =
        paymentFormDefaults(
          nextInvoice,
        );

      setInvoice(
        nextInvoice,
      );

      setPaymentAmount(
        defaults.amount,
      );

      setPaymentAccountToken(
        defaults.accountToken,
      );

      setPaymentDate(
        defaults.date,
      );

      setPaymentReference("");
      setPaymentEvidence(null);
      setPaymentSubmitError(null);
      setPaymentSubmitted(null);
    } catch (caught) {
      if (
        caught instanceof
          PublicInvoiceApiError &&
        caught.status === 404
      ) {
        setUnavailable(true);
        setInvoice(null);

        setError(
          "Link Tagihan tidak tersedia, sudah kedaluwarsa, atau telah diganti.",
        );
      } else {
        setError(
          caught instanceof Error
            ? caught.message
            : "Tagihan belum berhasil dimuat.",
        );
      }
    } finally {
      setLoading(false);
    }
  }


  useEffect(() => {
    let cancelled = false;

    if (!token) {
      queueMicrotask(
        () => {
          if (cancelled) {
            return;
          }

          setUnavailable(true);
          setInvoice(null);

          setError(
            "Link Tagihan tidak valid.",
          );

          setLoading(false);
        },
      );

      return () => {
        cancelled = true;
      };
    }

    void getPublicInvoice(
      token,
    )
      .then(
        (response) => {
          if (cancelled) {
            return;
          }

          const nextInvoice =
            response.data;

          const defaults =
            paymentFormDefaults(
              nextInvoice,
            );

          setInvoice(
            nextInvoice,
          );

          setPaymentAmount(
            defaults.amount,
          );

          setPaymentAccountToken(
            defaults.accountToken,
          );

          setPaymentDate(
            defaults.date,
          );

          setPaymentReference("");
          setPaymentEvidence(null);
          setPaymentSubmitError(null);
          setPaymentSubmitted(null);

          setUnavailable(false);
          setError(null);
        },
      )
      .catch(
        (caught) => {
          if (cancelled) {
            return;
          }

          if (
            caught instanceof
              PublicInvoiceApiError &&
            caught.status === 404
          ) {
            setUnavailable(true);
            setInvoice(null);

            setError(
              "Link Tagihan tidak tersedia, sudah kedaluwarsa, atau telah diganti.",
            );

            return;
          }

          setError(
            caught instanceof Error
              ? caught.message
              : "Tagihan belum berhasil dimuat.",
          );
        },
      )
      .finally(
        () => {
          if (!cancelled) {
            setLoading(false);
          }
        },
      );

    return () => {
      cancelled = true;
    };
  }, [
    token,
  ]);


  async function copyAccountNumber(
    accountNumber: string,
  ) {
    try {
      await navigator.clipboard
        .writeText(
          accountNumber,
        );

      setCopiedAccount(
        accountNumber,
      );

      window.setTimeout(
        () => {
          setCopiedAccount(
            (
              current,
            ) =>
              current ===
              accountNumber
                ? null
                : current,
          );
        },
        2200,
      );
    } catch {
      setCopiedAccount(
        null,
      );
    }
  }


  async function submitPayment(
    event:
      FormEvent<HTMLFormElement>,
  ) {
    event.preventDefault();

    if (
      !invoice ||
      !token
    ) {
      return;
    }

    setPaymentSubmitError(null);

    if (!paymentAccountToken) {
      setPaymentSubmitError(
        "Pilih rekening tujuan pembayaran.",
      );

      return;
    }

    const amount =
      Number(
        paymentAmount,
      );

    if (
      !Number.isFinite(amount) ||
      amount <= 0
    ) {
      setPaymentSubmitError(
        "Nominal pembayaran tidak valid.",
      );

      return;
    }

    if (!paymentDate) {
      setPaymentSubmitError(
        "Tanggal pembayaran wajib diisi.",
      );

      return;
    }

    if (!paymentEvidence) {
      setPaymentSubmitError(
        "Bukti pembayaran wajib diunggah.",
      );

      return;
    }

    if (
      paymentEvidence.size >
      5 * 1024 * 1024
    ) {
      setPaymentSubmitError(
        "Ukuran bukti pembayaran maksimal 5 MB.",
      );

      return;
    }

    setPaymentSubmitting(true);

    try {
      const response =
        await submitPublicInvoicePayment(
          token,
          {
            paymentAccountToken,
            amount:
              paymentAmount,
            paidAt:
              paymentDate,
            reference:
              paymentReference,
            evidence:
              paymentEvidence,
          },
        );

      setPaymentSubmitted(
        response.data,
      );

      setPaymentSubmitError(
        null,
      );
    } catch (caught) {
      setPaymentSubmitError(
        caught instanceof Error
          ? caught.message
          : "Konfirmasi pembayaran belum berhasil dikirim.",
      );
    } finally {
      setPaymentSubmitting(
        false,
      );
    }
  }


  if (loading) {
    return (
      <main
        className={
          styles.page
        }
      >
        <div
          className={
            styles.stateCard
          }
        >
          <div
            className={
              styles.loader
            }
            aria-hidden="true"
          />

          <h1>
            Membuka Tagihan
          </h1>

          <p>
            Dokumen sedang
            dipersiapkan.
          </p>
        </div>
      </main>
    );
  }


  if (
    unavailable ||
    !invoice
  ) {
    return (
      <main
        className={
          styles.page
        }
      >
        <div
          className={
            styles.stateCard
          }
        >
          <span
            className={
              styles.stateIcon
            }
          >
            <AlertCircle
              size={28}
            />
          </span>

          <h1>
            Tagihan Tidak Tersedia
          </h1>

          <p>
            {error ??
              "Link ini tidak dapat digunakan."}
          </p>

          <p
            className={
              styles.stateHint
            }
          >
            Silakan hubungi
            pengirim Tagihan
            untuk mendapatkan
            link terbaru.
          </p>
        </div>
      </main>
    );
  }


  const branding =
    invoice.branding;

  const businessName =
    branding.business_name ??
    "SIGNOVA";

  const paid =
    invoice.status ===
    "PAID";

  const voided =
    invoice.status ===
    "VOID";

  const hasPaymentMethod =
    invoice.payment_options
      .bank_transfer_enabled ||
    invoice.payment_options
      .static_qr_enabled;

  const bankAccounts =
    invoice
      .payment_options
      .bank_accounts;

  const canConfirmBankPayment =
    invoice.payment_allowed &&
    invoice
      .payment_options
      .bank_transfer_enabled &&
    bankAccounts.length > 0;

  const selectedPaymentAccount =
    bankAccounts.find(
      (account) =>
        account
          .payment_account_token ===
        paymentAccountToken,
    ) ?? null;


  return (
    <main
      className={
        styles.page
      }
    >
      <header
        className={
          styles.brandHeader
        }
      >
        <div
          className={
            styles.systemBrand
          }
        >
          <span
            className={
              styles.systemMark
            }
          >
            S
          </span>

          <div>
            <strong>
              SIGNOVA
            </strong>

            <small>
              Tagihan Digital
            </small>
          </div>
        </div>

        <span
          className={
            styles.secureBadge
          }
        >
          <ShieldCheck
            size={15}
          />

          Link Aman
        </span>
      </header>


      <section
        className={
          styles.document
        }
      >
        <header
          className={
            styles.businessHeader
          }
        >
          <div
            className={
              styles.businessBrand
            }
          >
            {branding.logo_data_uri ? (
              <Image
                src={
                  branding.logo_data_uri
                }
                alt={`Logo ${businessName}`}
                width={64}
                height={64}
                unoptimized
                className={
                  styles.businessLogo
                }
              />
            ) : (
              <span
                className={
                  styles.businessLogoFallback
                }
              >
                {brandInitial(
                  businessName,
                )}
              </span>
            )}

            <div
              className={
                styles.businessIdentity
              }
            >
              <span>
                TAGIHAN DARI
              </span>

              <h2>
                {businessName}
              </h2>

              {branding.address ? (
                <p>
                  {branding.address}
                </p>
              ) : null}

              {branding.phone ||
              branding.email ? (
                <small>
                  {[
                    branding.phone,
                    branding.email,
                  ]
                    .filter(Boolean)
                    .join(" • ")}
                </small>
              ) : null}
            </div>
          </div>
        </header>


        <div
          className={
            styles.documentHero
          }
        >
          <div>
            <span
              className={
                styles.eyebrow
              }
            >
              TAGIHAN
            </span>

            <h1>
              {
                invoice
                  .invoice_number
              }
            </h1>
          </div>

          <span
            className={`${styles.statusBadge} ${
              styles[
                `status${invoice.status}`
              ] ?? ""
            }`}
          >
            {
              STATUS_LABELS[
                invoice.status
              ]
            }
          </span>
        </div>


        <div
          className={
            styles.metaGrid
          }
        >
          <article
            className={
              styles.metaCard
            }
          >
            <UserRound
              size={18}
            />

            <div>
              <span>
                Pelanggan
              </span>

              <strong>
                {
                  invoice
                    .customer
                    .name
                }
              </strong>
            </div>
          </article>

          <article
            className={
              styles.metaCard
            }
          >
            <CalendarDays
              size={18}
            />

            <div>
              <span>
                Tanggal Terbit
              </span>

              <strong>
                {dateLabel(
                  invoice
                    .issued_at,
                )}
              </strong>
            </div>
          </article>

          <article
            className={
              styles.metaCard
            }
          >
            <CalendarDays
              size={18}
            />

            <div>
              <span>
                Jatuh Tempo
              </span>

              <strong>
                {dateLabel(
                  invoice
                    .due_at,
                )}
              </strong>
            </div>
          </article>
        </div>


        <section
          className={
            styles.section
          }
        >
          <div
            className={
              styles.sectionTitle
            }
          >
            <FileText
              size={18}
            />

            <div>
              <h2>
                Rincian Tagihan
              </h2>

              <p>
                Rincian pekerjaan dan
                nilai Tagihan yang
                diterbitkan kepada Anda.
              </p>
            </div>
          </div>

          <div
            className={
              styles.itemList
            }
          >
            {invoice.items.length >
            0 ? (
              invoice.items.map(
                (
                  item,
                  index,
                ) => (
                  <article
                    key={`${item.name}-${index}`}
                    className={
                      styles.itemCard
                    }
                  >
                    <div
                      className={
                        styles.itemHeading
                      }
                    >
                      <div>
                        <span>
                          Item{" "}
                          {index + 1}
                        </span>

                        <h3>
                          {
                            item.name
                          }
                        </h3>
                      </div>

                      <strong>
                        {money(
                          item.amount,
                          invoice.currency,
                        )}
                      </strong>
                    </div>

                    {item.description ? (
                      <p
                        className={
                          styles.itemDescription
                        }
                      >
                        {
                          item.description
                        }
                      </p>
                    ) : null}

                    <div
                      className={
                        styles.itemFacts
                      }
                    >
                      <div>
                        <span>
                          Jumlah
                        </span>

                        <strong>
                          {quantityLabel(
                            item.quantity,
                          )}
                        </strong>
                      </div>

                      <div>
                        <span>
                          Harga Satuan
                        </span>

                        <strong>
                          {money(
                            item.unit_price,
                            invoice.currency,
                          )}
                        </strong>
                      </div>

                      {Number(
                        item.discount_amount,
                      ) > 0 ? (
                        <div>
                          <span>
                            Diskon
                          </span>

                          <strong>
                            {money(
                              item.discount_amount,
                              invoice.currency,
                            )}
                          </strong>
                        </div>
                      ) : null}

                      {Number(
                        item.tax_amount,
                      ) > 0 ? (
                        <div>
                          <span>
                            Pajak
                          </span>

                          <strong>
                            {money(
                              item.tax_amount,
                              invoice.currency,
                            )}
                          </strong>
                        </div>
                      ) : null}
                    </div>
                  </article>
                ),
              )
            ) : (
              <div
                className={
                  styles.emptyItems
                }
              >
                Tidak ada rincian item
                yang ditampilkan.
              </div>
            )}
          </div>
        </section>


        <section
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

          {Number(
            invoice.discount_total,
          ) > 0 ? (
            <div>
              <span>
                Diskon
              </span>

              <strong>
                -
                {money(
                  invoice.discount_total,
                  invoice.currency,
                )}
              </strong>
            </div>
          ) : null}

          {Number(
            invoice.tax_total,
          ) > 0 ? (
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
          ) : null}

          <div
            className={
              styles.grandTotal
            }
          >
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
        </section>


        <section
          className={
            styles.receivableGrid
          }
        >
          <article>
            <span>
              Total Tagihan
            </span>

            <strong>
              {money(
                invoice.total,
                invoice.currency,
              )}
            </strong>
          </article>

          <article>
            <span>
              Sudah Dibayar
            </span>

            <strong>
              {money(
                invoice.paid_amount,
                invoice.currency,
              )}
            </strong>
          </article>

          <article
            className={
              styles.outstandingCard
            }
          >
            <span>
              Sisa Tagihan
            </span>

            <strong>
              {money(
                invoice
                  .outstanding_amount,
                invoice.currency,
              )}
            </strong>
          </article>
        </section>


        <section
          className={
            styles.documentActions
          }
        >
          <a
            href={`/api/public/v1/invoices/${encodeURIComponent(
              token,
            )}/pdf`}
            className={
              styles.pdfButton
            }
            download
          >
            <span
              className={
                styles.pdfButtonIcon
              }
            >
              <FileDown
                size={19}
              />
            </span>

            <span>
              <strong>
                Unduh PDF Tagihan
              </strong>

              <small>
                PDF dibuat dari status Tagihan terbaru.
              </small>
            </span>
          </a>
        </section>


        {paid ? (
          <section
            className={
              styles.paidState
            }
          >
            <CheckCircle2
              size={27}
            />

            <div>
              <span>
                LUNAS
              </span>

              <strong>
                Pembayaran telah selesai
              </strong>

              <p>
                Tagihan ini telah
                dibayar penuh. Tidak ada
                pembayaran tambahan yang
                diperlukan.
              </p>
            </div>
          </section>
        ) : null}


        {voided ? (
          <section
            className={
              styles.voidState
            }
          >
            <AlertCircle
              size={24}
            />

            <div>
              <strong>
                Tagihan dibatalkan
              </strong>

              <p>
                Tagihan ini tidak lagi
                menerima pembayaran.
                Hubungi pengirim jika
                Anda memerlukan
                informasi lebih lanjut.
              </p>
            </div>
          </section>
        ) : null}


        {invoice.payment_allowed ? (
          <section
            className={
              styles.paymentSection
            }
          >
            <div
              className={
                styles.sectionTitle
              }
            >
              <CreditCard
                size={18}
              />

              <div>
                <h2>
                  Cara Pembayaran
                </h2>

                <p>
                  Gunakan salah satu
                  metode pembayaran di
                  bawah ini.
                </p>
              </div>
            </div>

            {invoice
              .payment_options
              .bank_transfer_enabled ? (
              <div
                className={
                  styles.bankList
                }
              >
                {invoice
                  .payment_options
                  .bank_accounts
                  .map(
                    (
                      account,
                      index,
                    ) => (
                      <article
                        key={`${account.bank_name ?? account.name}-${account.account_number ?? index}`}
                        className={
                          styles.bankAccount
                        }
                      >
                        <div
                          className={
                            styles.bankHeading
                          }
                        >
                          <span
                            className={
                              styles.bankIcon
                            }
                          >
                            <Landmark
                              size={18}
                            />
                          </span>

                          <div>
                            <strong>
                              {
                                account.bank_name ??
                                account.name
                              }
                            </strong>

                            {account.is_default ? (
                              <span>
                                Rekening utama
                              </span>
                            ) : null}
                          </div>
                        </div>

                        {account.account_number ? (
                          <div
                            className={
                              styles.accountNumber
                            }
                          >
                            <div>
                              <span>
                                Nomor Rekening
                              </span>

                              <strong>
                                {
                                  account
                                    .account_number
                                }
                              </strong>
                            </div>

                            <button
                              type="button"
                              className={
                                styles.copyButton
                              }
                              onClick={
                                () =>
                                  void copyAccountNumber(
                                    account
                                      .account_number!,
                                  )
                              }
                            >
                              {copiedAccount ===
                              account.account_number ? (
                                <>
                                  <Check
                                    size={14}
                                  />
                                  Tersalin
                                </>
                              ) : (
                                <>
                                  <Clipboard
                                    size={14}
                                  />
                                  Salin
                                </>
                              )}
                            </button>
                          </div>
                        ) : null}

                        {account.account_name ? (
                          <div
                            className={
                              styles.accountOwner
                            }
                          >
                            <span>
                              Atas nama
                            </span>

                            <strong>
                              {
                                account
                                  .account_name
                              }
                            </strong>
                          </div>
                        ) : null}
                      </article>
                    ),
                  )}
              </div>
            ) : null}


            {invoice
              .payment_options
              .static_qr_enabled &&
            invoice
              .payment_options
              .static_qr_data_uri ? (
              <article
                className={
                  styles.qrCard
                }
              >
                <div
                  className={
                    styles.qrHeading
                  }
                >
                  <QrCode
                    size={19}
                  />

                  <div>
                    <strong>
                      QR Pembayaran
                    </strong>

                    <span>
                      Pindai QR untuk
                      melakukan
                      pembayaran.
                    </span>
                  </div>
                </div>

                <Image
                  src={
                    invoice
                      .payment_options
                      .static_qr_data_uri
                  }
                  alt="QR pembayaran"
                  width={260}
                  height={260}
                  unoptimized
                  className={
                    styles.qrImage
                  }
                />
              </article>
            ) : null}


            {invoice
              .payment_options
              .partial_payment_enabled ? (
              <div
                className={
                  styles.partialHint
                }
              >
                <Building2
                  size={17}
                />

                <span>
                  Pembayaran sebagian
                  diperbolehkan untuk
                  Tagihan ini. Isi nominal
                  sesuai jumlah yang telah
                  ditransfer.
                </span>
              </div>
            ) : null}


            {canConfirmBankPayment ? (
              <section
                className={
                  styles
                    .confirmationSection
                }
              >
                <div
                  className={
                    styles
                      .confirmationHeader
                  }
                >
                  <CheckCircle2
                    size={19}
                  />

                  <div>
                    <h3>
                      Konfirmasi Pembayaran
                    </h3>

                    <p>
                      Sudah melakukan
                      transfer? Kirim
                      detail dan bukti
                      pembayaran untuk
                      diverifikasi.
                    </p>
                  </div>
                </div>

                {paymentSubmitted ? (
                  <div
                    className={
                      styles.paymentSuccess
                    }
                    role="status"
                  >
                    <CheckCircle2
                      size={24}
                    />

                    <div>
                      <strong>
                        Konfirmasi pembayaran
                        telah dikirim
                      </strong>

                      <span>
                        {
                          money(
                            paymentSubmitted
                              .amount,
                            paymentSubmitted
                              .currency,
                          )
                        }
                      </span>

                      {selectedPaymentAccount ? (
                        <small>
                          {
                            selectedPaymentAccount
                              .bank_name ??
                            selectedPaymentAccount
                              .name
                          }
                          {
                            selectedPaymentAccount
                              .account_number
                              ? ` • ${selectedPaymentAccount.account_number}`
                              : ""
                          }
                        </small>
                      ) : null}

                      <small>
                        Tanggal pembayaran:{" "}
                        {
                          dateLabel(
                            paymentSubmitted
                              .paid_at,
                          )
                        }
                      </small>

                      <div
                        className={
                          styles.pendingBadge
                        }
                      >
                        Menunggu Verifikasi
                      </div>

                      <p>
                        Pembayaran belum
                        mengubah status
                        Tagihan sampai
                        diverifikasi oleh{" "}
                        {businessName}.
                      </p>
                    </div>
                  </div>
                ) : (
                  <form
                    className={
                      styles.paymentForm
                    }
                    onSubmit={
                      submitPayment
                    }
                  >
                    <div
                      className={
                        styles.formGrid
                      }
                    >
                      <label
                        className={
                          styles.field
                        }
                      >
                        <span>
                          Nominal Pembayaran
                          <b>*</b>
                        </span>

                        <div
                          className={
                            styles.amountInput
                          }
                        >
                          <span>
                            Rp
                          </span>

                          <input
                            type="number"
                            inputMode="decimal"
                            min="0.01"
                            step="0.01"
                            value={
                              paymentAmount
                            }
                            readOnly={
                              !invoice
                                .payment_options
                                .partial_payment_enabled
                            }
                            onChange={
                              (event) =>
                                setPaymentAmount(
                                  event
                                    .target
                                    .value,
                                )
                            }
                            required
                          />
                        </div>

                        <small>
                          Sisa Tagihan:{" "}
                          {
                            money(
                              invoice
                                .outstanding_amount,
                              invoice
                                .currency,
                            )
                          }
                        </small>
                      </label>

                      <label
                        className={
                          styles.field
                        }
                      >
                        <span>
                          Rekening Tujuan
                          <b>*</b>
                        </span>

                        <select
                          value={
                            paymentAccountToken
                          }
                          onChange={
                            (event) =>
                              setPaymentAccountToken(
                                event
                                  .target
                                  .value,
                              )
                          }
                          required
                        >
                          <option value="">
                            Pilih rekening
                          </option>

                          {bankAccounts.map(
                            (account) => (
                              <option
                                key={
                                  account
                                    .payment_account_token
                                }
                                value={
                                  account
                                    .payment_account_token
                                }
                              >
                                {
                                  account
                                    .bank_name ??
                                  account
                                    .name
                                }
                                {
                                  account
                                    .account_number
                                    ? ` • ${account.account_number}`
                                    : ""
                                }
                              </option>
                            ),
                          )}
                        </select>
                      </label>

                      <label
                        className={
                          styles.field
                        }
                      >
                        <span>
                          Tanggal Pembayaran
                          <b>*</b>
                        </span>

                        <input
                          type="date"
                          value={
                            paymentDate
                          }
                          max={
                            todayInputValue()
                          }
                          onChange={
                            (event) =>
                              setPaymentDate(
                                event
                                  .target
                                  .value,
                              )
                          }
                          required
                        />
                      </label>

                      <label
                        className={
                          styles.field
                        }
                      >
                        <span>
                          Referensi Transfer
                        </span>

                        <input
                          type="text"
                          maxLength={255}
                          value={
                            paymentReference
                          }
                          placeholder="Opsional"
                          onChange={
                            (event) =>
                              setPaymentReference(
                                event
                                  .target
                                  .value,
                              )
                          }
                        />
                      </label>
                    </div>

                    <label
                      className={
                        styles.uploadField
                      }
                    >
                      <span>
                        Bukti Pembayaran
                        <b>*</b>
                      </span>

                      <div
                        className={
                          styles.uploadBox
                        }
                      >
                        <FileText
                          size={22}
                        />

                        <div>
                          <strong>
                            {
                              paymentEvidence
                                ?.name ??
                              "Pilih foto atau PDF"
                            }
                          </strong>

                          <small>
                            JPG, PNG, WebP,
                            atau PDF • maks.
                            5 MB
                          </small>
                        </div>

                        <input
                          type="file"
                          accept="image/png,image/jpeg,image/webp,application/pdf"
                          onChange={
                            (event) => {
                              setPaymentEvidence(
                                event
                                  .target
                                  .files?.[0] ??
                                  null,
                              );

                              setPaymentSubmitError(
                                null,
                              );
                            }
                          }
                          required
                        />
                      </div>
                    </label>

                    {paymentSubmitError ? (
                      <div
                        className={
                          styles.paymentError
                        }
                        role="alert"
                      >
                        <AlertCircle
                          size={16}
                        />

                        <span>
                          {
                            paymentSubmitError
                          }
                        </span>
                      </div>
                    ) : null}

                    <button
                      type="submit"
                      className={
                        styles
                          .submitPaymentButton
                      }
                      disabled={
                        paymentSubmitting
                      }
                    >
                      {
                        paymentSubmitting
                          ? "Mengirim Konfirmasi..."
                          : "Kirim Konfirmasi Pembayaran"
                      }
                    </button>

                    <p
                      className={
                        styles.paymentDisclaimer
                      }
                    >
                      Pembayaran akan
                      berstatus Menunggu
                      Verifikasi dan belum
                      mengurangi sisa
                      Tagihan sampai
                      diperiksa oleh{" "}
                      {businessName}.
                    </p>
                  </form>
                )}
              </section>
            ) : null}


            {!hasPaymentMethod ? (
              <div
                className={
                  styles.paymentUnavailable
                }
              >
                Metode pembayaran belum
                tersedia pada link ini.
                Silakan hubungi pengirim
                Tagihan.
              </div>
            ) : null}
          </section>
        ) : null}


        {invoice.notes ? (
          <section
            className={
              styles.textSection
            }
          >
            <h2>
              Catatan
            </h2>

            <p>
              {invoice.notes}
            </p>
          </section>
        ) : null}


        {branding.invoice_footnote ? (
          <section
            className={
              styles.textSection
            }
          >
            <h2>
              Informasi
            </h2>

            <p>
              {
                branding
                  .invoice_footnote
              }
            </p>
          </section>
        ) : null}
      </section>


      <footer
        className={
          styles.footer
        }
      >
        <div
          className={
            styles.platformAttribution
          }
        >
          <span>
            Dokumen digital dibuat dengan
          </span>

          <a
            href={
              SIGNOVA_MARKETING_URL
            }
            target="_blank"
            rel="noopener noreferrer"
            className={
              styles.platformLink
            }
            aria-label="Kunjungi SIGNOVA"
          >
            SIGNOVA
            <span
              aria-hidden="true"
            >
              ↗
            </span>
          </a>
        </div>

        <button
          type="button"
          className={
            styles.retryButton
          }
          onClick={
            () =>
              void loadInvoice()
          }
        >
          <RefreshCcw
            size={14}
          />

          Muat Ulang
        </button>
      </footer>
    </main>
  );
}
