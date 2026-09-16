"use client";

import Image from "next/image";

import {
  Banknote,
  CreditCard,
  ImageIcon,
  Landmark,
  Save,
  Trash2,
  Upload,
} from "lucide-react";

import {
  useEffect,
  useState,
} from "react";

import {
  ActionFeedback,
} from "@/components/feedback/action-feedback";

import {
  SubmitArea,
} from "@/components/feedback/submit-area";

import {
  ModuleHero,
} from "@/components/module/module-hero";

import {
  apiErrorMessage,
  apiRequestId,
} from "@/lib/api/error-message";

import {
  deletePaymentQr,
  getPaymentQr,
  getPaymentSettings,
  updatePaymentSettings,
  uploadPaymentQr,
} from "@/lib/payment-settings/service";

import type {
  PaymentSettings as PaymentSettingsData,
} from "@/types/payment-settings";

import styles from "./payment-settings.module.css";

const emptyForm = {
  bank_transfer_enabled: false,
  bank_name: "",
  bank_account_number: "",
  bank_account_name: "",
  static_qr_enabled: false,
  partial_payment_enabled: true,
};

export function PaymentSettings() {
  const [
    settings,
    setSettings,
  ] =
    useState<
      PaymentSettingsData | null
    >(null);

  const [
    form,
    setForm,
  ] =
    useState(
      emptyForm,
    );

  const [
    loading,
    setLoading,
  ] =
    useState(true);

  const [
    submitting,
    setSubmitting,
  ] =
    useState(false);

  const [
    qrBusy,
    setQrBusy,
  ] =
    useState(false);

  const [
    qrFile,
    setQrFile,
  ] =
    useState<File | null>(
      null,
    );

  const [
    qrUrl,
    setQrUrl,
  ] =
    useState<string | null>(
      null,
    );

  const [
    error,
    setError,
  ] =
    useState<string | null>(
      null,
    );

  const [
    requestId,
    setRequestId,
  ] =
    useState<string | null>(
      null,
    );

  const [
    success,
    setSuccess,
  ] =
    useState<string | null>(
      null,
    );

  useEffect(
    () => {
      let active = true;

      async function load() {
        try {
          const response =
            await getPaymentSettings();

          if (!active) {
            return;
          }

          setSettings(
            response.data,
          );

          setForm({
            bank_transfer_enabled:
              response.data
                .bank_transfer_enabled,

            bank_name:
              response.data
                .bank_name ?? "",

            bank_account_number:
              response.data
                .bank_account_number ??
              "",

            bank_account_name:
              response.data
                .bank_account_name ??
              "",

            static_qr_enabled:
              response.data
                .static_qr_enabled,

            partial_payment_enabled:
              response.data
                .partial_payment_enabled,
          });
        } catch (exception) {
          if (!active) {
            return;
          }

          setError(
            apiErrorMessage(
              exception,
              "Pengaturan keuangan belum dapat dibuka.",
            ),
          );

          setRequestId(
            apiRequestId(
              exception,
            ),
          );
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
    [],
  );

  useEffect(
    () => {
      let active = true;
      let objectUrl:
        string | null = null;

      async function loadQr() {
        if (
          !settings
            ?.has_static_qr
        ) {
          setQrUrl(null);
          return;
        }

        try {
          const blob =
            await getPaymentQr();

          if (!active) {
            return;
          }

          objectUrl =
            URL.createObjectURL(
              blob,
            );

          setQrUrl(
            objectUrl,
          );
        } catch {
          if (active) {
            setQrUrl(null);
          }
        }
      }

      void loadQr();

      return () => {
        active = false;

        if (objectUrl) {
          URL.revokeObjectURL(
            objectUrl,
          );
        }
      };
    },
    [
      settings
        ?.has_static_qr,
      settings
        ?.static_qr?.id,
    ],
  );

  async function handleSave() {
    setSubmitting(true);
    setError(null);
    setSuccess(null);
    setRequestId(null);

    try {
      const response =
        await updatePaymentSettings({
          bank_transfer_enabled:
            form.bank_transfer_enabled,

          bank_name:
            form.bank_name,

          bank_account_number:
            form.bank_account_number,

          bank_account_name:
            form.bank_account_name,

          static_qr_enabled:
            form.static_qr_enabled,

          partial_payment_enabled:
            form.partial_payment_enabled,
        });

      setSettings(
        response.data,
      );

      setSuccess(
        "Pengaturan keuangan berhasil disimpan.",
      );
    } catch (exception) {
      setError(
        apiErrorMessage(
          exception,
          "Pengaturan keuangan belum dapat disimpan.",
        ),
      );

      setRequestId(
        apiRequestId(
          exception,
        ),
      );
    } finally {
      setSubmitting(false);
    }
  }

  async function handleUploadQr() {
    if (!qrFile) {
      setError(
        "Pilih gambar QR pembayaran terlebih dahulu.",
      );
      return;
    }

    setQrBusy(true);
    setError(null);
    setSuccess(null);
    setRequestId(null);

    try {
      const response =
        await uploadPaymentQr(
          qrFile,
        );

      setSettings(
        response.data,
      );

      setForm(
        (current) => ({
          ...current,
          static_qr_enabled:
            response.data
              .static_qr_enabled,
        }),
      );

      setQrFile(null);

      setSuccess(
        "QR pembayaran berhasil disimpan.",
      );
    } catch (exception) {
      setError(
        apiErrorMessage(
          exception,
          "QR pembayaran belum dapat disimpan.",
        ),
      );

      setRequestId(
        apiRequestId(
          exception,
        ),
      );
    } finally {
      setQrBusy(false);
    }
  }

  async function handleDeleteQr() {
    setQrBusy(true);
    setError(null);
    setSuccess(null);
    setRequestId(null);

    try {
      const response =
        await deletePaymentQr();

      setSettings(
        response.data,
      );

      setForm(
        (current) => ({
          ...current,
          static_qr_enabled:
            false,
        }),
      );

      setQrFile(null);

      setSuccess(
        "QR pembayaran berhasil dihapus.",
      );
    } catch (exception) {
      setError(
        apiErrorMessage(
          exception,
          "QR pembayaran belum dapat dihapus.",
        ),
      );

      setRequestId(
        apiRequestId(
          exception,
        ),
      );
    } finally {
      setQrBusy(false);
    }
  }

  if (loading) {
    return (
      <section
        className={
          styles.loading
        }
      >
        Memuat pengaturan keuangan…
      </section>
    );
  }

  return (
    <div
      className={
        styles.page
      }
    >
      <ModuleHero
        icon={Banknote}
        eyebrow="Pengaturan"
        title="Pengaturan Keuangan"
        description="Atur rekening penerimaan, QR pembayaran, dan kebijakan pembayaran untuk usaha aktif."
      />

      {success ? (
        <ActionFeedback
          tone="success"
          title="Pengaturan keuangan tersimpan"
          message={success}
        />
      ) : null}

      <section
        className={
          styles.card
        }
      >
        <header
          className={
            styles.cardHeader
          }
        >
          <span
            className={
              styles.cardIcon
            }
          >
            <Landmark
              size={20}
            />
          </span>

          <div>
            <h2>
              Rekening Penerimaan
            </h2>

            <p>
              Rekening yang digunakan untuk menerima pembayaran pelanggan pada usaha aktif.
            </p>
          </div>
        </header>

        <label
          className={
            styles.toggleRow
          }
        >
          <div>
            <strong>
              Aktifkan transfer bank
            </strong>
            <span>
              Tampilkan rekening sebagai metode pembayaran.
            </span>
          </div>

          <input
            type="checkbox"
            checked={
              form
                .bank_transfer_enabled
            }
            onChange={
              (event) =>
                setForm(
                  (current) => ({
                    ...current,
                    bank_transfer_enabled:
                      event.target
                        .checked,
                  }),
                )
            }
          />
        </label>

        <div
          className={
            styles.grid
          }
        >
          <label>
            <span>
              Nama bank
            </span>

            <input
              value={
                form.bank_name
              }
              maxLength={100}
              placeholder="Contoh: BCA"
              onChange={
                (event) =>
                  setForm(
                    (current) => ({
                      ...current,
                      bank_name:
                        event.target
                          .value,
                    }),
                  )
              }
            />
          </label>

          <label>
            <span>
              Nomor rekening
            </span>

            <input
              value={
                form
                  .bank_account_number
              }
              maxLength={100}
              inputMode="numeric"
              placeholder="Nomor rekening"
              onChange={
                (event) =>
                  setForm(
                    (current) => ({
                      ...current,
                      bank_account_number:
                        event.target
                          .value,
                    }),
                  )
              }
            />
          </label>

          <label
            className={
              styles.full
            }
          >
            <span>
              Nama pemilik rekening
            </span>

            <input
              value={
                form
                  .bank_account_name
              }
              maxLength={190}
              placeholder="Nama sesuai rekening bank"
              onChange={
                (event) =>
                  setForm(
                    (current) => ({
                      ...current,
                      bank_account_name:
                        event.target
                          .value,
                    }),
                  )
              }
            />
          </label>
        </div>
      </section>

      <section
        className={
          styles.card
        }
      >
        <header
          className={
            styles.cardHeader
          }
        >
          <span
            className={
              styles.cardIcon
            }
          >
            <ImageIcon
              size={20}
            />
          </span>

          <div>
            <h2>
              QR Pembayaran
            </h2>

            <p>
              Simpan QR pembayaran statis agar mudah digunakan saat menagih pelanggan.
            </p>
          </div>
        </header>

        <label
          className={
            styles.toggleRow
          }
        >
          <div>
            <strong>
              Aktifkan QR pembayaran
            </strong>
            <span>
              Gunakan QR yang tersimpan sebagai opsi pembayaran.
            </span>
          </div>

          <input
            type="checkbox"
            checked={
              form
                .static_qr_enabled
            }
            disabled={
              !settings
                ?.has_static_qr
            }
            onChange={
              (event) =>
                setForm(
                  (current) => ({
                    ...current,
                    static_qr_enabled:
                      event.target
                        .checked,
                  }),
                )
            }
          />
        </label>

        <div
          className={
            styles.qrGrid
          }
        >
          <div
            className={
              styles.qrPreview
            }
          >
            {qrUrl ? (
              <Image
                src={qrUrl}
                alt="QR pembayaran"
                width={240}
                height={240}
                unoptimized
              />
            ) : (
              <div
                className={
                  styles.qrEmpty
                }
              >
                <ImageIcon
                  size={34}
                />
                <span>
                  Belum ada QR pembayaran
                </span>
              </div>
            )}
          </div>

          <div
            className={
              styles.qrActions
            }
          >
            <label
              className={
                styles.fileField
              }
            >
              <span>
                Pilih gambar QR
              </span>

              <input
                type="file"
                accept="image/png,image/jpeg,image/webp"
                onChange={
                  (event) =>
                    setQrFile(
                      event.target
                        .files?.[0] ??
                      null,
                    )
                }
              />

              <small>
                PNG, JPG, atau WEBP.
              </small>
            </label>

            {qrFile ? (
              <small
                className={
                  styles.selectedFile
                }
              >
                Dipilih: {
                  qrFile.name
                }
              </small>
            ) : null}

            <div
              className={
                styles.buttonRow
              }
            >
              <button
                type="button"
                className={
                  styles.secondaryButton
                }
                disabled={
                  qrBusy ||
                  !qrFile
                }
                onClick={
                  () => {
                    void handleUploadQr();
                  }
                }
              >
                <Upload
                  size={17}
                />
                {
                  settings
                    ?.has_static_qr
                    ? "Ganti QR"
                    : "Upload QR"
                }
              </button>

              {settings
                ?.has_static_qr ? (
                <button
                  type="button"
                  className={
                    styles.dangerButton
                  }
                  disabled={
                    qrBusy
                  }
                  onClick={
                    () => {
                      void handleDeleteQr();
                    }
                  }
                >
                  <Trash2
                    size={17}
                  />
                  Hapus
                </button>
              ) : null}
            </div>

            {settings
              ?.static_qr ? (
              <p
                className={
                  styles.fileMeta
                }
              >
                {
                  settings
                    .static_qr
                    .original_name
                }
              </p>
            ) : null}
          </div>
        </div>
      </section>

      <section
        className={
          styles.card
        }
      >
        <header
          className={
            styles.cardHeader
          }
        >
          <span
            className={
              styles.cardIcon
            }
          >
            <CreditCard
              size={20}
            />
          </span>

          <div>
            <h2>
              Opsi Pembayaran
            </h2>

            <p>
              Atur kebijakan dasar pembayaran pelanggan.
            </p>
          </div>
        </header>

        <label
          className={
            styles.toggleRow
          }
        >
          <div>
            <strong>
              Izinkan pembayaran sebagian
            </strong>
            <span>
              Pembayaran dapat dicatat sebagian sebelum tagihan lunas.
            </span>
          </div>

          <input
            type="checkbox"
            checked={
              form
                .partial_payment_enabled
            }
            onChange={
              (event) =>
                setForm(
                  (current) => ({
                    ...current,
                    partial_payment_enabled:
                      event.target
                        .checked,
                  }),
                )
            }
          />
        </label>

        <div
          className={
            styles.gateway
          }
        >
          <div>
            <strong>
              Gateway pembayaran
            </strong>

            <p>
              Integrasi Midtrans akan dikelola melalui konfigurasi integrasi terpisah.
            </p>
          </div>

          <span>
            Belum tersedia
          </span>
        </div>
      </section>

      <SubmitArea
        stickyMobile
        feedback={
          error
            ? {
                tone: "error",
                title:
                  "Pengaturan keuangan belum tersimpan",
                message:
                  error,
                requestId:
                  requestId ??
                  undefined,
              }
            : undefined
        }
      >
        <button
          type="button"
          className={
            styles.submitButton
          }
          disabled={
            submitting
          }
          onClick={
            () => {
              void handleSave();
            }
          }
        >
          <Save
            size={17}
          />

          {submitting
            ? "Menyimpan..."
            : "Simpan Pengaturan Keuangan"}
        </button>
      </SubmitArea>
    </div>
  );
}
