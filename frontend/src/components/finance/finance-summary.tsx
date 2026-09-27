"use client";

import {
  ArrowDownRight,
  ArrowUpRight,
  CalendarDays,
  Landmark,
  ReceiptText,
  Scale,
  WalletCards,
} from "lucide-react";

import {
  useEffect,
  useMemo,
  useState,
} from "react";

import {
  ActionFeedback,
} from "@/components/feedback/action-feedback";

import {
  apiErrorMessage,
  apiRequestId,
} from "@/lib/api/error-message";

import {
  getFinanceSummary,
} from "@/lib/finance/finance-summary-service";

import type {
  FinanceSummary as FinanceSummaryData,
} from "@/types/finance-summary";

import styles from "./finance-summary.module.css";


type PeriodPreset =
  | "TODAY"
  | "LAST_7_DAYS"
  | "THIS_MONTH"
  | "LAST_MONTH"
  | "THIS_YEAR"
  | "LAST_YEAR"
  | "CUSTOM";


type DateRange = {
  from: string;
  to: string;
};


function dateValue(
  value: Date,
): string {
  const year =
    value.getFullYear();

  const month =
    String(
      value.getMonth() + 1,
    ).padStart(
      2,
      "0",
    );

  const day =
    String(
      value.getDate(),
    ).padStart(
      2,
      "0",
    );

  return `${year}-${month}-${day}`;
}


function startOfMonth(
  value: Date,
): Date {
  return new Date(
    value.getFullYear(),
    value.getMonth(),
    1,
  );
}


function endOfMonth(
  value: Date,
): Date {
  return new Date(
    value.getFullYear(),
    value.getMonth() + 1,
    0,
  );
}


function rangeForPreset(
  preset: PeriodPreset,
): DateRange | null {
  const now =
    new Date();

  switch (preset) {
    case "TODAY":
      return {
        from: dateValue(now),
        to: dateValue(now),
      };

    case "LAST_7_DAYS": {
      const from =
        new Date(
          now.getFullYear(),
          now.getMonth(),
          now.getDate() - 6,
        );

      return {
        from: dateValue(from),
        to: dateValue(now),
      };
    }

    case "THIS_MONTH":
      return {
        from:
          dateValue(
            startOfMonth(now),
          ),
        to:
          dateValue(
            endOfMonth(now),
          ),
      };

    case "LAST_MONTH": {
      const month =
        new Date(
          now.getFullYear(),
          now.getMonth() - 1,
          1,
        );

      return {
        from:
          dateValue(
            startOfMonth(month),
          ),
        to:
          dateValue(
            endOfMonth(month),
          ),
      };
    }

    case "THIS_YEAR":
      return {
        from:
          `${now.getFullYear()}-01-01`,
        to:
          `${now.getFullYear()}-12-31`,
      };

    case "LAST_YEAR": {
      const year =
        now.getFullYear() - 1;

      return {
        from: `${year}-01-01`,
        to: `${year}-12-31`,
      };
    }

    case "CUSTOM":
      return null;
  }
}


function money(
  value:
    | string
    | number,
): string {
  const amount =
    Number(value);

  if (
    !Number.isFinite(amount)
  ) {
    return "Rp0";
  }

  return new Intl.NumberFormat(
    "id-ID",
    {
      style: "currency",
      currency: "IDR",
      minimumFractionDigits: 0,
      maximumFractionDigits: 2,
    },
  ).format(amount);
}


function signedMoney(
  value:
    | string
    | number,
): string {
  const amount =
    Number(value);

  if (
    !Number.isFinite(amount)
  ) {
    return "Rp0";
  }

  if (amount > 0) {
    return `+${money(amount)}`;
  }

  return money(amount);
}


function localDate(
  value: string,
): string {
  const date =
    new Date(
      `${value}T00:00:00`,
    );

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
    },
  ).format(date);
}


function presetLabel(
  preset: PeriodPreset,
): string {
  switch (preset) {
    case "TODAY":
      return "Hari Ini";

    case "LAST_7_DAYS":
      return "7 Hari Terakhir";

    case "THIS_MONTH":
      return "Bulan Ini";

    case "LAST_MONTH":
      return "Bulan Lalu";

    case "THIS_YEAR":
      return "Tahun Ini";

    case "LAST_YEAR":
      return "Tahun Lalu";

    case "CUSTOM":
      return "Periode Khusus";
  }
}


