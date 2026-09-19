"use client";

import {
  ArrowUpRight,
  ChevronLeft,
  ChevronRight,
  Search,
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
  ManualExpenseAction,
} from "@/components/finance/manual-expense-action";

import {
  ExpenseWorkflowActions,
} from "@/components/finance/expense-workflow-actions";

import {
  apiErrorMessage,
  apiRequestId,
} from "@/lib/api/error-message";

import {
  getActiveCapabilities,
} from "@/lib/auth/active-capabilities-service";

import {
  listExpenses,
} from "@/lib/finance/expense-service";

import {
  getFinanceSummary,
} from "@/lib/finance/finance-summary-service";

import type {
  Expense,
  ExpenseStatus,
} from "@/types/expense";

import styles from "./expense-register.module.css";

type StatusFilter =
  | "ALL"
  | ExpenseStatus;

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

const STATUSES: {
  value: StatusFilter;
  label: string;
}[] = [
  {
    value: "ALL",
    label: "Semua",
  },
  {
    value: "POSTED",
    label: "Tercatat",
  },
  {
    value: "PENDING_APPROVAL",
    label: "Menunggu Persetujuan",
  },
  {
    value: "REJECTED",
    label: "Ditolak",
  },
  {
    value: "DRAFT",
    label: "Draf",
  },
  {
    value: "VOID",
    label: "Dibatalkan",
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

  return {
    from:
      inputDate(
        new Date(
          today.getFullYear(),
          today.getMonth(),
          1,
        ),
      ),

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

function statusLabel(
  status: ExpenseStatus,
): string {
  switch (status) {
    case "POSTED":
      return "Tercatat";

    case "PENDING_APPROVAL":
      return "Menunggu Persetujuan";

    case "REJECTED":
      return "Ditolak";

    case "VOID":
      return "Dibatalkan";

    case "DRAFT":
    default:
      return "Draf";
  }
}

function statusClass(
  status: ExpenseStatus,
): string {
  switch (status) {
    case "POSTED":
      return styles.sourceBadge;

    case "PENDING_APPROVAL":
      return styles.pendingBadge;

    case "REJECTED":
      return styles.rejectedBadge;

    case "VOID":
      return styles.reversedBadge;

    case "DRAFT":
    default:
      return styles.modeBadge;
  }
}

function itemTitle(
  item: Expense,
): string {
  return (
    item.category
    ?? item.description
  );
}

export function ExpenseRegister() {
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
    status,
    setStatus,
  ] = useState<
    StatusFilter
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
    Expense[]
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

  const [
    refreshVersion,
    setRefreshVersion,
  ] = useState(0);

  const [
    canManage,
    setCanManage,
  ] = useState(false);

  const [
    canApprove,
    setCanApprove,
  ] = useState(false);

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
    let active = true;

    void getActiveCapabilities()
      .then(
        (response) => {
          if (!active) {
            return;
          }

          const capabilityCodes =
            response
              .data
              .capability_codes;

          setCanManage(
            capabilityCodes.includes(
              "finance.expense.manage",
            ),
          );

          setCanApprove(
            capabilityCodes.includes(
              "finance.expense.approve",
            ),
          );
        },
      )
      .catch(() => {
        if (active) {
          setCanManage(false);
          setCanApprove(false);
        }
      });

    return () => {
      active = false;
    };
  }, []);

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
            .breakdown
            .expense_net,
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
  }, [
    range,
    refreshVersion,
  ]);

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
          await listExpenses({
            search:
              debouncedSearch
              || undefined,

            status:
              status === "ALL"
                ? undefined
                : status,

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
    status,
    debouncedSearch,
    page,
    refreshVersion,
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
            Pengeluaran
          </h1>

          <p>
            Catat dan pantau uang
            yang benar-benar keluar
            dari Kas atau Bank usaha.
          </p>
        </div>

        <span
          className={
            styles.heroIcon
          }
          aria-hidden="true"
        >
          <ArrowUpRight
            size={24}
          />
        </span>
      </header>

      <ManualExpenseAction
        onRecorded={() => {
          setPage(1);

          setRefreshVersion(
            (current) =>
              current + 1,
          );
        }}
      />

      <section
        className={
          styles.summaryCard
        }
      >
        <span>
          Uang Keluar
        </span>

        <strong>
          {range
          && periodTotal !== null
            ? formatMoney(
                periodTotal,
                "IDR",
              )
            : "—"}
        </strong>

        <small>
          Total pengeluaran efektif
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
                setPreset(
                  event.target
                    .value as PeriodPreset,
                );

                setPage(1);
              }
            }
          >
            {PERIODS.map(
              (item) => (
                <option
                  key={item.value}
                  value={item.value}
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
                value={customFrom}
                onChange={
                  (event) => {
                    setCustomFrom(
                      event.target.value,
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
                value={customTo}
                onChange={
                  (event) => {
                    setCustomTo(
                      event.target.value,
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
          aria-label="Filter status pengeluaran"
        >
          {STATUSES.map(
            (item) => (
              <button
                key={item.value}
                type="button"
                className={
                  status === item.value
                    ? styles
                        .filterActive
                    : styles
                        .filterButton
                }
                onClick={() => {
                  setStatus(
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
            placeholder="Cari kategori atau keterangan..."
            aria-label="Cari pengeluaran"
            onChange={
              (event) =>
                setSearch(
                  event.target.value,
                )
            }
          />
        </label>
      </section>

      {range && error ? (
        <ActionFeedback
          tone="error"
          title="Pengeluaran belum dapat dimuat"
          message={
            apiErrorMessage(
              error,
              "Data pengeluaran belum dapat dimuat. Coba lagi.",
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
          Daftar Pengeluaran
        </strong>

        <span>
          {totalItems} catatan
        </span>
      </div>

      {loading ? (
        <div
          className={
            styles.loadingList
          }
          aria-label="Memuat pengeluaran"
        >
          <div
            className={
              styles.skeleton
            }
          />

          <div
            className={
              styles.skeleton
            }
          />

          <div
            className={
              styles.skeleton
            }
          />
        </div>
      ) : null}

      {!loading
      && !error
      && items.length === 0 ? (
        <div
          className={
            styles.empty
          }
        >
          <strong>
            Belum ada pengeluaran
          </strong>

          <p>
            Pengeluaran pada periode
            dan filter yang dipilih
            belum tersedia.
          </p>
        </div>
      ) : null}

      {!loading
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
                className={[
                  styles.item,
                  item.status === "VOID"
                    ? styles.reversed
                    : "",
                ]
                  .filter(Boolean)
                  .join(" ")}
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
                        {formatDateTime(
                          item.incurred_at,
                        )}
                      </span>
                    </div>

                    <strong
                      className={
                        styles.amount
                      }
                    >
                      -{" "}
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
                        statusClass(
                          item.status,
                        )
                      }
                    >
                      {statusLabel(
                        item.status,
                      )}
                    </span>

                    {item.category ? (
                      <span
                        className={
                          styles.modeBadge
                        }
                      >
                        {item.category}
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
                        Kas / Bank
                      </dt>

                      <dd>
                        {item
                          .cash_account
                          ?.name
                          ?? "-"}
                      </dd>
                    </div>

                    <div>
                      <dt>
                        Keterangan
                      </dt>

                      <dd>
                        {item.description}
                      </dd>
                    </div>

                    {item.status ===
                    "VOID"
                    && item.void_reason ? (
                      <div>
                        <dt>
                          Alasan batal
                        </dt>

                        <dd>
                          {item.void_reason}
                        </dd>
                      </div>
                    ) : null}
                  </dl>
                </div>
                <ExpenseWorkflowActions
                  expense={item}
                  canManage={
                    canManage
                  }
                  canApprove={
                    canApprove
                  }
                  onChanged={() => {
                    setRefreshVersion(
                      (current) =>
                        current + 1,
                    );
                  }}
                />
              </article>
            ),
          )}
        </div>
      ) : null}

      {!loading
      && !error
      && lastPage > 1 ? (
        <nav
          className={
            styles.pagination
          }
          aria-label="Halaman pengeluaran"
        >
          <button
            type="button"
            disabled={page <= 1}
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
              size={16}
              aria-hidden="true"
            />
            Sebelumnya
          </button>

          <span>
            Halaman {page}
            {" / "}
            {lastPage}
          </span>

          <button
            type="button"
            disabled={
              page >= lastPage
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
              size={16}
              aria-hidden="true"
            />
          </button>
        </nav>
      ) : null}
    </section>
  );
}
