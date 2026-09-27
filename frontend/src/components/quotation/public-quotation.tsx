"use client";

import {
  useEffect,
  useRef,
  useState,
} from "react";

import {
  AlertCircle,
  CalendarDays,
  Check,
  CheckCircle2,
  FileText,
  MessageSquareWarning,
  RefreshCcw,
  ShieldCheck,
  UserRound,
  X,
} from "lucide-react";

import {
  useParams,
} from "next/navigation";

import {
  approvePublicQuotation,
  getPublicQuotation,
  markPublicQuotationViewed,
  PublicQuotationApiError,
  rejectPublicQuotation,
} from "@/lib/quotation/public-service";

import type {
  PublicQuotation,
  PublicQuotationStatus,
} from "@/types/public-quotation";

import {
  formatDecimalDisplay,
} from "@/lib/format/decimal";

import styles from "./public-quotation.module.css";

const STATUS_LABELS:
  Record<
    PublicQuotationStatus,
    string
  > = {
  SENT:
    "Menunggu Keputusan",

  VIEWED:
    "Sudah Dilihat",

  APPROVED:
    "Disetujui",

  REJECTED:
    "Perlu Revisi",

  CANCELLED:
    "Dibatalkan",

  EXPIRED:
    "Kedaluwarsa",
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

      minimumFractionDigits:
        0,

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
    return "Tidak dibatasi";
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
  unitSymbol:
    | string
    | null,
  unitName:
    | string
    | null,
): string {
  const formatted =
    formatDecimalDisplay(
      quantity,
    );

  const unit =
    unitSymbol ??
    unitName ??
    "";

  return [
    formatted,
    unit,
  ]
    .filter(Boolean)
    .join(" ");
}

