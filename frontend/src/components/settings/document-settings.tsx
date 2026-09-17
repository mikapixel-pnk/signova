"use client";

import Link from "next/link";

import {
  Eye,
  FileText,
  ImageIcon,
  Lock,
  Palette,
  ReceiptText,
  Save,
  Signature,
  Store,
  Trash2,
  Upload,
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
  deleteDocumentSignature,
  getDocumentSettings,
  getDocumentSignature,
  getInvoiceTemplateCatalog,
  getInvoiceTemplatePreview,
  updateDocumentSettings,
  updateInvoiceTemplate,
  uploadDocumentSignature,
} from "@/lib/document-settings/service";

import type {
  DocumentSettings as DocumentSettingsData,
  InvoiceTemplateCatalog,
} from "@/types/document-settings";

import styles from "./document-settings.module.css";

const emptyForm = {
  quotation_opening_text: "",
  quotation_closing_text: "",
  quotation_default_terms: "",
  quotation_default_validity_days: "14",
  invoice_footnote: "",
  signature_name: "",
  signature_title: "",
};

function paletteLabel(
  key: string,
): string {
  const labels:
    Record<
      string,
      string
    > = {
    blue: "Biru",
    emerald: "Emerald",
    slate: "Slate",
  };

  return (
    labels[key] ??
    key
      .replaceAll("_", " ")
      .replace(
        /\b\w/g,
        (value) =>
          value.toUpperCase(),
      )
  );
}

function tierLabel(
  tier: string,
): string {
  return tier
    .toLowerCase()
    .replaceAll("_", " ")
    .replace(
      /\b\w/g,
      (value) =>
        value.toUpperCase(),
    );
}

