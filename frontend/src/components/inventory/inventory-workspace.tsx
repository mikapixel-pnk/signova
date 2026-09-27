"use client";

import {
  Boxes,
  Package,
  PackageCheck,
  PackageX,
  Pencil,
  Plus,
  RefreshCw,
  Search,
  ShieldCheck,
  Tags,
  Warehouse as WarehouseIcon,
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
  ModuleHero,
} from "@/components/module/module-hero";

import {
  InventoryMaterialEditor,
} from "@/components/inventory/inventory-material-editor";

import {
  InventoryCategoryEditor,
  WarehouseEditor,
} from "@/components/inventory/inventory-reference-editor";

import {
  apiErrorMessage,
  apiRequestId,
} from "@/lib/api/error-message";

import {
  getActiveCapabilities,
} from "@/lib/auth/active-capabilities-service";

import {
  listCatalogUnits,
} from "@/lib/catalog/service";

import {
  inventoryTypeLabels,
  stockTrackingLabels,
} from "@/lib/inventory/labels";

import {
  listInventoryCategories,
  listMaterials,
  listWarehouses,
} from "@/lib/inventory/service";

import type {
  CatalogUnit,
} from "@/types/catalog";

import type {
  InventoryCategory,
  InventoryStatus,
  Material,
  Warehouse,
} from "@/types/inventory";

import styles from "./inventory-workspace.module.css";


export type InventorySection =
  | "MATERIALS"
  | "CATEGORIES"
  | "WAREHOUSES";


type StatusFilter =
  | "ALL"
  | InventoryStatus;


type InventoryEditor =
  | {
      kind:
        "MATERIAL";
      value:
        | Material
        | null;
    }
  | {
      kind:
        "CATEGORY";
      value:
        | InventoryCategory
        | null;
    }
  | {
      kind:
        "WAREHOUSE";
      value:
        | Warehouse
        | null;
    }
  | null;


function normalized(
  value:
    | string
    | null
    | undefined,
): string {
  return (
    value ?? ""
  )
    .trim()
    .toLocaleLowerCase(
      "id-ID",
    );
}


function statusMatches(
  status:
    InventoryStatus,
  filter:
    StatusFilter,
): boolean {
  return (
    filter === "ALL" ||
    status === filter
  );
}


function stockPolicyLabel(
  material:
    Material,
): string {
  return stockTrackingLabels[
    material.stock_tracking
  ];
}


function thresholdSummary(
  material:
    Material,
): string {
  if (
    material.stock_tracking !==
    "TRACKED"
  ) {
    return (
      "Tidak memakai batas stok"
    );
  }

  const minimum =
    material.minimum_stock ??
    "—";

  const reorder =
    material.reorder_point ??
    "—";

  const maximum =
    material.maximum_stock ??
    "—";

  return (
    `Min ${minimum} · `
    + `Pesan ulang ${reorder} · `
    + `Maks ${maximum}`
  );
}


