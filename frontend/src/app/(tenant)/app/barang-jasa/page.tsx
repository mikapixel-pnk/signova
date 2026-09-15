"use client";

import {
  Package,
  Pencil,
  Plus,
  Search,
  Wrench,
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
  createCatalogItem,
  listCatalogCategories,
  listCatalogItems,
  listCatalogUnits,
  updateCatalogItem,
} from "@/lib/catalog/service";

import {
  getModule,
} from "@/lib/module/registry";

import type {
  CatalogCategory,
  CatalogItem,
  CatalogItemCreatePayload,
  CatalogItemType,
  CatalogStatus,
  CatalogUnit,
} from "@/types/catalog";

import styles from "./catalog.module.css";

type StatusFilter =
  | "ALL"
  | CatalogStatus;

type TypeFilter =
  | "ALL"
  | CatalogItemType;

type CatalogFormState = {
  type: CatalogItemType;
  autoCode: boolean;
  code: string;
  name: string;
  categoryId: string;
  unitId: string;
  basePrice: string;
  description: string;
};

const emptyForm:
  CatalogFormState = {
    type: "PRODUCT",
    autoCode: true,
    code: "",
    name: "",
    categoryId: "",
    unitId: "",
    basePrice: "",
    description: "",
  };

function rupiah(
  value:
    | number
    | string,
): string {
  const numeric =
    Number(value) || 0;

  return new Intl.NumberFormat(
    "id-ID",
    {
      style: "currency",
      currency: "IDR",
      maximumFractionDigits: 0,
    },
  ).format(numeric);
}

function digitsOnly(
  value: string,
): string {
  return value.replace(
    /\D/g,
    "",
  );
}

function displayPriceInput(
  value: string,
): string {
  if (!value) {
    return "";
  }

  return new Intl.NumberFormat(
    "id-ID",
  ).format(
    Number(value) || 0,
  );
}

function categoryPrefix(
  category:
    CatalogCategory | undefined,
): string {
  const source =
    category?.code ?? "";

  const normalized =
    source
      .toUpperCase()
      .replace(
        /[^A-Z0-9]/g,
        "",
      );

  return (
    normalized.slice(
      0,
      3,
    ) || "GEN"
  );
}