export function DocumentSettings() {
  const [
    settings,
    setSettings,
  ] =
    useState<
      DocumentSettingsData | null
    >(null);

  const [
    form,
    setForm,
  ] =
    useState(
      emptyForm,
    );

  const [
    catalog,
    setCatalog,
  ] =
    useState<
      InvoiceTemplateCatalog | null
    >(null);

  const [
    templateKey,
    setTemplateKey,
  ] =
    useState("");

  const [
    paletteKey,
    setPaletteKey,
  ] =
    useState("");

  const [
    previewHtml,
    setPreviewHtml,
  ] =
    useState<string | null>(
      null,
    );

  const [
    signatureUrl,
    setSignatureUrl,
  ] =
    useState<string | null>(
      null,
    );

  const [
    signatureFile,
    setSignatureFile,
  ] =
    useState<File | null>(
      null,
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
    signatureBusy,
    setSignatureBusy,
  ] =
    useState(false);

  const [
    templateBusy,
    setTemplateBusy,
  ] =
    useState(false);

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
          const [
            documentResponse,
            templateResponse,
          ] =
            await Promise.all([
              getDocumentSettings(),
              getInvoiceTemplateCatalog(),
            ]);

          if (!active) {
            return;
          }

          setSettings(
            documentResponse.data,
          );

          setForm({
            quotation_opening_text:
              documentResponse
                .data
                .quotation_opening_text ??
              "",

            quotation_closing_text:
              documentResponse
                .data
                .quotation_closing_text ??
              "",

            quotation_default_terms:
              documentResponse
                .data
                .quotation_default_terms ??
              "",

            quotation_default_validity_days:
              String(
                documentResponse
                  .data
                  .quotation_default_validity_days ??
                14,
              ),

            invoice_footnote:
              documentResponse
                .data
                .invoice_footnote ??
              "",

            signature_name:
              documentResponse
                .data
                .signature_name ??
              "",

            signature_title:
              documentResponse
                .data
                .signature_title ??
              "",
          });

          setCatalog(
            templateResponse.data,
          );

          setTemplateKey(
            templateResponse
              .data
              .selected
              .template_key,
          );

          setPaletteKey(
            templateResponse
              .data
              .selected
              .palette_key,
          );
        } catch (exception) {
          if (!active) {
            return;
          }

          setError(
            apiErrorMessage(
              exception,
              "Pengaturan dokumen belum dapat dibuka.",
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

      async function loadSignature() {
        if (
          !settings
            ?.has_signature_image
        ) {
          setSignatureUrl(
            null,
          );
          return;
        }

        try {
          const blob =
            await getDocumentSignature();

          if (!active) {
            return;
          }

          objectUrl =
            URL.createObjectURL(
              blob,
            );

          setSignatureUrl(
            objectUrl,
          );
        } catch {
          if (active) {
            setSignatureUrl(
              null,
            );
          }
        }
      }

      void loadSignature();

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
        ?.has_signature_image,
      settings
        ?.signature_image
        ?.id,
    ],
  );

  const selectedTemplate =
    useMemo(
      () =>
        catalog
          ?.templates
          .find(
            (template) =>
              template.key ===
              templateKey,
          ) ??
        null,
      [
        catalog,
        templateKey,
      ],
    );

  function clearFeedback() {
    setSuccess(null);
    setError(null);
    setRequestId(null);
  }

  function updateField(
    field:
      keyof typeof form,
    value:
      string,
  ) {
    setForm(
      (current) => ({
        ...current,
        [field]:
          value,
      }),
    );

    clearFeedback();
  }

  async function handleSubmit(
    event:
      React.FormEvent<
        HTMLFormElement
      >,
  ) {
    event.preventDefault();

    setSubmitting(true);
    clearFeedback();

    try {
      const response =
        await updateDocumentSettings({
          quotation_opening_text:
            form
              .quotation_opening_text
              .trim() ||
            null,

          quotation_closing_text:
            form
              .quotation_closing_text
              .trim() ||
            null,

          quotation_default_terms:
            form
              .quotation_default_terms
              .trim() ||
            null,

          quotation_default_validity_days:
            Number(
              form
                .quotation_default_validity_days,
            ) ||
            null,

          invoice_footnote:
            form
              .invoice_footnote
              .trim() ||
            null,

          signature_name:
            form
              .signature_name
              .trim() ||
            null,

          signature_title:
            form
              .signature_title
              .trim() ||
            null,
        });

      setSettings(
        response.data,
      );

      setSuccess(
        response.message ??
          "Pengaturan dokumen berhasil disimpan.",
      );
    } catch (exception) {
      setError(
        apiErrorMessage(
          exception,
          "Pengaturan dokumen belum berhasil disimpan.",
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

  async function handleSignatureUpload() {
    if (!signatureFile) {
      setError(
        "Pilih file tanda tangan terlebih dahulu.",
      );
      return;
    }

    const allowed = [
      "image/png",
      "image/jpeg",
      "image/webp",
    ];

    if (
      !allowed.includes(
        signatureFile.type,
      )
    ) {
      setError(
        "Gunakan file PNG, JPG, JPEG, atau WebP.",
      );
      return;
    }

    if (
      signatureFile.size >
      2 * 1024 * 1024
    ) {
      setError(
        "Ukuran tanda tangan maksimal 2 MB.",
      );
      return;
    }

    setSignatureBusy(true);
    clearFeedback();

    try {
      const response =
        await uploadDocumentSignature(
          signatureFile,
        );

      setSettings(
        response.data,
      );

      setSignatureFile(
        null,
      );

      setSuccess(
        response.message ??
          "Tanda tangan berhasil disimpan.",
      );
    } catch (exception) {
      setError(
        apiErrorMessage(
          exception,
          "Tanda tangan belum berhasil disimpan.",
        ),
      );

      setRequestId(
        apiRequestId(
          exception,
        ),
      );
    } finally {
      setSignatureBusy(false);
    }
  }

  async function handleSignatureDelete() {
    if (
      !window.confirm(
        "Hapus tanda tangan digital untuk usaha aktif?",
      )
    ) {
      return;
    }

    setSignatureBusy(true);
    clearFeedback();

    try {
      const response =
        await deleteDocumentSignature();

      setSettings(
        response.data,
      );

      setSignatureFile(
        null,
      );

      setSuccess(
        response.message ??
          "Tanda tangan berhasil dihapus.",
      );
    } catch (exception) {
      setError(
        apiErrorMessage(
          exception,
          "Tanda tangan belum berhasil dihapus.",
        ),
      );

      setRequestId(
        apiRequestId(
          exception,
        ),
      );
    } finally {
      setSignatureBusy(false);
    }
  }

  function chooseTemplate(
    key: string,
  ) {
    const template =
      catalog
        ?.templates
        .find(
          (item) =>
            item.key === key,
        );

    if (!template) {
      return;
    }

    setTemplateKey(
      template.key,
    );

    setPaletteKey(
      template.palettes.includes(
        paletteKey,
      )
        ? paletteKey
        : template
            .default_palette,
    );

    setPreviewHtml(
      null,
    );

    clearFeedback();
  }

  async function handleTemplatePreview() {
    if (!templateKey) {
      return;
    }

    setTemplateBusy(true);
    clearFeedback();

    try {
      const html =
        await getInvoiceTemplatePreview(
          templateKey,
          paletteKey ||
            null,
        );

      setPreviewHtml(
        html,
      );
    } catch (exception) {
      setError(
        apiErrorMessage(
          exception,
          "Pratinjau template belum dapat dibuat.",
        ),
      );

      setRequestId(
        apiRequestId(
          exception,
        ),
      );
    } finally {
      setTemplateBusy(false);
    }
  }

  async function handleTemplateSave() {
    if (
      !templateKey ||
      !selectedTemplate
        ?.is_available
    ) {
      return;
    }

    setTemplateBusy(true);
    clearFeedback();

    try {
      const response =
        await updateInvoiceTemplate({
          invoice_template_key:
            templateKey,

          invoice_palette_key:
            paletteKey ||
            null,
        });

      setCatalog(
        response.data,
      );

      setTemplateKey(
        response
          .data
          .selected
          .template_key,
      );

      setPaletteKey(
        response
          .data
          .selected
          .palette_key,
      );

      setSuccess(
        response.message ??
          "Template invoice berhasil diperbarui.",
      );
    } catch (exception) {
      setError(
        apiErrorMessage(
          exception,
          "Template invoice belum berhasil disimpan.",
        ),
      );

      setRequestId(
        apiRequestId(
          exception,
        ),
      );
    } finally {
      setTemplateBusy(false);
    }
  }

  if (loading) {
    return (
      <div
        className={
          styles.state
        }
      >
        Memuat pengaturan
        dokumen...
      </div>
    );
  }

  if (
    error &&
    settings === null
  ) {
    return (
      <ActionFeedback
        tone="error"
        title="Pengaturan dokumen belum dapat dibuka"
        message={error}
        requestId={
          requestId ??
          undefined
        }
      />
    );
  }

  if (!settings) {
    return null;
  }

  return (
    <div
      className={
        styles.page
      }
    >
      <ModuleHero
        eyebrow="Dokumen & Template"
        title="Dokumen & Template"
        description="Atur isi penawaran, catatan tagihan, penandatangan, dan tampilan dokumen sesuai usaha aktif."
        icon={FileText}
        tone="cyan"
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
          <CardHeader
            icon={Store}
            title="Identitas Dokumen"
            description="Mengikuti Profil Usaha aktif."
          />

          <div
            className={
              styles.identityGrid
            }
          >
            <Info
              label="Nama Usaha"
              value={
                settings.business_name
              }
            />

            <Info
              label="Nama Legal"
              value={
                settings.legal_name
              }
            />

            <Info
              label="Email"
              value={
                settings.email
              }
            />

            <Info
              label="Telepon"
              value={
                settings.phone
              }
            />

            <Info
              label="NPWP"
              value={
                settings.tax_id
              }
            />

            <Info
              label="Alamat"
              value={[
                settings.address,
                settings.city,
                settings.province,
              ]
                .filter(Boolean)
                .join(", ")}
            />
          </div>

          <Link
            href="/app/pengaturan?bagian=bisnis#settings-detail"
            className={
              styles.profileLink
            }
          >
            Ubah di Profil Usaha
          </Link>
        </section>

        <section
          className={
            styles.card
          }
        >
          <CardHeader
            icon={FileText}
            title="Konten Penawaran"
            description="Teks default yang digunakan pada dokumen penawaran."
          />

          <label
            className={
              styles.field
            }
          >
            <span>
              Paragraf Pembuka
              Penawaran
            </span>

            <textarea
              rows={5}
              value={
                form
                  .quotation_opening_text
              }
              onChange={
                (event) =>
                  updateField(
                    "quotation_opening_text",
                    event.target
                      .value,
                  )
              }
              placeholder="Contoh: Dengan hormat, bersama ini kami sampaikan penawaran..."
            />
          </label>

          <label
            className={
              styles.field
            }
          >
            <span>
              Paragraf Penutup
              Penawaran
            </span>

            <textarea
              rows={5}
              value={
                form
                  .quotation_closing_text
              }
              onChange={
                (event) =>
                  updateField(
                    "quotation_closing_text",
                    event.target
                      .value,
                  )
              }
              placeholder="Contoh: Demikian penawaran ini kami sampaikan..."
            />
          </label>

          <label
            className={
              styles.field
            }
          >
            <span>
              Syarat & Ketentuan
              Default
            </span>

            <textarea
              rows={6}
              value={
                form
                  .quotation_default_terms
              }
              onChange={
                (event) =>
                  updateField(
                    "quotation_default_terms",
                    event.target.value,
                  )
              }
              placeholder="Syarat dan ketentuan default untuk Penawaran baru."
            />
          </label>

          <label
            className={
              styles.field
            }
          >
            <span>
              Masa Berlaku
              Penawaran Default
            </span>

            <div
              className={
                styles.validityField
              }
            >
              <input
                type="number"
                min="1"
                max="365"
                inputMode="numeric"
                value={
                  form
                    .quotation_default_validity_days
                }
                onChange={
                  (event) =>
                    updateField(
                      "quotation_default_validity_days",
                      event.target.value,
                    )
                }
              />

              <span>
                hari
              </span>
            </div>

            <small>
              Digunakan sebagai nilai awal
              saat membuat Penawaran baru.
              Dapat diubah pada transaksi.
            </small>
          </label>
        </section>

        <section
          className={
            styles.card
          }
        >
          <CardHeader
            icon={ReceiptText}
            title="Tagihan"
            description="Catatan kaki yang digunakan pada dokumen tagihan."
          />

          <label
            className={
              styles.field
            }
          >
            <span>
              Catatan Kaki
              Tagihan
            </span>

            <textarea
              rows={4}
              value={
                form
                  .invoice_footnote
              }
              onChange={
                (event) =>
                  updateField(
                    "invoice_footnote",
                    event.target
                      .value,
                  )
              }
              placeholder="Contoh: Pembayaran dianggap sah setelah dana diterima."
            />
          </label>
        </section>

        <section
          className={
            styles.card
          }
        >
          <CardHeader
            icon={Signature}
            title="Penandatangan"
            description="Nama, jabatan, dan tanda tangan digital pada dokumen."
          />

          <div
            className={
              styles.twoColumns
            }
          >
            <label
              className={
                styles.field
              }
            >
              <span>
                Nama
                Penandatangan
              </span>

              <input
                value={
                  form
                    .signature_name
                }
                onChange={
                  (event) =>
                    updateField(
                      "signature_name",
                      event.target
                        .value,
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
                Jabatan
              </span>

              <input
                value={
                  form
                    .signature_title
                }
                onChange={
                  (event) =>
                    updateField(
                      "signature_title",
                      event.target
                        .value,
                    )
                }
              />
            </label>
          </div>

          <div
            className={
              styles.signaturePanel
            }
          >
            <div
              className={
                styles.signaturePreview
              }
            >
              {signatureUrl ? (
                <>
                  {/* Blob private hasil endpoint terautentikasi. */}
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img
                    src={
                      signatureUrl
                    }
                    alt="Tanda tangan digital"
                  />
                </>
              ) : (
                <>
                  <ImageIcon
                    size={27}
                  />

                  <span>
                    Belum ada
                    tanda tangan
                  </span>
                </>
              )}
            </div>

            <div
              className={
                styles.signatureControls
              }
            >
              <label
                className={
                  styles.filePicker
                }
              >
                <Upload
                  size={16}
                />

                <span>
                  {settings
                    .has_signature_image
                    ? "Pilih Pengganti"
                    : "Pilih Tanda Tangan"}
                </span>

                <input
                  type="file"
                  accept="image/png,image/jpeg,image/webp"
                  onChange={
                    (event) => {
                      setSignatureFile(
                        event
                          .target
                          .files?.[0] ??
                          null,
                      );

                      clearFeedback();
                    }
                  }
                />
              </label>

              {signatureFile ? (
                <small>
                  {
                    signatureFile.name
                  }
                </small>
              ) : settings
                  .signature_image
                  ?.original_name ? (
                <small>
                  {
                    settings
                      .signature_image
                      .original_name
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
                    !signatureFile ||
                    signatureBusy
                  }
                  onClick={
                    () =>
                      void handleSignatureUpload()
                  }
                >
                  <Upload
                    size={15}
                  />

                  {signatureBusy
                    ? "Memproses..."
                    : settings
                        .has_signature_image
                      ? "Ganti"
                      : "Upload"}
                </button>

                {settings
                  .has_signature_image ? (
                  <button
                    type="button"
                    className={
                      styles.dangerButton
                    }
                    disabled={
                      signatureBusy
                    }
                    onClick={
                      () =>
                        void handleSignatureDelete()
                    }
                  >
                    <Trash2
                      size={15}
                    />

                    Hapus
                  </button>
                ) : null}
              </div>

              <small>
                PNG, JPG/JPEG,
                atau WebP. Maksimal
                2 MB.
              </small>
            </div>
          </div>
        </section>

        {catalog ? (
          <section
            className={
              styles.card
            }
          >
            <CardHeader
              icon={Palette}
              title="Template & Tampilan"
              description="Pilih tampilan default untuk Tagihan baru."
            />

            <div
              className={
                styles.templateGrid
              }
            >
              {catalog.templates.map(
                (template) => {
                  const active =
                    template.key ===
                    templateKey;

                  return (
                    <button
                      key={
                        template.key
                      }
                      type="button"
                      className={
                        active
                          ? styles.templateCardActive
                          : styles.templateCard
                      }
                      onClick={
                        () =>
                          chooseTemplate(
                            template.key,
                          )
                      }
                    >
                      <div
                        className={
                          styles.templateCardHeader
                        }
                      >
                        <strong>
                          {
                            template.name
                          }
                        </strong>

                        <span
                          className={
                            styles.templateTier
                          }
                        >
                          {
                            tierLabel(
                              template.tier,
                            )
                          }
                        </span>
                      </div>

                      <span
                        className={
                          styles.templateLayout
                        }
                      >
                        Tampilan {
                          template.layout
                        }
                      </span>

                      <div
                        className={
                          styles.templateMeta
                        }
                      >
                        {template
                          .is_selected ? (
                          <span
                            className={
                              styles.templateSelected
                            }
                          >
                            Aktif
                          </span>
                        ) : active ? (
                          <span>
                            Sedang dilihat
                          </span>
                        ) : (
                          <span>
                            Pilih untuk melihat
                          </span>
                        )}

                        {!template
                          .is_available ? (
                          <span
                            className={
                              styles.templateLocked
                            }
                          >
                            <Lock
                              size={12}
                            />

                            Perlu paket {
                              tierLabel(
                                template.tier,
                              )
                            }
                          </span>
                        ) : null}
                      </div>
                    </button>
                  );
                },
              )}
            </div>

            {selectedTemplate ? (
              <div
                className={
                  styles.paletteSection
                }
              >
                <strong>
                  Palet Warna
                </strong>

                <div
                  className={
                    styles.paletteOptions
                  }
                >
                  {selectedTemplate
                    .palettes
                    .map(
                      (
                        palette,
                      ) => (
                        <button
                          key={
                            palette
                          }
                          type="button"
                          data-palette={
                            palette
                          }
                          className={
                            palette ===
                            paletteKey
                              ? styles.paletteButtonActive
                              : styles.paletteButton
                          }
                          onClick={
                            () => {
                              setPaletteKey(
                                palette,
                              );
                              setPreviewHtml(
                                null,
                              );
                              clearFeedback();
                            }
                          }
                        >
                          <span
                            className={
                              styles.paletteDot
                            }
                          />

                          {
                            paletteLabel(
                              palette,
                            )
                          }
                        </button>
                      ),
                    )}
                </div>
              </div>
            ) : null}

            {selectedTemplate &&
            !selectedTemplate
              .is_available ? (
              <div
                className={
                  styles.templateUpgradeNote
                }
              >
                <Lock
                  size={15}
                />

                <div>
                  <strong>
                    Template dapat dipratinjau
                  </strong>

                  <span>
                    Template ini memerlukan paket {
                      tierLabel(
                        selectedTemplate
                          .tier,
                      )
                    } untuk digunakan.
                  </span>
                </div>
              </div>
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
                  templateBusy ||
                  !templateKey
                }
                onClick={
                  () =>
                    void handleTemplatePreview()
                }
              >
                <Eye
                  size={15}
                />

                Pratinjau
              </button>

              <button
                type="button"
                className={
                  styles.primaryButton
                }
                disabled={
                  templateBusy ||
                  !templateKey ||
                  !selectedTemplate
                    ?.is_available
                }
                onClick={
                  () =>
                    void handleTemplateSave()
                }
              >
                <Save
                  size={15}
                />

                Simpan Template
              </button>
            </div>

            {previewHtml ? (
              <div
                className={
                  styles.previewFrame
                }
              >
                <iframe
                  title="Pratinjau Template Invoice"
                  srcDoc={
                    previewHtml
                  }
                  sandbox=""
                />
              </div>
            ) : null}
          </section>
        ) : null}

        {success ? (
          <ActionFeedback
            tone="success"
            title="Perubahan tersimpan"
            message={
              success
            }
          />
        ) : null}

        <SubmitArea
          stickyMobile
          feedback={
            error
              ? {
                  tone:
                    "error",
                  title:
                    "Perubahan belum tersimpan",
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
              : "Simpan Pengaturan Dokumen"}
          </button>
        </SubmitArea>
      </form>
    </div>
  );
}

function CardHeader({
  icon: Icon,
  title,
  description,
}: {
  icon:
    React.ComponentType<{
      size?: number;
    }>;

  title: string;
  description: string;
}) {
  return (
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
        <Icon
          size={20}
        />
      </span>

      <div>
        <h2>
          {title}
        </h2>

        <p>
          {description}
        </p>
      </div>
    </header>
  );
}

function Info({
  label,
  value,
}: {
  label: string;
  value:
    string | null | undefined;
}) {
  return (
    <div
      className={
        styles.info
      }
    >
      <span>
        {label}
      </span>

      <strong>
        {value ||
          "Belum diisi"}
      </strong>
    </div>
  );
}
