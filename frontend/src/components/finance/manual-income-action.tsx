"use client";

import {
  Plus,
  X,
} from "lucide-react";

import Link from "next/link";

import {
  useEffect,
  useState,
} from "react";

import {
  Button,
} from "@/components/ui/button";

import {
  apiErrorMessage,
  apiRequestId,
} from "@/lib/api/error-message";

import {
  getActiveCapabilities,
} from "@/lib/auth/active-capabilities-service";

import {
  listCashAccounts,
} from "@/lib/finance/cash-account-service";

import {
  DEFAULT_MANUAL_INCOME_CATEGORY,
  MANUAL_INCOME_CATEGORIES,
} from "@/lib/finance/manual-income-categories";

import {
  recordIncome,
} from "@/lib/finance/income-record-service";

import type {
  CashAccount,
} from "@/types/cash-account";

import styles from "./manual-income-action.module.css";

type ManualIncomeActionProps = {
  onRecorded?: () => void;
};

type FormState = {
  cashAccountId: string;
  amount: string;
  date: string;
  category: string;
  description: string;
  reference: string;
};

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

function inputTime(
  date: Date,
): string {
  const hours =
    String(
      date.getHours(),
    ).padStart(
      2,
      "0",
    );

  const minutes =
    String(
      date.getMinutes(),
    ).padStart(
      2,
      "0",
    );

  return `${hours}:${minutes}:00`;
}

function emptyForm(): FormState {
  return {
    cashAccountId: "",
    amount: "",
    date:
      inputDate(
        new Date(),
      ),
    category:
      DEFAULT_MANUAL_INCOME_CATEGORY,
    description: "",
    reference: "",
  };
}

function accountLabel(
  account: CashAccount,
): string {
  if (
    account.bank_name
  ) {
    return `${
      account.bank_name
    } · ${
      account.name
    }`;
  }

  return account.name;
}

