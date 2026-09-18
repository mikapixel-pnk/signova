"use client";

import {
  ArrowDownLeft,
  ChevronLeft,
  ChevronRight,
  Search,
} from "lucide-react";

import Link from "next/link";

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

import {
  incomeGroupLabel,
} from "@/lib/finance/income-register-labels";

import {
  listIncomeRegister,
} from "@/lib/finance/income-register-service";

import type {
  IncomeRegisterGroup,
  IncomeRegisterItem,
} from "@/types/income-register";

import styles from "./income-register.module.css";


type GroupFilter =
  | "ALL"
  | IncomeRegisterGroup;


type PeriodPreset =
  | "TODAY"
  | "LAST_7_DAYS"
  | "THIS_MONTH"
  | "LAST_MONTH"
  | "CUSTOM";


type DateRange = {
  from: string;
  to: string;
};


const GROUPS: {
  value: GroupFilter;
  label: string;
}[] = [
  {
    value: "ALL",
    label: "Semua",
  },
  {
    value: "CUSTOMER_PAYMENT",
    label: "Tagihan",
  },
  {
    value: "POS",
    label: "POS",
  },
  {
    value: "MARKETPLACE",
    label: "Marketplace",
  },
  {
    value: "MANUAL",
    label: "Lainnya",
  },
];


const PERIODS: {
  value: PeriodPreset;
  label: string;
}[] = [
  {
    value: "THIS_MONTH",
    label: "Bulan Ini",
  },
  {
    value: "TODAY",
    label: "Hari Ini",
  },
  {
    value: "LAST_7_DAYS",
    label: "7 Hari Terakhir",
  },
  {
    value: "LAST_MONTH",
    label: "Bulan Lalu",
  },
  {
    value: "CUSTOM",
    label: "Pilih Periode",
  },
];


function inputDate(
  date: Date,
): string {
  const year =
    date.getFullYear();

  const month =
    String(
      date.getMonth() + 1,
    ).padStart(
      2,
      "0",
    );

  const day =
    String(
      date.getDate(),
    ).padStart(
      2,
      "0",
    );

  return `${year}-${month}-${day}`;
}


function currentMonthRange():
DateRange {
  const today =
    new Date();

  const from =
    new Date(
      today.getFullYear(),
      today.getMonth(),
      1,
    );

  return {
    from:
      inputDate(from),

    to:
      inputDate(today),
  };
}


function presetRange(
  preset: PeriodPreset,
  customFrom: string,
  customTo: string,
): DateRange | null {
  const today =
    new Date();

  if (preset === "CUSTOM") {
    if (
      !customFrom
      || !customTo
      || customTo < customFrom
    ) {
      return null;
    }

    return {
      from: customFrom,
      to: customTo,
    };
  }

  if (preset === "TODAY") {
    const value =
      inputDate(today);

    return {
      from: value,
      to: value,
    };
  }

  if (
    preset ===
    "LAST_7_DAYS"
  ) {
    const from =
      new Date(today);

    from.setDate(
      from.getDate() - 6,
    );

    return {
      from:
        inputDate(from),

      to:
        inputDate(today),
    };
  }

  if (
    preset ===
    "LAST_MONTH"
  ) {
    const from =
      new Date(
        today.getFullYear(),
        today.getMonth() - 1,
        1,
      );

    const to =
      new Date(
        today.getFullYear(),
        today.getMonth(),
        0,
      );

    return {
      from:
        inputDate(from),

      to:
        inputDate(to),
    };
  }

  return currentMonthRange();
}


function formatMoney(
  amount: string,
  currency: string,
): string {
  const numeric =
    Number(amount);

  if (
    !Number.isFinite(
      numeric,
    )
  ) {
    return amount;
  }

  return new Intl
    .NumberFormat(
      "id-ID",
      {
        style: "currency",
        currency:
          currency || "IDR",
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      },
    )
    .format(numeric);
}


function formatDateTime(
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

  return new Intl
    .DateTimeFormat(
      "id-ID",
      {
        day: "numeric",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
      },
    )
    .format(date);
}


function accountLabel(
  item: IncomeRegisterItem,
): string {
  if (
    item.cash_account
      .bank_name
  ) {
    return `${
      item.cash_account
        .bank_name
    } · ${
      item.cash_account.name
    }`;
  }

  return item.cash_account.name;
}


function itemTitle(
  item: IncomeRegisterItem,
): string {
  return (
    item.party?.name
    ?? item.source.category
    ?? item.description
    ?? item.source.label
  );
}


