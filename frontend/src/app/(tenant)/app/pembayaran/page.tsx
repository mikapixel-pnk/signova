"use client";

import Link from "next/link";

import {
  AlertCircle,
  Banknote,
  CheckCircle2,
  ChevronLeft,
  ChevronRight,
  Clock3,
  Eye,
  FileSearch,
  Landmark,
  LoaderCircle,
  ReceiptText,
  RefreshCcw,
  Search,
  ShieldCheck,
  X,
  XCircle,
} from "lucide-react";

import {
  useCallback,
  useEffect,
  useState,
} from "react";

import {
  TenantShell,
} from "@/components/layout/tenant-shell";

import {
  getPayment,
  getPaymentEvidence,
  listPayments,
  rejectPayment,
  verifyPayment,
} from "@/lib/payment/service";

import type {
  PaymentCenterItem,
  PaymentStatus,
} from "@/types/payment";

import styles from "./payment-center.module.css";


type StatusFilter =
  | "ALL"
  | PaymentStatus;


const STATUS_FILTERS: {
  value:
    StatusFilter;
  label:
    string;
}[] = [
  {
    value: "ALL",
    label: "Semua",
  },
  {
    value: "PENDING",
    label: "Menunggu",
  },
  {
    value: "VERIFIED",
    label: "Terverifikasi",
  },
  {
    value: "REJECTED",
    label: "Ditolak",
  },
  {
    value: "REVERSED",
    label: "Dibalik",
  },
];


const STATUS_LABELS:
  Record<
    PaymentStatus,
    string
  > = {
  PENDING:
    "Menunggu Verifikasi",

  VERIFIED:
    "Terverifikasi",

  REJECTED:
    "Ditolak",

  REVERSED:
    "Dibalik",
};


function money(
  value:
    | string
    | number,
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
        currency ||
        "IDR",

      maximumFractionDigits:
        2,
    },
  ).format(
    Number.isFinite(number)
      ? number
      : 0,
  );
}


function dateTimeLabel(
  value:
    | string
    | null,
): string {
  if (!value) {
    return "-";
  }

  const parsed =
    new Date(value);

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
      dateStyle:
        "medium",

      timeStyle:
        "short",
    },
  ).format(
    parsed,
  );
}


function accountLabel(
  payment:
    PaymentCenterItem,
): string {
  const account =
    payment.cash_account;

  if (!account) {
    return "-";
  }

  const name =
    account.bank_name ??
    account.name;

  const number =
    account.account_number;

  return number
    ? `${name} • ${number}`
    : name;
}


