"use client";

import {
  Ban,
  Check,
  Eye,
  FileUp,
  Pencil,
  RotateCcw,
  Send,
  Trash2,
  X,
  XCircle,
} from "lucide-react";

import {
  useState,
} from "react";

import {
  apiErrorMessage,
  apiRequestId,
} from "@/lib/api/error-message";

import {
  listCashAccounts,
} from "@/lib/finance/cash-account-service";

import {
  DEFAULT_EXPENSE_CATEGORY,
  EXPENSE_CATEGORIES,
} from "@/lib/finance/expense-categories";

import {
  approveExpense,
  getExpenseEvidence,
  postExpense,
  rejectExpense,
  removeExpenseEvidence,
  reviseExpense,
  submitExpense,
  updateExpense,
  uploadExpenseEvidence,
  voidExpense,
} from "@/lib/finance/expense-service";

import type {
  CashAccount,
} from "@/types/cash-account";

import type {
  Expense,
} from "@/types/expense";

import styles from "./expense-workflow-actions.module.css";

type ExpenseWorkflowActionsProps = {
  expense: Expense;
  canManage: boolean;
  canApprove: boolean;
  onChanged: () => void;
};

type EditState = {
  cashAccountId: string;
  amount: string;
  date: string;
  category: string;
  description: string;
};

type ReasonMode =
  | "REJECT"
  | "VOID"
  | null;

function inputDate(
  value: string | null,
): string {
  const date =
    value
      ? new Date(value)
      : new Date();

  if (
    Number.isNaN(
      date.getTime(),
    )
  ) {
    return "";
  }

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
  value: string | null,
): string {
  const date =
    value
      ? new Date(value)
      : new Date();

  if (
    Number.isNaN(
      date.getTime(),
    )
  ) {
    return "00:00:00";
  }

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

  const seconds =
    String(
      date.getSeconds(),
    ).padStart(
      2,
      "0",
    );

  return `${hours}:${minutes}:${seconds}`;
}