export function IncomeRegister() {
  const initialRange =
    useMemo(
      () =>
        currentMonthRange(),
      [],
    );

  const [
    preset,
    setPreset,
  ] = useState<
    PeriodPreset
  >(
    "THIS_MONTH",
  );

  const [
    customFrom,
    setCustomFrom,
  ] = useState(
    initialRange.from,
  );

  const [
    customTo,
    setCustomTo,
  ] = useState(
    initialRange.to,
  );

  const [
    group,
    setGroup,
  ] = useState<
    GroupFilter
  >(
    "ALL",
  );

  const [
    search,
    setSearch,
  ] = useState("");

  const [
    debouncedSearch,
    setDebouncedSearch,
  ] = useState("");

  const [
    page,
    setPage,
  ] = useState(1);

  const [
    items,
    setItems,
  ] = useState<
    IncomeRegisterItem[]
  >([]);

  const [
    totalItems,
    setTotalItems,
  ] = useState(0);

  const [
    lastPage,
    setLastPage,
  ] = useState(1);

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

  const [
    periodTotal,
    setPeriodTotal,
  ] = useState<
    string | null
  >(null);


  const range =
    useMemo(
      () =>
        presetRange(
          preset,
          customFrom,
          customTo,
        ),
      [
        preset,
        customFrom,
        customTo,
      ],
    );


  const invalidRange =
    preset === "CUSTOM"
    && range === null;


  useEffect(() => {
    const timer =
      window.setTimeout(
        () => {
          setDebouncedSearch(
            search.trim(),
          );

          setPage(1);
        },
        350,
      );

    return () => {
      window.clearTimeout(
        timer,
      );
    };
  }, [search]);


  useEffect(() => {
    if (!range) {
      return;
    }

    const resolvedRange =
      range;

    let active = true;

    async function loadSummary() {
      try {
        const response =
          await getFinanceSummary({
            from:
              resolvedRange.from,

            to:
              resolvedRange.to,
          });

        if (!active) {
          return;
        }

        setPeriodTotal(
          response
            .data
            .cashflow
            .total_in,
        );
      } catch {
        if (active) {
          setPeriodTotal(null);
        }
      }
    }

    void loadSummary();

    return () => {
      active = false;
    };
  }, [range]);


  useEffect(() => {
    if (!range) {
      return;
    }

    const resolvedRange =
      range;

    let active = true;

    async function load() {
      setLoading(true);
      setError(null);

      try {
        const response =
          await listIncomeRegister({
            search:
              debouncedSearch
              || undefined,

            group:
              group === "ALL"
                ? undefined
                : group,

            from:
              resolvedRange.from,

            to:
              resolvedRange.to,

            page,

            per_page: 20,
          });

        if (!active) {
          return;
        }

        setItems(
          response.data,
        );

        setTotalItems(
          response.meta.total,
        );

        setLastPage(
          Math.max(
            1,
            response
              .meta
              .last_page,
          ),
        );
      } catch (caught) {
        if (!active) {
          return;
        }

        setItems([]);
        setTotalItems(0);
        setLastPage(1);
        setError(caught);
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
  }, [
    range,
    group,
    debouncedSearch,
    page,
  ]);


  return (
    <section
      className={styles.page}
    >
      <header
        className={styles.hero}
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
            Pemasukan
          </h1>

          <p>
            Semua uang yang
            diterima usaha dari
            pembayaran pelanggan
            dan sumber lainnya.
          </p>
        </div>

        <span
          className={
            styles.heroIcon
          }
          aria-hidden="true"
        >
          <ArrowDownLeft
            size={24}
          />
        </span>
      </header>


      <section
        className={
          styles.summaryCard
        }
      >
        <span>
          Uang Masuk
        </span>

        <strong>
          {range && periodTotal !== null
            ? formatMoney(
                periodTotal,
                "IDR",
              )
            : "—"}
        </strong>

        <small>
          Total seluruh sumber
          pada periode yang dipilih.
        </small>
      </section>


      <section
        className={
          styles.periodCard
        }
      >
        <label>
          <span>
            Periode
          </span>

          <select
            value={preset}
            onChange={
              (event) => {
                const nextPreset =
                  event.target.value as PeriodPreset;

                setPreset(
                  nextPreset,
                );

                setPage(1);
              }
            }
          >
            {PERIODS.map(
              (item) => (
                <option
                  key={
                    item.value
                  }
                  value={
                    item.value
                  }
                >
                  {item.label}
                </option>
              ),
            )}
          </select>
        </label>

        {preset ===
        "CUSTOM" ? (
          <div
            className={
              styles.customDates
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
                  (event) => {
                    setCustomFrom(
                      event
                        .target
                        .value,
                    );

                    setPage(1);
                  }
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
                  (event) => {
                    setCustomTo(
                      event
                        .target
                        .value,
                    );

                    setPage(1);
                  }
                }
              />
            </label>
          </div>
        ) : null}
      </section>


      {invalidRange ? (
        <ActionFeedback
          tone="error"
          title="Periode belum benar"
          message="Tanggal akhir tidak boleh lebih awal daripada tanggal mulai."
        />
      ) : null}


      <section
        className={
          styles.controls
        }
      >
        <div
          className={
            styles.filters
          }
          aria-label="Filter sumber pemasukan"
        >
          {GROUPS.map(
            (item) => (
              <button
                key={item.value}
                type="button"
                className={
                  group ===
                  item.value
                    ? styles
                        .filterActive
                    : styles
                        .filterButton
                }
                onClick={() => {
                  setGroup(
                    item.value,
                  );

                  setPage(1);
                }}
              >
                {item.label}
              </button>
            ),
          )}
        </div>

        <label
          className={
            styles.searchBox
          }
        >
          <Search
            size={18}
            aria-hidden="true"
          />

          <input
            type="search"
            value={search}
            placeholder="Cari pelanggan, referensi, atau keterangan..."
            aria-label="Cari pemasukan"
            onChange={
              (event) =>
                setSearch(
                  event
                    .target
                    .value,
                )
            }
          />
        </label>
      </section>


      {range && error ? (
        <ActionFeedback
          tone="error"
          title="Pemasukan belum dapat dimuat"
          message={
            apiErrorMessage(
              error,
              "Data pemasukan belum dapat dimuat. Coba lagi.",
            )
          }
          requestId={
            apiRequestId(
              error,
            )
          }
        />
      ) : null}


      <div
        className={
          styles.listHeader
        }
      >
        <strong>
          Riwayat Pemasukan
        </strong>

        {range && !loading ? (
          <span>
            {totalItems}
            {" "}
            transaksi
          </span>
        ) : null}
      </div>


      {range && loading ? (
        <div
          className={
            styles.loadingList
          }
          aria-label="Memuat pemasukan"
        >
          {[1, 2, 3].map(
            (item) => (
              <div
                key={item}
                className={
                  styles.skeleton
                }
              />
            ),
          )}
        </div>
      ) : null}


      {range
      && !loading
      && !error
      && items.length === 0 ? (
        <section
          className={
            styles.empty
          }
        >
          <strong>
            Belum ada pemasukan
          </strong>

          <p>
            Tidak ada transaksi
            uang masuk yang sesuai
            dengan periode dan
            filter ini.
          </p>
        </section>
      ) : null}


      {range
      && !loading
      && items.length > 0 ? (
        <div
          className={
            styles.list
          }
        >
          {items.map(
            (item) => (
              <article
                key={item.id}
                className={
                  item.reversed
                    ? `${styles.item} ${styles.reversed}`
                    : styles.item
                }
              >
                <div
                  className={
                    styles.itemMain
                  }
                >
                  <div
                    className={
                      styles.itemHeading
                    }
                  >
                    <div>
                      <strong>
                        {itemTitle(
                          item,
                        )}
                      </strong>

                      <span>
                        {
                          item
                            .source
                            .label
                        }
                      </span>
                    </div>

                    <strong
                      className={
                        styles.amount
                      }
                    >
                      {formatMoney(
                        item.amount,
                        item.currency,
                      )}
                    </strong>
                  </div>

                  <div
                    className={
                      styles.badges
                    }
                  >
                    <span
                      className={
                        styles
                          .sourceBadge
                      }
                    >
                      {incomeGroupLabel(
                        item
                          .source
                          .group,
                      )}
                    </span>

                    <span
                      className={
                        styles
                          .modeBadge
                      }
                    >
                      {item
                        .source
                        .automatic
                        ? "Otomatis"
                        : "Manual"}
                    </span>

                    {item.reversed ? (
                      <span
                        className={
                          styles
                            .reversedBadge
                        }
                      >
                        Dibatalkan
                      </span>
                    ) : null}
                  </div>

                  <dl
                    className={
                      styles.meta
                    }
                  >
                    <div>
                      <dt>
                        Tanggal
                      </dt>

                      <dd>
                        {formatDateTime(
                          item
                            .occurred_at,
                        )}
                      </dd>
                    </div>

                    <div>
                      <dt>
                        Masuk ke
                      </dt>

                      <dd>
                        {accountLabel(
                          item,
                        )}
                      </dd>
                    </div>

                    {item
                      .document ? (
                      <div>
                        <dt>
                          Tagihan
                        </dt>

                        <dd>
                          <Link
                            href={`/app/tagihan/${
                              item
                                .document
                                .id
                            }`}
                          >
                            {
                              item
                                .document
                                .number
                            }
                          </Link>
                        </dd>
                      </div>
                    ) : null}

                    {item
                      .reference ? (
                      <div>
                        <dt>
                          Referensi
                        </dt>

                        <dd>
                          {
                            item
                              .reference
                          }
                        </dd>
                      </div>
                    ) : null}
                  </dl>
                </div>
              </article>
            ),
          )}
        </div>
      ) : null}


      {range
      && !loading
      && !error
      && totalItems > 0 ? (
        <footer
          className={
            styles.pagination
          }
        >
          <button
            type="button"
            disabled={
              page <= 1
            }
            onClick={() =>
              setPage(
                (current) =>
                  Math.max(
                    1,
                    current - 1,
                  ),
              )
            }
          >
            <ChevronLeft
              size={17}
            />

            Sebelumnya
          </button>

          <span>
            Halaman {page}
            {" "}
            dari {lastPage}
          </span>

          <button
            type="button"
            disabled={
              page >=
              lastPage
            }
            onClick={() =>
              setPage(
                (current) =>
                  Math.min(
                    lastPage,
                    current + 1,
                  ),
              )
            }
          >
            Berikutnya

            <ChevronRight
              size={17}
            />
          </button>
        </footer>
      ) : null}
    </section>
  );
}
