"use client";

import {
  Building2,
  Save,
} from "lucide-react";

import {
  FormEvent,
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
  getBusinessProfile,
  updateBusinessProfile,
} from "@/lib/business-profile/service";

import type {
  BusinessProfile,
  BusinessProfilePayload,
} from "@/types/business-profile";

import styles from "./business-profile-settings.module.css";

type FormState = {
  name: string;
  legalName: string;
  address: string;
  city: string;
  province: string;
  postalCode: string;
  phone: string;
  whatsapp: string;
  email: string;
  website: string;
  taxId: string;
};

type FieldErrors =
  Record<string, string[]>;

const emptyForm: FormState = {
  name: "",
  legalName: "",
  address: "",
  city: "",
  province: "",
  postalCode: "",
  phone: "",
  whatsapp: "",
  email: "",
  website: "",
  taxId: "",
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

function formFromProfile(
  profile: BusinessProfile,
): FormState {
  return {
    name: profile.name,
    legalName:
      profile.legal_name ?? "",
    address:
      profile.address ?? "",
    city:
      profile.city ?? "",
    province:
      profile.province ?? "",
    postalCode:
      profile.postal_code ?? "",
    phone:
      profile.phone ?? "",
    whatsapp:
      profile.whatsapp ?? "",
    email:
      profile.email ?? "",
    website:
      profile.website ?? "",
    taxId:
      profile.tax_id ?? "",
  };
}

function readFieldErrors(
  error: unknown,
): FieldErrors {
  if (
    !error ||
    typeof error !== "object" ||
    !("fieldErrors" in error)
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
  key: string,
): string | null {
  return errors[key]?.[0] ?? null;
}

export function BusinessProfileSettings() {
  const [
    profile,
    setProfile,
  ] = useState<
    BusinessProfile | null
  >(null);

  const [
    form,
    setForm,
  ] = useState<FormState>(
    emptyForm,
  );

  const [
    loading,
    setLoading,
  ] = useState(true);

  const [
    submitting,
    setSubmitting,
  ] = useState(false);

  const [
    loadError,
    setLoadError,
  ] = useState<string | null>(
    null,
  );

  const [
    submitError,
    setSubmitError,
  ] = useState<string | null>(
    null,
  );

  const [
    successMessage,
    setSuccessMessage,
  ] = useState<string | null>(
    null,
  );

  const [
    requestId,
    setRequestId,
  ] = useState<string | null>(
    null,
  );

  const [
    fieldErrors,
    setFieldErrors,
  ] = useState<FieldErrors>(
    {},
  );

  useEffect(() => {
    let cancelled = false;

    async function load() {
      setLoading(true);
      setLoadError(null);
      setRequestId(null);

      try {
        const response =
          await getBusinessProfile();

        if (!cancelled) {
          setProfile(
            response.data,
          );

          setForm(
            formFromProfile(
              response.data,
            ),
          );
        }
      } catch (error) {
        if (!cancelled) {
          setLoadError(
            apiErrorMessage(
              error,
              "Profil usaha belum berhasil dimuat.",
            ),
          );

          setRequestId(
            apiRequestId(error),
          );
        }
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    }

    void load();

    return () => {
      cancelled = true;
    };
  }, []);

  function updateField(
    key: keyof FormState,
    value: string,
  ) {
    setForm(
      (current) => ({
        ...current,
        [key]: value,
      }),
    );

    setSubmitError(null);
    setSuccessMessage(null);
    setRequestId(null);
  }

  async function handleSubmit(
    event: FormEvent<HTMLFormElement>,
  ) {
    event.preventDefault();

    if (submitting) {
      return;
    }

    setSubmitting(true);
    setSubmitError(null);
    setSuccessMessage(null);
    setRequestId(null);
    setFieldErrors({});

    const payload:
      BusinessProfilePayload = {
        name: form.name.trim(),

        legal_name:
          nullableText(
            form.legalName,
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

        postal_code:
          nullableText(
            form.postalCode,
          ),

        phone:
          nullableText(
            form.phone,
          ),

        whatsapp:
          nullableText(
            form.whatsapp,
          ),

        email:
          nullableText(
            form.email,
          ),

        website:
          nullableText(
            form.website,
          ),

        tax_id:
          nullableText(
            form.taxId,
          ),
      };

    try {
      const response =
        await updateBusinessProfile(
          payload,
        );

      setProfile(
        response.data,
      );

      setForm(
        formFromProfile(
          response.data,
        ),
      );

      setSuccessMessage(
        response.message ??
          "Profil usaha berhasil diperbarui.",
      );
    } catch (error) {
      setFieldErrors(
        readFieldErrors(error),
      );

      setSubmitError(
        apiErrorMessage(
          error,
          "Profil usaha belum berhasil disimpan.",
        ),
      );

      setRequestId(
        apiRequestId(error),
      );
    } finally {
      setSubmitting(false);
    }
  }

  if (loading) {
    return (
      <div
        className={
          styles.loading
        }
      >
        Memuat profil usaha...
      </div>
    );
  }

  if (
    loadError ||
    !profile
  ) {
    return (
      <ActionFeedback
        tone="error"
        title="Profil usaha belum dapat dibuka"
        message={
          loadError ??
          "Profil usaha tidak ditemukan."
        }
        requestId={
          requestId
        }
      />
    );
  }

  return (
    <div
      className={
        styles.page
      }
    >
      <ModuleHero
        eyebrow="Pengaturan"
        title="Profil Usaha"
        description="Kelola identitas usaha aktif yang digunakan di SIGNOVA."
        icon={Building2}
        tone="blue"
        insightTitle={
          profile.is_default
            ? "Usaha utama"
            : "Usaha aktif"
        }
        insightDescription={
          profile.status ===
          "ACTIVE"
            ? "Status usaha aktif."
            : "Status usaha tidak aktif."
        }
      />

      <form
        className={
          styles.form
        }
        onSubmit={
          handleSubmit
        }
      >
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
            <div>
              <h2>
                Identitas Usaha
              </h2>

              <p>
                Informasi utama yang
                mewakili usaha aktif.
              </p>
            </div>
          </header>

          <div
            className={
              styles.grid
            }
          >
            <label
              className={
                styles.field
              }
            >
              <span>
                Nama Usaha *
              </span>

              <input
                value={form.name}
                maxLength={190}
                required
                onChange={
                  (event) =>
                    updateField(
                      "name",
                      event.target.value,
                    )
                }
              />

              {firstError(
                fieldErrors,
                "name",
              ) && (
                <small>
                  {
                    firstError(
                      fieldErrors,
                      "name",
                    )
                  }
                </small>
              )}
            </label>

            <label
              className={
                styles.field
              }
            >
              <span>
                Nama Legal
              </span>

              <input
                value={
                  form.legalName
                }
                maxLength={190}
                onChange={
                  (event) =>
                    updateField(
                      "legalName",
                      event.target.value,
                    )
                }
              />
            </label>

            <label
              className={
                `${styles.field} ${styles.full}`
              }
            >
              <span>
                Alamat
              </span>

              <textarea
                value={
                  form.address
                }
                rows={3}
                maxLength={2000}
                onChange={
                  (event) =>
                    updateField(
                      "address",
                      event.target.value,
                    )
                }
              />
            </label>

            <label
              className={
                styles.field
              }
            >
              <span>Kota</span>

              <input
                value={form.city}
                maxLength={120}
                onChange={
                  (event) =>
                    updateField(
                      "city",
                      event.target.value,
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
                Provinsi
              </span>

              <input
                value={
                  form.province
                }
                maxLength={120}
                onChange={
                  (event) =>
                    updateField(
                      "province",
                      event.target.value,
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
                Kode Pos
              </span>

              <input
                value={
                  form.postalCode
                }
                maxLength={32}
                inputMode="numeric"
                onChange={
                  (event) =>
                    updateField(
                      "postalCode",
                      event.target.value,
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
                Telepon
              </span>

              <input
                value={
                  form.phone
                }
                maxLength={64}
                inputMode="tel"
                onChange={
                  (event) =>
                    updateField(
                      "phone",
                      event.target.value,
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
                WhatsApp
              </span>

              <input
                value={
                  form.whatsapp
                }
                maxLength={64}
                inputMode="tel"
                onChange={
                  (event) =>
                    updateField(
                      "whatsapp",
                      event.target.value,
                    )
                }
              />
            </label>

            <label
              className={
                styles.field
              }
            >
              <span>Email</span>

              <input
                type="email"
                value={
                  form.email
                }
                maxLength={190}
                onChange={
                  (event) =>
                    updateField(
                      "email",
                      event.target.value,
                    )
                }
              />
            </label>

            <label
              className={
                styles.field
              }
            >
              <span>Website</span>

              <input
                type="url"
                placeholder="https://..."
                value={
                  form.website
                }
                maxLength={255}
                onChange={
                  (event) =>
                    updateField(
                      "website",
                      event.target.value,
                    )
                }
              />
            </label>

            <label
              className={
                styles.field
              }
            >
              <span>NPWP</span>

              <input
                value={
                  form.taxId
                }
                maxLength={100}
                onChange={
                  (event) =>
                    updateField(
                      "taxId",
                      event.target.value,
                    )
                }
              />
            </label>
          </div>
        </section>

        {successMessage && (
          <ActionFeedback
            tone="success"
            title="Profil usaha tersimpan"
            message={
              successMessage
            }
          />
        )}

        <SubmitArea
          stickyMobile
          feedback={
            submitError
              ? {
                  tone: "error",
                  title:
                    "Profil usaha belum tersimpan",
                  message:
                    submitError,
                  requestId,
                }
              : undefined
          }
        >
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
              size={17}
            />

            {submitting
              ? "Menyimpan..."
              : "Simpan Profil Usaha"}
          </button>
        </SubmitArea>
      </form>
    </div>
  );
}