export function PublicQuotationView() {
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
    quotation,
    setQuotation,
  ] = useState<
    PublicQuotation | null
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
    decision,
    setDecision,
  ] = useState<
    "APPROVE" | "REJECT" | null
  >(null);

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
    actionSuccess,
    setActionSuccess,
  ] = useState<
    string | null
  >(null);

  const viewedTokenRef =
    useRef<
      string | null
    >(null);

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

  async function loadQuotation() {
    if (!token) {
      setUnavailable(true);
      setError(
        "Link Penawaran tidak valid.",
      );
      setLoading(false);

      return;
    }

    setLoading(true);
    setError(null);
    setUnavailable(false);

    try {
      const response =
        await getPublicQuotation(
          token,
        );

      setQuotation(
        response.data,
      );
    } catch (caught) {
      if (
        caught instanceof
          PublicQuotationApiError &&
        caught.status === 404
      ) {
        setUnavailable(true);
        setQuotation(null);
        setError(
          "Link Penawaran tidak tersedia, sudah kedaluwarsa, atau telah diganti.",
        );
      } else {
        setError(
          caught instanceof Error
            ? caught.message
            : "Penawaran belum berhasil dimuat.",
        );
      }
    } finally {
      setLoading(false);
    }
  }

  function closeDecision() {
    if (actionLoading) {
      return;
    }

    setDecision(null);
    setRejectReason("");
    setActionError(null);
  }

  async function handleApprove() {
    if (
      !token ||
      !quotation ||
      (
        quotation.status !==
          "SENT" &&
        quotation.status !==
          "VIEWED"
      )
    ) {
      return;
    }

    setActionLoading(true);
    setActionError(null);

    try {
      const response =
        await approvePublicQuotation(
          token,
        );

      setQuotation(
        response.data,
      );

      setDecision(null);
      setRejectReason("");

      setActionSuccess(
        "Penawaran berhasil disetujui.",
      );
    } catch (caught) {
      setActionError(
        caught instanceof Error
          ? caught.message
          : "Persetujuan belum berhasil disimpan.",
      );
    } finally {
      setActionLoading(false);
    }
  }

  async function handleReject() {
    if (
      !token ||
      !quotation ||
      (
        quotation.status !==
          "SENT" &&
        quotation.status !==
          "VIEWED"
      )
    ) {
      return;
    }

    const reason =
      rejectReason.trim();

    if (!reason) {
      setActionError(
        "Tuliskan perubahan yang Anda perlukan terlebih dahulu.",
      );
      return;
    }

    setActionLoading(true);
    setActionError(null);

    try {
      const response =
        await rejectPublicQuotation(
          token,
          reason,
        );

      setQuotation(
        response.data,
      );

      setDecision(null);
      setRejectReason("");

      setActionSuccess(
        "Permintaan perubahan berhasil dikirim.",
      );
    } catch (caught) {
      setActionError(
        caught instanceof Error
          ? caught.message
          : "Permintaan perubahan belum berhasil dikirim.",
      );
    } finally {
      setActionLoading(false);
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
          setQuotation(null);
          setError(
            "Link Penawaran tidak valid.",
          );
          setLoading(false);
        },
      );

      return () => {
        cancelled = true;
      };
    }

    void getPublicQuotation(
      token,
    )
      .then(
        (response) => {
          if (cancelled) {
            return;
          }

          setQuotation(
            response.data,
          );
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
              PublicQuotationApiError &&
            caught.status === 404
          ) {
            setUnavailable(true);
            setQuotation(null);
            setError(
              "Link Penawaran tidak tersedia, sudah kedaluwarsa, atau telah diganti.",
            );

            return;
          }

          setError(
            caught instanceof Error
              ? caught.message
              : "Penawaran belum berhasil dimuat.",
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

  useEffect(() => {
    if (
      loading ||
      !quotation ||
      quotation.status !==
        "SENT" ||
      !token ||
      viewedTokenRef.current ===
        token
    ) {
      return;
    }

    viewedTokenRef.current =
      token;

    void markPublicQuotationViewed(
      token,
    )
      .then(
        (response) => {
          setQuotation(
            response.data,
          );
        },
      )
      .catch(
        () => {
          /*
           * Tracking VIEW tidak boleh
           * menggagalkan dokumen yang
           * sudah berhasil dibaca.
           */
        },
      );
  }, [
    loading,
    quotation,
    token,
  ]);

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
            Membuka Penawaran
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
    !quotation
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
            Penawaran Tidak Tersedia
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
            pengirim Penawaran
            untuk mendapatkan link
            terbaru.
          </p>
        </div>
      </main>
    );
  }

  const version =
    quotation.version;

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
            styles.brand
          }
        >
          <span
            className={
              styles.brandMark
            }
          >
            S
          </span>

          <div>
            <strong>
              SIGNOVA
            </strong>

            <small>
              Dokumen Penawaran
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
              PENAWARAN
            </span>

            <h1>
              {
                quotation.quotation_number
              }
            </h1>

            <p>
              Revisi{" "}
              {String(
                version.revision_no,
              ).padStart(
                2,
                "0",
              )}
            </p>
          </div>

          <span
            className={`${styles.statusBadge} ${
              styles[
                `status${quotation.status}`
              ] ?? ""
            }`}
          >
            {
              STATUS_LABELS[
                quotation.status
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
                  quotation
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
                Berlaku Sampai
              </span>

              <strong>
                {dateLabel(
                  quotation.valid_until,
                )}
              </strong>
            </div>
          </article>
        </div>

        {error ? (
          <div
            className={
              styles.softWarning
            }
          >
            <AlertCircle
              size={17}
            />

            <span>
              Dokumen berhasil
              dimuat, tetapi status
              kunjungan belum dapat
              diperbarui.
            </span>
          </div>
        ) : null}

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
                Rincian Penawaran
              </h2>

              <p>
                Harga dan nilai di
                bawah merupakan
                versi Penawaran yang
                dikirim kepada Anda.
              </p>
            </div>
          </div>

          <div
            className={
              styles.itemList
            }
          >
            {version.items.map(
              (
                item,
                index,
              ) => (
                <article
                  key={`${item.code ?? item.name}-${index}`}
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

                      {item.code ? (
                        <small>
                          {
                            item.code
                          }
                        </small>
                      ) : null}
                    </div>

                    <strong>
                      {money(
                        item.amount,
                        version.currency,
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
                          item.unit_symbol,
                          item.unit_name,
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
                          version.currency,
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
                            version.currency,
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
                            version.currency,
                          )}
                        </strong>
                      </div>
                    ) : null}
                  </div>
                </article>
              ),
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
                version.subtotal,
                version.currency,
              )}
            </strong>
          </div>

          {Number(
            version.discount_total,
          ) > 0 ? (
            <div>
              <span>
                Diskon
              </span>

              <strong>
                -
                {money(
                  version.discount_total,
                  version.currency,
                )}
              </strong>
            </div>
          ) : null}

          {Number(
            version.tax_total,
          ) > 0 ? (
            <div>
              <span>
                Pajak
              </span>

              <strong>
                {money(
                  version.tax_total,
                  version.currency,
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
              Total Penawaran
            </span>

            <strong>
              {money(
                version.total,
                version.currency,
              )}
            </strong>
          </div>
        </section>

        {version.notes ? (
          <section
            className={
              styles.textSection
            }
          >
            <h2>
              Catatan
            </h2>

            <p>
              {version.notes}
            </p>
          </section>
        ) : null}

        {version.terms ? (
          <section
            className={
              styles.textSection
            }
          >
            <h2>
              Syarat & Ketentuan
            </h2>

            <p>
              {version.terms}
            </p>
          </section>
        ) : null}

        {actionSuccess ? (
          <div
            className={
              styles.actionSuccess
            }
            role="status"
          >
            <CheckCircle2
              size={18}
            />

            <span>
              {actionSuccess}
            </span>
          </div>
        ) : null}

        {actionError &&
        !decision ? (
          <div
            className={
              styles.actionError
            }
            role="alert"
          >
            <AlertCircle
              size={18}
            />

            <span>
              {actionError}
            </span>
          </div>
        ) : null}

        {quotation.status ===
          "SENT" ||
        quotation.status ===
          "VIEWED" ? (
          <section
            className={
              styles.customerDecision
            }
          >
            <div
              className={
                styles.customerDecisionCopy
              }
            >
              <span>
                KEPUTUSAN ANDA
              </span>

              <h2>
                Apakah Penawaran ini
                sudah sesuai?
              </h2>

              <p>
                Anda dapat menyetujui
                Penawaran atau
                mengirim permintaan
                perubahan kepada
                pengirim.
              </p>
            </div>

            <div
              className={
                styles.customerDecisionActions
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
                  () => {
                    setActionError(
                      null,
                    );
                    setDecision(
                      "REJECT",
                    );
                  }
                }
              >
                <MessageSquareWarning
                  size={17}
                />

                Ajukan Perubahan
              </button>

              <button
                type="button"
                className={
                  styles.approveButton
                }
                disabled={
                  actionLoading
                }
                onClick={
                  () => {
                    setActionError(
                      null,
                    );
                    setDecision(
                      "APPROVE",
                    );
                  }
                }
              >
                <Check
                  size={17}
                />

                Setujui Penawaran
              </button>
            </div>
          </section>
        ) : null}

        {quotation.status ===
        "REJECTED" ? (
          <div
            className={
              styles.revisionRequestedState
            }
          >
            <MessageSquareWarning
              size={21}
            />

            <div>
              <strong>
                Permintaan perubahan
                telah dikirim
              </strong>

              <p>
                Pengirim akan
                menyiapkan revisi
                Penawaran. Link baru
                dapat dikirim setelah
                revisi selesai.
              </p>
            </div>
          </div>
        ) : null}

        {quotation.status ===
        "APPROVED" ? (
          <div
            className={
              styles.decisionState
            }
          >
            <CheckCircle2
              size={21}
            />

            <div>
              <strong>
                Penawaran telah
                disetujui
              </strong>

              <p>
                Tidak ada tindakan
                tambahan yang perlu
                dilakukan pada link
                ini.
              </p>
            </div>
          </div>
        ) : null}
      </section>

      {decision ? (
        <div
          className={
            styles.decisionBackdrop
          }
          role="presentation"
        >
          <section
            className={
              styles.decisionDialog
            }
            role="dialog"
            aria-modal="true"
            aria-labelledby="quotation-decision-title"
          >
            <header
              className={
                styles.decisionHeader
              }
            >
              <div>
                <span>
                  {decision ===
                  "APPROVE"
                    ? "SETUJUI PENAWARAN"
                    : "AJUKAN PERUBAHAN"}
                </span>

                <h2
                  id="quotation-decision-title"
                >
                  {decision ===
                  "APPROVE"
                    ? "Konfirmasi Persetujuan"
                    : "Apa yang perlu diubah?"}
                </h2>
              </div>

              <button
                type="button"
                className={
                  styles.dialogClose
                }
                disabled={
                  actionLoading
                }
                onClick={
                  closeDecision
                }
                aria-label="Tutup"
              >
                <X size={20} />
              </button>
            </header>

            <div
              className={
                styles.decisionContent
              }
            >
              {decision ===
              "APPROVE" ? (
                <div
                  className={
                    styles.approveConfirmation
                  }
                >
                  <span
                    className={
                      styles.approveConfirmationIcon
                    }
                  >
                    <CheckCircle2
                      size={26}
                    />
                  </span>

                  <p>
                    Dengan menyetujui
                    Penawaran{" "}
                    <strong>
                      {
                        quotation.quotation_number
                      }
                    </strong>
                    , Anda menyatakan
                    bahwa rincian dan
                    nilai Penawaran
                    ini telah sesuai.
                  </p>
                </div>
              ) : (
                <label
                  className={
                    styles.reasonField
                  }
                >
                  <span>
                    Permintaan
                    Perubahan *
                  </span>

                  <textarea
                    rows={5}
                    maxLength={1000}
                    value={
                      rejectReason
                    }
                    disabled={
                      actionLoading
                    }
                    placeholder="Contoh: Mohon ubah jumlah item, ukuran, harga, jadwal, atau detail lainnya."
                    onChange={
                      (event) => {
                        setRejectReason(
                          event.target
                            .value,
                        );

                        if (
                          actionError
                        ) {
                          setActionError(
                            null,
                          );
                        }
                      }
                    }
                  />

                  <small>
                    {
                      rejectReason.length
                    }
                    /1000
                  </small>
                </label>
              )}

              {actionError ? (
                <div
                  className={
                    styles.dialogError
                  }
                  role="alert"
                >
                  <AlertCircle
                    size={17}
                  />

                  <span>
                    {actionError}
                  </span>
                </div>
              ) : null}
            </div>

            <footer
              className={
                styles.decisionFooter
              }
            >
              <button
                type="button"
                className={
                  styles.dialogSecondaryButton
                }
                disabled={
                  actionLoading
                }
                onClick={
                  closeDecision
                }
              >
                Kembali
              </button>

              <button
                type="button"
                className={
                  decision ===
                  "APPROVE"
                    ? styles.approveButton
                    : styles.rejectSubmitButton
                }
                disabled={
                  actionLoading
                }
                onClick={
                  () => {
                    if (
                      decision ===
                      "APPROVE"
                    ) {
                      void handleApprove();
                    } else {
                      void handleReject();
                    }
                  }
                }
              >
                {actionLoading
                  ? "Menyimpan..."
                  : decision ===
                      "APPROVE"
                    ? "Ya, Setujui"
                    : "Kirim Permintaan"}
              </button>
            </footer>
          </section>
        </div>
      ) : null}

      <footer
        className={
          styles.footer
        }
      >
        <span>
          Dokumen elektronik
          melalui SIGNOVA
        </span>

        <button
          type="button"
          className={
            styles.retryButton
          }
          onClick={
            () =>
              void loadQuotation()
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
