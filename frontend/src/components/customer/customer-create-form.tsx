"use client";

import {
  ArrowLeft,
  Building2,
  Save,
  UserRound,
} from "lucide-react";

import Link from "next/link";

import {
  FormEvent,
  useState,
} from "react";

import {
  useRouter,
} from "next/navigation";

import {
  ModuleHero,
} from "@/components/module/module-hero";

import {
  SubmitArea,
} from "@/components/feedback/submit-area";

import {
  getModule,
} from "@/lib/module/registry";

import {
  createCustomer,
} from "@/lib/customer/service";

import {
  apiErrorMessage,
  apiRequestId,
} from "@/lib/api/error-message";

import type {
  CustomerPayload,
  CustomerType,
} from "@/types/customer";

import styles from "./customer-create-form.module.css";

type FormState = {
  type: CustomerType;
  name: string;
  code: string;
  phone: string;
  email: string;
  taxId: string;
  paymentTermsDays: string;
  notes: string;
};

type FieldErrors =
  Record<
    string,
    string[]
  >;

const initialForm:
  FormState = {
  type: "COMPANY",
  name: "",
  code: "",
  phone: "",
  email: "",
  taxId: "",
  paymentTermsDays: "30",
  notes: "",
};

function nullableText(
  value: string,
): string | null {
  const trimmed =
    value.trim();

  return trimmed
    ? trimmed
    : null;
}

function readFieldErrors(
  error: unknown,
): FieldErrors {
  if (
    !error ||
    typeof error !==
      "object" ||
    !(
      "fieldErrors" in
      error
    )
  ) {
    return {};
  }

  const value =
    (
      error as {
        fieldErrors?:
          unknown;
      }
    ).fieldErrors;

  if (
    !value ||
    typeof value !==
      "object" ||
    Array.isArray(value)
  ) {
    return {};
  }

  return value as
    FieldErrors;
}

function apiFieldName(
  key: keyof FormState,
): string {
  switch (key) {
    case "taxId":
      return "tax_id";

    case "paymentTermsDays":
      return "payment_terms_days";

    default:
      return key;
  }
}

function firstFieldError(
  errors: FieldErrors,
  field: string,
): string | null {
  return (
    errors[field]?.[0] ??
    null
  );
}