export function ManualIncomeAction({
  onRecorded,
}: ManualIncomeActionProps) {
  const [
    canManage,
    setCanManage,
  ] = useState<
    boolean | null
  >(null);

  const [
    open,
    setOpen,
  ] = useState(false);

  const [
    accounts,
    setAccounts,
  ] = useState<
    CashAccount[]
  >([]);

  const [
    accountsLoading,
    setAccountsLoading,
  ] = useState(false);

  const [
    saving,
    setSaving,
  ] = useState(false);

  const [
    form,
    setForm,
  ] = useState<FormState>(
    emptyForm,
  );

  const [
    error,
    setError,
  ] = useState<
    string | null
  >(null);

  const [
    requestId,
    setRequestId,
  ] = useState<
    string | null
  >(null);

  const [
    success,
    setSuccess,
  ] = useState<
    string | null
  >(null);

  useEffect(() => {
    let active =
      true;

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
              "finance.income.manage",
            ),
          );
        },
      )
      .catch(() => {
        if (active) {
          /*
           * UI hint harus fail closed.
           * API business tetap menjadi
           * enforcement authorization.
           */
          setCanManage(false);
        }
      });

    return () => {
      active = false;
    };
  }, []);

  async function openForm() {
    setForm(
      emptyForm(),
    );

    setError(null);
    setRequestId(null);
    setSuccess(null);
    setOpen(true);
    setAccountsLoading(true);

    try {
      const response =
        await listCashAccounts();

      const activeAccounts =
        response.data.filter(
          (account) =>
            account.status ===
            "ACTIVE",
        );

      setAccounts(
        activeAccounts,
      );

      const preferred =
        activeAccounts.find(
          (account) =>
            account.is_default,
        )
        ?? activeAccounts[0];

      if (preferred) {
        setForm(
          (current) => ({
            ...current,
            cashAccountId:
              preferred.id,
          }),
        );
      }
    } catch (caught) {
      setAccounts([]);

      setError(
        apiErrorMessage(
          caught,
          "Daftar Kas & Bank belum dapat dimuat.",
        ),
      );

      setRequestId(
        apiRequestId(
          caught,
        ),
      );
    } finally {
      setAccountsLoading(
        false,
      );
    }
  }

  function closeForm() {
    if (saving) {
      return;
    }

    setOpen(false);
    setError(null);
    setRequestId(null);
  }

  async function handleSubmit() {
    const amount =
      Number(
        form.amount,
      );

    const description =
      form.description.trim();

    if (
      !form.cashAccountId
    ) {
      setError(
        "Pilih Kas atau Bank tujuan pemasukan.",
      );

      return;
    }

    if (
      !Number.isFinite(
        amount,
      )
      || amount <= 0
    ) {
      setError(
        "Nominal pemasukan harus lebih dari Rp0.",
      );

      return;
    }

    if (!form.date) {
      setError(
        "Tanggal pemasukan wajib diisi.",
      );

      return;
    }

    if (!description) {
      setError(
        "Keterangan pemasukan wajib diisi.",
      );

      return;
    }

    setSaving(true);
    setError(null);
    setRequestId(null);

    try {
      const now =
        new Date();

      const response =
        await recordIncome({
          cash_account_id:
            form.cashAccountId,

          amount:
            amount.toFixed(2),

          occurred_at:
            `${
              form.date
            } ${
              inputTime(now)
            }`,

          category:
            form.category.trim()
            || null,

          description,

          reference:
            form.reference.trim()
            || null,
        });

      setSuccess(
        response.message
        ?? "Pemasukan berhasil dicatat.",
      );

      setOpen(false);

      onRecorded?.();
    } catch (caught) {
      setError(
        apiErrorMessage(
          caught,
          "Pemasukan belum dapat dicatat.",
        ),
      );

      setRequestId(
        apiRequestId(
          caught,
        ),
      );
    } finally {
      setSaving(false);
    }
  }

  if (
    canManage !== true
  ) {
    return null;
  }

  const hasAccounts =
    accounts.length > 0;

  return (
    <>
      <div
        className={
          styles.actionArea
        }
      >
        {success ? (
          <div
            className={
              styles.success
            }
            role="status"
          >
            {success}
          </div>
        ) : null}

        <Button
          type="button"
          className={
            styles.actionButton
          }
          leadingIcon={
            <Plus
              size={18}
              aria-hidden="true"
            />
          }
          onClick={() =>
            void openForm()
          }
        >
          Pemasukan Lainnya
        </Button>
      </div>

      {open ? (
        <div
          className={
            styles.overlay
          }
          onMouseDown={
            closeForm
          }
        >
          <form
            className={
              styles.sheet
            }
            role="dialog"
            aria-modal="true"
            aria-labelledby="manual-income-title"
            onMouseDown={
              (event) =>
                event.stopPropagation()
            }
            onSubmit={
              (event) => {
                event.preventDefault();

                void handleSubmit();
              }
            }
          >
            <header
              className={
                styles.sheetHeader
              }
            >
              <div>
                <span>
                  Pemasukan
                </span>

                <h2
                  id="manual-income-title"
                >
                  Catat Pemasukan Lainnya
                </h2>

                <p>
                  Catat uang yang benar-benar
                  sudah diterima di Kas atau
                  Bank.
                </p>
              </div>

              <button
                type="button"
                className={
                  styles.closeButton
                }
                aria-label="Tutup"
                disabled={saving}
                onClick={
                  closeForm
                }
              >
                <X
                  size={19}
                  aria-hidden="true"
                />
              </button>
            </header>

            <div
              className={
                styles.sheetBody
              }
            >
              {error ? (
                <div
                  className={
                    styles.errorBox
                  }
                  role="alert"
                >
                  <strong>
                    Belum dapat diproses
                  </strong>

                  <span>
                    {error}
                  </span>

                  {requestId ? (
                    <small>
                      ID Permintaan:{" "}
                      {requestId}
                    </small>
                  ) : null}
                </div>
              ) : null}

              <label
                className={
                  styles.field
                }
              >
                <span>
                  Masuk ke Kas / Bank *
                </span>

                <select
                  value={
                    form.cashAccountId
                  }
                  disabled={
                    saving
                    || accountsLoading
                    || !hasAccounts
                  }
                  onChange={
                    (event) =>
                      setForm(
                        (
                          current,
                        ) => ({
                          ...current,
                          cashAccountId:
                            event
                              .target
                              .value,
                        }),
                      )
                  }
                >
                  {accountsLoading ? (
                    <option value="">
                      Memuat Kas & Bank...
                    </option>
                  ) : null}

                  {!accountsLoading
                  && !hasAccounts ? (
                    <option value="">
                      Belum ada akun aktif
                    </option>
                  ) : null}

                  {accounts.map(
                    (account) => (
                      <option
                        key={
                          account.id
                        }
                        value={
                          account.id
                        }
                      >
                        {accountLabel(
                          account,
                        )}
                      </option>
                    ),
                  )}
                </select>
              </label>

              {!accountsLoading
              && !hasAccounts ? (
                <div
                  className={
                    styles.accountEmpty
                  }
                >
                  <span>
                    Tambahkan atau aktifkan
                    akun Kas & Bank terlebih
                    dahulu.
                  </span>

                  <Link
                    href="/app/keuangan/kas-bank"
                  >
                    Buka Kas & Bank
                  </Link>
                </div>
              ) : null}

              <label
                className={
                  styles.field
                }
              >
                <span>
                  Nominal *
                </span>

                <input
                  type="number"
                  inputMode="decimal"
                  min="0.01"
                  step="0.01"
                  placeholder="Contoh: 250000"
                  value={
                    form.amount
                  }
                  disabled={saving}
                  onChange={
                    (event) =>
                      setForm(
                        (
                          current,
                        ) => ({
                          ...current,
                          amount:
                            event
                              .target
                              .value,
                        }),
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
                  Tanggal *
                </span>

                <input
                  type="date"
                  value={
                    form.date
                  }
                  disabled={saving}
                  onChange={
                    (event) =>
                      setForm(
                        (
                          current,
                        ) => ({
                          ...current,
                          date:
                            event
                              .target
                              .value,
                        }),
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
                  Kategori
                </span>

                <select
                  value={
                    form.category
                  }
                  disabled={saving}
                  onChange={
                    (event) =>
                      setForm(
                        (
                          current,
                        ) => ({
                          ...current,
                          category:
                            event
                              .target
                              .value,
                        }),
                      )
                  }
                >
                  {MANUAL_INCOME_CATEGORIES.map(
                    (category) => (
                      <option
                        key={category}
                        value={category}
                      >
                        {category}
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
                  Keterangan *
                </span>

                <textarea
                  rows={3}
                  maxLength={500}
                  value={
                    form.description
                  }
                  disabled={saving}
                  placeholder="Contoh: Penjualan material sisa proyek"
                  onChange={
                    (event) =>
                      setForm(
                        (
                          current,
                        ) => ({
                          ...current,
                          description:
                            event
                              .target
                              .value,
                        }),
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
                  Referensi
                </span>

                <input
                  type="text"
                  maxLength={190}
                  value={
                    form.reference
                  }
                  disabled={saving}
                  placeholder="Opsional"
                  onChange={
                    (event) =>
                      setForm(
                        (
                          current,
                        ) => ({
                          ...current,
                          reference:
                            event
                              .target
                              .value,
                        }),
                      )
                  }
                />
              </label>
            </div>

            <footer
              className={
                styles.sheetFooter
              }
            >
              <Button
                type="button"
                variant="secondary"
                disabled={saving}
                onClick={
                  closeForm
                }
              >
                Batal
              </Button>

              <Button
                type="submit"
                loading={saving}
                loadingLabel="Mencatat..."
                disabled={
                  accountsLoading
                  || !hasAccounts
                }
              >
                Catat Pemasukan
              </Button>
            </footer>
          </form>
        </div>
      ) : null}
    </>
  );
}