function editState(
  expense: Expense,
): EditState {
  return {
    cashAccountId:
      expense.cash_account?.id
      ?? "",

    amount:
      expense.amount,

    date:
      inputDate(
        expense.incurred_at,
      ),

    category:
      expense.category
      ?? DEFAULT_EXPENSE_CATEGORY,

    description:
      expense.description,
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

function validEvidence(
  file: File,
): string | null {
  const allowed =
    new Set([
      "image/png",
      "image/jpeg",
      "image/webp",
      "application/pdf",
    ]);

  if (!allowed.has(file.type)) {
    return "Bukti harus berupa PNG, JPG, WebP, atau PDF.";
  }

  if (
    file.size >
    5 * 1024 * 1024
  ) {
    return "Ukuran bukti maksimal 5 MB.";
  }

  return null;
}

export function ExpenseWorkflowActions({
  expense,
  canManage,
  canApprove,
  onChanged,
}: ExpenseWorkflowActionsProps) {
  const [
    busy,
    setBusy,
  ] = useState(false);

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
    message,
    setMessage,
  ] = useState<
    string | null
  >(null);

  const [
    editing,
    setEditing,
  ] = useState<
    Expense | null
  >(null);

  const [
    form,
    setForm,
  ] = useState<EditState>(
    () =>
      editState(expense),
  );

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
    evidenceFile,
    setEvidenceFile,
  ] = useState<
    File | null
  >(null);

  const [
    reasonMode,
    setReasonMode,
  ] = useState<ReasonMode>(
    null,
  );

  const [
    reason,
    setReason,
  ] = useState("");

  function clearFeedback() {
    setError(null);
    setRequestId(null);
    setMessage(null);
  }

  async function openEvidence() {
    const previewWindow =
      window.open(
        "",
        "_blank",
      );

    if (previewWindow) {
      try {
        previewWindow.opener =
          null;

        previewWindow.document.title =
          "Memuat bukti pengeluaran...";
      } catch {
        // Browser dapat membatasi tab baru.
      }
    }

    setBusy(true);
    clearFeedback();

    try {
      const blob =
        await getExpenseEvidence(
          expense.id,
        );

      const url =
        URL.createObjectURL(
          blob,
        );

      if (previewWindow) {
        previewWindow.location.href =
          url;
      } else {
        window.open(
          url,
          "_blank",
          "noopener,noreferrer",
        );
      }

      window.setTimeout(
        () => {
          URL.revokeObjectURL(
            url,
          );
        },
        60_000,
      );
    } catch (caught) {
      previewWindow?.close();

      setError(
        apiErrorMessage(
          caught,
          "Bukti pengeluaran belum dapat dibuka.",
        ),
      );

      setRequestId(
        apiRequestId(
          caught,
        ),
      );
    } finally {
      setBusy(false);
    }
  }

  async function loadAccounts() {
    setAccountsLoading(true);

    try {
      const response =
        await listCashAccounts();

      setAccounts(
        response.data.filter(
          (account) =>
            account.status ===
            "ACTIVE",
        ),
      );
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

  async function openEdit(
    target: Expense,
  ) {
    clearFeedback();

    setEditing(target);

    setForm(
      editState(target),
    );

    setEvidenceFile(null);

    await loadAccounts();
  }

  function closeEdit() {
    if (busy) {
      return;
    }

    setEditing(null);
    setEvidenceFile(null);
  }

  async function runAction(
    action:
      () => Promise<unknown>,
    successMessage: string,
  ) {
    setBusy(true);
    clearFeedback();

    try {
      await action();

      setMessage(
        successMessage,
      );

      onChanged();
    } catch (caught) {
      setError(
        apiErrorMessage(
          caught,
          "Pengeluaran belum dapat diproses.",
        ),
      );

      setRequestId(
        apiRequestId(
          caught,
        ),
      );
    } finally {
      setBusy(false);
    }
  }

  async function handleRevise() {
    setBusy(true);
    clearFeedback();

    try {
      const response =
        await reviseExpense(
          expense.id,
        );

      onChanged();

      await openEdit(
        response.data,
      );
    } catch (caught) {
      setError(
        apiErrorMessage(
          caught,
          "Pengeluaran belum dapat dikembalikan ke Draf.",
        ),
      );

      setRequestId(
        apiRequestId(
          caught,
        ),
      );
    } finally {
      setBusy(false);
    }
  }

  async function saveEdit() {
    if (!editing) {
      return;
    }

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

    if (evidenceFile) {
      const evidenceError =
        validEvidence(
          evidenceFile,
        );

      if (evidenceError) {
        setError(
          evidenceError,
        );

        return;
      }
    }

    setBusy(true);
    clearFeedback();

    try {
      await updateExpense(
        editing.id,
        {
          cash_account_id:
            form.cashAccountId,

          amount:
            amount.toFixed(2),

          incurred_at:
            `${
              form.date
            } ${
              inputTime(
                editing.incurred_at,
              )
            }`,

          category:
            form.category.trim()
            || null,

          description,
        },
      );

      if (evidenceFile) {
        await uploadExpenseEvidence(
          editing.id,
          evidenceFile,
        );
      }

      setMessage(
        "Draf pengeluaran berhasil diperbarui.",
      );

      setEditing(null);
      setEvidenceFile(null);

      onChanged();
    } catch (caught) {
      setError(
        apiErrorMessage(
          caught,
          "Draf pengeluaran belum dapat disimpan.",
        ),
      );

      setRequestId(
        apiRequestId(
          caught,
        ),
      );
    } finally {
      setBusy(false);
    }
  }

  async function removeEvidence() {
    if (!editing) {
      return;
    }

    setBusy(true);
    clearFeedback();

    try {
      const response =
        await removeExpenseEvidence(
          editing.id,
        );

      setEditing(
        response.data,
      );

      setMessage(
        "Bukti pengeluaran berhasil dihapus.",
      );

      onChanged();
    } catch (caught) {
      setError(
        apiErrorMessage(
          caught,
          "Bukti pengeluaran belum dapat dihapus.",
        ),
      );

      setRequestId(
        apiRequestId(
          caught,
        ),
      );
    } finally {
      setBusy(false);
    }
  }

  async function submitReason() {
    const normalized =
      reason.trim();

    if (
      normalized.length < 3
    ) {
      setError(
        "Alasan minimal 3 karakter.",
      );

      return;
    }

    setBusy(true);
    clearFeedback();

    try {
      if (
        reasonMode === "REJECT"
      ) {
        await rejectExpense(
          expense.id,
          normalized,
        );

        setMessage(
          "Pengeluaran berhasil ditolak.",
        );
      }

      if (
        reasonMode === "VOID"
      ) {
        await voidExpense(
          expense.id,
          normalized,
        );

        setMessage(
          "Pengeluaran berhasil dibatalkan.",
        );
      }

      setReasonMode(null);
      setReason("");

      onChanged();
    } catch (caught) {
      setError(
        apiErrorMessage(
          caught,
          "Pengeluaran belum dapat diproses.",
        ),
      );

      setRequestId(
        apiRequestId(
          caught,
        ),
      );
    } finally {
      setBusy(false);
    }
  }

  const hasActions =
    expense.has_evidence
    || (
      expense.status === "DRAFT"
      && (
        canManage
        || canApprove
      )
    )
    || (
      expense.status ===
        "PENDING_APPROVAL"
      && canApprove
    )
    || (
      expense.status ===
        "REJECTED"
      && canManage
    )
    || (
      expense.status ===
        "POSTED"
      && canApprove
    );

  if (!hasActions) {
    return null;
  }

  return (
    <>
      {error ? (
        <div
          className={
            styles.feedbackError
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

      {message ? (
        <div
          className={
            styles.feedbackSuccess
          }
          role="status"
        >
          {message}
        </div>
      ) : null}

      <div
        className={
          styles.actionBar
        }
      >
        {expense.has_evidence ? (
          <button
            type="button"
            className={
              styles.secondaryButton
            }
            disabled={busy}
            onClick={() =>
              void openEvidence()
            }
          >
            <Eye
              size={15}
              aria-hidden="true"
            />
            Lihat Bukti
          </button>
        ) : null}

        {expense.status === "DRAFT"
        && canManage ? (
          <button
            type="button"
            className={
              styles.secondaryButton
            }
            disabled={busy}
            onClick={() =>
              void openEdit(
                expense,
              )
            }
          >
            <Pencil
              size={15}
              aria-hidden="true"
            />
            Edit Draf
          </button>
        ) : null}

        {expense.status === "DRAFT"
        && canApprove ? (
          <button
            type="button"
            className={
              styles.primaryButton
            }
            disabled={busy}
            onClick={() =>
              void runAction(
                () =>
                  postExpense(
                    expense.id,
                  ),
                "Pengeluaran berhasil dicatat.",
              )
            }
          >
            <Check
              size={15}
              aria-hidden="true"
            />
            Catat
          </button>
        ) : null}

        {expense.status === "DRAFT"
        && canManage
        && !canApprove ? (
          <button
            type="button"
            className={
              styles.primaryButton
            }
            disabled={busy}
            onClick={() =>
              void runAction(
                () =>
                  submitExpense(
                    expense.id,
                  ),
                "Pengeluaran berhasil diajukan.",
              )
            }
          >
            <Send
              size={15}
              aria-hidden="true"
            />
            Ajukan
          </button>
        ) : null}

        {expense.status ===
          "PENDING_APPROVAL"
        && canApprove ? (
          <>
            <button
              type="button"
              className={
                styles.primaryButton
              }
              disabled={busy}
              onClick={() =>
                void runAction(
                  () =>
                    approveExpense(
                      expense.id,
                    ),
                  "Pengeluaran berhasil disetujui.",
                )
              }
            >
              <Check
                size={15}
                aria-hidden="true"
              />
              Setujui
            </button>

            <button
              type="button"
              className={
                styles.dangerButton
              }
              disabled={busy}
              onClick={() => {
                clearFeedback();
                setReason("");
                setReasonMode(
                  "REJECT",
                );
              }}
            >
              <XCircle
                size={15}
                aria-hidden="true"
              />
              Tolak
            </button>
          </>
        ) : null}

        {expense.status ===
          "REJECTED"
        && canManage ? (
          <button
            type="button"
            className={
              styles.primaryButton
            }
            disabled={busy}
            onClick={() =>
              void handleRevise()
            }
          >
            <RotateCcw
              size={15}
              aria-hidden="true"
            />
            Perbaiki
          </button>
        ) : null}

        {expense.status === "POSTED"
        && canApprove ? (
          <button
            type="button"
            className={
              styles.dangerButton
            }
            disabled={busy}
            onClick={() => {
              clearFeedback();
              setReason("");
              setReasonMode(
                "VOID",
              );
            }}
          >
            <Ban
              size={15}
              aria-hidden="true"
            />
            Batalkan
          </button>
        ) : null}
      </div>

      {editing ? (
        <div
          className={
            styles.overlay
          }
          onMouseDown={
            closeEdit
          }
        >
          <form
            className={
              styles.modal
            }
            role="dialog"
            aria-modal="true"
            aria-labelledby="expense-edit-title"
            onMouseDown={
              (event) =>
                event.stopPropagation()
            }
            onSubmit={
              (event) => {
                event.preventDefault();

                void saveEdit();
              }
            }
          >
            <header
              className={
                styles.modalHeader
              }
            >
              <div>
                <span>
                  Pengeluaran
                </span>

                <h3
                  id="expense-edit-title"
                >
                  Edit Draf
                </h3>
              </div>

              <button
                type="button"
                className={
                  styles.closeButton
                }
                aria-label="Tutup"
                disabled={busy}
                onClick={
                  closeEdit
                }
              >
                <X
                  size={18}
                  aria-hidden="true"
                />
              </button>
            </header>

            <div
              className={
                styles.modalBody
              }
            >
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
                    busy
                    || accountsLoading
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
                  && accounts.length === 0 ? (
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
                  min="0.01"
                  step="0.01"
                  inputMode="decimal"
                  value={
                    form.amount
                  }
                  disabled={busy}
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
                  disabled={busy}
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
                  disabled={busy}
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
                  maxLength={5000}
                  value={
                    form.description
                  }
                  disabled={busy}
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
                  Bukti Pengeluaran
                </span>

                <input
                  type="file"
                  accept="image/png,image/jpeg,image/webp,application/pdf"
                  disabled={busy}
                  onChange={
                    (event) => {
                      const file =
                        event.target
                          .files?.[0]
                        ?? null;

                      if (!file) {
                        setEvidenceFile(
                          null,
                        );

                        return;
                      }

                      const evidenceError =
                        validEvidence(
                          file,
                        );

                      if (evidenceError) {
                        setError(
                          evidenceError,
                        );

                        event.target.value =
                          "";

                        setEvidenceFile(
                          null,
                        );

                        return;
                      }

                      setError(null);

                      setEvidenceFile(
                        file,
                      );
                    }
                  }
                />

                <small>
                  PNG, JPG, WebP, atau PDF.
                  Maksimal 5 MB.
                </small>
              </label>

              {editing.has_evidence ? (
                <button
                  type="button"
                  className={
                    styles.deleteEvidenceButton
                  }
                  disabled={busy}
                  onClick={() =>
                    void removeEvidence()
                  }
                >
                  <Trash2
                    size={15}
                    aria-hidden="true"
                  />
                  Hapus Bukti Saat Ini
                </button>
              ) : null}
            </div>

            <footer
              className={
                styles.modalFooter
              }
            >
              <button
                type="button"
                className={
                  styles.secondaryButton
                }
                disabled={busy}
                onClick={
                  closeEdit
                }
              >
                Batal
              </button>

              <button
                type="submit"
                className={
                  styles.primaryButton
                }
                disabled={
                  busy
                  || accountsLoading
                }
              >
                {busy
                  ? "Menyimpan..."
                  : (
                    <>
                      <FileUp
                        size={15}
                        aria-hidden="true"
                      />
                      Simpan Draf
                    </>
                  )}
              </button>
            </footer>
          </form>
        </div>
      ) : null}

      {reasonMode ? (
        <div
          className={
            styles.overlay
          }
          onMouseDown={() => {
            if (!busy) {
              setReasonMode(null);
            }
          }}
        >
          <form
            className={
              styles.reasonModal
            }
            role="dialog"
            aria-modal="true"
            aria-labelledby="expense-reason-title"
            onMouseDown={
              (event) =>
                event.stopPropagation()
            }
            onSubmit={
              (event) => {
                event.preventDefault();

                void submitReason();
              }
            }
          >
            <header
              className={
                styles.modalHeader
              }
            >
              <div>
                <span>
                  Pengeluaran
                </span>

                <h3
                  id="expense-reason-title"
                >
                  {reasonMode === "REJECT"
                    ? "Tolak Pengeluaran"
                    : "Batalkan Pengeluaran"}
                </h3>
              </div>

              <button
                type="button"
                className={
                  styles.closeButton
                }
                disabled={busy}
                aria-label="Tutup"
                onClick={() =>
                  setReasonMode(null)
                }
              >
                <X
                  size={18}
                  aria-hidden="true"
                />
              </button>
            </header>

            <div
              className={
                styles.modalBody
              }
            >
              <label
                className={
                  styles.field
                }
              >
                <span>
                  Alasan *
                </span>

                <textarea
                  rows={4}
                  maxLength={1000}
                  value={reason}
                  disabled={busy}
                  placeholder={
                    reasonMode === "REJECT"
                      ? "Jelaskan alasan penolakan."
                      : "Jelaskan alasan pembatalan."
                  }
                  onChange={
                    (event) =>
                      setReason(
                        event.target.value,
                      )
                  }
                />
              </label>
            </div>

            <footer
              className={
                styles.modalFooter
              }
            >
              <button
                type="button"
                className={
                  styles.secondaryButton
                }
                disabled={busy}
                onClick={() =>
                  setReasonMode(null)
                }
              >
                Kembali
              </button>

              <button
                type="submit"
                className={
                  styles.dangerButton
                }
                disabled={busy}
              >
                {busy
                  ? "Memproses..."
                  : (
                    reasonMode === "REJECT"
                      ? "Tolak Pengeluaran"
                      : "Batalkan Pengeluaran"
                  )}
              </button>
            </footer>
          </form>
        </div>
      ) : null}
    </>
  );
}
