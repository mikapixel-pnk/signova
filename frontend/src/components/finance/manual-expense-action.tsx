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
  DEFAULT_EXPENSE_CATEGORY,
  EXPENSE_CATEGORIES,
} from "@/lib/finance/expense-categories";

import {
  createExpense,
  postExpense,
  submitExpense,
  uploadExpenseEvidence,
} from "@/lib/finance/expense-service";

import type {
  CashAccount,
} from "@/types/cash-account";

import styles from "./manual-expense-action.module.css";

type ManualExpenseActionProps = {
  onRecorded?: () => void;
};

type FormState = {
  cashAccountId: string;
  amount: string;
  date: string;
  category: string;
  description: string;
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
      DEFAULT_EXPENSE_CATEGORY,
    description: "",
  };
}

function accountLabel(
  account: CashAccount,
): string {
  if (account.bank_name) {
    return `${
      account.bank_name
    } · ${
      account.name
    }`;
  }

  return account.name;
}

export function ManualExpenseAction({
  onRecorded,
}: ManualExpenseActionProps) {
  const [
    canManage,
    setCanManage,
  ] = useState<
    boolean | null
  >(null);

  const [
    canApprove,
    setCanApprove,
  ] = useState(false);

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

  const [
    evidence,
    setEvidence,
  ] = useState<
    File | null
  >(null);

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

  async function openForm() {
    setForm(
      emptyForm(),
    );

    setError(null);
    setRequestId(null);
    setSuccess(null);
    setEvidence(null);
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

    if (!form.cashAccountId) {
      setError(
        "Pilih Kas atau Bank sumber pengeluaran.",
      );

      return;
    }

    if (
      !Number.isFinite(amount)
      || amount <= 0
    ) {
      setError(
        "Nominal pengeluaran harus lebih dari Rp0.",
      );

      return;
    }

    if (!form.date) {
      setError(
        "Tanggal pengeluaran wajib diisi.",
      );

      return;
    }

    if (!description) {
      setError(
        "Keterangan pengeluaran wajib diisi.",
      );

      return;
    }

    if (evidence) {
      const allowedTypes =
        new Set([
          "image/png",
          "image/jpeg",
          "image/webp",
          "application/pdf",
        ]);

      if (
        !allowedTypes.has(
          evidence.type,
        )
      ) {
        setError(
          "Bukti harus berupa PNG, JPG, WebP, atau PDF.",
        );

        return;
      }

      if (
        evidence.size >
        5 * 1024 * 1024
      ) {
        setError(
          "Ukuran bukti maksimal 5 MB.",
        );

        return;
      }
    }

    setSaving(true);
    setError(null);
    setRequestId(null);

    let draftCreated = false;

    try {
      const now =
        new Date();

      const created =
        await createExpense({
          cash_account_id:
            form.cashAccountId,

          amount:
            amount.toFixed(2),

          incurred_at:
            `${
              form.date
            } ${
              inputTime(now)
            }`,

          category:
            form.category.trim()
            || null,

          description,
        });

      draftCreated = true;

      if (evidence) {
        await uploadExpenseEvidence(
          created.data.id,
          evidence,
        );
      }

      const response =
        canApprove
          ? await postExpense(
              created.data.id,
            )
          : await submitExpense(
              created.data.id,
            );

      setSuccess(
        response.message
        ?? (
          canApprove
            ? "Pengeluaran berhasil dicatat."
            : "Pengeluaran berhasil diajukan."
        ),
      );

      setEvidence(null);
      setOpen(false);

      onRecorded?.();
    } catch (caught) {
      if (draftCreated) {
        onRecorded?.();
      }

      setError(
        apiErrorMessage(
          caught,
          draftCreated
            ? "Draf sudah tersimpan, tetapi proses berikutnya belum selesai. Periksa Draf Pengeluaran."
            : "Pengeluaran belum dapat disimpan.",
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

  if (canManage !== true) {
    return null;
  }

  const hasAccounts =
    accounts.length > 0;

  const actionLabel =
    canApprove
      ? "Catat Pengeluaran"
      : "Ajukan Pengeluaran";

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
          {actionLabel}
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
            aria-labelledby="manual-expense-title"
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
                  Pengeluaran
                </span>

                <h2
                  id="manual-expense-title"
                >
                  {actionLabel}
                </h2>

                <p>
                  {canApprove
                    ? "Catat uang yang benar-benar sudah dibayarkan dari Kas atau Bank."
                    : "Ajukan pengeluaran untuk diperiksa sebelum uang keluar dicatat."}
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
                  Dibayar dari Kas / Bank *
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
                        (current) => ({
                          ...current,
                          cashAccountId:
                            event.target.value,
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
                        key={account.id}
                        value={account.id}
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
                        (current) => ({
                          ...current,
                          amount:
                            event.target.value,
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
                        (current) => ({
                          ...current,
                          date:
                            event.target.value,
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
                        (current) => ({
                          ...current,
                          category:
                            event.target.value,
                        }),
                      )
                  }
                >
                  {EXPENSE_CATEGORIES.map(
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
                  maxLength={1000}
                  value={
                    form.description
                  }
                  disabled={saving}
                  placeholder="Contoh: Biaya bensin operasional"
                  onChange={
                    (event) =>
                      setForm(
                        (current) => ({
                          ...current,
                          description:
                            event.target.value,
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
                  Bukti Pengeluaran (opsional)
                </span>

                <input
                  type="file"
                  accept="image/png,image/jpeg,image/webp,application/pdf"
                  disabled={saving}
                  onChange={
                    (event) => {
                      const file =
                        event.target
                          .files?.[0]
                        ?? null;

                      setEvidence(
                        file,
                      );
                    }
                  }
                />

                <small
                  className={
                    styles.fieldHelp
                  }
                >
                  PNG, JPG, WebP, atau PDF.
                  Maksimal 5 MB.
                </small>
              </label>

              <div
                className={
                  styles.accountEmpty
                }
              >
                Tagihan pemasok yang belum
                dibayar tidak dicatat di sini.
                Pembelian kredit akan dikelola
                melalui alur Pembelian dan
                Utang Usaha.
              </div>
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
                {actionLabel}
              </Button>
            </footer>
          </form>
        </div>
      ) : null}
    </>
  );
}