export function FinanceSummaryPanel() {
  const initialRange =
    useMemo(
      () =>
        rangeForPreset(
          "THIS_MONTH",
        ),
      [],
    );

  const [
    preset,
    setPreset,
  ] = useState<PeriodPreset>(
    "THIS_MONTH",
  );

  const [
    customFrom,
    setCustomFrom,
  ] = useState(
    initialRange?.from ?? "",
  );

  const [
    customTo,
    setCustomTo,
  ] = useState(
    initialRange?.to ?? "",
  );

  const [
    summary,
    setSummary,
  ] = useState<
    FinanceSummaryData | null
  >(null);

  const [
    loading,
    setLoading,
  ] = useState(true);

  const [
    error,
    setError,
  ] = useState<
    unknown
  >(null);


  const selectedRange =
    useMemo(
      () => {
        if (
          preset ===
          "CUSTOM"
        ) {
          if (
            !customFrom ||
            !customTo
          ) {
            return null;
          }

          return {
            from: customFrom,
            to: customTo,
          };
        }

        return rangeForPreset(
          preset,
        );
      },
      [
        preset,
        customFrom,
        customTo,
      ],
    );


  const invalidCustomRange =
    preset === "CUSTOM"
    && Boolean(
      customFrom
      && customTo
      && customFrom > customTo,
    );


  useEffect(
    () => {
      const range =
        selectedRange;

      if (
        !range
        || invalidCustomRange
      ) {
        return;
      }

      const requestRange: DateRange = {
        from: range.from,
        to: range.to,
      };

      let active = true;

      async function load() {
        setLoading(true);
        setError(null);

        try {
          const response =
            await getFinanceSummary(
              requestRange,
            );

          if (!active) {
            return;
          }

          setSummary(
            response.data,
          );
        } catch (exception) {
          if (!active) {
            return;
          }

          setError(exception);
        } finally {
          if (active) {
            setLoading(false);
          }
        }
      }

      void load();

      return () => {
        active = false;
      };
    },
    [
      selectedRange,
      invalidCustomRange,
    ],
  );


  return (
    <section
      className={
        styles.summary
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
            DATA KEUANGAN
          </span>

          <h2>
            Kondisi keuangan
          </h2>

          <p>
            Angka aktual berdasarkan
            transaksi SIGNOVA.
          </p>
        </div>

        <label
          className={
            styles.periodSelect
          }
        >
          <CalendarDays
            size={16}
          />

          <select
            value={preset}
            aria-label="Periode ringkasan keuangan"
            onChange={
              (event) =>
                setPreset(
                  event.target
                    .value as
                    PeriodPreset,
                )
            }
          >
            <option value="TODAY">
              Hari Ini
            </option>

            <option value="LAST_7_DAYS">
              7 Hari Terakhir
            </option>

            <option value="THIS_MONTH">
              Bulan Ini
            </option>

            <option value="LAST_MONTH">
              Bulan Lalu
            </option>

            <option value="THIS_YEAR">
              Tahun Ini
            </option>

            <option value="LAST_YEAR">
              Tahun Lalu
            </option>

            <option value="CUSTOM">
              Periode Khusus
            </option>
          </select>
        </label>
      </header>


      {preset ===
      "CUSTOM" ? (
        <div
          className={
            styles.customPeriod
          }
        >
          <label>
            <span>
              Dari
            </span>

            <input
              type="date"
              value={
                customFrom
              }
              onChange={
                (event) =>
                  setCustomFrom(
                    event.target
                      .value,
                  )
              }
            />
          </label>

          <label>
            <span>
              Sampai
            </span>

            <input
              type="date"
              value={
                customTo
              }
              onChange={
                (event) =>
                  setCustomTo(
                    event.target
                      .value,
                  )
              }
            />
          </label>
        </div>
      ) : null}


      {invalidCustomRange ? (
        <ActionFeedback
          tone="error"
          title="Periode belum benar"
          message="Tanggal akhir tidak boleh lebih awal daripada tanggal mulai."
        />
      ) : null}


      {error ? (
        <ActionFeedback
          tone="error"
          title="Ringkasan belum dapat dimuat"
          message={
            apiErrorMessage(
              error,
              "Data keuangan belum dapat dimuat.",
            )
          }
          requestId={
            apiRequestId(
              error,
            )
          }
        />
      ) : null}


      {loading &&
      !summary ? (
        <div
          className={
            styles.loading
          }
        >
          Memuat ringkasan
          keuangan...
        </div>
      ) : null}


      {summary ? (
        <>
          <article
            className={
              styles.balanceCard
            }
          >
            <span
              className={
                styles.balanceIcon
              }
            >
              <WalletCards
                size={23}
                strokeWidth={1.9}
              />
            </span>

            <div
              className={
                styles.balanceCopy
              }
            >
              <span>
                Saldo Kas & Bank
              </span>

              <strong>
                {money(
                  summary
                    .cash_bank
                    .total_balance,
                )}
              </strong>

              <small>
                {
                  summary
                    .cash_bank
                    .account_count
                }{" "}
                rekening • posisi
                saat ini
              </small>
            </div>

            <div
              className={
                styles.balanceBreakdown
              }
            >
              <span>
                Kas
                <strong>
                  {money(
                    summary
                      .cash_bank
                      .cash_balance,
                  )}
                </strong>
              </span>

              <span>
                Bank
                <strong>
                  {money(
                    summary
                      .cash_bank
                      .bank_balance,
                  )}
                </strong>
              </span>
            </div>
          </article>


          <div
            className={
              styles.flowGrid
            }
          >
            <article
              className={
                styles.metricCard
              }
            >
              <span
                className={
                  styles.metricIcon
                }
                data-tone="in"
              >
                <ArrowUpRight
                  size={18}
                />
              </span>

              <span>
                Uang Masuk
              </span>

              <strong>
                {money(
                  summary
                    .cashflow
                    .total_in,
                )}
              </strong>
            </article>

            <article
              className={
                styles.metricCard
              }
            >
              <span
                className={
                  styles.metricIcon
                }
                data-tone="out"
              >
                <ArrowDownRight
                  size={18}
                />
              </span>

              <span>
                Uang Keluar
              </span>

              <strong>
                {money(
                  summary
                    .cashflow
                    .total_out,
                )}
              </strong>
            </article>

            <article
              className={
                styles.metricCard
              }
            >
              <span
                className={
                  styles.metricIcon
                }
                data-tone="net"
              >
                <Scale
                  size={18}
                />
              </span>

              <span>
                Arus Kas Bersih
              </span>

              <strong>
                {signedMoney(
                  summary
                    .cashflow
                    .net,
                )}
              </strong>
            </article>
          </div>


          <div
            className={
              styles.detailGrid
            }
          >
            <article
              className={
                styles.detailCard
              }
            >
              <header>
                <Landmark
                  size={19}
                />

                <div>
                  <strong>
                    Penerimaan
                  </strong>

                  <span>
                    Sumber uang masuk
                    pada periode ini.
                  </span>
                </div>
              </header>

              <dl>
                <div>
                  <dt>
                    Pembayaran
                    Pelanggan
                  </dt>

                  <dd>
                    {money(
                      summary
                        .cashflow
                        .breakdown
                        .customer_payment_net,
                    )}
                  </dd>
                </div>

                <div>
                  <dt>
                    Pemasukan
                    Lainnya
                  </dt>

                  <dd>
                    {money(
                      summary
                        .cashflow
                        .breakdown
                        .manual_income_net,
                    )}
                  </dd>
                </div>

                <div>
                  <dt>
                    Pengeluaran
                    Operasional
                  </dt>

                  <dd>
                    {money(
                      summary
                        .cashflow
                        .breakdown
                        .expense_net,
                    )}
                  </dd>
                </div>
              </dl>
            </article>


            <article
              className={
                styles.detailCard
              }
            >
              <header>
                <ReceiptText
                  size={19}
                />

                <div>
                  <strong>
                    Piutang
                  </strong>

                  <span>
                    Tagihan yang belum
                    diterima penuh.
                  </span>
                </div>
              </header>

              <div
                className={
                  styles.receivableValue
                }
              >
                <span>
                  Belum diterima
                </span>

                <strong>
                  {money(
                    summary
                      .receivable
                      .outstanding_total,
                  )}
                </strong>

                <small>
                  {
                    summary
                      .receivable
                      .invoice_count
                  }{" "}
                  Tagihan berjalan
                </small>
              </div>

              <div
                className={
                  styles.overdue
                }
              >
                <span>
                  Jatuh tempo
                </span>

                <strong>
                  {money(
                    summary
                      .receivable
                      .overdue_total,
                  )}
                </strong>

                <small>
                  {
                    summary
                      .receivable
                      .overdue_count
                  }{" "}
                  Tagihan
                </small>
              </div>
            </article>
          </div>


          <footer
            className={
              styles.periodNote
            }
          >
            <CalendarDays
              size={15}
            />

            <span>
              Arus kas{" "}
              <strong>
                {
                  presetLabel(
                    preset,
                  )
                }
              </strong>
              :{" "}
              {localDate(
                summary
                  .period
                  .from,
              )}{" "}
              –{" "}
              {localDate(
                summary
                  .period
                  .to,
              )}
              . Saldo Kas & Bank dan
              Piutang adalah posisi
              saat ini.
            </span>
          </footer>
        </>
      ) : null}
    </section>
  );
}