export default function Page() {
  const [
    payments,
    setPayments,
  ] = useState<
    PaymentCenterItem[]
  >([]);

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
    search,
    setSearch,
  ] = useState("");

  const [
    status,
    setStatus,
  ] = useState<
    StatusFilter
  >("ALL");

  const [
    page,
    setPage,
  ] = useState(1);

  const [
    lastPage,
    setLastPage,
  ] = useState(1);

  const [
    total,
    setTotal,
  ] = useState(0);

  const [
    pendingCount,
    setPendingCount,
  ] = useState(0);

  const [
    verifiedCount,
    setVerifiedCount,
  ] = useState(0);

  const [
    rejectedCount,
    setRejectedCount,
  ] = useState(0);

  const [
    selected,
    setSelected,
  ] = useState<
    PaymentCenterItem | null
  >(null);

  const [
    detailLoading,
    setDetailLoading,
  ] = useState(false);

  const [
    evidenceUrl,
    setEvidenceUrl,
  ] = useState<
    string | null
  >(null);

  const [
    evidenceLoading,
    setEvidenceLoading,
  ] = useState(false);

  const [
    rejectReason,
    setRejectReason,
  ] = useState("");

  const [
    actionLoading,
    setActionLoading,
  ] = useState(false);

  const [
    actionError,
    setActionError,
  ] = useState<
    string | null
  >(null);

  const [
    actionMessage,
    setActionMessage,
  ] = useState<
    string | null
  >(null);


  const loadSummary =
    useCallback(
      async () => {
        try {
          const [
            pending,
            verified,
            rejected,
          ] =
            await Promise.all([
              listPayments({
                status:
                  "PENDING",
                page:
                  1,
                per_page:
                  1,
              }),

              listPayments({
                status:
                  "VERIFIED",
                page:
                  1,
                per_page:
                  1,
              }),

              listPayments({
                status:
                  "REJECTED",
                page:
                  1,
                per_page:
                  1,
              }),
            ]);

          setPendingCount(
            pending.meta.total,
          );

          setVerifiedCount(
            verified.meta.total,
          );

          setRejectedCount(
            rejected.meta.total,
          );
        } catch {
          /*
           * Summary adalah informasi tambahan.
           * Kegagalan tidak boleh memblokir
           * daftar utama.
           */
        }
      },
      [],
    );


  const loadPayments =
    useCallback(
      async (
        currentPage:
          number,
      ) => {
        setLoading(true);
        setError(null);

        try {
          const response =
            await listPayments({
              search:
                search.trim()
                  || undefined,

              status:
                status ===
                  "ALL"
                  ? undefined
                  : status,

              page:
                currentPage,

              per_page:
                20,
            });

          setPayments(
            response.data,
          );

          setPage(
            response
              .meta
              .current_page,
          );

          setLastPage(
            Math.max(
              response
                .meta
                .last_page,
              1,
            ),
          );

          setTotal(
            response
              .meta
              .total,
          );
        } catch (caught) {
          setPayments([]);

          setError(
            caught instanceof
              Error
              ? caught.message
              : "Daftar pembayaran belum berhasil dimuat.",
          );
        } finally {
          setLoading(false);
        }
      },
      [
        search,
        status,
      ],
    );


  useEffect(() => {
    const timeout =
      window.setTimeout(
        () => {
          void loadPayments(
            1,
          );

          void loadSummary();
        },
        220,
      );

    return () => {
      window.clearTimeout(
        timeout,
      );
    };
  }, [
    loadPayments,
    loadSummary,
  ]);


  useEffect(() => {
    return () => {
      if (evidenceUrl) {
        URL.revokeObjectURL(
          evidenceUrl,
        );
      }
    };
  }, [
    evidenceUrl,
  ]);


  async function openPayment(
    paymentId: string,
  ) {
    setDetailLoading(true);
    setActionError(null);
    setActionMessage(null);
    setRejectReason("");

    if (evidenceUrl) {
      URL.revokeObjectURL(
        evidenceUrl,
      );

      setEvidenceUrl(null);
    }

    try {
      const response =
        await getPayment(
          paymentId,
        );

      setSelected(
        response.data,
      );
    } catch (caught) {
      setActionError(
        caught instanceof Error
          ? caught.message
          : "Detail pembayaran belum berhasil dimuat.",
      );
    } finally {
      setDetailLoading(false);
    }
  }


  function closeDetail() {
    if (evidenceUrl) {
      URL.revokeObjectURL(
        evidenceUrl,
      );
    }

    setEvidenceUrl(null);
    setSelected(null);
    setRejectReason("");
    setActionError(null);
    setActionMessage(null);
  }


  async function previewEvidence() {
    if (
      !selected ||
      !selected.has_evidence
    ) {
      return;
    }

    setEvidenceLoading(true);
    setActionError(null);

    try {
      const blob =
        await getPaymentEvidence(
          selected.id,
        );

      if (evidenceUrl) {
        URL.revokeObjectURL(
          evidenceUrl,
        );
      }

      setEvidenceUrl(
        URL.createObjectURL(
          blob,
        ),
      );
    } catch (caught) {
      setActionError(
        caught instanceof Error
          ? caught.message
          : "Bukti pembayaran belum berhasil dibuka.",
      );
    } finally {
      setEvidenceLoading(
        false,
      );
    }
  }


  async function submitVerify() {
    if (!selected) {
      return;
    }

    setActionLoading(true);
    setActionError(null);
    setActionMessage(null);

    try {
      const response =
        await verifyPayment(
          selected.id,
        );

      setSelected(
        response.data,
      );

      setActionMessage(
        "Pembayaran berhasil diverifikasi dan Tagihan telah diperbarui.",
      );

      await Promise.all([
        loadPayments(page),
        loadSummary(),
      ]);
    } catch (caught) {
      setActionError(
        caught instanceof Error
          ? caught.message
          : "Pembayaran belum berhasil diverifikasi.",
      );
    } finally {
      setActionLoading(false);
    }
  }


  async function submitReject() {
    if (!selected) {
      return;
    }

    const reason =
      rejectReason.trim();

    if (!reason) {
      setActionError(
        "Alasan penolakan wajib diisi.",
      );

      return;
    }

    setActionLoading(true);
    setActionError(null);

    try {
      const response =
        await rejectPayment(
          selected.id,
          reason,
        );

      setSelected(
        response.data,
      );

      setActionMessage(
        "Pembayaran berhasil ditolak.",
      );

      setRejectReason("");

      await Promise.all([
        loadPayments(page),
        loadSummary(),
      ]);
    } catch (caught) {
      setActionError(
        caught instanceof Error
          ? caught.message
          : "Pembayaran belum berhasil ditolak.",
      );
    } finally {
      setActionLoading(false);
    }
  }


  return (
    <TenantShell>
      <main
        className={
          styles.page
        }
      >
        <header
          className={
            styles.header
          }
        >
          <div>
            <span
              className={
                styles.eyebrow
              }
            >
              KEUANGAN
            </span>

            <h1>
              Pembayaran
            </h1>

            <p>
              Periksa konfirmasi pembayaran
              pelanggan sebelum memberi
              dampak ke Tagihan dan Kas/Bank.
            </p>
          </div>

          <button
            type="button"
            className={
              styles.refreshButton
            }
            onClick={
              () =>
                void Promise.all([
                  loadPayments(
                    page,
                  ),
                  loadSummary(),
                ])
            }
          >
            <RefreshCcw
              size={16}
            />

            Muat Ulang
          </button>
        </header>


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
            <Clock3
              size={20}
            />

            <div>
              <span>
                Menunggu
              </span>

              <strong>
                {pendingCount}
              </strong>
            </div>
          </article>

          <article
            className={
              styles.summaryCard
            }
          >
            <CheckCircle2
              size={20}
            />

            <div>
              <span>
                Terverifikasi
              </span>

              <strong>
                {verifiedCount}
              </strong>
            </div>
          </article>

          <article
            className={
              styles.summaryCard
            }
          >
            <XCircle
              size={20}
            />

            <div>
              <span>
                Ditolak
              </span>

              <strong>
                {rejectedCount}
              </strong>
            </div>
          </article>
        </section>


        <section
          className={
            styles.toolbar
          }
        >
          <label
            className={
              styles.searchBox
            }
          >
            <Search
              size={16}
            />

            <input
              type="search"
              value={
                search
              }
              placeholder="Cari pelanggan atau referensi..."
              onChange={
                (event) => {
                  setSearch(
                    event
                      .target
                      .value,
                  );

                  setPage(1);
                }
              }
            />
          </label>

          <div
            className={
              styles.filters
            }
          >
            {STATUS_FILTERS.map(
              (item) => (
                <button
                  type="button"
                  key={
                    item.value
                  }
                  className={
                    status ===
                    item.value
                      ? styles.filterActive
                      : styles.filterButton
                  }
                  onClick={
                    () => {
                      setStatus(
                        item.value,
                      );

                      setPage(1);
                    }
                  }
                >
                  {item.label}
                </button>
              ),
            )}
          </div>
        </section>


        {error ? (
          <div
            className={
              styles.errorBox
            }
          >
            <AlertCircle
              size={18}
            />

            <span>
              {error}
            </span>
          </div>
        ) : null}


        <section
          className={
            styles.listSection
          }
        >
          <div
            className={
              styles.listHeader
            }
          >
            <div>
              <h2>
                Daftar Pembayaran
              </h2>

              <span>
                {total} transaksi
              </span>
            </div>
          </div>

          {loading ? (
            <div
              className={
                styles.emptyState
              }
            >
              <LoaderCircle
                className={
                  styles.spinner
                }
                size={28}
              />

              <strong>
                Memuat pembayaran
              </strong>
            </div>
          ) : payments.length ===
            0 ? (
            <div
              className={
                styles.emptyState
              }
            >
              <ReceiptText
                size={30}
              />

              <strong>
                Belum ada pembayaran
              </strong>

              <span>
                Pembayaran pelanggan
                akan tampil di sini.
              </span>
            </div>
          ) : (
            <div
              className={
                styles.paymentList
              }
            >
              {payments.map(
                (payment) => (
                  <article
                    key={
                      payment.id
                    }
                    className={
                      styles.paymentCard
                    }
                  >
                    <div
                      className={
                        styles.cardTop
                      }
                    >
                      <div
                        className={
                          styles.customerBlock
                        }
                      >
                        <span
                          className={
                            styles.paymentIcon
                          }
                        >
                          <Banknote
                            size={18}
                          />
                        </span>

                        <div>
                          <strong>
                            {
                              payment
                                .customer
                                ?.name ??
                              "Pelanggan"
                            }
                          </strong>

                          <span>
                            {
                              payment
                                .intended_invoice
                                ?.invoice_number ??
                              "Tanpa Tagihan"
                            }
                          </span>
                        </div>
                      </div>

                      <span
                        className={`${styles.statusBadge} ${
                          styles[
                            `status${payment.status}`
                          ]
                        }`}
                      >
                        {
                          STATUS_LABELS[
                            payment.status
                          ]
                        }
                      </span>
                    </div>

                    <div
                      className={
                        styles.amountBlock
                      }
                    >
                      <strong>
                        {
                          money(
                            payment.amount,
                            payment.currency,
                          )
                        }
                      </strong>

                      <span>
                        {
                          dateTimeLabel(
                            payment.paid_at,
                          )
                        }
                      </span>
                    </div>

                    <div
                      className={
                        styles.cardMeta
                      }
                    >
                      <span>
                        <Landmark
                          size={14}
                        />

                        {
                          accountLabel(
                            payment,
                          )
                        }
                      </span>

                      {payment.reference ? (
                        <span>
                          Ref:{" "}
                          {
                            payment.reference
                          }
                        </span>
                      ) : null}
                    </div>

                    <button
                      type="button"
                      className={
                        styles.detailButton
                      }
                      onClick={
                        () =>
                          void openPayment(
                            payment.id,
                          )
                      }
                    >
                      <Eye
                        size={15}
                      />

                      Lihat Detail
                    </button>
                  </article>
                ),
              )}
            </div>
          )}


          <footer
            className={
              styles.pagination
            }
          >
            <button
              type="button"
              disabled={
                page <= 1 ||
                loading
              }
              onClick={
                () =>
                  void loadPayments(
                    page - 1,
                  )
              }
            >
              <ChevronLeft
                size={16}
              />

              Sebelumnya
            </button>

            <span>
              Halaman {page} dari{" "}
              {lastPage}
            </span>

            <button
              type="button"
              disabled={
                page >=
                  lastPage ||
                loading
              }
              onClick={
                () =>
                  void loadPayments(
                    page + 1,
                  )
              }
            >
              Berikutnya

              <ChevronRight
                size={16}
              />
            </button>
          </footer>
        </section>


        {selected ||
        detailLoading ? (
          <div
            className={
              styles.overlay
            }
            role="presentation"
            onMouseDown={
              (event) => {
                if (
                  event.target ===
                  event.currentTarget
                ) {
                  closeDetail();
                }
              }
            }
          >
            <aside
              className={
                styles.detailPanel
              }
              aria-label="Detail pembayaran"
            >
              <header
                className={
                  styles.detailHeader
                }
              >
                <div>
                  <span>
                    DETAIL PEMBAYARAN
                  </span>

                  <h2>
                    {
                      selected
                        ?.customer
                        ?.name ??
                      "Pembayaran"
                    }
                  </h2>
                </div>

                <button
                  type="button"
                  aria-label="Tutup"
                  onClick={
                    closeDetail
                  }
                >
                  <X
                    size={20}
                  />
                </button>
              </header>

              {detailLoading ||
              !selected ? (
                <div
                  className={
                    styles.detailLoading
                  }
                >
                  <LoaderCircle
                    className={
                      styles.spinner
                    }
                    size={28}
                  />
                </div>
              ) : (
                <>
                  <div
                    className={
                      styles.detailStatus
                    }
                  >
                    <span
                      className={`${styles.statusBadge} ${
                        styles[
                          `status${selected.status}`
                        ]
                      }`}
                    >
                      {
                        STATUS_LABELS[
                          selected.status
                        ]
                      }
                    </span>

                    <strong>
                      {
                        money(
                          selected.amount,
                          selected.currency,
                        )
                      }
                    </strong>
                  </div>

                  <dl
                    className={
                      styles.detailGrid
                    }
                  >
                    <div>
                      <dt>
                        Pelanggan
                      </dt>

                      <dd>
                        {
                          selected
                            .customer
                            ?.name ??
                          "-"
                        }
                      </dd>
                    </div>

                    <div>
                      <dt>
                        Tagihan
                      </dt>

                      <dd>
                        {selected
                          .intended_invoice ? (
                          <Link
                            href={`/app/tagihan/${selected.intended_invoice.id}`}
                          >
                            {
                              selected
                                .intended_invoice
                                .invoice_number
                            }
                          </Link>
                        ) : (
                          "-"
                        )}
                      </dd>
                    </div>

                    <div>
                      <dt>
                        Tanggal Pembayaran
                      </dt>

                      <dd>
                        {
                          dateTimeLabel(
                            selected.paid_at,
                          )
                        }
                      </dd>
                    </div>

                    <div>
                      <dt>
                        Rekening Tujuan
                      </dt>

                      <dd>
                        {
                          accountLabel(
                            selected,
                          )
                        }
                      </dd>
                    </div>

                    <div>
                      <dt>
                        Atas Nama
                      </dt>

                      <dd>
                        {
                          selected
                            .cash_account
                            ?.account_name ??
                          "-"
                        }
                      </dd>
                    </div>

                    <div>
                      <dt>
                        Referensi
                      </dt>

                      <dd>
                        {
                          selected
                            .reference ??
                          "-"
                        }
                      </dd>
                    </div>
                  </dl>


                  <section
                    className={
                      styles.evidenceSection
                    }
                  >
                    <div
                      className={
                        styles.sectionHeading
                      }
                    >
                      <FileSearch
                        size={17}
                      />

                      <div>
                        <strong>
                          Bukti Pembayaran
                        </strong>

                        <span>
                          Dokumen private,
                          hanya untuk
                          pemeriksaan internal.
                        </span>
                      </div>
                    </div>

                    {selected.has_evidence ? (
                      <>
                        <button
                          type="button"
                          className={
                            styles.evidenceButton
                          }
                          disabled={
                            evidenceLoading
                          }
                          onClick={
                            () =>
                              void previewEvidence()
                          }
                        >
                          {evidenceLoading ? (
                            <LoaderCircle
                              className={
                                styles.spinner
                              }
                              size={16}
                            />
                          ) : (
                            <Eye
                              size={16}
                            />
                          )}

                          Lihat Bukti
                        </button>

                        {evidenceUrl ? (
                          <iframe
                            className={
                              styles.evidencePreview
                            }
                            src={
                              evidenceUrl
                            }
                            title="Bukti pembayaran"
                          />
                        ) : null}
                      </>
                    ) : (
                      <span
                        className={
                          styles.noEvidence
                        }
                      >
                        Tidak ada bukti
                        pembayaran.
                      </span>
                    )}
                  </section>


                  {selected.status ===
                  "PENDING" ? (
                    <section
                      className={
                        styles.reviewSection
                      }
                    >
                      <div
                        className={
                          styles.reviewNotice
                        }
                      >
                        <ShieldCheck
                          size={18}
                        />

                        <p>
                          Pembayaran ini
                          belum memberi
                          dampak ke
                          Tagihan maupun
                          Kas/Bank.
                        </p>
                      </div>

                      <label
                        className={
                          styles.rejectField
                        }
                      >
                        <span>
                          Alasan Penolakan
                        </span>

                        <textarea
                          rows={3}
                          maxLength={500}
                          value={
                            rejectReason
                          }
                          placeholder="Isi jika pembayaran akan ditolak."
                          onChange={
                            (event) =>
                              setRejectReason(
                                event
                                  .target
                                  .value,
                              )
                          }
                        />
                      </label>

                      <div
                        className={
                          styles.reviewActions
                        }
                      >
                        <button
                          type="button"
                          className={
                            styles.rejectButton
                          }
                          disabled={
                            actionLoading
                          }
                          onClick={
                            () =>
                              void submitReject()
                          }
                        >
                          <XCircle
                            size={16}
                          />

                          Tolak Pembayaran
                        </button>

                        <button
                          type="button"
                          className={
                            styles.verifyButton
                          }
                          disabled={
                            actionLoading
                          }
                          onClick={
                            () =>
                              void submitVerify()
                          }
                        >
                          {actionLoading ? (
                            <LoaderCircle
                              className={
                                styles.spinner
                              }
                              size={16}
                            />
                          ) : (
                            <CheckCircle2
                              size={16}
                            />
                          )}

                          {
                            actionLoading
                              ? "Memproses..."
                              : "Verifikasi Pembayaran"
                          }
                        </button>
                      </div>
                    </section>
                  ) : null}


                  {actionError ? (
                    <div
                      className={
                        styles.actionError
                      }
                    >
                      <AlertCircle
                        size={16}
                      />

                      {actionError}
                    </div>
                  ) : null}

                  {actionMessage ? (
                    <div
                      className={
                        styles.actionSuccess
                      }
                    >
                      <CheckCircle2
                        size={16}
                      />

                      {actionMessage}
                    </div>
                  ) : null}


                  {selected.status ===
                  "REJECTED" &&
                  selected
                    .rejection_reason ? (
                    <div
                      className={
                        styles.rejectionBox
                      }
                    >
                      <strong>
                        Alasan Penolakan
                      </strong>

                      <p>
                        {
                          selected
                            .rejection_reason
                        }
                      </p>
                    </div>
                  ) : null}
                </>
              )}
            </aside>
          </div>
        ) : null}
      </main>
    </TenantShell>
  );
}
