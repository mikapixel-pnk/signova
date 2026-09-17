"use client";

import {
  Banknote,
  Check,
  Landmark,
  Pencil,
  Plus,
  Power,
  PowerOff,
  Star,
  WalletCards,
  X,
} from "lucide-react";

import {
  useEffect,
  useState,
} from "react";

import {
  ActionFeedback,
} from "@/components/feedback/action-feedback";

import {
  ModuleHero,
} from "@/components/module/module-hero";

import {
  apiErrorMessage,
  apiRequestId,
} from "@/lib/api/error-message";

import {
  activateCashAccount,
  createCashAccount,
  deactivateCashAccount,
  listCashAccounts,
  setDefaultCashAccount,
  updateCashAccount,
} from "@/lib/finance/cash-account-service";

import type {
  CashAccount,
  CashAccountPayload,
  CashAccountType,
} from "@/types/cash-account";

import styles from "./cash-bank.module.css";

type FormState = {
  name: string;
  type: CashAccountType;

  bank_name: string;
  account_number: string;
  account_name: string;

  accepts_payments: boolean;
};

const emptyForm: FormState = {
  name: "",
  type: "BANK",
  bank_name: "",
  account_number: "",
  account_name: "",
  accepts_payments: false,
};

function money(
  value:
    | number
    | string,
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

function maskAccountNumber(
  value:
    | string
    | null,
): string | null {
  if (!value) {
    return null;
  }

  const cleaned =
    value.trim();

  if (
    cleaned.length <= 4
  ) {
    return cleaned;
  }

  return `${"•".repeat(
    Math.min(
      6,
      cleaned.length - 4,
    ),
  )}${cleaned.slice(-4)}`;
}

function accountTypeLabel(
  type: CashAccountType,
): string {
  return type === "BANK"
    ? "Bank"
    : "Kas";
}

export function CashBank() {
  const [
    accounts,
    setAccounts,
  ] = useState<
    CashAccount[]
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
    formOpen,
    setFormOpen,
  ] = useState(false);

  const [
    editing,
    setEditing,
  ] = useState<
    CashAccount | null
  >(null);

  const [
    form,
    setForm,
  ] = useState<FormState>(
    emptyForm,
  );

  const [
    saving,
    setSaving,
  ] = useState(false);

  const [
    actionId,
    setActionId,
  ] = useState<
    string | null
  >(null);

  async function loadAccounts() {
    setLoading(true);
    setError(null);
    setRequestId(null);

    try {
      const response =
        await listCashAccounts();

      setAccounts(
        response.data,
      );
    } catch (exception) {
      setError(
        apiErrorMessage(
          exception,
          "Daftar Kas & Bank belum dapat dimuat.",
        ),
      );

      setRequestId(
        apiRequestId(
          exception,
        ),
      );
    } finally {
      setLoading(false);
    }
  }

  useEffect(
    () => {
      let active = true;

      async function loadInitialAccounts() {
        try {
          const response =
            await listCashAccounts();

          if (!active) {
            return;
          }

          setAccounts(
            response.data,
          );
        } catch (exception) {
          if (!active) {
            return;
          }

          setError(
            apiErrorMessage(
              exception,
              "Daftar Kas & Bank belum dapat dimuat.",
            ),
          );

          setRequestId(
            apiRequestId(
              exception,
            ),
          );
        } finally {
          if (active) {
            setLoading(
              false,
            );
          }
        }
      }

      void loadInitialAccounts();

      return () => {
        active = false;
      };
    },
    [],
  );

  function openCreate() {
    setEditing(null);
    setForm(
      emptyForm,
    );

    setError(null);
    setRequestId(null);
    setSuccess(null);
    setFormOpen(true);
  }

  function openEdit(
    account: CashAccount,
  ) {
    setEditing(
      account,
    );

    setForm({
      name:
        account.name,

      type:
        account.type,

      bank_name:
        account.bank_name ??
        "",

      account_number:
        account.account_number ??
        "",

      account_name:
        account.account_name ??
        "",

      accepts_payments:
        account.type ===
          "BANK"
          ? account
              .accepts_payments
          : false,
    });

    setError(null);
    setRequestId(null);
    setSuccess(null);
    setFormOpen(true);
  }

  function closeForm() {
    if (saving) {
      return;
    }

    setFormOpen(false);
    setEditing(null);
    setForm(
      emptyForm,
    );
  }

  async function handleSave() {
    const name =
      form.name.trim();

    if (!name) {
      setError(
        "Nama akun wajib diisi.",
      );
      return;
    }

    if (
      form.type === "BANK"
      && !form.bank_name.trim()
    ) {
      setError(
        "Nama bank wajib diisi.",
      );
      return;
    }

    if (
      form.type === "BANK"
      && !form.account_name.trim()
    ) {
      setError(
        "Nama pemilik rekening wajib diisi.",
      );
      return;
    }

    const payload:
      CashAccountPayload = {
        name,
        type:
          form.type,

        bank_name:
          form.type === "BANK"
            ? form.bank_name.trim()
            : null,

        account_number:
          form.type === "BANK"
            ? (
                form
                  .account_number
                  .trim() ||
                null
              )
            : null,

        account_name:
          form.type === "BANK"
            ? form.account_name.trim()
            : null,

        accepts_payments:
          form.type === "BANK"
            ? form.accepts_payments
            : false,
      };

    setSaving(true);
    setError(null);
    setRequestId(null);
    setSuccess(null);

    try {
      const response =
        editing
          ? await updateCashAccount(
              editing.id,
              payload,
            )
          : await createCashAccount(
              payload,
            );

      setSuccess(
        response.message ??
        (
          editing
            ? "Akun Kas & Bank berhasil diperbarui."
            : "Akun Kas & Bank berhasil ditambahkan."
        ),
      );

      setFormOpen(false);
      setEditing(null);
      setForm(
        emptyForm,
      );

      await loadAccounts();
    } catch (exception) {
      setError(
        apiErrorMessage(
          exception,
          "Akun Kas & Bank belum dapat disimpan.",
        ),
      );

      setRequestId(
        apiRequestId(
          exception,
        ),
      );
    } finally {
      setSaving(false);
    }
  }

  async function runAction(
    account: CashAccount,
    action:
      | "activate"
      | "deactivate"
      | "default",
  ) {
    if (
      action === "deactivate"
      && account.is_default
    ) {
      setError(
        "Rekening default tidak dapat dinonaktifkan. Jadikan rekening lain sebagai default terlebih dahulu.",
      );

      return;
    }

    if (
      action === "deactivate"
      && !window.confirm(
        `Nonaktifkan "${account.name}"?`,
      )
    ) {
      return;
    }

    setActionId(
      account.id,
    );

    setError(null);
    setRequestId(null);
    setSuccess(null);

    try {
      const response =
        action === "activate"
          ? await activateCashAccount(
              account.id,
            )
          : action === "deactivate"
            ? await deactivateCashAccount(
                account.id,
              )
            : await setDefaultCashAccount(
                account.id,
              );

      setSuccess(
        response.message ??
        "Perubahan akun berhasil disimpan.",
      );

      await loadAccounts();
    } catch (exception) {
      setError(
        apiErrorMessage(
          exception,
          "Perubahan akun belum dapat diproses.",
        ),
      );

      setRequestId(
        apiRequestId(
          exception,
        ),
      );
    } finally {
      setActionId(
        null,
      );
    }
  }

  return (
    <div
      className={
        styles.page
      }
    >
      <ModuleHero
        icon={WalletCards}
        eyebrow="Keuangan"
        title="Kas & Bank"
        description="Kelola kas, rekening bank, akun default, dan rekening yang boleh digunakan pelanggan untuk membayar tagihan."
      />

      <div
        className={
          styles.toolbar
        }
      >
        <div>
          <strong>
            Akun Keuangan
          </strong>

          <span>
            {accounts.length} akun
          </span>
        </div>

        <button
          type="button"
          className={
            styles.primaryButton
          }
          onClick={
            openCreate
          }
        >
          <Plus
            size={17}
          />
          Tambah Akun
        </button>
      </div>

      {success ? (
        <ActionFeedback
          tone="success"
          title="Perubahan tersimpan"
          message={success}
        />
      ) : null}

      {error ? (
        <ActionFeedback
          tone="error"
          title="Kas & Bank belum dapat diproses"
          message={error}
          requestId={
            requestId ??
            undefined
          }
        />
      ) : null}

      {loading ? (
        <section
          className={
            styles.loading
          }
        >
          Memuat akun Kas & Bank…
        </section>
      ) : accounts.length ===
        0 ? (
        <section
          className={
            styles.empty
          }
        >
          <Landmark
            size={32}
          />

          <strong>
            Belum ada akun Kas & Bank
          </strong>

          <p>
            Tambahkan kas atau rekening bank pertama untuk mulai mencatat transaksi dan pembayaran.
          </p>

          <button
            type="button"
            className={
              styles.primaryButton
            }
            onClick={
              openCreate
            }
          >
            <Plus
              size={17}
            />
            Tambah Akun
          </button>
        </section>
      ) : (
        <div
          className={
            styles.accountGrid
          }
        >
          {accounts.map(
            (
              account,
            ) => {
              const busy =
                actionId ===
                account.id;

              const masked =
                maskAccountNumber(
                  account.account_number,
                );

              return (
                <article
                  key={
                    account.id
                  }
                  className={
                    styles.accountCard
                  }
                >
                  <header
                    className={
                      styles.accountHeader
                    }
                  >
                    <span
                      className={
                        styles.accountIcon
                      }
                    >
                      {account.type ===
                      "BANK" ? (
                        <Landmark
                          size={20}
                        />
                      ) : (
                        <Banknote
                          size={20}
                        />
                      )}
                    </span>

                    <div
                      className={
                        styles.accountTitle
                      }
                    >
                      <strong>
                        {account.name}
                      </strong>

                      <span>
                        {accountTypeLabel(
                          account.type,
                        )}

                        {account.bank_name
                          ? ` • ${account.bank_name}`
                          : ""}

                        {masked
                          ? ` • ${masked}`
                          : ""}
                      </span>
                    </div>

                    <span
                      className={
                        account.status ===
                        "ACTIVE"
                          ? styles.activeBadge
                          : styles.inactiveBadge
                      }
                    >
                      {account.status ===
                      "ACTIVE"
                        ? "Aktif"
                        : "Nonaktif"}
                    </span>
                  </header>

                  {account.account_name ? (
                    <p
                      className={
                        styles.accountOwner
                      }
                    >
                      Atas nama{" "}
                      <strong>
                        {account.account_name}
                      </strong>
                    </p>
                  ) : null}

                  <div
                    className={
                      styles.badges
                    }
                  >
                    {account.is_default ? (
                      <span>
                        <Star
                          size={13}
                        />
                        Default
                      </span>
                    ) : null}

                    {account
                      .accepts_payments ? (
                      <span>
                        <Check
                          size={13}
                        />
                        Pembayaran pelanggan
                      </span>
                    ) : null}
                  </div>

                  <div
                    className={
                      styles.balance
                    }
                  >
                    <span>
                      Saldo
                    </span>

                    <strong>
                      {money(
                        account.balance,
                      )}
                    </strong>
                  </div>

                  <div
                    className={
                      styles.actions
                    }
                  >
                    <button
                      type="button"
                      className={
                        styles.secondaryButton
                      }
                      disabled={
                        busy
                      }
                      onClick={
                        () =>
                          openEdit(
                            account,
                          )
                      }
                    >
                      <Pencil
                        size={15}
                      />
                      Ubah
                    </button>

                    {!account.is_default &&
                    account.status ===
                      "ACTIVE" ? (
                      <button
                        type="button"
                        className={
                          styles.secondaryButton
                        }
                        disabled={
                          busy
                        }
                        onClick={
                          () =>
                            void runAction(
                              account,
                              "default",
                            )
                        }
                      >
                        <Star
                          size={15}
                        />
                        Jadikan Default
                      </button>
                    ) : null}

                    {account.status ===
                    "ACTIVE" ? (
                      <button
                        type="button"
                        className={
                          styles.dangerButton
                        }
                        disabled={
                          busy ||
                          account.is_default
                        }
                        title={
                          account.is_default
                            ? "Pindahkan default ke akun lain sebelum menonaktifkan akun ini."
                            : undefined
                        }
                        onClick={
                          () =>
                            void runAction(
                              account,
                              "deactivate",
                            )
                        }
                      >
                        <PowerOff
                          size={15}
                        />
                        Nonaktifkan
                      </button>
                    ) : (
                      <button
                        type="button"
                        className={
                          styles.secondaryButton
                        }
                        disabled={
                          busy
                        }
                        onClick={
                          () =>
                            void runAction(
                              account,
                              "activate",
                            )
                        }
                      >
                        <Power
                          size={15}
                        />
                        Aktifkan
                      </button>
                    )}
                  </div>
                </article>
              );
            },
          )}
        </div>
      )}

      {formOpen ? (
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
            aria-labelledby="cash-account-form-title"
            onMouseDown={
              (
                event,
              ) =>
                event.stopPropagation()
            }
            onSubmit={
              (
                event,
              ) => {
                event.preventDefault();
                void handleSave();
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
                  Kas & Bank
                </span>

                <h2
                  id="cash-account-form-title"
                >
                  {editing
                    ? "Ubah Akun"
                    : "Tambah Akun"}
                </h2>

                <p>
                  Kelola akun operasional dan rekening pembayaran pelanggan.
                </p>
              </div>

              <button
                type="button"
                className={
                  styles.closeButton
                }
                aria-label="Tutup"
                disabled={
                  saving
                }
                onClick={
                  closeForm
                }
              >
                <X
                  size={19}
                />
              </button>
            </header>

            <div
              className={
                styles.sheetBody
              }
            >
              <label
                className={
                  styles.field
                }
              >
                <span>
                  Nama akun
                </span>

                <input
                  type="text"
                  maxLength={190}
                  placeholder="Contoh: BCA Operasional"
                  value={
                    form.name
                  }
                  disabled={
                    saving
                  }
                  onChange={
                    (
                      event,
                    ) =>
                      setForm(
                        (
                          current,
                        ) => ({
                          ...current,
                          name:
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
                  Jenis akun
                </span>

                <select
                  value={
                    form.type
                  }
                  disabled={
                    saving
                  }
                  onChange={
                    (
                      event,
                    ) => {
                      const type =
                        event
                          .target
                          .value as
                          CashAccountType;

                      setForm(
                        (
                          current,
                        ) => ({
                          ...current,
                          type,

                          bank_name:
                            type ===
                            "BANK"
                              ? current.bank_name
                              : "",

                          account_number:
                            type ===
                            "BANK"
                              ? current.account_number
                              : "",

                          account_name:
                            type ===
                            "BANK"
                              ? current.account_name
                              : "",

                          accepts_payments:
                            type ===
                            "BANK"
                              ? current.accepts_payments
                              : false,
                        }),
                      );
                    }
                  }
                >
                  <option
                    value="BANK"
                  >
                    Bank
                  </option>

                  <option
                    value="CASH"
                  >
                    Kas
                  </option>
                </select>
              </label>

              {form.type ===
              "BANK" ? (
                <>
                  <label
                    className={
                      styles.field
                    }
                  >
                    <span>
                      Nama bank
                    </span>

                    <input
                      type="text"
                      maxLength={100}
                      placeholder="Contoh: BCA"
                      value={
                        form.bank_name
                      }
                      disabled={
                        saving
                      }
                      onChange={
                        (
                          event,
                        ) =>
                          setForm(
                            (
                              current,
                            ) => ({
                              ...current,
                              bank_name:
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
                      Nomor rekening
                    </span>

                    <input
                      type="text"
                      inputMode="numeric"
                      maxLength={100}
                      placeholder="Nomor rekening"
                      value={
                        form.account_number
                      }
                      disabled={
                        saving
                      }
                      onChange={
                        (
                          event,
                        ) =>
                          setForm(
                            (
                              current,
                            ) => ({
                              ...current,
                              account_number:
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
                      Nama pemilik rekening
                    </span>

                    <input
                      type="text"
                      maxLength={190}
                      placeholder="Nama sesuai rekening bank"
                      value={
                        form.account_name
                      }
                      disabled={
                        saving
                      }
                      onChange={
                        (
                          event,
                        ) =>
                          setForm(
                            (
                              current,
                            ) => ({
                              ...current,
                              account_name:
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
                      styles.toggle
                    }
                  >
                    <div>
                      <strong>
                        Tampilkan untuk pembayaran pelanggan
                      </strong>

                      <span>
                        Rekening ini boleh ditampilkan pada tagihan dan halaman pembayaran pelanggan.
                      </span>
                    </div>

                    <input
                      type="checkbox"
                      checked={
                        form.accepts_payments
                      }
                      disabled={
                        saving
                      }
                      onChange={
                        (
                          event,
                        ) =>
                          setForm(
                            (
                              current,
                            ) => ({
                              ...current,
                              accepts_payments:
                                event
                                  .target
                                  .checked,
                            }),
                          )
                      }
                    />
                  </label>
                </>
              ) : (
                <div
                  className={
                    styles.cashHint
                  }
                >
                  Akun Kas digunakan untuk transaksi internal dan tidak ditampilkan sebagai rekening pembayaran pelanggan.
                </div>
              )}
            </div>

            <footer
              className={
                styles.sheetFooter
              }
            >
              <button
                type="button"
                className={
                  styles.secondaryButton
                }
                disabled={
                  saving
                }
                onClick={
                  closeForm
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
                  saving
                }
              >
                {saving
                  ? "Menyimpan..."
                  : "Simpan"}
              </button>
            </footer>
          </form>
        </div>
      ) : null}
    </div>
  );
}
