"use client";

import {
  ArrowLeft,
  Save,
} from "lucide-react";

import Link from "next/link";

import {
  FormEvent,
  useEffect,
  useState,
} from "react";

import {
  useRouter,
} from "next/navigation";

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
  getActiveCapabilities,
} from "@/lib/auth/active-capabilities-service";

import {
  getModule,
} from "@/lib/module/registry";

import {
  createSupplier,
  updateSupplier,
} from "@/lib/supplier/service";

import type {
  Supplier,
  SupplierPayload,
  SupplierStatus,
} from "@/types/supplier";

import styles from "./supplier-form.module.css";

type FieldErrors =
  Record<
    string,
    string[]
  >;

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
    typeof error !== "object" ||
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
        fieldErrors?: unknown;
      }
    ).fieldErrors;

  if (
    !value ||
    typeof value !== "object" ||
    Array.isArray(value)
  ) {
    return {};
  }

  return value as FieldErrors;
}

function firstError(
  errors: FieldErrors,
  name: string,
): string | null {
  return errors[name]?.[0] ?? null;
}

type SupplierFormProps = {
  mode?: "create" | "edit";
  initialSupplier?: Supplier;
};

export function SupplierForm({
  mode = "create",
  initialSupplier,
}: SupplierFormProps) {
  const router =
    useRouter();

  const supplierModule =
    getModule(
      "suppliers",
    );

  const SupplierIcon =
    supplierModule.icon;

  const [
    allowed,
    setAllowed,
  ] = useState<boolean | null>(
    null,
  );

  const [
    form,
    setForm,
  ] = useState({
    name:
      initialSupplier?.name ??
      "",
    code:
      initialSupplier?.code ??
      "",
    contactName:
      initialSupplier?.contact_name ??
      "",
    phone:
      initialSupplier?.phone ??
      "",
    email:
      initialSupplier?.email ??
      "",
    taxId:
      initialSupplier?.tax_id ??
      "",
    address:
      initialSupplier?.address ??
      "",
    city:
      initialSupplier?.city ??
      "",
    province:
      initialSupplier?.province ??
      "",
    paymentTermsDays:
      String(
        initialSupplier
          ?.payment_terms_days ??
        0,
      ),
    notes:
      initialSupplier?.notes ??
      "",
    status:
      initialSupplier?.status ??
      "ACTIVE" as SupplierStatus,
  });

  const [
    submitting,
    setSubmitting,
  ] = useState(false);

  const [
    fieldErrors,
    setFieldErrors,
  ] = useState<FieldErrors>({});

  const [
    submitError,
    setSubmitError,
  ] = useState<string | null>(
    null,
  );

  const [
    requestId,
    setRequestId,
  ] = useState<string | null>(
    null,
  );

  useEffect(() => {
    let cancelled = false;

    async function load() {
      try {
        const response =
          await getActiveCapabilities();

        if (!cancelled) {
          setAllowed(
            response.data
              .capability_codes
              .includes(
                mode === "edit"
                  ? "supplier.update"
                  : "supplier.create",
              ),
          );
        }
      } catch {
        if (!cancelled) {
          setAllowed(false);
        }
      }
    }

    void load();

    return () => {
      cancelled = true;
    };
  }, [
    mode,
  ]);

  function update(
    key: keyof typeof form,
    value: string,
  ) {
    setForm(
      (current) => ({
        ...current,
        [key]: value,
      }),
    );

    setSubmitError(null);
    setRequestId(null);
  }

  async function handleSubmit(
    event:
      FormEvent<HTMLFormElement>,
  ) {
    event.preventDefault();

    if (
      submitting ||
      allowed !== true
    ) {
      return;
    }

    setFieldErrors({});
    setSubmitError(null);
    setRequestId(null);

    const paymentTermsDays =
      form.paymentTermsDays.trim()
        ? Number(
            form.paymentTermsDays,
          )
        : 0;

    if (!form.name.trim()) {
      setFieldErrors({
        name: [
          "Nama pemasok wajib diisi.",
        ],
      });

      return;
    }

    if (
      !Number.isFinite(
        paymentTermsDays,
      ) ||
      paymentTermsDays < 0
    ) {
      setFieldErrors({
        payment_terms_days: [
          "Termin pembayaran harus berupa jumlah hari yang valid.",
        ],
      });

      return;
    }

    const payload:
      SupplierPayload = {
      name:
        form.name.trim(),
      code:
        nullableText(
          form.code,
        ),
      contact_name:
        nullableText(
          form.contactName,
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
      address:
        nullableText(
          form.address,
        ),
      city:
        nullableText(
          form.city,
        ),
      province:
        nullableText(
          form.province,
        ),
      payment_terms_days:
        paymentTermsDays,
      notes:
        nullableText(
          form.notes,
        ),
      status:
        form.status,
    };

    setSubmitting(true);

    try {
      const response =
        mode === "edit" &&
        initialSupplier
          ? await updateSupplier(
              initialSupplier.id,
              payload,
            )
          : await createSupplier(
              payload,
            );

      router.replace(
        `/app/pemasok/${response.data.id}`,
      );
    } catch (error) {
      setFieldErrors(
        readFieldErrors(
          error,
        ),
      );

      setSubmitError(
        apiErrorMessage(
          error,
          "Pemasok belum berhasil disimpan. Silakan periksa data lalu coba lagi.",
        ),
      );

      setRequestId(
        apiRequestId(
          error,
        ),
      );
    } finally {
      setSubmitting(false);
    }
  }

  if (allowed === null) {
    return (
      <section
        className={styles.page}
      >
        <div
          className={
            styles.loading
          }
        >
          Memeriksa hak akses...
        </div>
      </section>
    );
  }

  if (!allowed) {
    return (
      <section
        className={styles.page}
      >
        <Link
          href="/app/pemasok"
          className={
            styles.backLink
          }
        >
          <ArrowLeft size={17} />
          Kembali ke Pemasok
        </Link>

        <ActionFeedback
          tone="error"
          title={
            mode === "edit"
              ? "Tidak dapat mengubah pemasok"
              : "Tidak dapat menambah pemasok"
          }
          message={
            mode === "edit"
              ? "Akun Anda tidak memiliki hak untuk mengubah data pemasok."
              : "Akun Anda tidak memiliki hak untuk membuat data pemasok."
          }
        />
      </section>
    );
  }

  return (
    <section
      className={styles.page}
    >
      <Link
        href="/app/pemasok"
        className={styles.backLink}
      >
        <ArrowLeft size={17} />
        Kembali ke Pemasok
      </Link>

      <ModuleHero
        eyebrow="Data Pemasok"
        title={
          mode === "edit"
            ? "Ubah Pemasok"
            : "Tambah Pemasok"
        }
        description={
          mode === "edit"
            ? "Perbarui identitas, kontak, alamat, status, dan ketentuan pembayaran pemasok."
            : "Simpan identitas, kontak, alamat, dan ketentuan pembayaran pemasok."
        }
        icon={SupplierIcon}
        tone={
          supplierModule.tone
        }
        insightTitle="Isi data yang memang digunakan"
        insightDescription="Nama pemasok wajib. Data lain dapat dilengkapi sesuai kebutuhan usaha."
      />

      {submitError ? (
        <ActionFeedback
          tone="error"
          title="Pemasok belum tersimpan"
          message={submitError}
          requestId={requestId}
        />
      ) : null}

      <form
        onSubmit={
          (event) =>
            void handleSubmit(
              event,
            )
        }
        className={styles.form}
      >
        <section
          className={styles.card}
        >
          <header>
            <h2>
              Identitas Pemasok
            </h2>
            <p>
              Data utama yang
              memudahkan pemasok
              dikenali.
            </p>
          </header>

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
                Nama Pemasok *
              </span>

              <input
                value={form.name}
                onChange={(event) =>
                  update(
                    "name",
                    event.target.value,
                  )
                }
                maxLength={190}
                autoFocus
              />

              {firstError(
                fieldErrors,
                "name",
              ) ? (
                <small
                  className={
                    styles.error
                  }
                >
                  {firstError(
                    fieldErrors,
                    "name",
                  )}
                </small>
              ) : null}
            </label>

            <label>
              <span>
                Kode Pemasok
              </span>
              <input
                value={form.code}
                onChange={(event) =>
                  update(
                    "code",
                    event.target.value,
                  )
                }
                maxLength={80}
                placeholder="Contoh: SUP-001"
              />
            </label>

            <label>
              <span>
                Kontak Utama
              </span>
              <input
                value={
                  form.contactName
                }
                onChange={(event) =>
                  update(
                    "contactName",
                    event.target.value,
                  )
                }
                maxLength={190}
              />
            </label>
          </div>
        </section>

        <section
          className={styles.card}
        >
          <header>
            <h2>Kontak</h2>
            <p>
              Saluran komunikasi utama
              dengan pemasok.
            </p>
          </header>

          <div
            className={
              styles.fields
            }
          >
            <label>
              <span>
                Telepon / WhatsApp
              </span>
              <input
                value={form.phone}
                onChange={(event) =>
                  update(
                    "phone",
                    event.target.value,
                  )
                }
                maxLength={64}
              />
            </label>

            <label>
              <span>Email</span>
              <input
                type="email"
                value={form.email}
                onChange={(event) =>
                  update(
                    "email",
                    event.target.value,
                  )
                }
                maxLength={190}
              />
            </label>
          </div>
        </section>

        <section
          className={styles.card}
        >
          <header>
            <h2>Alamat</h2>
            <p>
              Lokasi utama pemasok
              bila diperlukan untuk
              pembelian atau
              pengiriman.
            </p>
          </header>

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
              <span>Alamat</span>
              <textarea
                value={form.address}
                onChange={(event) =>
                  update(
                    "address",
                    event.target.value,
                  )
                }
                rows={3}
              />
            </label>

            <label>
              <span>Kota</span>
              <input
                value={form.city}
                onChange={(event) =>
                  update(
                    "city",
                    event.target.value,
                  )
                }
                maxLength={120}
              />
            </label>

            <label>
              <span>Provinsi</span>
              <input
                value={
                  form.province
                }
                onChange={(event) =>
                  update(
                    "province",
                    event.target.value,
                  )
                }
                maxLength={120}
              />
            </label>
          </div>
        </section>

        <section
          className={styles.card}
        >
          <header>
            <h2>
              Informasi Bisnis
            </h2>
            <p>
              Informasi yang akan
              digunakan pada proses
              purchasing berikutnya.
            </p>
          </header>

          <div
            className={
              styles.fields
            }
          >
            <label>
              <span>NPWP</span>
              <input
                value={form.taxId}
                onChange={(event) =>
                  update(
                    "taxId",
                    event.target.value,
                  )
                }
                maxLength={100}
              />
            </label>

            <label>
              <span>
                Termin Pembayaran
                (hari)
              </span>

              <input
                type="number"
                min="0"
                max="3650"
                value={
                  form.paymentTermsDays
                }
                onChange={(event) =>
                  update(
                    "paymentTermsDays",
                    event.target.value,
                  )
                }
              />

              {firstError(
                fieldErrors,
                "payment_terms_days",
              ) ? (
                <small
                  className={
                    styles.error
                  }
                >
                  {firstError(
                    fieldErrors,
                    "payment_terms_days",
                  )}
                </small>
              ) : null}
            </label>

            <label>
              <span>Status</span>

              <select
                value={
                  form.status
                }
                onChange={(event) =>
                  update(
                    "status",
                    event.target
                      .value as SupplierStatus,
                  )
                }
              >
                <option value="ACTIVE">
                  Aktif
                </option>

                <option value="INACTIVE">
                  Nonaktif
                </option>
              </select>
            </label>

            <label
              className={
                styles.fieldWide
              }
            >
              <span>Catatan</span>
              <textarea
                value={form.notes}
                onChange={(event) =>
                  update(
                    "notes",
                    event.target.value,
                  )
                }
                rows={4}
              />
            </label>
          </div>
        </section>

        <div
          className={
            styles.submitBar
          }
        >
          <Link
            href="/app/pemasok"
            className={
              styles.secondaryButton
            }
          >
            Batal
          </Link>

          <button
            type="submit"
            disabled={submitting}
            className={
              styles.submitButton
            }
          >
            <Save size={18} />

            {submitting
              ? "Menyimpan..."
              : mode === "edit"
                ? "Simpan Perubahan"
                : "Simpan Pemasok"}
          </button>
        </div>
      </form>
    </section>
  );
}
