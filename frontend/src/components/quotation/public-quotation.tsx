"use client";

import {
  useEffect,
  useRef,
  useState,
} from "react";

import {
  AlertCircle,
  CalendarDays,
  CheckCircle2,
  FileText,
  RefreshCcw,
  ShieldCheck,
  UserRound,
} from "lucide-react";

import {
  useParams,
} from "next/navigation";

import {
  getPublicQuotation,
  markPublicQuotationViewed,
  PublicQuotationApiError,
} from "@/lib/quotation/public-service";

import type {
  PublicQuotation,
  PublicQuotationStatus,
} from "@/types/public-quotation";

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
  const value =
    Number(quantity);

  const formatted =
    Number.isFinite(value)
      ? new Intl.NumberFormat(
          "id-ID",
          {
            maximumFractionDigits:
              4,
          },
        ).format(value)
      : String(quantity);

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

  const viewedTokenRef =
    useRef<
      string | null
    >(null);

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
