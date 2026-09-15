"use client";

import {
  Pencil,
  Plus,
  Search,
  Tags,
  X,
} from "lucide-react";

import {
  useCallback,
  useEffect,
  useMemo,
  useState,
} from "react";

import {
  ActionFeedback,
} from "@/components/feedback/action-feedback";

import {
  TenantShell,
} from "@/components/layout/tenant-shell";

import {
  ModuleFooterCard,
} from "@/components/module/module-footer-card";

import {
  ModuleHero,
} from "@/components/module/module-hero";

import {
  apiErrorMessage,
  apiRequestId,
} from "@/lib/api/error-message";

import {
  createCatalogCategory,
  listCatalogCategories,
  updateCatalogCategory,
} from "@/lib/catalog/service";

import {
  getModule,
} from "@/lib/module/registry";

import type {
  CatalogCategory,
  CatalogCategoryCreatePayload,
  CatalogStatus,
} from "@/types/catalog";

import styles from "./categories.module.css";

type StatusFilter =
  | "ALL"
  | CatalogStatus;

type CategoryFormState = {
  code: string;
  name: string;
  description: string;
};

const emptyForm:
  CategoryFormState = {
    code: "",
    name: "",
    description: "",
  };

export default function CategoriesPage() {
  const moduleDef =
    getModule("categories");

  const ModuleIcon =
    moduleDef.icon;

  const [
    categories,
    setCategories,
  ] = useState<
    CatalogCategory[]
  >([]);

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
    search,
    setSearch,
  ] = useState("");

  const [
    status,
    setStatus,
  ] = useState<
    StatusFilter
  >("ALL");

  const [
    editorOpen,
    setEditorOpen,
  ] = useState(false);

  const [
    editingCategory,
    setEditingCategory,
  ] = useState<
    CatalogCategory | null
  >(null);

  const [
    form,
    setForm,
  ] = useState<
    CategoryFormState
  >(emptyForm);

  const [
    saving,
    setSaving,
  ] = useState(false);

  const [
    saveError,
    setSaveError,
  ] = useState<
    unknown
  >(null);

  const [
    success,
    setSuccess,
  ] = useState<
    string | null
  >(null);

  useEffect(() => {
    if (!success) {
      return;
    }

    const timeoutId =
      window.setTimeout(
        () => {
          setSuccess(null);
        },
        4000,
      );

    return () => {
      window.clearTimeout(
        timeoutId,
      );
    };
  }, [success]);

  const loadCategories =
    useCallback(
      async () => {
        setLoading(true);
        setError(null);

        try {
          const response =
            await listCatalogCategories();

          setCategories(
            response.data,
          );
        } catch (caught) {
          setError(caught);
        } finally {
          setLoading(false);
        }
      },
      [],
    );

  useEffect(() => {
    let active = true;

    listCatalogCategories()
      .then((response) => {
        if (!active) {
          return;
        }

        setCategories(
          response.data,
        );
      })
      .catch((caught) => {
        if (!active) {
          return;
        }

        setError(caught);
      })
      .finally(() => {
        if (!active) {
          return;
        }

        setLoading(false);
      });

    return () => {
      active = false;
    };
  }, []);

  const visibleCategories =
    useMemo(
      () => {
        const query =
          search
            .trim()
            .toLocaleLowerCase(
              "id-ID",
            );

        return categories.filter(
          (category) => {
            if (
              status !== "ALL" &&
              category.status !== status
            ) {
              return false;
            }

            if (!query) {
              return true;
            }

            return [
              category.code ?? "",
              category.name,
              category.description ?? "",
            ].some(
              (value) =>
                value
                  .toLocaleLowerCase(
                    "id-ID",
                  )
                  .includes(query),
            );
          },
        );
      },
      [
        categories,
        search,
        status,
      ],
    );

  function openCreate() {
    setEditingCategory(null);
    setForm(emptyForm);
    setSaveError(null);
    setSuccess(null);
    setEditorOpen(true);
  }

  function openEdit(
    category:
      CatalogCategory,
  ) {
    setEditingCategory(
      category,
    );

    setForm({
      code:
        category.code ?? "",
      name:
        category.name,
      description:
        category.description ?? "",
    });

    setSaveError(null);
    setSuccess(null);
    setEditorOpen(true);
  }

  function closeEditor() {
    if (saving) {
      return;
    }

    setEditorOpen(false);
    setSaveError(null);
  }

  async function saveCategory() {
    setSaving(true);
    setSaveError(null);

    try {
      const payload:
        CatalogCategoryCreatePayload = {
          code:
            form.code.trim()
              ? form.code
                  .trim()
                  .toUpperCase()
              : null,
          name:
            form.name.trim(),
          description:
            form.description.trim()
              ? form.description.trim()
              : null,
        };

      if (editingCategory) {
        await updateCatalogCategory(
          editingCategory.id,
          payload,
        );

        setSuccess(
          "Kategori berhasil diperbarui.",
        );
      } else {
        await createCatalogCategory(
          payload,
        );

        setSuccess(
          "Kategori berhasil ditambahkan.",
        );
      }

      await loadCategories();

      setEditorOpen(false);
      setEditingCategory(null);
      setForm(emptyForm);
    } catch (caught) {
      setSaveError(caught);
    } finally {
      setSaving(false);
    }
  }

  async function toggleStatus(
    category:
      CatalogCategory,
  ) {
    setSaveError(null);
    setSuccess(null);

    try {
      const nextStatus:
        CatalogStatus =
          category.status ===
          "ACTIVE"
            ? "INACTIVE"
            : "ACTIVE";

      await updateCatalogCategory(
        category.id,
        {
          status:
            nextStatus,
        },
      );

      setSuccess(
        nextStatus === "ACTIVE"
          ? "Kategori berhasil diaktifkan."
          : "Kategori berhasil dinonaktifkan.",
      );

      await loadCategories();
    } catch (caught) {
      setSaveError(caught);
    }
  }

  return (
    <TenantShell>
      <section
        className={
          styles.page
        }
      >
        <ModuleHero
          eyebrow="Master Data"
          title={
            moduleDef.label
          }
          description={
            moduleDef.description
          }
          icon={
            ModuleIcon
          }
          tone={
            moduleDef.tone
          }
          insightTitle={
            moduleDef.insight.title
          }
          insightDescription={
            moduleDef.insight.description
          }
          actions={
            <button
              type="button"
              className={
                styles.primaryAction
              }
              onClick={
                openCreate
              }
            >
              <Plus
                size={18}
              />
              Tambah Kategori
            </button>
          }
        />

        <section
          className={
            styles.toolbar
          }
        >
          <label
            className={
              styles.searchBox
            }
          >
            <Search
              size={18}
            />

            <input
              type="search"
              value={search}
              onChange={(
                event,
              ) =>
                setSearch(
                  event
                    .target
                    .value,
                )
              }
              placeholder="Cari nama, kode, deskripsi..."
              aria-label="Cari kategori"
            />
          </label>

          <div
            className={
              styles.filters
            }
          >
            {(
              [
                [
                  "ALL",
                  "Semua",
                ],
                [
                  "ACTIVE",
                  "Aktif",
                ],
                [
                  "INACTIVE",
                  "Nonaktif",
                ],
              ] as const
            ).map(
              ([
                value,
                label,
              ]) => (
                <button
                  type="button"
                  key={value}
                  className={
                    status === value
                      ? styles.filterActive
                      : styles.filter
                  }
                  onClick={() =>
                    setStatus(
                      value,
                    )
                  }
                >
                  {label}
                </button>
              ),
            )}
          </div>
        </section>

        <div
          className={
            styles.resultMeta
          }
        >
          {loading
            ? "Memuat kategori..."
            : `${visibleCategories.length} kategori`}
        </div>

        {success ? (
          <ActionFeedback
            tone="success"
            placement="viewport"
            title="Berhasil"
            message={
              success
            }
          />
        ) : null}

        {saveError &&
        !editorOpen ? (
          <ActionFeedback
            tone="error"
            title="Perubahan belum dapat disimpan"
            message={
              apiErrorMessage(
                saveError,
              )
            }
            requestId={
              apiRequestId(
                saveError,
              )
            }
          />
        ) : null}

        {error ? (
          <ActionFeedback
            tone="error"
            title="Kategori belum dapat dimuat"
            message={
              apiErrorMessage(
                error,
              )
            }
            requestId={
              apiRequestId(
                error,
              )
            }
          />
        ) : null}

        {loading ? (
          <div
            className={
              styles.skeletonList
            }
          >
            {[1, 2, 3].map(
              (item) => (
                <div
                  key={item}
                  className={
                    styles.skeletonCard
                  }
                />
              ),
            )}
          </div>
        ) : null}

        {!loading &&
        !error &&
        visibleCategories.length ===
          0 ? (
          <section
            className={
              styles.emptyState
            }
          >
            <div
              className={
                styles.emptyIcon
              }
            >
              <Tags
                size={26}
              />
            </div>

            <h2>
              Belum ada kategori
            </h2>

            <p>
              Buat kategori untuk
              mengelompokkan barang dan
              jasa agar lebih mudah
              ditemukan.
            </p>

            <button
              type="button"
              className={
                styles.primaryAction
              }
              onClick={
                openCreate
              }
            >
              <Plus
                size={18}
              />
              Tambah Kategori
            </button>
          </section>
        ) : null}

        {!loading &&
        !error &&
        visibleCategories.length >
          0 ? (
          <>
            <div
              className={
                styles.mobileList
              }
            >
              {visibleCategories.map(
                (category) => (
                  <article
                    key={
                      category.id
                    }
                    className={
                      styles.categoryCard
                    }
                  >
                    <div
                      className={
                        styles.categoryIcon
                      }
                    >
                      <Tags
                        size={19}
                      />
                    </div>

                    <div
                      className={
                        styles.categoryMain
                      }
                    >
                      <strong>
                        {category.name}
                      </strong>

                      <span
                        className={
                          styles.categoryMeta
                        }
                      >
                        {category.code ??
                          "Tanpa kode"}
                      </span>

                      {category.description ? (
                        <span
                          className={
                            styles.description
                          }
                        >
                          {
                            category.description
                          }
                        </span>
                      ) : null}

                      <span
                        className={
                          category.status ===
                          "ACTIVE"
                            ? styles.statusActive
                            : styles.statusInactive
                        }
                      >
                        {
                          category.status_label
                        }
                      </span>
                    </div>

                    <button
                      type="button"
                      className={
                        styles.iconButton
                      }
                      onClick={() =>
                        openEdit(
                          category,
                        )
                      }
                      aria-label={`Ubah ${category.name}`}
                    >
                      <Pencil
                        size={17}
                      />
                    </button>
                  </article>
                ),
              )}
            </div>

            <div
              className={
                styles.desktopTableWrap
              }
            >
              <table
                className={
                  styles.table
                }
              >
                <thead>
                  <tr>
                    <th>Kategori</th>
                    <th>Kode</th>
                    <th>Status</th>
                    <th>Aksi</th>
                  </tr>
                </thead>

                <tbody>
                  {visibleCategories.map(
                    (category) => (
                      <tr
                        key={
                          category.id
                        }
                      >
                        <td>
                          <strong>
                            {category.name}
                          </strong>

                          {category.description ? (
                            <span
                              className={
                                styles.tableDescription
                              }
                            >
                              {
                                category.description
                              }
                            </span>
                          ) : null}
                        </td>

                        <td>
                          {category.code ??
                            "—"}
                        </td>

                        <td>
                          <span
                            className={
                              category.status ===
                              "ACTIVE"
                                ? styles.statusActive
                                : styles.statusInactive
                            }
                          >
                            {
                              category.status_label
                            }
                          </span>
                        </td>

                        <td>
                          <button
                            type="button"
                            className={
                              styles.iconButton
                            }
                            onClick={() =>
                              openEdit(
                                category,
                              )
                            }
                            aria-label={`Ubah ${category.name}`}
                          >
                            <Pencil
                              size={17}
                            />
                          </button>
                        </td>
                      </tr>
                    ),
                  )}
                </tbody>
              </table>
            </div>
          </>
        ) : null}

        <ModuleFooterCard
          title="Kategori membuat katalog lebih teratur"
          description="Gunakan kategori yang konsisten agar pencarian barang dan jasa lebih cepat."
        />
      </section>

      {editorOpen ? (
        <div
          className={
            styles.modalBackdrop
          }
          role="presentation"
        >
          <section
            className={
              styles.modal
            }
            role="dialog"
            aria-modal="true"
            aria-label={
              editingCategory
                ? "Ubah kategori"
                : "Tambah kategori"
            }
          >
            <header
              className={
                styles.modalHeader
              }
            >
              <div
                className={
                  styles.modalHeading
                }
              >
                <div
                  className={
                    styles.modalHeadingIcon
                  }
                  aria-hidden="true"
                >
                  <Tags
                    size={21}
                    strokeWidth={2}
                  />
                </div>

                <div>
                  <span>
                    Master Data
                  </span>

                  <h2>
                    {editingCategory
                      ? "Ubah Kategori"
                      : "Tambah Kategori"}
                  </h2>

                  <p
                    className={
                      styles.modalLead
                    }
                  >
                    {editingCategory
                      ? "Perbarui informasi kategori katalog Anda."
                      : "Buat kelompok agar barang dan jasa lebih mudah dikelola."}
                  </p>
                </div>
              </div>

              <button
                type="button"
                className={
                  styles.iconButton
                }
                onClick={
                  closeEditor
                }
                aria-label="Tutup"
              >
                <X
                  size={18}
                />
              </button>
            </header>

            <div
              className={
                styles.formGrid
              }
            >
              <label>
                <span
                  className={
                    styles.fieldLabel
                  }
                >
                  Kode

                  <small>
                    Opsional
                  </small>
                </span>

                <input
                  value={
                    form.code
                  }
                  onChange={(
                    event,
                  ) =>
                    setForm(
                      (
                        current,
                      ) => ({
                        ...current,
                        code:
                          event
                            .target
                            .value,
                      }),
                    )
                  }
                  placeholder="Contoh: RUNNING_TEXT"
                  maxLength={80}
                />
              </label>

              <label>
                <span
                  className={
                    styles.fieldLabel
                  }
                >
                  Nama

                  <strong>
                    Wajib
                  </strong>
                </span>

                <input
                  value={
                    form.name
                  }
                  onChange={(
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
                  placeholder="Contoh: Running Text"
                  maxLength={160}
                />
              </label>

              <label
                className={
                  styles.fullField
                }
              >
                <span
                  className={
                    styles.fieldLabel
                  }
                >
                  Deskripsi

                  <small>
                    Opsional
                  </small>
                </span>

                <textarea
                  value={
                    form.description
                  }
                  onChange={(
                    event,
                  ) =>
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
                  placeholder="Keterangan singkat kategori"
                  maxLength={500}
                  rows={4}
                />
              </label>
            </div>

            {saveError ? (
              <ActionFeedback
                tone="error"
                title="Perubahan belum dapat disimpan"
                message={
                  apiErrorMessage(
                    saveError,
                  )
                }
                requestId={
                  apiRequestId(
                    saveError,
                  )
                }
              />
            ) : null}

            {editingCategory ? (
              <section
                className={
                  styles.statusPanel
                }
              >
                <div>
                  <strong>
                    Status Kategori
                  </strong>

                  <span>
                    {
                      editingCategory.status_label
                    }
                  </span>
                </div>

                <button
                  type="button"
                  className={
                    styles.secondaryAction
                  }
                  disabled={
                    saving
                  }
                  onClick={() =>
                    toggleStatus(
                      editingCategory,
                    )
                  }
                >
                  {editingCategory.status ===
                  "ACTIVE"
                    ? "Nonaktifkan"
                    : "Aktifkan"}
                </button>
              </section>
            ) : null}

            <footer
              className={
                styles.modalActions
              }
            >
              <button
                type="button"
                className={
                  styles.secondaryAction
                }
                disabled={
                  saving
                }
                onClick={
                  closeEditor
                }
              >
                Batal
              </button>

              <button
                type="button"
                className={
                  styles.primaryAction
                }
                disabled={
                  saving ||
                  !form.name.trim()
                }
                onClick={
                  saveCategory
                }
              >
                {saving
                  ? "Menyimpan..."
                  : editingCategory
                    ? "Simpan Perubahan"
                    : "Simpan Kategori"}
              </button>
            </footer>
          </section>
        </div>
      ) : null}
    </TenantShell>
  );
}