export function CustomerCreateForm() {
  const router =
    useRouter();

  const customerModule =
    getModule(
      "customers",
    );

  const [
    form,
    setForm,
  ] = useState<FormState>(
    initialForm,
  );

  const [
    submitting,
    setSubmitting,
  ] = useState(false);

  const [
    fieldErrors,
    setFieldErrors,
  ] = useState<FieldErrors>(
    {},
  );

  const [
    submitError,
    setSubmitError,
  ] = useState<
    string | null
  >(null);

  const [
    requestId,
    setRequestId,
  ] = useState<
    string | null
  >(null);

  function updateField<
    K extends keyof FormState,
  >(
    key: K,
    value: FormState[K],
  ) {
    setForm(
      (current) => ({
        ...current,
        [key]: value,
      }),
    );

    setFieldErrors(
      (current) => {
        const apiKey =
          apiFieldName(
            key,
          );

        if (
          !current[
            apiKey
          ]
        ) {
          return current;
        }

        const next = {
          ...current,
        };

        delete next[
          apiKey
        ];

        return next;
      },
    );

    setSubmitError(
      null,
    );

    setRequestId(
      null,
    );
  }

  async function handleSubmit(
    event:
      FormEvent<HTMLFormElement>,
  ) {
    event.preventDefault();

    if (submitting) {
      return;
    }

    setSubmitError(
      null,
    );

    setRequestId(
      null,
    );

    setFieldErrors(
      {},
    );

    const payload:
      CustomerPayload = {
      type: form.type,

      name:
        form.name.trim(),

      code:
        nullableText(
          form.code,
        ),

      phone:
        nullableText(
          form.phone,
        ),

      email:
        nullableText(
          form.email,
        ),

      tax_id:
        nullableText(
          form.taxId,
        ),

      payment_terms_days:
        form.paymentTermsDays
          .trim()
          ? Number(
              form.paymentTermsDays,
            )
          : 0,

      notes:
        nullableText(
          form.notes,
        ),
    };

    if (!payload.name) {
      setFieldErrors({
        name: [
          "Nama pelanggan wajib diisi.",
        ],
      });

      return;
    }

    if (
      !Number.isFinite(
        payload.payment_terms_days,
      ) ||
      (
        payload.payment_terms_days ??
        0
      ) < 0
    ) {
      setFieldErrors({
        payment_terms_days: [
          "Termin pembayaran harus berupa jumlah hari yang valid.",
        ],
      });

      return;
    }

    setSubmitting(
      true,
    );

    try {
      await createCustomer(
        payload,
      );

      router.push(
        "/app/pelanggan",
      );

      router.refresh();
    } catch (error) {
      setFieldErrors(
        readFieldErrors(
          error,
        ),
      );

      setSubmitError(
        apiErrorMessage(
          error,
          "Pelanggan belum berhasil disimpan. Silakan periksa data lalu coba lagi.",
        ),
      );

      setRequestId(
        apiRequestId(
          error,
        ),
      );
    } finally {
      setSubmitting(
        false,
      );
    }
  }

  const nameError =
    firstFieldError(
      fieldErrors,
      "name",
    );

  const codeError =
    firstFieldError(
      fieldErrors,
      "code",
    );

  const phoneError =
    firstFieldError(
      fieldErrors,
      "phone",
    );

  const emailError =
    firstFieldError(
      fieldErrors,
      "email",
    );

  const taxIdError =
    firstFieldError(
      fieldErrors,
      "tax_id",
    );

  const paymentTermsError =
    firstFieldError(
      fieldErrors,
      "payment_terms_days",
    );

  const notesError =
    firstFieldError(
      fieldErrors,
      "notes",
    );

  return (
    <section
      className={
        styles.page
      }
    >
      <div
        className={
          styles.backRow
        }
      >
        <Link
          href="/app/pelanggan"
          className={
            styles.backLink
          }
        >
          <ArrowLeft
            size={17}
          />

          Kembali ke Pelanggan
        </Link>
      </div>

      <ModuleHero
        eyebrow={
          customerModule.eyebrow ??
          customerModule.label
        }
        title="Tambah Pelanggan"
        description="Simpan data pelanggan agar dapat digunakan kembali pada penawaran, tagihan, dan pembayaran."
        icon={
          customerModule.icon
        }
        tone="violet"
        insightTitle="Simpan sekali, gunakan kembali"
        insightDescription="Data pelanggan yang lengkap akan mempercepat transaksi berikutnya dan mengurangi input berulang."
      />

      <form
        className={
          styles.form
        }
        onSubmit={
          handleSubmit
        }
        noValidate
      >
        <section
          className={
            styles.card
          }
        >
          <div
            className={
              styles.sectionHeading
            }
          >
            <div>
              <p
                className={
                  styles.sectionEyebrow
                }
              >
                Informasi Utama
              </p>

              <h2>
                Data pelanggan
              </h2>

              <p>
                Isi informasi yang
                paling sering digunakan
                dalam transaksi.
              </p>
            </div>
          </div>

          <fieldset
            className={
              styles.typeFieldset
            }
          >
            <legend>
              Tipe pelanggan
            </legend>

            <div
              className={
                styles.typeGrid
              }
            >
              <button
                type="button"
                className={
                  form.type ===
                  "COMPANY"
                    ? styles.typeCardActive
                    : styles.typeCard
                }
                onClick={() =>
                  updateField(
                    "type",
                    "COMPANY",
                  )
                }
              >
                <span
                  className={
                    styles.typeIcon
                  }
                >
                  <Building2
                    size={20}
                  />
                </span>

                <span>
                  <strong>
                    Perusahaan
                  </strong>

                  <small>
                    Untuk badan usaha
                    atau perusahaan.
                  </small>
                </span>
              </button>

              <button
                type="button"
                className={
                  form.type ===
                  "INDIVIDUAL"
                    ? styles.typeCardActive
                    : styles.typeCard
                }
                onClick={() =>
                  updateField(
                    "type",
                    "INDIVIDUAL",
                  )
                }
              >
                <span
                  className={
                    styles.typeIcon
                  }
                >
                  <UserRound
                    size={20}
                  />
                </span>

                <span>
                  <strong>
                    Perorangan
                  </strong>

                  <small>
                    Untuk pelanggan
                    individu.
                  </small>
                </span>
              </button>
            </div>
          </fieldset>

          <div
            className={
              styles.fields
            }
          >
            <label
              className={
                styles.fieldWide
              }
            >
              <span>
                Nama pelanggan
                <b> *</b>
              </span>

              <input
                value={
                  form.name
                }
                onChange={
                  (event) =>
                    updateField(
                      "name",
                      event.target
                        .value,
                    )
                }
                placeholder={
                  form.type ===
                  "COMPANY"
                    ? "Contoh: CV Maju Bersama"
                    : "Contoh: Budi Santoso"
                }
                aria-invalid={
                  Boolean(
                    nameError,
                  )
                }
              />

              {nameError ? (
                <small
                  className={
                    styles.fieldError
                  }
                >
                  {nameError}
                </small>
              ) : null}
            </label>

            <label>
              <span>
                Kode pelanggan
              </span>

              <input
                value={
                  form.code
                }
                onChange={
                  (event) =>
                    updateField(
                      "code",
                      event.target
                        .value,
                    )
                }
                placeholder="Opsional"
                aria-invalid={
                  Boolean(
                    codeError,
                  )
                }
              />

              <small
                className={
                  codeError
                    ? styles.fieldError
                    : styles.help
                }
              >
                {codeError ??
                  "Kode internal usaha, jika digunakan."}
              </small>
            </label>

            <label>
              <span>
                Telepon / WhatsApp
              </span>

              <input
                value={
                  form.phone
                }
                onChange={
                  (event) =>
                    updateField(
                      "phone",
                      event.target
                        .value,
                    )
                }
                placeholder="08xxxxxxxxxx"
                inputMode="tel"
                aria-invalid={
                  Boolean(
                    phoneError,
                  )
                }
              />

              {phoneError ? (
                <small
                  className={
                    styles.fieldError
                  }
                >
                  {phoneError}
                </small>
              ) : null}
            </label>

            <label>
              <span>
                Email
              </span>

              <input
                value={
                  form.email
                }
                onChange={
                  (event) =>
                    updateField(
                      "email",
                      event.target
                        .value,
                    )
                }
                placeholder="nama@perusahaan.com"
                type="email"
                inputMode="email"
                aria-invalid={
                  Boolean(
                    emailError,
                  )
                }
              />

              {emailError ? (
                <small
                  className={
                    styles.fieldError
                  }
                >
                  {emailError}
                </small>
              ) : null}
            </label>
          </div>
        </section>

        <section
          className={
            styles.card
          }
        >
          <div
            className={
              styles.sectionHeading
            }
          >
            <div>
              <p
                className={
                  styles.sectionEyebrow
                }
              >
                Informasi Bisnis
              </p>

              <h2>
                Preferensi transaksi
              </h2>

              <p>
                Data ini membantu
                SIGNOVA menyiapkan
                transaksi pelanggan
                berikutnya.
              </p>
            </div>
          </div>

          <div
            className={
              styles.fields
            }
          >
            <label>
              <span>
                NPWP / Tax ID
              </span>

              <input
                value={
                  form.taxId
                }
                onChange={
                  (event) =>
                    updateField(
                      "taxId",
                      event.target
                        .value,
                    )
                }
                placeholder="Opsional"
                aria-invalid={
                  Boolean(
                    taxIdError,
                  )
                }
              />

              {taxIdError ? (
                <small
                  className={
                    styles.fieldError
                  }
                >
                  {taxIdError}
                </small>
              ) : null}
            </label>

            <label>
              <span>
                Termin pembayaran
              </span>

              <div
                className={
                  styles.inputSuffix
                }
              >
                <input
                  value={
                    form.paymentTermsDays
                  }
                  onChange={
                    (event) =>
                      updateField(
                        "paymentTermsDays",
                        event.target
                          .value,
                      )
                  }
                  type="number"
                  min="0"
                  max="3650"
                  inputMode="numeric"
                  aria-invalid={
                    Boolean(
                      paymentTermsError,
                    )
                  }
                />

                <span>
                  hari
                </span>
              </div>

              <small
                className={
                  paymentTermsError
                    ? styles.fieldError
                    : styles.help
                }
              >
                {paymentTermsError ??
                  "Digunakan sebagai acuan jatuh tempo tagihan."}
              </small>
            </label>

            <label
              className={
                styles.fieldWide
              }
            >
              <span>
                Catatan
              </span>

              <textarea
                value={
                  form.notes
                }
                onChange={
                  (event) =>
                    updateField(
                      "notes",
                      event.target
                        .value,
                    )
                }
                rows={4}
                placeholder="Catatan internal mengenai pelanggan..."
                aria-invalid={
                  Boolean(
                    notesError,
                  )
                }
              />

              {notesError ? (
                <small
                  className={
                    styles.fieldError
                  }
                >
                  {notesError}
                </small>
              ) : null}
            </label>
          </div>
        </section>

        <SubmitArea
          stickyMobile
          feedback={
            submitError
              ? {
                  tone:
                    "error",
                  title:
                    "Pelanggan belum tersimpan",
                  message:
                    submitError,
                  requestId,
                }
              : undefined
          }
        >
          <Link
            href="/app/pelanggan"
            className={
              styles.cancelButton
            }
          >
            Batal
          </Link>

          <button
            type="submit"
            className={
              styles.submitButton
            }
            disabled={
              submitting
            }
          >
            <Save
              size={18}
            />

            {submitting
              ? "Menyimpan..."
              : "Simpan Pelanggan"}
          </button>
        </SubmitArea>
      </form>
    </section>
  );
}