export default function CatalogPage() {
  const moduleDef =
    getModule("catalog");

  const ModuleIcon =
    moduleDef.icon;

  const [
    items,
    setItems,
  ] = useState<
    CatalogItem[]
  >([]);

  const [
    categories,
    setCategories,
  ] = useState<
    CatalogCategory[]
  >([]);

  const [
    units,
    setUnits,
  ] = useState<
    CatalogUnit[]
  >([]);

  const [
    loading,
    setLoading,
  ] = useState(true);

  const [
    referenceLoading,
    setReferenceLoading,
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
    statusFilter,
    setStatusFilter,
  ] = useState<
    StatusFilter
  >("ALL");

  const [
    typeFilter,
    setTypeFilter,
  ] = useState<
    TypeFilter
  >("ALL");

  const [
    editorOpen,
    setEditorOpen,
  ] = useState(false);

  const [
    editingItem,
    setEditingItem,
  ] = useState<
    CatalogItem | null
  >(null);

  const [
    form,
    setForm,
  ] = useState<
    CatalogFormState
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

  const loadItems =
    useCallback(
      async (
        silent = false,
      ) => {
        if (!silent) {
          setLoading(true);
        }

        setError(null);

        try {
          const response =
            await listCatalogItems({
              search:
                search.trim() ||
                undefined,
              status:
                statusFilter ===
                "ALL"
                  ? undefined
                  : statusFilter,
              type:
                typeFilter ===
                "ALL"
                  ? undefined
                  : typeFilter,
            });

          setItems(
            response.data,
          );
        } catch (caught) {
          setError(caught);
        } finally {
          if (!silent) {
            setLoading(false);
          }
        }
      },
      [
        search,
        statusFilter,
        typeFilter,
      ],
    );

  useEffect(() => {
    const timeoutId =
      window.setTimeout(
        () => {
          void loadItems();
        },
        250,
      );

    return () => {
      window.clearTimeout(
        timeoutId,
      );
    };
  }, [loadItems]);

  useEffect(() => {
    let active = true;

    Promise.all([
      listCatalogCategories(),
      listCatalogUnits(),
    ])
      .then(
        ([
          categoryResponse,
          unitResponse,
        ]) => {
          if (!active) {
            return;
          }

          setCategories(
            categoryResponse.data,
          );

          setUnits(
            unitResponse.data,
          );
        },
      )
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

        setReferenceLoading(false);
      });

    return () => {
      active = false;
    };
  }, []);

  const categoryMap =
    useMemo(
      () =>
        new Map(
          categories.map(
            (category) => [
              category.id,
              category,
            ],
          ),
        ),
      [categories],
    );

  const unitMap =
    useMemo(
      () =>
        new Map(
          units.map(
            (unit) => [
              unit.id,
              unit,
            ],
          ),
        ),
      [units],
    );

  const activeCategories =
    useMemo(
      () =>
        categories.filter(
          (category) =>
            category.status ===
            "ACTIVE",
        ),
      [categories],
    );

  const activeUnits =
    useMemo(
      () =>
        units.filter(
          (unit) =>
            unit.status ===
            "ACTIVE",
        ),
      [units],
    );

  const selectedCategory =
    categoryMap.get(
      form.categoryId,
    );

  const generatedCodePreview =
    `${
      form.type ===
      "SERVICE"
        ? "JSA"
        : "BRG"
    }-${
      categoryPrefix(
        selectedCategory,
      )
    }-••••••`;

  function openCreate() {
    setEditingItem(null);
    setForm(emptyForm);
    setSaveError(null);
    setSuccess(null);
    setEditorOpen(true);
  }

  function openEdit(
    item: CatalogItem,
  ) {
    setEditingItem(item);

    setForm({
      type: item.type,
      autoCode: false,
      code:
        item.code ?? "",
      name:
        item.name,
      categoryId:
        item.category_id ?? "",
      unitId:
        item.unit_id ?? "",
      basePrice:
        String(
          Math.round(
            Number(
              item.base_price,
            ) || 0,
          ),
        ),
      description:
        item.description ?? "",
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

  async function saveItem() {
    setSaving(true);
    setSaveError(null);

    try {
      const payload:
        CatalogItemCreatePayload = {
          type:
            form.type,
          code:
            form.autoCode
              ? null
              : form.code.trim() ||
                null,
          name:
            form.name.trim(),
          category_id:
            form.categoryId ||
            null,
          unit_id:
            form.unitId ||
            null,
          description:
            form.description
              .trim() ||
            null,
          pricing_method:
            editingItem
              ? editingItem
                  .pricing_method
              : "STANDARD",
          base_price:
            Number(
              form.basePrice,
            ) || 0,
          currency:
            editingItem
              ? editingItem
                  .currency
              : "IDR",
          pricing_config:
            editingItem
              ? editingItem
                  .pricing_config
              : null,
        };

      if (editingItem) {
        await updateCatalogItem(
          editingItem.id,
          payload,
        );

        setSuccess(
          "Barang atau jasa berhasil diperbarui.",
        );
      } else {
        await createCatalogItem(
          payload,
        );

        setSuccess(
          "Barang atau jasa berhasil ditambahkan.",
        );
      }

      setEditorOpen(false);
      setEditingItem(null);
      setForm(emptyForm);

      void loadItems(true);
    } catch (caught) {
      setSaveError(caught);
    } finally {
      setSaving(false);
    }
  }

  async function toggleStatus(
    item: CatalogItem,
  ) {
    setSaveError(null);
    setSuccess(null);

    try {
      const nextStatus:
        CatalogStatus =
          item.status ===
          "ACTIVE"
            ? "INACTIVE"
            : "ACTIVE";

      await updateCatalogItem(
        item.id,
        {
          status:
            nextStatus,
        },
      );

      setSuccess(
        nextStatus === "ACTIVE"
          ? "Barang atau jasa berhasil diaktifkan."
          : "Barang atau jasa berhasil dinonaktifkan.",
      );

      void loadItems(true);
    } catch (caught) {
      setSaveError(caught);
    }
  }

  const editingCategory =
    editingItem?.category_id
      ? categoryMap.get(
          editingItem.category_id,
        )
      : undefined;

  const editingUnit =
    editingItem?.unit_id
      ? unitMap.get(
          editingItem.unit_id,
        )
      : undefined;

  const availableCategories =
    editingCategory &&
    editingCategory.status ===
      "INACTIVE"
      ? [
          editingCategory,
          ...activeCategories.filter(
            (category) =>
              category.id !==
              editingCategory.id,
          ),
        ]
      : activeCategories;

  const availableUnits =
    editingUnit &&
    editingUnit.status ===
      "INACTIVE"
      ? [
          editingUnit,
          ...activeUnits.filter(
            (unit) =>
              unit.id !==
              editingUnit.id,
          ),
        ]
      : activeUnits;

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
              Tambah Barang & Jasa
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
              placeholder="Cari nama atau kode..."
              aria-label="Cari barang dan jasa"
            />
          </label>

          <div
            className={
              styles.filterStack
            }
          >
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
                    "PRODUCT",
                    "Barang",
                  ],
                  [
                    "SERVICE",
                    "Jasa",
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
                      typeFilter ===
                      value
                        ? styles.filterActive
                        : styles.filter
                    }
                    onClick={() =>
                      setTypeFilter(
                        value,
                      )
                    }
                  >
                    {label}
                  </button>
                ),
              )}
            </div>

            <div
              className={
                styles.filters
              }
            >
              {(
                [
                  [
                    "ALL",
                    "Semua Status",
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
                      statusFilter ===
                      value
                        ? styles.filterActive
                        : styles.filter
                    }
                    onClick={() =>
                      setStatusFilter(
                        value,
                      )
                    }
                  >
                    {label}
                  </button>
                ),
              )}
            </div>
          </div>
        </section>

        <div
          className={
            styles.resultMeta
          }
        >
          {loading
            ? "Memuat barang & jasa..."
            : `${items.length} data pada halaman ini`}
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
            title="Barang & Jasa belum dapat dimuat"
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
        items.length ===
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
              <Package
                size={26}
              />
            </div>

            <h2>
              Belum ada barang atau jasa
            </h2>

            <p>
              Tambahkan barang atau jasa agar dapat
              digunakan kembali saat membuat penawaran
              dan tagihan.
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
              Tambah Barang & Jasa
            </button>
          </section>
        ) : null}

        {!loading &&
        !error &&
        items.length >
          0 ? (
          <>
            <div
              className={
                styles.mobileList
              }
            >
              {items.map(
                (item) => {
                  const category =
                    item.category_id
                      ? categoryMap.get(
                          item.category_id,
                        )
                      : undefined;

                  const unit =
                    item.unit_id
                      ? unitMap.get(
                          item.unit_id,
                        )
                      : undefined;

                  const ItemIcon =
                    item.type ===
                    "SERVICE"
                      ? Wrench
                      : Package;

                  return (
                    <article
                      key={
                        item.id
                      }
                      className={
                        styles.itemCard
                      }
                    >
                      <div
                        className={
                          styles.itemIcon
                        }
                        data-type={
                          item.type
                        }
                      >
                        <ItemIcon
                          size={19}
                        />
                      </div>

                      <div
                        className={
                          styles.itemMain
                        }
                      >
                        <strong>
                          {item.name}
                        </strong>

                        <span
                          className={
                            styles.itemCode
                          }
                        >
                          {item.code ??
                            "Tanpa kode"}
                        </span>

                        <span
                          className={
                            styles.itemMeta
                          }
                        >
                          {item.type_label}
                          {category
                            ? ` • ${category.name}`
                            : ""}
                          {unit
                            ? ` • ${unit.symbol ?? unit.name}`
                            : ""}
                        </span>

                        <div
                          className={
                            styles.itemBottom
                          }
                        >
                          <strong
                            className={
                              styles.price
                            }
                          >
                            {rupiah(
                              item.base_price,
                            )}
                          </strong>

                          <span
                            className={
                              item.status ===
                              "ACTIVE"
                                ? styles.statusActive
                                : styles.statusInactive
                            }
                          >
                            {
                              item.status_label
                            }
                          </span>
                        </div>
                      </div>

                      <button
                        type="button"
                        className={
                          styles.iconButton
                        }
                        onClick={() =>
                          openEdit(
                            item,
                          )
                        }
                        aria-label={`Ubah ${item.name}`}
                      >
                        <Pencil
                          size={17}
                        />
                      </button>
                    </article>
                  );
                },
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
                    <th>Nama</th>
                    <th>Tipe</th>
                    <th>Kategori</th>
                    <th>Satuan</th>
                    <th>Harga</th>
                    <th>Status</th>
                    <th>Aksi</th>
                  </tr>
                </thead>

                <tbody>
                  {items.map(
                    (item) => {
                      const category =
                        item.category_id
                          ? categoryMap.get(
                              item.category_id,
                            )
                          : undefined;

                      const unit =
                        item.unit_id
                          ? unitMap.get(
                              item.unit_id,
                            )
                          : undefined;

                      return (
                        <tr
                          key={
                            item.id
                          }
                        >
                          <td>
                            <strong>
                              {item.name}
                            </strong>

                            <span
                              className={
                                styles.tableSub
                              }
                            >
                              {item.code ??
                                "Tanpa kode"}
                            </span>
                          </td>

                          <td>
                            {
                              item.type_label
                            }
                          </td>

                          <td>
                            {category?.name ??
                              "—"}
                          </td>

                          <td>
                            {unit?.symbol ??
                              unit?.name ??
                              "—"}
                          </td>

                          <td>
                            {rupiah(
                              item.base_price,
                            )}
                          </td>

                          <td>
                            <span
                              className={
                                item.status ===
                                "ACTIVE"
                                  ? styles.statusActive
                                  : styles.statusInactive
                              }
                            >
                              {
                                item.status_label
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
                                  item,
                                )
                              }
                              aria-label={`Ubah ${item.name}`}
                            >
                              <Pencil
                                size={17}
                              />
                            </button>
                          </td>
                        </tr>
                      );
                    },
                  )}
                </tbody>
              </table>
            </div>
          </>
        ) : null}

        <ModuleFooterCard
          title="Satu katalog untuk transaksi yang lebih cepat"
          description="Barang, jasa, kategori, satuan, dan harga acuan dapat digunakan kembali saat membuat dokumen penjualan."
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
              editingItem
                ? "Ubah barang atau jasa"
                : "Tambah barang atau jasa"
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
                  {form.type ===
                  "SERVICE" ? (
                    <Wrench
                      size={21}
                    />
                  ) : (
                    <Package
                      size={21}
                    />
                  )}
                </div>

                <div>
                  <span>
                    Master Data
                  </span>

                  <h2>
                    {editingItem
                      ? "Ubah Barang & Jasa"
                      : "Tambah Barang & Jasa"}
                  </h2>

                  <p
                    className={
                      styles.modalLead
                    }
                  >
                    {editingItem
                      ? "Perbarui informasi katalog bisnis Anda."
                      : "Tambahkan produk atau layanan yang ditawarkan bisnis Anda."}
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
              <fieldset
                className={
                  styles.typeField
                }
              >
                <legend>
                  Jenis
                </legend>

                <div
                  className={
                    styles.segmented
                  }
                >
                  <button
                    type="button"
                    className={
                      form.type ===
                      "PRODUCT"
                        ? styles.segmentActive
                        : styles.segment
                    }
                    onClick={() =>
                      setForm(
                        (
                          current,
                        ) => ({
                          ...current,
                          type:
                            "PRODUCT",
                        }),
                      )
                    }
                  >
                    <Package
                      size={17}
                    />
                    Barang
                  </button>

                  <button
                    type="button"
                    className={
                      form.type ===
                      "SERVICE"
                        ? styles.segmentActive
                        : styles.segment
                    }
                    onClick={() =>
                      setForm(
                        (
                          current,
                        ) => ({
                          ...current,
                          type:
                            "SERVICE",
                        }),
                      )
                    }
                  >
                    <Wrench
                      size={17}
                    />
                    Jasa
                  </button>
                </div>
              </fieldset>

              {!editingItem ? (
                <section
                  className={
                    styles.autoCodePanel
                  }
                >
                  <div
                    className={
                      styles.autoCodeHeader
                    }
                  >
                    <div>
                      <strong>
                        Kode Otomatis
                      </strong>

                      <span>
                        SIGNOVA dapat membuat kode berdasarkan jenis dan kategori.
                      </span>
                    </div>

                    <button
                      type="button"
                      role="switch"
                      aria-checked={
                        form.autoCode
                      }
                      className={
                        form.autoCode
                          ? styles.switchOn
                          : styles.switchOff
                      }
                      onClick={() =>
                        setForm(
                          (
                            current,
                          ) => ({
                            ...current,
                            autoCode:
                              !current.autoCode,
                          }),
                        )
                      }
                    >
                      <span />
                    </button>
                  </div>

                  {form.autoCode ? (
                    <div
                      className={
                        styles.codePreview
                      }
                    >
                      <small>
                        Preview format
                      </small>

                      <strong>
                        {
                          generatedCodePreview
                        }
                      </strong>

                      <span>
                        Nomor akhir dibuat saat data disimpan.
                      </span>
                    </div>
                  ) : null}
                </section>
              ) : null}

              {!form.autoCode ||
              editingItem ? (
                <label>
                  <span
                    className={
                      styles.fieldLabel
                    }
                  >
                    Kode

                    {!editingItem ? (
                      <small>
                        Opsional
                      </small>
                    ) : null}
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
                              .value
                              .toUpperCase(),
                        }),
                      )
                    }
                    placeholder="Contoh: NB-ACR-001"
                    maxLength={100}
                  />

                  {!editingItem ? (
                    <small
                      className={
                        styles.fieldHint
                      }
                    >
                      Kosongkan untuk tetap dibuat otomatis.
                    </small>
                  ) : null}
                </label>
              ) : null}

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
                  placeholder={
                    form.type ===
                    "PRODUCT"
                      ? "Contoh: Neon Box Acrylic"
                      : "Contoh: Jasa Pasang Neon"
                  }
                  maxLength={190}
                />
              </label>

              <label>
                <span
                  className={
                    styles.fieldLabel
                  }
                >
                  Kategori
                  <small>
                    Opsional
                  </small>
                </span>

                <select
                  value={
                    form.categoryId
                  }
                  onChange={(
                    event,
                  ) =>
                    setForm(
                      (
                        current,
                      ) => ({
                        ...current,
                        categoryId:
                          event
                            .target
                            .value,
                      }),
                    )
                  }
                  disabled={
                    referenceLoading
                  }
                >
                  <option value="">
                    Tanpa kategori
                  </option>

                  {availableCategories.map(
                    (category) => (
                      <option
                        key={
                          category.id
                        }
                        value={
                          category.id
                        }
                      >
                        {category.name}
                        {category.status ===
                        "INACTIVE"
                          ? " — Nonaktif"
                          : ""}
                      </option>
                    ),
                  )}
                </select>
              </label>

              <label>
                <span
                  className={
                    styles.fieldLabel
                  }
                >
                  Satuan
                  <small>
                    Opsional
                  </small>
                </span>

                <select
                  value={
                    form.unitId
                  }
                  onChange={(
                    event,
                  ) =>
                    setForm(
                      (
                        current,
                      ) => ({
                        ...current,
                        unitId:
                          event
                            .target
                            .value,
                      }),
                    )
                  }
                  disabled={
                    referenceLoading
                  }
                >
                  <option value="">
                    Tanpa satuan
                  </option>

                  {availableUnits.map(
                    (unit) => (
                      <option
                        key={
                          unit.id
                        }
                        value={
                          unit.id
                        }
                      >
                        {unit.name}
                        {unit.symbol
                          ? ` (${unit.symbol})`
                          : ""}
                        {unit.status ===
                        "INACTIVE"
                          ? " — Nonaktif"
                          : ""}
                      </option>
                    ),
                  )}
                </select>
              </label>

              <label>
                <span
                  className={
                    styles.fieldLabel
                  }
                >
                  Harga Jual
                </span>

                <div
                  className={
                    styles.moneyInput
                  }
                >
                  <span>
                    Rp
                  </span>

                  <input
                    inputMode="numeric"
                    value={
                      displayPriceInput(
                        form.basePrice,
                      )
                    }
                    onChange={(
                      event,
                    ) =>
                      setForm(
                        (
                          current,
                        ) => ({
                          ...current,
                          basePrice:
                            digitsOnly(
                              event
                                .target
                                .value,
                            ),
                        }),
                      )
                    }
                    placeholder="0"
                  />
                </div>
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
                  placeholder="Keterangan singkat barang atau jasa"
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

            {editingItem ? (
              <section
                className={
                  styles.statusPanel
                }
              >
                <div>
                  <strong>
                    Status Barang & Jasa
                  </strong>

                  <span>
                    {
                      editingItem.status_label
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
                      editingItem,
                    )
                  }
                >
                  {editingItem.status ===
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
                  saveItem
                }
              >
                {saving
                  ? "Menyimpan..."
                  : editingItem
                    ? "Simpan Perubahan"
                    : form.type ===
                        "SERVICE"
                      ? "Simpan Jasa"
                      : "Simpan Barang"}
              </button>
            </footer>
          </section>
        </div>
      ) : null}
    </TenantShell>
  );
}