export function InventoryWorkspace({
  section,
}: {
  section:
    InventorySection;
}) {
  const activeTab =
    section;

  const [
    search,
    setSearch,
  ] = useState("");

  const [
    statusFilter,
    setStatusFilter,
  ] = useState<StatusFilter>(
    "ALL",
  );

  const [
    materials,
    setMaterials,
  ] = useState<Material[]>([]);

  const [
    categories,
    setCategories,
  ] = useState<
    InventoryCategory[]
  >([]);

  const [
    warehouses,
    setWarehouses,
  ] = useState<Warehouse[]>([]);

  const [
    units,
    setUnits,
  ] = useState<CatalogUnit[]>(
    [],
  );

  const [
    canView,
    setCanView,
  ] = useState<
    boolean | null
  >(null);

  const [
    canManage,
    setCanManage,
  ] = useState(false);

  const [
    canViewCatalog,
    setCanViewCatalog,
  ] = useState(false);

  const [
    editor,
    setEditor,
  ] = useState<
    InventoryEditor
  >(null);

  const [
    successMessage,
    setSuccessMessage,
  ] = useState<
    string | null
  >(null);

  const [
    loading,
    setLoading,
  ] = useState(true);

  const [
    error,
    setError,
  ] = useState<unknown>(null);

  const [
    reloadToken,
    setReloadToken,
  ] = useState(0);


  useEffect(() => {
    let cancelled = false;

    void getActiveCapabilities()
      .then((response) => {
        if (cancelled) {
          return;
        }

        const capabilityCodes =
          response.data
            .capability_codes;

        const allowed =
          capabilityCodes.includes(
            "inventory.view",
          );

        setCanView(
          allowed,
        );

        setCanManage(
          capabilityCodes.includes(
            "inventory.master.manage",
          ),
        );

        setCanViewCatalog(
          capabilityCodes.includes(
            "catalog.view",
          ),
        );

        if (!allowed) {
          setLoading(false);
        }
      })
      .catch((caught) => {
        if (cancelled) {
          return;
        }

        setCanView(false);
        setCanManage(false);
        setCanViewCatalog(false);
        setError(caught);
        setLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, []);


  useEffect(() => {
    if (canView !== true) {
      return;
    }

    let cancelled = false;

    const unitRequest =
      canViewCatalog
        ? listCatalogUnits()
            .catch(() => null)
        : Promise.resolve(null);

    void Promise.all([
      listMaterials(),
      listInventoryCategories(),
      listWarehouses(),
      unitRequest,
    ])
      .then(([
        materialResponse,
        categoryResponse,
        warehouseResponse,
        unitResponse,
      ]) => {
        if (cancelled) {
          return;
        }

        setMaterials(
          materialResponse.data,
        );

        setCategories(
          categoryResponse.data,
        );

        setWarehouses(
          warehouseResponse.data,
        );

        setUnits(
          unitResponse?.data ??
          [],
        );
      })
      .catch((caught) => {
        if (!cancelled) {
          setError(caught);
        }
      })
      .finally(() => {
        if (!cancelled) {
          setLoading(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [
    canView,
    canViewCatalog,
    reloadToken,
  ]);


  const unitById =
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
      [
        units,
      ],
    );


  const visibleMaterials =
    useMemo(
      () => {
        const query =
          normalized(
            search,
          );

        return materials.filter(
          (material) => {
            if (
              !statusMatches(
                material.status,
                statusFilter,
              )
            ) {
              return false;
            }

            if (!query) {
              return true;
            }

            const unit =
              material.unit_id
                ? unitById.get(
                    material.unit_id,
                  )
                : null;

            const haystack = [
              material.code,
              material.name,
              material.category,
              material
                .inventory_category
                ?.name,
              inventoryTypeLabels[
                material.inventory_type
              ],
              stockPolicyLabel(
                material,
              ),
              unit?.name,
              unit?.symbol,
            ]
              .map(normalized)
              .join(" ");

            return haystack.includes(
              query,
            );
          },
        );
      },
      [
        materials,
        search,
        statusFilter,
        unitById,
      ],
    );


  const visibleCategories =
    useMemo(
      () => {
        const query =
          normalized(
            search,
          );

        return categories.filter(
          (category) => {
            if (
              !statusMatches(
                category.status,
                statusFilter,
              )
            ) {
              return false;
            }

            if (!query) {
              return true;
            }

            return [
              category.code,
              category.name,
              category.description,
            ]
              .map(normalized)
              .join(" ")
              .includes(
                query,
              );
          },
        );
      },
      [
        categories,
        search,
        statusFilter,
      ],
    );


  const visibleWarehouses =
    useMemo(
      () => {
        const query =
          normalized(
            search,
          );

        return warehouses.filter(
          (warehouse) => {
            if (
              !statusMatches(
                warehouse.status,
                statusFilter,
              )
            ) {
              return false;
            }

            if (!query) {
              return true;
            }

            return [
              warehouse.name,
              warehouse.location,
            ]
              .map(normalized)
              .join(" ")
              .includes(
                query,
              );
          },
        );
      },
      [
        warehouses,
        search,
        statusFilter,
      ],
    );


  const activeMaterialCount =
    materials.filter(
      (material) =>
        material.status ===
        "ACTIVE",
    ).length;

  const trackedMaterialCount =
    materials.filter(
      (material) =>
        material.status ===
          "ACTIVE" &&
        material.stock_tracking ===
          "TRACKED",
    ).length;

  const activeWarehouseCount =
    warehouses.filter(
      (warehouse) =>
        warehouse.status ===
        "ACTIVE",
    ).length;


  function reloadData() {
    setLoading(true);
    setError(null);

    setReloadToken(
      (
        current,
      ) =>
        current + 1,
    );
  }


  function openMaterialEditor(
    material:
      | Material
      | null,
  ) {
    setSuccessMessage(
      null,
    );

    setEditor({
      kind:
        "MATERIAL",
      value:
        material,
    });
  }


  function openCategoryEditor(
    category:
      | InventoryCategory
      | null,
  ) {
    setSuccessMessage(
      null,
    );

    setEditor({
      kind:
        "CATEGORY",
      value:
        category,
    });
  }


  function openWarehouseEditor(
    warehouse:
      | Warehouse
      | null,
  ) {
    setSuccessMessage(
      null,
    );

    setEditor({
      kind:
        "WAREHOUSE",
      value:
        warehouse,
    });
  }


  function saved(
    message:
      string,
  ) {
    setEditor(null);

    setSuccessMessage(
      message,
    );

    reloadData();
  }


  const sectionTitle =
    activeTab ===
    "MATERIALS"
      ? "Barang Persediaan"
      : activeTab ===
          "CATEGORIES"
        ? "Kategori Persediaan"
        : "Gudang";

  const sectionDescription =
    activeTab ===
    "MATERIALS"
      ? "Kelola barang fisik dan kebijakan persediaan tanpa mengubah saldo stok secara langsung."
      : activeTab ===
          "CATEGORIES"
        ? "Kelola kategori untuk mengelompokkan barang persediaan secara konsisten."
        : "Kelola lokasi fisik penyimpanan persediaan usaha.";

  const sectionInsightTitle =
    activeTab ===
    "MATERIALS"
      ? "Stok mengikuti transaksi nyata"
      : activeTab ===
          "CATEGORIES"
        ? "Kategori adalah klasifikasi master"
        : "Gudang adalah lokasi fisik";

  const sectionInsightDescription =
    activeTab ===
    "MATERIALS"
      ? "Saldo persediaan berasal dari pergerakan stok. Master hanya menyimpan identitas dan kebijakan barang."
      : activeTab ===
          "CATEGORIES"
        ? "Kategori membantu pengelompokan barang tanpa mengubah saldo maupun histori transaksi."
        : "Gudang menyimpan konteks lokasi stok. Proyek tetap menjadi tujuan atau alokasi penggunaan.";

  const SectionIcon =
    activeTab ===
    "MATERIALS"
      ? Boxes
      : activeTab ===
          "CATEGORIES"
        ? Tags
        : WarehouseIcon;


  const resultCount =
    activeTab === "MATERIALS"
      ? visibleMaterials.length
      : activeTab ===
          "CATEGORIES"
        ? visibleCategories.length
        : visibleWarehouses.length;


  return (
    <section
      className={
        styles.page
      }
    >
      <ModuleHero
        eyebrow="Operasional"
        title={
          sectionTitle
        }
        description={
          sectionDescription
        }
        icon={
          SectionIcon
        }
        tone="cyan"
        insightTitle={
          sectionInsightTitle
        }
        insightDescription={
          sectionInsightDescription
        }
      />

      {canView === false ? (
        <ActionFeedback
          tone="error"
          title={
            `${sectionTitle} belum dapat dibuka`
          }
          message={
            error
              ? apiErrorMessage(
                  error,
                  `${sectionTitle} belum dapat dibuka karena hak akses belum dapat diverifikasi.`,
                )
              : `Akun Anda belum memiliki hak untuk melihat ${sectionTitle}.`
          }
          requestId={
            error
              ? apiRequestId(
                  error,
                )
              : null
          }
        />
      ) : null}

      {canView === true ? (
        <>
          <section
            className={
              styles.summaryGrid
            }
            aria-label="Ringkasan persediaan"
          >
            <article
              className={
                styles.summaryCard
              }
            >
              <span
                className={
                  styles.summaryIcon
                }
              >
                <PackageCheck
                  size={20}
                  aria-hidden="true"
                />
              </span>

              <div>
                <strong>
                  {
                    activeMaterialCount
                  }
                </strong>
                <span>
                  Barang aktif
                </span>
              </div>
            </article>

            <article
              className={
                styles.summaryCard
              }
            >
              <span
                className={
                  styles.summaryIcon
                }
              >
                <Package
                  size={20}
                  aria-hidden="true"
                />
              </span>

              <div>
                <strong>
                  {
                    trackedMaterialCount
                  }
                </strong>
                <span>
                  Stok dipantau
                </span>
              </div>
            </article>

            <article
              className={
                styles.summaryCard
              }
            >
              <span
                className={
                  styles.summaryIcon
                }
              >
                <WarehouseIcon
                  size={20}
                  aria-hidden="true"
                />
              </span>

              <div>
                <strong>
                  {
                    activeWarehouseCount
                  }
                </strong>
                <span>
                  Gudang aktif
                </span>
              </div>
            </article>
          </section>

          {canManage ? (
            <div
              className={
                styles.manageHint
              }
            >
              <ShieldCheck
                size={18}
                aria-hidden="true"
              />

              <span>
                Anda memiliki hak
                untuk mengelola
                master persediaan.
              </span>
            </div>
          ) : null}

          {canManage ? (
            <div
              className={
                styles.masterActions
              }
            >
              <button
                type="button"
                className={
                  styles.primaryAction
                }
                onClick={() => {
                  if (
                    activeTab ===
                    "MATERIALS"
                  ) {
                    openMaterialEditor(
                      null,
                    );

                    return;
                  }

                  if (
                    activeTab ===
                    "CATEGORIES"
                  ) {
                    openCategoryEditor(
                      null,
                    );

                    return;
                  }

                  openWarehouseEditor(
                    null,
                  );
                }}
              >
                <Plus
                  size={18}
                  aria-hidden="true"
                />

                {activeTab ===
                  "MATERIALS"
                  ? "Tambah Barang"
                  : activeTab ===
                      "CATEGORIES"
                    ? "Tambah Kategori"
                    : "Tambah Gudang"}
              </button>
            </div>
          ) : null}

          <section
            className={
              styles.toolbar
            }
          >
            <label
              className={
                styles.search
              }
            >
              <Search
                size={18}
                aria-hidden="true"
              />

              <input
                type="search"
                value={
                  search
                }
                onChange={(
                  event,
                ) =>
                  setSearch(
                    event
                      .target
                      .value,
                  )
                }
                placeholder={
                  activeTab ===
                  "MATERIALS"
                    ? "Cari nama, kode, kategori..."
                    : activeTab ===
                        "CATEGORIES"
                      ? "Cari kategori..."
                      : "Cari gudang atau lokasi..."
                }
                aria-label="Cari data"
              />
            </label>

            <div
              className={
                styles.statusFilters
              }
              aria-label="Filter status"
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
                    key={value}
                    type="button"
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

            <button
              type="button"
              className={
                styles.refreshButton
              }
              disabled={
                loading
              }
              onClick={
                reloadData
              }
            >
              <RefreshCw
                size={17}
                aria-hidden="true"
              />
              Muat ulang
            </button>
          </section>

          <div
            className={
              styles.resultMeta
            }
          >
            {loading
              ? "Memuat data..."
              : `${resultCount} data ditemukan`}
          </div>

          {successMessage ? (
            <ActionFeedback
              tone="success"
              placement="viewport"
              title="Berhasil"
              message={
                successMessage
              }
            />
          ) : null}

          {error ? (
            <ActionFeedback
              tone="error"
              title="Data persediaan belum dapat dimuat"
              message={
                apiErrorMessage(
                  error,
                  `Data ${sectionTitle} belum berhasil dimuat. Coba lagi.`,
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
              aria-hidden="true"
            >
              {Array.from({
                length: 4,
              }).map(
                (_, index) => (
                  <div
                    key={index}
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
          activeTab ===
            "MATERIALS" ? (
            <MaterialList
              rows={
                visibleMaterials
              }
              unitById={
                unitById
              }
              canManage={
                canManage
              }
              onEdit={
                openMaterialEditor
              }
            />
          ) : null}

          {!loading &&
          !error &&
          activeTab ===
            "CATEGORIES" ? (
            <CategoryList
              rows={
                visibleCategories
              }
              canManage={
                canManage
              }
              onEdit={
                openCategoryEditor
              }
            />
          ) : null}

          {!loading &&
          !error &&
          activeTab ===
            "WAREHOUSES" ? (
            <WarehouseList
              rows={
                visibleWarehouses
              }
              canManage={
                canManage
              }
              onEdit={
                openWarehouseEditor
              }
            />
          ) : null}

          {editor?.kind ===
          "MATERIAL" ? (
            <InventoryMaterialEditor
              material={
                editor.value
              }
              categories={
                categories
              }
              units={
                units
              }
              onClose={() =>
                setEditor(
                  null,
                )
              }
              onSaved={
                saved
              }
            />
          ) : null}

          {editor?.kind ===
          "CATEGORY" ? (
            <InventoryCategoryEditor
              category={
                editor.value
              }
              onClose={() =>
                setEditor(
                  null,
                )
              }
              onSaved={
                saved
              }
            />
          ) : null}

          {editor?.kind ===
          "WAREHOUSE" ? (
            <WarehouseEditor
              warehouse={
                editor.value
              }
              onClose={() =>
                setEditor(
                  null,
                )
              }
              onSaved={
                saved
              }
            />
          ) : null}

          <section
            className={
              styles.ledgerNote
            }
          >
            <PackageX
              size={19}
              aria-hidden="true"
            />

            <div>
              <strong>
                Saldo stok tidak
                diedit dari master
              </strong>

              <p>
                Perubahan jumlah
                persediaan harus
                berasal dari transaksi
                pergerakan stok yang
                sah dan dapat
                ditelusuri.
              </p>
            </div>
          </section>
        </>
      ) : null}
    </section>
  );
}


function MaterialList({
  rows,
  unitById,
  canManage,
  onEdit,
}: {
  rows: Material[];
  unitById: Map<
    string,
    CatalogUnit
  >;
  canManage:
    boolean;
  onEdit: (
    material:
      Material,
  ) => void;
}) {
  if (rows.length === 0) {
    return (
      <EmptyState
        icon={Package}
        title="Belum ada barang persediaan"
        description="Belum ada data yang sesuai dengan pencarian atau filter saat ini."
      />
    );
  }

  return (
    <>
      <div
        className={
          styles.mobileList
        }
      >
        {rows.map(
          (material) => {
            const unit =
              material.unit_id
                ? unitById.get(
                    material.unit_id,
                  )
                : null;

            return (
              <article
                key={
                  material.id
                }
                className={
                  styles.dataCard
                }
              >
                <div
                  className={
                    styles.cardTop
                  }
                >
                  <div>
                    <span
                      className={
                        styles.code
                      }
                    >
                      {
                        material.code ??
                        "Tanpa kode"
                      }
                    </span>

                    <h2>
                      {
                        material.name
                      }
                    </h2>
                  </div>

                  <StatusBadge
                    label={
                      material
                        .status_label
                    }
                    active={
                      material.status ===
                      "ACTIVE"
                    }
                  />
                </div>

                <dl
                  className={
                    styles.detailList
                  }
                >
                  <div>
                    <dt>
                      Kategori
                    </dt>
                    <dd>
                      {
                        material
                          .inventory_category
                          ?.name ??
                        material
                          .category ??
                        "Belum dipilih"
                      }
                    </dd>
                  </div>

                  <div>
                    <dt>
                      Jenis
                    </dt>
                    <dd>
                      {
                        inventoryTypeLabels[
                          material
                            .inventory_type
                        ]
                      }
                    </dd>
                  </div>

                  <div>
                    <dt>
                      Satuan
                    </dt>
                    <dd>
                      {unit
                        ? (
                            unit.symbol
                              ? `${unit.name} (${unit.symbol})`
                              : unit.name
                          )
                        : material
                            .unit_id
                          ? "Satuan terhubung"
                          : "Belum dipilih"}
                    </dd>
                  </div>

                  <div>
                    <dt>
                      Kebijakan stok
                    </dt>
                    <dd>
                      {
                        stockPolicyLabel(
                          material,
                        )
                      }
                    </dd>
                  </div>
                </dl>

                <p
                  className={
                    styles.threshold
                  }
                >
                  {
                    thresholdSummary(
                      material,
                    )
                  }
                </p>

                {canManage ? (
                  <div
                    className={
                      styles.cardActions
                    }
                  >
                    <button
                      type="button"
                      className={
                        styles.editButton
                      }
                      onClick={() =>
                        onEdit(
                          material,
                        )
                      }
                    >
                      <Pencil
                        size={16}
                        aria-hidden="true"
                      />
                      Ubah Barang
                    </button>
                  </div>
                ) : null}
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
              <th>Barang</th>
              <th>Kategori</th>
              <th>Jenis</th>
              <th>Stok</th>
              <th>Status</th>
              {canManage ? (
                <th>Aksi</th>
              ) : null}
            </tr>
          </thead>

          <tbody>
            {rows.map(
              (material) => (
                <tr
                  key={
                    material.id
                  }
                >
                  <td>
                    <strong>
                      {
                        material.name
                      }
                    </strong>
                    <span>
                      {
                        material.code ??
                        "Tanpa kode"
                      }
                    </span>
                  </td>

                  <td>
                    {
                      material
                        .inventory_category
                        ?.name ??
                      material
                        .category ??
                      "—"
                    }
                  </td>

                  <td>
                    {
                      inventoryTypeLabels[
                        material
                          .inventory_type
                      ]
                    }
                  </td>

                  <td>
                    <strong>
                      {
                        stockPolicyLabel(
                          material,
                        )
                      }
                    </strong>
                    <span>
                      {
                        thresholdSummary(
                          material,
                        )
                      }
                    </span>
                  </td>

                  <td>
                    <StatusBadge
                      label={
                        material
                          .status_label
                      }
                      active={
                        material.status ===
                        "ACTIVE"
                      }
                    />
                  </td>

                  {canManage ? (
                    <td>
                      <button
                        type="button"
                        className={
                          styles.editButton
                        }
                        onClick={() =>
                          onEdit(
                            material,
                          )
                        }
                      >
                        <Pencil
                          size={15}
                          aria-hidden="true"
                        />
                        Ubah
                      </button>
                    </td>
                  ) : null}
                </tr>
              ),
            )}
          </tbody>
        </table>
      </div>
    </>
  );
}


function CategoryList({
  rows,
  canManage,
  onEdit,
}: {
  rows:
    InventoryCategory[];
  canManage:
    boolean;
  onEdit: (
    category:
      InventoryCategory,
  ) => void;
}) {
  if (rows.length === 0) {
    return (
      <EmptyState
        icon={Tags}
        title="Belum ada kategori persediaan"
        description="Belum ada kategori yang sesuai dengan pencarian atau filter saat ini."
      />
    );
  }

  return (
    <>
      <div
        className={
          styles.mobileList
        }
      >
        {rows.map(
          (category) => (
            <article
              key={
                category.id
              }
              className={
                styles.dataCard
              }
            >
              <div
                className={
                  styles.cardTop
                }
              >
                <div>
                  <span
                    className={
                      styles.code
                    }
                  >
                    {
                      category.code
                    }
                  </span>

                  <h2>
                    {
                      category.name
                    }
                  </h2>
                </div>

                <StatusBadge
                  label={
                    category
                      .status_label
                  }
                  active={
                    category.status ===
                    "ACTIVE"
                  }
                />
              </div>

              <p
                className={
                  styles.description
                }
              >
                {
                  category
                    .description ??
                  "Belum ada keterangan kategori."
                }
              </p>

              {canManage ? (
                <div
                  className={
                    styles.cardActions
                  }
                >
                  <button
                    type="button"
                    className={
                      styles.editButton
                    }
                    onClick={() =>
                      onEdit(
                        category,
                      )
                    }
                  >
                    <Pencil
                      size={16}
                      aria-hidden="true"
                    />
                    Ubah Kategori
                  </button>
                </div>
              ) : null}
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
              <th>Kode</th>
              <th>Kategori</th>
              <th>Keterangan</th>
              <th>Status</th>
              {canManage ? (
                <th>Aksi</th>
              ) : null}
            </tr>
          </thead>

          <tbody>
            {rows.map(
              (category) => (
                <tr
                  key={
                    category.id
                  }
                >
                  <td>
                    {
                      category.code
                    }
                  </td>

                  <td>
                    <strong>
                      {
                        category.name
                      }
                    </strong>
                  </td>

                  <td>
                    {
                      category
                        .description ??
                      "—"
                    }
                  </td>

                  <td>
                    <StatusBadge
                      label={
                        category
                          .status_label
                      }
                      active={
                        category.status ===
                        "ACTIVE"
                      }
                    />
                  </td>

                  {canManage ? (
                    <td>
                      <button
                        type="button"
                        className={
                          styles.editButton
                        }
                        onClick={() =>
                          onEdit(
                            category,
                          )
                        }
                      >
                        <Pencil
                          size={15}
                          aria-hidden="true"
                        />
                        Ubah
                      </button>
                    </td>
                  ) : null}
                </tr>
              ),
            )}
          </tbody>
        </table>
      </div>
    </>
  );
}


function WarehouseList({
  rows,
  canManage,
  onEdit,
}: {
  rows: Warehouse[];
  canManage:
    boolean;
  onEdit: (
    warehouse:
      Warehouse,
  ) => void;
}) {
  if (rows.length === 0) {
    return (
      <EmptyState
        icon={WarehouseIcon}
        title="Belum ada gudang"
        description="Belum ada gudang yang sesuai dengan pencarian atau filter saat ini."
      />
    );
  }

  return (
    <>
      <div
        className={
          styles.mobileList
        }
      >
        {rows.map(
          (warehouse) => (
            <article
              key={
                warehouse.id
              }
              className={
                styles.dataCard
              }
            >
              <div
                className={
                  styles.cardTop
                }
              >
                <div>
                  <span
                    className={
                      styles.code
                    }
                  >
                    Gudang
                  </span>

                  <h2>
                    {
                      warehouse.name
                    }
                  </h2>
                </div>

                <StatusBadge
                  label={
                    warehouse
                      .status_label
                  }
                  active={
                    warehouse.status ===
                    "ACTIVE"
                  }
                />
              </div>

              <p
                className={
                  styles.description
                }
              >
                {
                  warehouse
                    .location ??
                  "Lokasi belum dicatat."
                }
              </p>

              {canManage ? (
                <div
                  className={
                    styles.cardActions
                  }
                >
                  <button
                    type="button"
                    className={
                      styles.editButton
                    }
                    onClick={() =>
                      onEdit(
                        warehouse,
                      )
                    }
                  >
                    <Pencil
                      size={16}
                      aria-hidden="true"
                    />
                    Ubah Gudang
                  </button>
                </div>
              ) : null}
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
              <th>Gudang</th>
              <th>Lokasi</th>
              <th>Status</th>
              {canManage ? (
                <th>Aksi</th>
              ) : null}
            </tr>
          </thead>

          <tbody>
            {rows.map(
              (warehouse) => (
                <tr
                  key={
                    warehouse.id
                  }
                >
                  <td>
                    <strong>
                      {
                        warehouse.name
                      }
                    </strong>
                  </td>

                  <td>
                    {
                      warehouse
                        .location ??
                      "—"
                    }
                  </td>

                  <td>
                    <StatusBadge
                      label={
                        warehouse
                          .status_label
                      }
                      active={
                        warehouse.status ===
                        "ACTIVE"
                      }
                    />
                  </td>

                  {canManage ? (
                    <td>
                      <button
                        type="button"
                        className={
                          styles.editButton
                        }
                        onClick={() =>
                          onEdit(
                            warehouse,
                          )
                        }
                      >
                        <Pencil
                          size={15}
                          aria-hidden="true"
                        />
                        Ubah
                      </button>
                    </td>
                  ) : null}
                </tr>
              ),
            )}
          </tbody>
        </table>
      </div>
    </>
  );
}


function StatusBadge({
  label,
  active,
}: {
  label: string;
  active: boolean;
}) {
  return (
    <span
      className={
        active
          ? styles.statusActive
          : styles.statusInactive
      }
    >
      {label}
    </span>
  );
}


function EmptyState({
  icon: Icon,
  title,
  description,
}: {
  icon:
    typeof Package;
  title: string;
  description: string;
}) {
  return (
    <section
      className={
        styles.emptyState
      }
    >
      <span
        className={
          styles.emptyIcon
        }
      >
        <Icon
          size={28}
          aria-hidden="true"
        />
      </span>

      <h2>{title}</h2>

      <p>
        {description}
      </p>
    </section>
  );
}
